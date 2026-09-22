<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SearchService;
use App\Services\WorkspaceContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        private readonly SearchService $search,
        private readonly WorkspaceContextService $workspaces,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            return response()->json(['data' => []]);
        }

        $context = $this->workspaces->activeContext($request->user());

        return response()->json([
            'data' => $this->search->search($request->user(), $context, $query),
        ]);
    }
}
