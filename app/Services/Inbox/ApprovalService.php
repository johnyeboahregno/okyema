<?php

declare(strict_types=1);

namespace App\Services\Inbox;

use App\Enums\ApprovalStatus;
use App\Enums\DraftStatus;
use App\Enums\MessageChannel;
use App\Models\ApprovalRequest;
use App\Models\ConnectorAccount;
use App\Models\Draft;
use App\Models\User;
use App\Services\Connectors\ChatConnector;
use App\Services\Connectors\MailConnector;
use Illuminate\Support\Facades\Log;

/**
 * The explicit approval step before any message is sent. Approving runs the
 * send through the connector adapter; without a connected account the draft
 * stays approved but unsent and the reason is reported back.
 */
final class ApprovalService
{
    public function __construct(
        private readonly MailConnector $mail,
        private readonly ChatConnector $chat,
    ) {}

    /**
     * @return array{approval: ApprovalRequest, draft: ?Draft, sent: bool, reason: ?string}
     */
    public function approve(User $user, ApprovalRequest $approval): array
    {
        abort_unless($approval->user_id === $user->id, 403);
        abort_unless($approval->status === ApprovalStatus::Pending, 422, 'This request has already been decided.');

        $approval->forceFill([
            'status' => ApprovalStatus::Approved->value,
            'decided_at' => now(),
        ])->save();

        $draft = Draft::find($approval->details['draft_id'] ?? null);
        if ($draft === null) {
            return ['approval' => $approval, 'draft' => null, 'sent' => false, 'reason' => 'Draft not found.'];
        }

        $draft->forceFill(['status' => DraftStatus::Approved->value])->save();

        $sent = false;
        $reason = null;

        try {
            $this->send($draft, $user);
            $draft->forceFill(['status' => DraftStatus::Sent->value])->save();
            $sent = true;
        } catch (\Throwable $e) {
            $reason = $e->getMessage();
            Log::warning('approval.send.failed', ['draft_id' => $draft->id, 'error' => $e->getMessage()]);
        }

        return ['approval' => $approval, 'draft' => $draft->fresh(), 'sent' => $sent, 'reason' => $reason];
    }

    public function reject(User $user, ApprovalRequest $approval): ApprovalRequest
    {
        abort_unless($approval->user_id === $user->id, 403);
        abort_unless($approval->status === ApprovalStatus::Pending, 422, 'This request has already been decided.');

        $approval->forceFill([
            'status' => ApprovalStatus::Rejected->value,
            'decided_at' => now(),
        ])->save();

        $draft = Draft::find($approval->details['draft_id'] ?? null);
        if ($draft !== null) {
            $draft->forceFill(['status' => DraftStatus::Rejected->value])->save();
        }

        return $approval;
    }

    private function send(Draft $draft, User $user): void
    {
        $token = $this->tokenFor($draft->channel, $user);
        $recipients = $draft->to_recipients ?? [];

        match ($draft->channel) {
            MessageChannel::Email => $this->mail->send($recipients, (string) $draft->subject, (string) $draft->body, $token),
            default => $this->chat->send($recipients, (string) $draft->body, $token),
        };
    }

    private function tokenFor(MessageChannel $channel, User $user): ?string
    {
        $providers = match ($channel) {
            MessageChannel::Email => ['google', 'microsoft'],
            MessageChannel::Slack => ['slack'],
            MessageChannel::Teams => ['microsoft'],
            default => [],
        };

        if ($providers === []) {
            return null;
        }

        return ConnectorAccount::query()
            ->where('user_id', $user->id)
            ->whereIn('provider', $providers)
            ->whereNotNull('access_token')
            ->value('access_token');
    }
}
