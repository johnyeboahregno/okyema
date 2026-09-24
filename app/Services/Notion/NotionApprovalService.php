<?php

declare(strict_types=1);

namespace App\Services\Notion;

use App\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * The approval step for Notion changes. Approving runs the write; rejecting
 * cancels it. Mirrors the guard rules of Inbox\ApprovalService so the same
 * approval semantics apply in both interface modes.
 */
final class NotionApprovalService
{
    public function __construct(
        private readonly NotionWriteService $writes,
    ) {}

    /**
     * @return array{approval: ApprovalRequest, page: ?array<string, mixed>, ok: bool, reason: ?string}
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
            $page = $this->writes->execute($user, $approval);

            return ['approval' => $approval, 'page' => $page, 'ok' => true, 'reason' => null];
        } catch (\Throwable $e) {
            Log::warning('notion.approval.failed', ['approval_id' => $approval->id, 'error' => $e->getMessage()]);

            return ['approval' => $approval->fresh(), 'page' => null, 'ok' => false, 'reason' => $e->getMessage()];
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
}
