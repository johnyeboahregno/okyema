<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Assistant\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    public function __construct(
        private readonly AssistantService $assistant,
    ) {}

    public function ask(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'request' => ['required', 'string', 'max:4000'],
            'workspace_context_id' => ['nullable', 'integer'],
        ]);

        return response()->json([
            'data' => $this->assistant->ask(
                $request->user(),
                trim($validated['request']),
                isset($validated['workspace_context_id']) ? (int) $validated['workspace_context_id'] : null,
            ),
        ]);
    }
}
