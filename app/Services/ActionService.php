<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ActionPriority;
use App\Enums\ActionStatus;
use App\Models\ActionAudit;
use App\Models\ActionItem;
use App\Models\Decision;
use App\Models\SourceCitation;
use App\Models\User;
use App\Models\WorkspaceContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Actions and commitments. "Overdue" is derived from due_date, never stored.
 * Every status change writes an audit row.
 */
final class ActionService
{
    /**
     * @return Collection<int, ActionItem>
     */
    public function index(User $user, WorkspaceContext $context, string $view): Collection
    {
        $query = ActionItem::query()
            ->where('user_id', $user->id)
            ->where('workspace_context_id', $context->id)
            ->with(['meeting', 'citations']);

        $today = CarbonImmutable::now($this->timezone($user))->toDateString();

        $query = match ($view) {
            'today' => $query->where('status', '!=', 'completed')->where('status', '!=', 'cancelled')->whereDate('due_date', $today),
            'upcoming' => $query->where('status', '!=', 'completed')->where('status', '!=', 'cancelled')->whereDate('due_date', '>', $today),
            'overdue' => $query->where('status', '!=', 'completed')->where('status', '!=', 'cancelled')->whereDate('due_date', '<', $today),
            'waiting' => $query->where('status', 'waiting'),
            'delegated' => $query->where('status', 'delegated'),
            'completed' => $query->where('status', 'completed'),
            default => $query->where('status', '!=', 'completed')->where('status', '!=', 'cancelled'),
        };

        return $query->orderBy('due_date')->orderByDesc('id')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, WorkspaceContext $context, array $data): ActionItem
    {
        $action = ActionItem::create([
            'user_id' => $user->id,
            'workspace_context_id' => $context->id,
            'meeting_id' => $data['meeting_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'owner' => $data['owner'] ?? null,
            'status' => $data['status'] ?? ActionStatus::Open->value,
            'priority' => $data['priority'] ?? ActionPriority::Medium->value,
            'due_date' => $data['due_date'] ?? null,
        ]);

        $this->audit($action, $user, null, $action->status->value, 'Created');

        return $action;
    }

    /**
     * Create an action from an accepted meeting decision (explicit user act),
     * carrying the decision's source citations across.
     */
    public function convertDecision(Decision $decision, User $user): ActionItem
    {
        $action = DB::transaction(function () use ($decision, $user) {
            $action = ActionItem::create([
                'user_id' => $user->id,
                'workspace_context_id' => $decision->workspace_context_id,
                'meeting_id' => $decision->meeting_id,
                'title' => $decision->title,
                'description' => $decision->details,
                'owner' => $decision->owner,
                'status' => ActionStatus::Open->value,
                'priority' => ActionPriority::Medium->value,
            ]);

            foreach ($decision->citations as $citation) {
                SourceCitation::create([
                    'meeting_id' => $decision->meeting_id,
                    'citeable_type' => ActionItem::class,
                    'citeable_id' => $action->id,
                    'note_id' => $citation->note_id,
                    'quote' => $citation->quote,
                ]);
            }

            $this->audit($action, $user, null, ActionStatus::Open->value, 'Converted from decision');

            return $action;
        });

        return $action->load('citations');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ActionItem $action, User $user, array $data): ActionItem
    {
        $before = $action->status->value;
        $action->fill($data)->save();

        if (isset($data['status']) && $data['status'] !== $before) {
            $this->audit($action, $user, $before, $action->status->value, $data['audit_note'] ?? null);
        }

        return $action;
    }

    public function transition(ActionItem $action, User $user, string $toStatus, ?string $note = null): ActionItem
    {
        $status = ActionStatus::tryFrom($toStatus);

        if ($status === null) {
            throw new InvalidArgumentException("Unknown action status [{$toStatus}].");
        }

        $before = $action->status->value;
        $action->forceFill(['status' => $status->value])->save();
        $this->audit($action, $user, $before, $status->value, $note);

        return $action;
    }

    private function audit(ActionItem $action, User $user, ?string $from, string $to, ?string $note): void
    {
        ActionAudit::create([
            'action_item_id' => $action->id,
            'user_id' => $user->id,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'created_at' => now(),
        ]);
    }

    private function timezone(User $user): string
    {
        return $user->profile?->timezone ?: 'UTC';
    }
}
