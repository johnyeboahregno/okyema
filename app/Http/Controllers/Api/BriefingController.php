<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BriefingService;
use App\Services\WorkspaceContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BriefingController extends Controller
{
    public function __construct(
        private readonly BriefingService $briefing,
        private readonly WorkspaceContextService $workspaces,
    ) {}

    public function morning(Request $request): JsonResponse
    {
        $context = $this->workspaces->activeContext($request->user());

        return response()->json([
            'data' => $this->briefing->morning($request->user(), $context, $request->query('timezone')),
        ]);
    }

    public function weekly(Request $request): JsonResponse
    {
        $context = $this->workspaces->activeContext($request->user());

        return response()->json([
            'data' => $this->briefing->weekly($request->user(), $context),
        ]);
    }
}
