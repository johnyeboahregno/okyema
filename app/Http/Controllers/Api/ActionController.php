<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\ActionPriority;
use App\Enums\ActionStatus;
use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Controller;
use App\Models\ActionItem;
use App\Models\Decision;
use App\Services\ActionService;
use App\Services\WorkspaceContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ActionController extends Controller
{
    use AuthorizesOwnership;

    private const VIEWS = ['inbox', 'today', 'upcoming', 'waiting', 'delegated', 'overdue', 'completed'];

    public function __construct(
        private readonly ActionService $actions,
        private readonly WorkspaceContextService $workspaces,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $view = $request->query('view', 'inbox');
        if (! in_array($view, self::VIEWS, true)) {
            $view = 'inbox';
        }

        $context = $this->workspaces->activeContext($request->user());

        return response()->json([
            'data' => $this->actions->index($request->user(), $context, $view)->map(fn (ActionItem $action) => [
                'id' => $action->id,
                'title' => $action->title,
                'description' => $action->description,
                'owner' => $action->owner,
                'status' => $action->status->value,
                'priority' => $action->priority->value,
                'due_date' => $action->due_date?->toDateString(),
                'overdue' => $action->isOverdue(),
                'meeting_id' => $action->meeting_id,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'owner' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meeting_id' => ['sometimes', 'nullable', 'integer', 'exists:meetings,id'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', Rule::enum(ActionStatus::class)],
            'priority' => ['sometimes', Rule::enum(ActionPriority::class)],
        ]);

        $action = $this->actions->create(
            $request->user(),
            $this->workspaces->activeContext($request->user()),
            $validated,
        );

        return response()->json(['data' => $action], 201);
    }

    public function update(Request $request, ActionItem $action): JsonResponse
    {
        $this->authorizeModel($action);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'owner' => ['sometimes', 'nullable', 'string', 'max:255'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', Rule::enum(ActionStatus::class)],
            'priority' => ['sometimes', Rule::enum(ActionPriority::class)],
            'audit_note' => ['sometimes', 'nullable', 'string'],
        ]);

        return response()->json(['data' => $this->actions->update($action, $request->user(), $validated)]);
    }

    public function destroy(Request $request, ActionItem $action): JsonResponse
    {
        $this->authorizeModel($action);

        $action->delete();

        return response()->json(null, 204);
    }

    public function transition(Request $request, ActionItem $action): JsonResponse
    {
        $this->authorizeModel($action);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(ActionStatus::class)],
            'note' => ['sometimes', 'nullable', 'string'],
        ]);

        try {
            $action = $this->actions->transition($action, $request->user(), $validated['status'], $validated['note'] ?? null);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $action->load('audits')]);
    }

    public function convertDecision(Request $request, Decision $decision): JsonResponse
    {
        abort_unless($decision->user_id === $request->user()->id, 403, 'You do not have access to this resource.');

        return response()->json(['data' => $this->actions->convertDecision($decision, $request->user())], 201);
    }
}
