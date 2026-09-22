<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DocumentService;
use App\Services\WorkspaceContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentService $documents,
        private readonly WorkspaceContextService $workspaces,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $context = $this->workspaces->activeContext($request->user());
        $query = $request->query('q');

        return response()->json([
            'data' => $this->documents->index($request->user(), $context, is_string($query) ? $query : null),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'provider' => ['sometimes', 'string', 'max:50'],
            'provider_document_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'deep_link' => ['sometimes', 'nullable', 'url', 'max:1024'],
            'mime_type' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        return response()->json([
            'data' => $this->documents->store($request->user(), $this->workspaces->activeContext($request->user()), $validated),
        ], 201);
    }
}
