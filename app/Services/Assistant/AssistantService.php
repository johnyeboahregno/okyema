<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Models\WorkspaceContext;
use App\Services\AI\AIProviderInterface;
use App\Services\AI\AIRunLogger;
use App\Services\Notion\NotionService;
use App\Services\WorkspaceContextService;
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
                        ['request' => $request, 'notion' => $notionHits],
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
        ];
    }

    private function systemPrompt(): string
    {
        return 'You are Okyema, a discreet executive chief of staff. '
            .'Answer the user\'s request from the supplied context. Never invent facts. '
            .'When your answer relies on the Notion records listed under "notion", cite each one in "sources" as {title, url} exactly as given. '
            .'If the user asks you to CREATE a new page in Notion or UPDATE an existing Notion page, you MUST set "intent" to "notion_change" and include a "notion_change" object with "kind" ("create" or "update"), "title" (string), "body" (string) and, for updates only, "page_id" (string). '
            .'Creating a page does NOT require any existing Notion records — extract the title and details from the user\'s message and never refuse for lack of context. '
            .'Otherwise set "intent" to "answer". '
            .'Return a single JSON object with keys "intent" (string), "answer" (string), "sources" (array of {title, url}) and "notion_change" (object or null). '
            .'Example — user: "Create a Notion page titled Christmas Party with a guest list"; respond: {"intent":"notion_change","answer":"I\'ll create that page.","sources":[],"notion_change":{"kind":"create","title":"Christmas Party","body":"guest list"}}.';
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
