<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\Inbox\InboxService;
use App\Services\WorkspaceContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InboxController extends Controller
{
    public function __construct(
        private readonly InboxService $inbox,
        private readonly WorkspaceContextService $workspaces,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $context = $this->workspaces->activeContext($request->user());
        $personId = $request->query('person');

        $conversations = $this->inbox->index(
            $request->user(),
            $context,
            is_numeric($personId) ? (int) $personId : null,
        );

        return response()->json([
            'data' => $conversations->map(fn (Conversation $conversation) => [
                'id' => $conversation->id,
                'subject' => $conversation->subject,
                'channel' => $conversation->channel->value,
                'person' => $conversation->person?->name,
                'is_vip' => $conversation->is_vip,
                'needs_reply' => $conversation->needs_reply,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                'snippet' => $conversation->messages->first()?->snippet,
            ]),
        ]);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        return response()->json([
            'data' => $this->inbox->show($request->user(), $conversation),
        ]);
    }

    public function draft(Request $request, Conversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $draft = $this->inbox->draftReply(
            $request->user(),
            $conversation,
            $validated['body'],
            $validated['subject'] ?? 'Re: '.($conversation->subject ?? ''),
        );

        return response()->json(['data' => $draft], 201);
    }
}
