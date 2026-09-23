<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NoteSourceType;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\Note;
use App\Models\User;
use App\Models\WorkspaceContext;
use Illuminate\Database\Eloquent\Collection;

/**
 * Meeting workspace: CRUD, notes, participants and the sourced pre-meeting
 * brief. All reads stay inside the given workspace context.
 */
final class MeetingService
{
    /**
     * @return Collection<int, Meeting>
     */
    public function index(User $user, ?WorkspaceContext $context): Collection
    {
        return Meeting::query()
            ->forContext($user, $context)
            ->with(['participants'])
            ->withCount(['decisions', 'actionItems'])
            ->orderByDesc('starts_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, WorkspaceContext $context, array $data): Meeting
    {
        return Meeting::create([
            'user_id' => $user->id,
            'workspace_context_id' => $context->id,
            'event_id' => $data['event_id'] ?? null,
            'title' => $data['title'],
            'agenda' => $data['agenda'] ?? null,
            'location' => $data['location'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'classification' => $data['classification'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Meeting $meeting, array $data): Meeting
    {
        $meeting->fill($data)->save();

        return $meeting;
    }

    public function addNote(Meeting $meeting, User $user, string $body, ?string $sourceType = null): Note
    {
        return Note::create([
            'meeting_id' => $meeting->id,
            'user_id' => $user->id,
            'workspace_context_id' => $meeting->workspace_context_id,
            'body' => trim($body),
            'source_type' => $sourceType ? NoteSourceType::from($sourceType)->value : NoteSourceType::Manual->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addParticipant(Meeting $meeting, array $data): MeetingParticipant
    {
        return MeetingParticipant::create([
            'meeting_id' => $meeting->id,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'organisation' => $data['organisation'] ?? null,
            'role' => $data['role'] ?? null,
        ]);
    }

    /**
     * The sourced pre-meeting brief: everything the owner needs before the
     * meeting, with citations to the records behind it.
     *
     * @return array<string, mixed>
     */
    public function brief(Meeting $meeting): array
    {
        $meeting->load([
            'participants',
            'notes',
            'summaries',
            'decisions.citations',
            'actionItems.citations',
        ]);

        $previous = Meeting::query()
            ->where('user_id', $meeting->user_id)
            ->where('workspace_context_id', $meeting->workspace_context_id)
            ->where('id', '!=', $meeting->id)
            ->whereNotNull('starts_at')
            ->where('starts_at', '<', $meeting->starts_at ?? now())
            ->orderByDesc('starts_at')
            ->limit(3)
            ->get(['id', 'title', 'starts_at']);

        return [
            'meeting' => $meeting,
            'previous_meetings' => $previous,
            'participants' => $meeting->participants,
            'agenda' => $meeting->agenda,
            'notes' => $meeting->notes,
            'summary' => $meeting->summaries->last(),
            'decisions' => $meeting->decisions,
            'actions' => $meeting->actionItems,
        ];
    }
}
