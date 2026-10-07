<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Enums\ApprovalStatus;
use App\Enums\ConnectorStatus;
use App\Models\ApprovalRequest;
use App\Models\Calendar;
use App\Models\ConnectorAccount;
use App\Models\User;
use App\Models\WorkspaceContext;
use App\Services\AI\AIProviderInterface;
use App\Services\AI\AIRunLogger;
use App\Services\Notion\NotionService;
use App\Services\WorkspaceContextService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * The assistant pipeline both interface modes share. Typed and spoken requests
 * arrive here, are grounded in the active context (including Notion), and
 * produce an answer. Proposed Notion changes are NEVER executed here — they
 * become an ApprovalRequest the user must explicitly approve.
 */
final class AssistantService
{
    public function __construct(
        private readonly AIProviderInterface $provider,
        private readonly AIRunLogger $logger,
        private readonly WorkspaceContextService $contexts,
        private readonly NotionService $notion,
    ) {}

    /**
     * @return array{answer: string, sources: list<array{title: string, url: string}>, approval: ?array<string, mixed>, notice: ?string}
     */
    public function ask(User $user, string $request, ?int $contextId = null): array
    {
        $context = $this->resolveContext($user, $contextId);

        $notionHits = $this->notion->search($user, $context, $request);

        $ai = null;

        if (config('okyema.ai.enabled')) {
            try {
                $ai = $this->normalise(
                    $this->provider->generateStructuredResponse(
                        $this->systemPrompt(),
                        [
                            'request' => $request,
                            'notion' => $notionHits,
                            'calendars' => array_column($this->googleCalendars($user), 'label'),
                        ],
                        $this->schema(),
                    ),
                );
            } catch (\Throwable $e) {
                Log::warning('ai.assistant.failed', ['error' => $e->getMessage()]);
                $this->logger->log('assistant', ['request' => $request], [], $user->id, status: 'ERROR', errorMessage: $e->getMessage());
            }
        }

        $this->logger->log('assistant', ['request' => $request], [
            'intent' => $ai['intent'] ?? 'fallback',
            'sources' => count($ai['sources'] ?? $notionHits),
        ], $user->id);

        if ($ai === null) {
            return $this->fallbackResponse($request, $notionHits);
        }

        return $this->aiResponse($user, $context, $ai, $request, $notionHits);
    }

    private function resolveContext(User $user, ?int $contextId): ?WorkspaceContext
    {
        if ($contextId !== null) {
            $context = WorkspaceContext::find($contextId);

            abort_if($context === null, 422, 'Workspace not found.');
            abort_unless($this->contexts->isMember($context, $user), 403, 'You do not have access to this workspace.');

            return $context;
        }

        return $this->contexts->activeContext($user);
    }

    /**
     * @param  list<array{title: string, url: string}>  $notionHits
     * @return array{answer: string, sources: list<array{title: string, url: string}>, approval: ?array<string, mixed>, notice: ?string}
     */
    private function fallbackResponse(string $request, array $notionHits): array
    {
        if ($notionHits !== []) {
            return [
                'answer' => 'Here is what I found in Notion for “'.$request.'”:',
                'sources' => $notionHits,
                'approval' => null,
                'notice' => null,
            ];
        }

        return [
            'answer' => 'I received your request, but live answers are disabled until an AI provider is configured (AI_ENABLED=true). '
                .'Right now I can search Notion — connect it in Settings and ask again to get source-backed answers.',
            'sources' => [],
            'approval' => null,
            'notice' => null,
        ];
    }

    /**
     * @param  array{intent: string, answer: string, sources: list<array{title: string, url: string}>, notion_change: ?array<string, mixed>}  $ai
     * @param  list<array{title: string, url: string}>  $notionHits
     * @return array{answer: string, sources: list<array{title: string, url: string}>, approval: ?array<string, mixed>, notice: ?string}
     */
    private function aiResponse(User $user, ?WorkspaceContext $context, array $ai, string $request, array $notionHits): array
    {
        $answer = trim((string) ($ai['answer'] ?? ''));

        if ($answer === '') {
            return $this->fallbackResponse($request, $notionHits);
        }

        $sources = $ai['sources'];

        $calendarChange = $ai['calendar_change'] ?? null;

        if (is_array($calendarChange) && ($calendarChange['kind'] ?? null) === 'create') {
            [$approval, $notice] = $this->proposeCalendarChange($user, $context, $calendarChange);

            return ['answer' => $answer, 'sources' => $sources, 'approval' => $approval, 'notice' => $notice];
        }

        $change = $ai['notion_change'];

        if (is_array($change) && in_array($change['kind'] ?? null, ['create', 'update'], true)) {
            [$approval, $notice] = $this->proposeNotionChange($user, $context, $change);

            return ['answer' => $answer, 'sources' => $sources, 'approval' => $approval, 'notice' => $notice];
        }

        return ['answer' => $answer, 'sources' => $sources, 'approval' => null, 'notice' => null];
    }

    /**
     * @param  array<string, mixed>  $change
     * @return array{0: ?array<string, mixed>, 1: ?string}
     */
    private function proposeCalendarChange(User $user, ?WorkspaceContext $context, array $change): array
    {
        $title = trim((string) ($change['title'] ?? ''));

        if ($title === '') {
            return [null, 'I could not determine a title for that event.'];
        }

        $calendars = $this->googleCalendars($user);

        if ($calendars === []) {
            $connected = ConnectorAccount::query()
                ->where('user_id', $user->id)
                ->where('provider', 'google')
                ->where('status', ConnectorStatus::Connected->value)
                ->exists();

            if (! $connected) {
                return [null, 'Google Calendar is not connected. Connect it first, then ask me again.'];
            }
        }

        $target = $this->resolveCalendar($calendars, trim((string) ($change['calendar'] ?? '')));

        if ($target === false) {
            return [null, 'I could not find a calendar called “'.trim((string) $change['calendar']).'”. Your calendars: '
                .implode(', ', array_column($calendars, 'label')).'.'];
        }

        $startsAt = $this->parseDate($change['starts_at'] ?? null);
        $endsAt = $this->parseDate($change['ends_at'] ?? null);

        if ($startsAt === null || $endsAt === null || $endsAt->lte($startsAt)) {
            return [null, 'I could not work out a start and end time for that event. Please give me a clear date and time.'];
        }

        $approval = ApprovalRequest::create([
            'user_id' => $user->id,
            'workspace_context_id' => $context?->id,
            'kind' => 'calendar_event_create',
            'title' => 'Add to '.($target['label'] ?? 'Google Calendar').': '.$title,
            'details' => [
                'title' => $title,
                'description' => trim((string) ($change['description'] ?? '')),
                'location' => trim((string) ($change['location'] ?? '')),
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
                'timezone' => trim((string) ($change['timezone'] ?? 'UTC')) ?: 'UTC',
                'calendar_id' => $target['calendar_id'] ?? 'primary',
                'connector_account_id' => $target['account_id'] ?? null,
            ],
            'status' => ApprovalStatus::Pending->value,
        ]);

        return [[
            'id' => $approval->id,
            'kind' => 'calendar_event_create',
            'title' => $approval->title,
            'summary' => $title,
        ], null];
    }

    /**
     * The user's synced Google calendars, primary first, labelled so calendars
     * with the same name on different accounts stay distinguishable.
     *
     * @return list<array{label: string, calendar_id: string, account_id: int, is_primary: bool}>
     */
    private function googleCalendars(User $user): array
    {
        return Calendar::query()
            ->where('calendars.user_id', $user->id)
            ->where('calendars.provider', 'google')
            ->whereHas('connectorAccount', fn ($q) => $q->where('status', ConnectorStatus::Connected->value))
            ->with('connectorAccount')
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get()
            ->map(fn (Calendar $c) => [
                'label' => ($c->is_primary ? $c->connectorAccount->external_account_id : $c->name)
                    .' ('.$c->connectorAccount->external_account_id.')',
                'calendar_id' => (string) $c->provider_calendar_id,
                'account_id' => $c->connector_account_id,
                'is_primary' => (bool) $c->is_primary,
            ])
            ->all();
    }

    /**
     * Match the calendar the model named. Null means "use the default"
     * (first primary calendar); false means the name matched nothing.
     *
     * @param  list<array{label: string, calendar_id: string, account_id: int, is_primary: bool}>  $calendars
     * @return array{label: string, calendar_id: string, account_id: int, is_primary: bool}|null|false
     */
    private function resolveCalendar(array $calendars, string $name): array|null|false
    {
        if ($calendars === []) {
            return null;
        }

        if ($name === '') {
            return $calendars[0];
        }

        $needle = mb_strtolower($name);
        $exact = array_values(array_filter($calendars, fn ($c) => mb_strtolower($c['label']) === $needle));

        if (count($exact) === 1) {
            return $exact[0];
        }

        // Looser: the bare calendar name or account email, only if unambiguous.
        $loose = array_values(array_filter($calendars, fn ($c) => str_contains(mb_strtolower($c['label']), $needle)));

        return count($loose) === 1 ? $loose[0] : false;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse(trim($value));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $change
     * @return array{0: ?array<string, mixed>, 1: ?string}
     */
    private function proposeNotionChange(User $user, ?WorkspaceContext $context, array $change): array
    {
        $title = trim((string) ($change['title'] ?? ''));

        if ($title === '') {
            return [null, 'I could not determine a page title for that change.'];
        }

        if (! $this->notion->isConnected($user)) {
            return [null, 'Connect Notion in Settings before making changes.'];
        }

        if ($this->notion->databaseId($context) === null) {
            return [null, 'Notion is not configured for this workspace.'];
        }

        $kind = ($change['kind'] ?? null) === 'update' ? 'notion_page_update' : 'notion_page_create';
        $pageId = $kind === 'notion_page_update' ? trim((string) ($change['page_id'] ?? '')) : null;

        if ($kind === 'notion_page_update' && $pageId === '') {
            return [null, 'Choose the Notion page to update.'];
        }

        $approval = ApprovalRequest::create([
            'user_id' => $user->id,
            'workspace_context_id' => $context?->id,
            'kind' => $kind,
            'title' => ($kind === 'notion_page_update' ? 'Update ' : 'Create ').'Notion page: '.$title,
            'details' => [
                'workspace_context_id' => $context?->id,
                'title' => $title,
                'body' => trim((string) ($change['body'] ?? '')),
                'page_id' => $pageId,
            ],
            'status' => ApprovalStatus::Pending->value,
        ]);

        return [[
            'id' => $approval->id,
            'kind' => $kind,
            'title' => $approval->title,
            'summary' => $title,
        ], null];
    }

    /**
     * @return array{intent: string, answer: string, sources: list<array{title: string, url: string}>, notion_change: ?array<string, mixed>}
     */
    private function normalise(array $ai): array
    {
        $sources = [];

        foreach (is_array($ai['sources'] ?? null) ? $ai['sources'] : [] as $source) {
            if (! is_array($source)) {
                continue;
            }

            $title = trim((string) ($source['title'] ?? ''));
            $url = trim((string) ($source['url'] ?? ''));

            if ($title !== '' && $url !== '') {
                $sources[] = ['title' => $title, 'url' => $url];
            }
        }

        return [
            'intent' => is_string($ai['intent'] ?? null) ? $ai['intent'] : 'answer',
            'answer' => is_string($ai['answer'] ?? null) ? $ai['answer'] : '',
            'sources' => $sources,
            'notion_change' => is_array($ai['notion_change'] ?? null) ? $ai['notion_change'] : null,
            'calendar_change' => is_array($ai['calendar_change'] ?? null) ? $ai['calendar_change'] : null,
        ];
    }

    private function systemPrompt(): string
    {
        return 'You are Okyema, a discreet executive chief of staff. '
            .'Today is '.now()->format('l j F Y H:i').' '.config('app.timezone', 'UTC').'. '
            .'Answer the user\'s request from the supplied context. Never invent facts. '
            .'When your answer relies on the Notion records listed under "notion", cite each one in "sources" as {title, url} exactly as given. '
            .'If the user asks you to CREATE a new page in Notion or UPDATE an existing Notion page, you MUST set "intent" to "notion_change" and include a "notion_change" object with "kind" ("create" or "update"), "title" (string), "body" (string) and, for updates only, "page_id" (string). '
            .'Creating a page does NOT require any existing Notion records — extract the title and details from the user\'s message and never refuse for lack of context. '
            .'If the user asks you to ADD, CREATE or SCHEDULE a meeting, event or appointment in Google Calendar, you MUST set "intent" to "calendar_change" and include a "calendar_change" object with "kind" ("create"), "title" (string), "starts_at" and "ends_at" (ISO 8601 date-times resolved from the supplied today), "timezone" (IANA string, e.g. Europe/London), "description" (string), "location" (string) and, only when the user names a specific calendar, "calendar" (copy one label from the supplied "calendars" list exactly; omit it otherwise). Resolve relative dates like "tomorrow" or "next Tuesday" using the supplied today. '
            .'Otherwise set "intent" to "answer". '
            .'Return a single JSON object with keys "intent" (string), "answer" (string), "sources" (array of {title, url}), "notion_change" (object or null) and "calendar_change" (object or null). '
            .'Example — user: "Add a meeting tomorrow at 3pm for an hour titled Team sync"; respond: {"intent":"calendar_change","answer":"I\'ll add that to Google Calendar.","sources":[],"notion_change":null,"calendar_change":{"kind":"create","title":"Team sync","starts_at":"2026-10-08T15:00:00+01:00","ends_at":"2026-10-08T16:00:00+01:00","timezone":"Europe/London","description":"","location":""}}.';
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['intent', 'answer'],
            'properties' => [
                'intent' => ['type' => 'string'],
                'answer' => ['type' => 'string'],
            ],
        ];
    }
}
