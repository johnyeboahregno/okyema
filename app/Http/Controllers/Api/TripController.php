<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Services\Travel\ItineraryExtractor;
use App\Services\Travel\TripService;
use App\Services\WorkspaceContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripController extends Controller
{
    public function __construct(
        private readonly TripService $trips,
        private readonly ItineraryExtractor $extractor,
        private readonly WorkspaceContextService $workspaces,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $context = $this->workspaces->activeContext($request->user());

        return response()->json([
            'data' => $this->trips->index($request->user(), $context)->map(fn (Trip $trip) => [
                'id' => $trip->id,
                'title' => $trip->title,
                'starts_on' => $trip->starts_on?->toDateString(),
                'ends_on' => $trip->ends_on?->toDateString(),
                'status' => $trip->status->value,
                'segments' => $trip->segments,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'purpose' => ['sometimes', 'nullable', 'string'],
            'starts_on' => ['sometimes', 'nullable', 'date'],
            'ends_on' => ['sometimes', 'nullable', 'date'],
        ]);

        return response()->json([
            'data' => $this->trips->create($request->user(), $this->workspaces->activeContext($request->user()), $validated),
        ], 201);
    }

    public function extract(Request $request): JsonResponse
    {
        $validated = $request->validate(['text' => ['required', 'string']]);

        return response()->json(['data' => $this->extractor->extract($validated['text'])]);
    }

    public function saveItinerary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'starts_on' => ['sometimes', 'nullable', 'date'],
            'ends_on' => ['sometimes', 'nullable', 'date'],
            'segments' => ['sometimes', 'array'],
        ]);

        $trip = $this->trips->saveItinerary(
            $request->user(),
            $this->workspaces->activeContext($request->user()),
            $validated['title'],
            $validated['starts_on'] ?? null,
            $validated['ends_on'] ?? null,
            $validated['segments'] ?? [],
        );

        return response()->json(['data' => $trip], 201);
    }
}
