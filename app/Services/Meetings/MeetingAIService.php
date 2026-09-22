<?php

declare(strict_types=1);

namespace App\Services\Meetings;

use App\Models\Decision;
use App\Models\Meeting;
use App\Models\Note;
use App\Models\SourceCitation;
use App\Models\Summary;
use App\Models\User;
use App\Services\AI\AIProviderInterface;
use App\Services\AI\AIRunLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Generates a meeting summary plus extracted decisions, and PROPOSES actions.
 *
 * Actions are never created silently: the AI (or the deterministic extractor)
 * proposes them and the user explicitly accepts each one. Decisions and the
 * summary are recorded because they are informational, not consequential.
 */
final class MeetingAIService
{
    public function __construct(
        private readonly AIProviderInterface $provider,
        private readonly AIRunLogger $logger,
        private readonly MeetingExtractor $extractor,
    ) {}

    /**
     * @return array{
     *     summary: Summary,
     *     decisions: Collection<int, Decision>,
     *     proposed_actions: list<array<string, mixed>>
     * }
     */
    public function generate(Meeting $meeting, User $user): array
    {
        $notes = $meeting->notes()->orderBy('id')->get();
        $bodies = $notes->pluck('body')->map(fn ($body) => (string) $body)->all();

        if ($bodies === []) {
            throw new InvalidArgumentException('Add a note before generating a summary.');
        }

        if (config('okyema.ai.enabled')) {
            try {
                $ai = $this->generateWithAi($bodies);

                return $this->persist(
                    $meeting,
                    $user,
                    $notes,
                    $ai['summary'],
                    $ai['decisions'],
                    $ai['actions'],
                    'ai',
                    config('okyema.ai.model'),
                );
            } catch (\Throwable $e) {
                Log::warning('ai.meeting_summary.failed', [
                    'meeting_id' => $meeting->id,
                    'error' => $e->getMessage(),
                ]);

                $this->logger->log(
                    'meeting_summary',
                    ['notes' => $bodies],
                    [],
                    $user->id,
                    status: 'ERROR',
                    errorMessage: $e->getMessage(),
                );
            }
        }

        $extracted = $this->extractor->extract($bodies);
        $summary = $this->extractor->summarise($bodies, count($extracted['decisions']), count($extracted['actions']));

        return $this->persist(
            $meeting,
            $user,
            $notes,
            $summary,
            $extracted['decisions'],
            $extracted['actions'],
            'deterministic',
            null,
        );
    }

    /**
     * @param  Collection<int, Note>  $notes
     * @param  list<array<string, mixed>>  $decisions
     * @param  list<array<string, mixed>>  $actions
     * @return array{summary: Summary, decisions: Collection<int, Decision>, proposed_actions: list<array<string, mixed>>}
     */
    private function persist(
        Meeting $meeting,
        User $user,
        Collection $notes,
        string $summary,
        array $decisions,
        array $actions,
        string $generatedBy,
        ?string $model,
    ): array {
        DB::transaction(function () use ($meeting, $user, $notes, $summary, $decisions, $generatedBy, $model) {
            // Regenerating replaces the previous derived artifacts.
            Summary::where('meeting_id', $meeting->id)->delete();
            Decision::where('meeting_id', $meeting->id)->each(fn (Decision $decision) => $decision->citations()->delete());
            Decision::where('meeting_id', $meeting->id)->delete();

            Summary::create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'workspace_context_id' => $meeting->workspace_context_id,
                'length' => 'standard',
                'body' => $summary,
                'generated_by' => $generatedBy,
                'model' => $model,
            ]);

            foreach ($decisions as $decision) {
                $created = Decision::create([
                    'meeting_id' => $meeting->id,
                    'user_id' => $user->id,
                    'workspace_context_id' => $meeting->workspace_context_id,
                    'title' => $decision['title'],
                    'details' => $decision['details'] ?? null,
                    'owner' => $decision['owner'] ?? null,
                ]);

                $this->cite($meeting, $notes, $created, $decision['quote'] ?? null);
            }
        });

        return [
            'summary' => Summary::where('meeting_id', $meeting->id)->latest('id')->firstOrFail(),
            'decisions' => Decision::where('meeting_id', $meeting->id)->with('citations')->get(),
            'proposed_actions' => $this->proposedActions($meeting, $notes, $actions),
        ];
    }

    /**
     * @param  Collection<int, Note>  $notes
     * @param  list<array<string, mixed>>  $actions
     * @return list<array<string, mixed>>
     */
    private function proposedActions(Meeting $meeting, Collection $notes, array $actions): array
    {
        return array_map(function (array $action) use ($meeting, $notes) {
            $note = $this->locateNote($notes, $action['quote'] ?? null);

            return [
                'title' => $action['title'],
                'owner' => $action['owner'] ?? null,
                'due_date' => $action['due_date'] ?? null,
                'priority' => $action['priority'] ?? 'medium',
                'meeting_id' => $meeting->id,
                'note_id' => $note?->id,
                'quote' => $action['quote'] ?? null,
            ];
        }, $actions);
    }

    /**
     * @param  Collection<int, Note>  $notes
     */
    private function cite(Meeting $meeting, Collection $notes, Decision $decision, ?string $quote): void
    {
        $note = $this->locateNote($notes, $quote);

        SourceCitation::create([
            'meeting_id' => $meeting->id,
            'citeable_type' => Decision::class,
            'citeable_id' => $decision->id,
            'note_id' => $note?->id,
            'quote' => $quote,
        ]);
    }

    /**
     * @param  Collection<int, Note>  $notes
     */
    private function locateNote(Collection $notes, ?string $quote): ?Note
    {
        if ($quote === null || $quote === '') {
            return $notes->first();
        }

        foreach ($notes as $note) {
            if (str_contains((string) $note->body, $quote)) {
                return $note;
            }
        }

        return $notes->first();
    }

    /**
     * @param  list<string>  $bodies
     * @return array{summary: string, decisions: list<array<string, mixed>>, actions: list<array<string, mixed>>}
     */
    private function generateWithAi(array $bodies): array
    {
        $result = $this->provider->generateStructuredResponse(
            'You are Okyema, a discreet executive chief of staff. From the meeting notes, produce a short factual '
            .'summary, the decisions that were made, and the follow-up actions. Never invent facts that are not in '
            .'the notes. Return a single JSON object with keys "summary" (string), "decisions" (array of objects with '
            .'a string "title", optional "details" and optional "owner") and "actions" (array of objects with a string '
            .'"title", optional "owner", optional "due_date" in YYYY-MM-DD and optional "priority" one of low/medium/high).',
            ['notes' => $bodies],
            [
                'type' => 'object',
                'required' => ['summary', 'decisions', 'actions'],
                'properties' => [
                    'summary' => ['type' => 'string'],
                    'decisions' => ['type' => 'array', 'items' => ['type' => 'object']],
                    'actions' => ['type' => 'array', 'items' => ['type' => 'object']],
                ],
            ],
        );

        $summary = trim((string) ($result['summary'] ?? ''));
        if ($summary === '') {
            throw new InvalidArgumentException('AI summary was empty.');
        }

        return [
            'summary' => $summary,
            'decisions' => $this->sanitiseDecisions($result['decisions'] ?? []),
            'actions' => $this->sanitiseActions($result['actions'] ?? []),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sanitiseDecisions(mixed $raw): array
    {
        $out = [];

        foreach (is_array($raw) ? $raw : [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $title = trim((string) ($item['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $out[] = [
                'title' => $title,
                'details' => isset($item['details']) ? trim((string) $item['details']) : null,
                'owner' => isset($item['owner']) ? trim((string) $item['owner']) : null,
                'quote' => null,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sanitiseActions(mixed $raw): array
    {
        $out = [];
        $priorities = ['low', 'medium', 'high'];

        foreach (is_array($raw) ? $raw : [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $title = trim((string) ($item['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $priority = strtolower((string) ($item['priority'] ?? 'medium'));
            if (! in_array($priority, $priorities, true)) {
                $priority = 'medium';
            }

            $dueDate = null;
            if (isset($item['due_date']) && is_string($item['due_date']) && $item['due_date'] !== '') {
                try {
                    $dueDate = CarbonImmutable::parse($item['due_date'])->toDateString();
                } catch (\Throwable) {
                    $dueDate = null;
                }
            }

            $out[] = [
                'title' => $title,
                'owner' => isset($item['owner']) ? trim((string) $item['owner']) : null,
                'due_date' => $dueDate,
                'priority' => $priority,
                'quote' => null,
            ];
        }

        return $out;
    }
}
