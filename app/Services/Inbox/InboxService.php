<?php

declare(strict_types=1);

namespace App\Services\Inbox;

use App\Enums\MessageDirection;
use App\Models\ApprovalRequest;
use App\Models\Conversation;
use App\Models\Draft;
use App\Models\User;
use App\Models\WorkspaceContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Communications inbox: conversations, needs-reply detection and the
 * approval-gated reply flow. Consequential sends are never performed here —
 * they go through an ApprovalRequest the user must explicitly approve.
 */
final class InboxService
{
    /**
     * @return Collection<int, Conversation>
     */
    public function index(User $user, WorkspaceContext $context, ?int $personId = null): Collection
    {
        return Conversation::query()
            ->where('user_id', $user->id)
            ->where('workspace_context_id', $context->id)
            ->when($personId, fn ($query) => $query->where('person_id', $personId))
            ->with(['person', 'messages' => fn ($query) => $query->orderByDesc('received_at')])
            ->orderByDesc('last_message_at')
            ->get()
            ->each(fn (Conversation $conversation) => $conversation->setAttribute('needs_reply', $this->needsReply($conversation)));
    }

    public function show(User $user, Conversation $conversation): Conversation
    {
        abort_unless($conversation->user_id === $user->id, 403);

        $conversation->load(['person', 'messages.sender']);

        return $conversation;
    }

    /**
     * A conversation needs a reply when its latest message is inbound.
     */
    public function needsReply(Conversation $conversation): bool
    {
        $latest = $conversation->messages->sortByDesc('received_at')->first();

        return $latest !== null && $latest->direction === MessageDirection::Inbound;
    }

    /**
     * Draft a reply and open the approval request required before it can be
     * sent. The draft itself is never sent from here.
     */
    public function draftReply(User $user, Conversation $conversation, string $body, string $subject): Draft
    {
        return DB::transaction(function () use ($user, $conversation, $body, $subject) {
            $draft = Draft::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'workspace_context_id' => $conversation->workspace_context_id,
                'channel' => $conversation->channel->value,
                'to_recipients' => $conversation->person?->providerIdentities?->pluck('email')->filter()->all() ?? [],
                'subject' => $subject,
                'body' => $body,
                'status' => 'draft',
            ]);

            $approval = ApprovalRequest::create([
                'user_id' => $user->id,
                'workspace_context_id' => $conversation->workspace_context_id,
                'kind' => 'send_message',
                'title' => 'Send reply: '.($conversation->subject ?? $conversation->person?->name ?? 'message'),
                'details' => [
                    'draft_id' => $draft->id,
                    'conversation_id' => $conversation->id,
                    'channel' => $conversation->channel->value,
                ],
                'status' => 'pending',
            ]);

            $draft->forceFill(['approval_request_id' => $approval->id])->save();

            return $draft->load('approvalRequest');
        });
    }
}
