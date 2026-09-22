<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Controller;
use App\Models\AutomationRule;
use App\Services\RuleEngine;
use App\Services\WorkspaceContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutomationController extends Controller
{
    use AuthorizesOwnership;

    public function __construct(
        private readonly RuleEngine $engine,
        private readonly WorkspaceContextService $workspaces,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $context = $this->workspaces->activeContext($request->user());

        return response()->json([
            'data' => AutomationRule::query()
                ->where('user_id', $request->user()->id)
                ->where('workspace_context_id', $context->id)
                ->withCount('runs')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trigger' => ['required', 'string', 'in:action_due_soon,morning_briefing,weekly_review'],
            'conditions' => ['sometimes', 'array'],
            'action' => ['sometimes', 'nullable', 'string', 'max:255'],
            'approval_level' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $rule = AutomationRule::create([
            'user_id' => $request->user()->id,
            'workspace_context_id' => $this->workspaces->activeContext($request->user())->id,
            'name' => $validated['name'],
            'trigger' => $validated['trigger'],
            'conditions' => $validated['conditions'] ?? null,
            'action' => $validated['action'] ?? null,
            'approval_level' => $validated['approval_level'] ?? 'none',
        ]);

        return response()->json(['data' => $rule], 201);
    }

    public function run(Request $request, AutomationRule $rule): JsonResponse
    {
        $this->authorizeModel($rule);

        return response()->json([
            'data' => $this->engine->run($rule, $request->user()),
        ]);
    }
}
