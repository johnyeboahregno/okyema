<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Decision;
use App\Models\Receipt;
use App\Models\Trip;
use App\Services\ActionService;
use App\Services\CalendarService;
use App\Services\Inbox\InboxService;
use App\Services\WorkspaceContextService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The "Today" executive briefing: date, active context, the next meeting,
 * today's calendar, action/inbox/receipt counts, recent decisions and travel
 * alerts — with a factual briefing summary.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly WorkspaceContextService $workspaces,
        private readonly CalendarService $calendar,
        private readonly ActionService $actions,
        private readonly InboxService $inbox,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $context = $this->workspaces->activeContext($user);
        $timezone = $user->profile?->timezone ?: 'UTC';
        $today = CarbonImmutable::now($timezone)->startOfDay();

        $meetingsToday = count($this->calendar->agenda($user, $context, $today, $timezone));
        $overdueActions = $this->actions->index($user, $context, 'overdue')->count();
        $messagesNeedingReply = $this->inbox->index($user, $context)->where('needs_reply', true)->count();
        $unprocessedReceipts = Receipt::query()
            ->forContext($user, $context)
            ->whereNull('expense_id')
            ->count();

        $recentDecisions = Decision::query()
            ->forContext($user, $context)
            ->orderByDesc('created_at')
            ->limit(3)
            ->get(['id', 'title']);

        $travelAlerts = Trip::query()
            ->forContext($user, $context)
            ->whereDate('starts_on', '>=', $today->toDateString())
            ->whereDate('starts_on', '<=', $today->addDays(7)->toDateString())
            ->count();

        return response()->json([
            'data' => [
                'date' => CarbonImmutable::now($timezone)->toDateString(),
                'timezone' => $timezone,
                'context' => [
                    'id' => $context?->id,
                    'key' => $context?->type,
                    'name' => $context?->name ?? 'All contexts',
                    'all' => $context === null,
                ],
                'next_meeting' => $this->calendar->nextMeeting($user, $context),
                'meetings_today' => $meetingsToday,
                'overdue_actions' => $overdueActions,
                'messages_needing_reply' => $messagesNeedingReply,
                'unprocessed_receipts' => $unprocessedReceipts,
                'travel_alerts' => $travelAlerts,
                'recent_decisions' => $recentDecisions,
                'briefing' => sprintf(
                    '%d meeting%s today, %d overdue action%s, %d message%s needing a reply, %d unprocessed receipt%s.',
                    $meetingsToday,
                    $meetingsToday === 1 ? '' : 's',
                    $overdueActions,
                    $overdueActions === 1 ? '' : 's',
                    $messagesNeedingReply,
                    $messagesNeedingReply === 1 ? '' : 's',
                    $unprocessedReceipts,
                    $unprocessedReceipts === 1 ? '' : 's',
                ),
            ],
        ]);
    }
}
