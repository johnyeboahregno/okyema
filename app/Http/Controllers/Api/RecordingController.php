<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Services\Transcription\RecordingService;
use App\Services\WorkspaceContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecordingController extends Controller
{
    public function __construct(
        private readonly RecordingService $recordings,
        private readonly WorkspaceContextService $workspaces,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $context = $this->workspaces->activeContext($request->user());

        return response()->json([
            'data' => $this->recordings->transcripts($request->user(), $context),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:51200'],
            'title' => ['required', 'string', 'max:200'],
        ]);

        $file = $request->file('audio');

        $job = $this->recordings->start(
            $request->user(),
            $this->workspaces->activeContextOrFail($request->user()),
            (string) $validated['title'],
            $file->getContent(),
            (string) ($file->getMimeType() ?: 'audio/webm'),
            request()->root().'/api/webhooks/assemblyai',
        );

        return response()->json([
            'data' => [
                'id' => $job->id,
                'status' => $job->status,
            ],
        ], 201);
    }

    public function storeTranscript(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'transcript' => ['required', 'string'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $meeting = $this->recordings->saveTranscript(
            $request->user(),
            $this->workspaces->activeContextOrFail($request->user()),
            (string) $validated['title'],
            (string) $validated['transcript'],
            $validated['starts_at'] ?? null,
            $validated['ends_at'] ?? null,
        );

        return response()->json([
            'data' => ['id' => $meeting->id, 'title' => $meeting->title],
        ], 201);
    }

    public function destroy(Request $request, Meeting $meeting): JsonResponse
    {
        abort_unless($meeting->user_id === $request->user()->id, 403);

        $meeting->delete();

        return response()->json(null, 204);
    }
}
