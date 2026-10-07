<?php

declare(strict_types=1);

namespace App\Services\Connectors;

use App\Enums\ApprovalStatus;
use App\Enums\ConnectorStatus;
use App\Models\ApprovalRequest;
use App\Models\ConnectorAccount;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * The approval step for Google Calendar event changes. Approving runs the
 * write through GoogleCalendarConnector; rejecting cancels it. Mirrors the
 * guard rules of Notion\NotionApprovalService so approval semantics are
 * identical across intents.
 */
final class CalendarApprovalService
{
    public function __construct(
        private readonly GoogleCalendarConnector $connector,
    ) {}

    /**
     * @return array{approval: ApprovalRequest, event: ?array<string, mixed>, ok: bool, reason: ?string}
     */
    public function approve(User $user, ApprovalRequest $approval): array
    {
        abort_unless($approval->user_id === $user->id, 403);
        abort_unless($approval->status === ApprovalStatus::Pending, 422, 'This request has already been decided.');

        $approval->forceFill([
            'status' => ApprovalStatus::Approved->value,
            'decided_at' => now(),
        ])->save();

        try {
            $event = $this->execute($user, $approval);

            return ['approval' => $approval, 'event' => $event, 'ok' => true, 'reason' => null];
        } catch (\Throwable $e) {
            Log::warning('calendar.approval.failed', ['approval_id' => $approval->id, 'error' => $e->getMessage()]);

            return ['approval' => $approval->fresh(), 'event' => null, 'ok' => false, 'reason' => $e->getMessage()];
        }
    }

    public function reject(User $user, ApprovalRequest $approval): ApprovalRequest
    {
        abort_unless($approval->user_id === $user->id, 403);
        abort_unless($approval->status === ApprovalStatus::Pending, 422, 'This request has already been decided.');

        $approval->forceFill([
            'status' => ApprovalStatus::Rejected->value,
            'decided_at' => now(),
        ])->save();

        return $approval;
    }

    /**
     * @return array{id: string, url: string, title: string}
     */
    private function execute(User $user, ApprovalRequest $approval): array
    {
        $details = $approval->details ?? [];

        $account = ConnectorAccount::query()
            ->where('user_id', $user->id)
            ->where('provider', 'google')
            ->where('status', ConnectorStatus::Connected->value)
            ->when(
                $details['connector_account_id'] ?? null,
                fn ($query, $id) => $query->where('id', $id),
            )
            ->first();

        abort_if($account === null, 422, 'Google Calendar is not connected.');

        return $this->connector->createEvent($account, [
            'title' => (string) ($details['title'] ?? ''),
            'description' => (string) ($details['description'] ?? ''),
            'location' => (string) ($details['location'] ?? ''),
            'starts_at' => (string) ($details['starts_at'] ?? ''),
            'ends_at' => (string) ($details['ends_at'] ?? ''),
            'timezone' => (string) ($details['timezone'] ?? 'UTC'),
            'calendar_id' => (string) ($details['calendar_id'] ?? 'primary'),
        ]);
    }
}
