<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Receipt;
use App\Models\User;
use App\Models\WorkspaceContext;
use Carbon\CarbonImmutable;

/**
 * Morning and weekly briefings. Facts come from the underlying records and are
 * labelled as such; the summary sentence is a count of those facts, never an
 * invented narrative.
 */
final class BriefingService
{
    public function __construct(
        private readonly CalendarService $calendar,
        private readonly ActionService $actions,
        private readonly Inbox\InboxService $inbox,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function morning(User $user, WorkspaceContext $context, ?string $timezone = null): array
    {
        $timezone = $timezone ?: $user->profile?->timezone ?: 'UTC';
        $today = CarbonImmutable::now($timezone)->startOfDay();

        $overdue = $this->actions->index($user, $context, 'overdue');
        $needingReply = $this->inbox->index($user, $context)->where('needs_reply', true)->count();
        $unprocessedReceipts = Receipt::query()
            ->where('user_id', $user->id)
            ->where('workspace_context_id', $context->id)
            ->whereNull('expense_id')
            ->count();

        $meetingsToday = count($this->calendar->agenda($user, $context, $today, $timezone));

        return [
            'date' => CarbonImmutable::now($timezone)->toDateString(),
            'context' => $context->type->value,
            'next_meeting' => $this->calendar->nextMeeting($user, $context),
            'meetings_today' => $meetingsToday,
            'overdue_actions' => $overdue->map(fn ($action) => $action->title)->values(),
            'messages_needing_reply' => $needingReply,
            'unprocessed_receipts' => $unprocessedReceipts,
            'summary' => sprintf(
                '%d meeting%s today, %d overdue action%s, %d message%s needing a reply, %d unprocessed receipt%s.',
                $meetingsToday,
                $meetingsToday === 1 ? '' : 's',
                $overdue->count(),
                $overdue->count() === 1 ? '' : 's',
                $needingReply,
                $needingReply === 1 ? '' : 's',
                $unprocessedReceipts,
                $unprocessedReceipts === 1 ? '' : 's',
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function weekly(User $user, WorkspaceContext $context): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $weekEnd = $today->addDays(6)->endOfDay();

        $meetings = $this->calendar->timeline($user, $context, $today, $weekEnd);
        $actions = $this->actions->index($user, $context, 'upcoming')
            ->filter(fn ($action) => $action->due_date !== null && $action->due_date->lte($weekEnd->toDateString()));

        return [
            'week_start' => $today->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'meetings' => $meetings,
            'actions_due' => $actions->map(fn ($action) => $action->title)->values(),
            'summary' => sprintf('%d meeting%s and %d action%s due over the next seven days.', count($meetings), count($meetings) === 1 ? '' : 's', $actions->count(), $actions->count() === 1 ? '' : 's'),
        ];
    }
}
