<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Services\Meetings\MeetingAIService;
use App\Services\MeetingService;
use App\Services\WorkspaceContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class MeetingController extends Controller
{
    use AuthorizesOwnership;

    public function __construct(
        private readonly MeetingService $meetings,
        private readonly MeetingAIService $ai,
        private readonly WorkspaceContextService $workspaces,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $context = $this->workspaces->activeContext($request->user());

        return response()->json([
            'data' => $this->meetings->index($request->user(), $context)->map(fn (Meeting $meeting) => [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'location' => $meeting->location,
                'starts_at' => $meeting->starts_at?->toIso8601String(),
                'participants_count' => $meeting->participants->count(),
                'decisions_count' => $meeting->decisions_count,
                'actions_count' => $meeting->action_items_count,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'agenda' => ['sometimes', 'nullable', 'string'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'event_id' => ['sometimes', 'nullable', 'integer', 'exists:events,id'],
            'classification' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $meeting = $this->meetings->create(
            $request->user(),
            $this->workspaces->activeContextOrFail($request->user()),
            $validated,
        );

        return response()->json(['data' => $meeting], 201);
    }

    public function show(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeModel($meeting);

        return response()->json(['data' => $this->meetings->brief($meeting)]);
    }

    public function update(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeModel($meeting);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'agenda' => ['sometimes', 'nullable', 'string'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'classification' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        return response()->json(['data' => $this->meetings->update($meeting, $validated)]);
    }

    public function destroy(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeModel($meeting);

        $meeting->delete();

        return response()->json(null, 204);
    }

    public function addNote(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeModel($meeting);

        $validated = $request->validate([
            'body' => ['required', 'string'],
            'source_type' => ['sometimes', 'nullable', Rule::in(['manual', 'transcript', 'import'])],
        ]);

        $note = $this->meetings->addNote($meeting, $request->user(), $validated['body'], $validated['source_type'] ?? null);

        return response()->json(['data' => $note], 201);
    }

    public function addParticipant(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeModel($meeting);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'organisation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'role' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        return response()->json(['data' => $this->meetings->addParticipant($meeting, $validated)], 201);
    }

    public function generate(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeModel($meeting);

        try {
            $result = $this->ai->generate($meeting, $request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'summary' => $result['summary'],
                'decisions' => $result['decisions'],
                'proposed_actions' => $result['proposed_actions'],
            ],
        ]);
    }
}
