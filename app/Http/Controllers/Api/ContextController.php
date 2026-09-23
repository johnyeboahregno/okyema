<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\MembershipRole;
use App\Http\Controllers\Api\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContextController extends Controller
{
    use AuthorizesWorkspace;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $memberships = $user->memberships()->with('workspaceContext')->get();

        $contexts = $memberships->map(fn ($membership) => $this->present(
            $membership->workspaceContext,
            (bool) $membership->is_active_context,
            $membership->role->value,
        ));

        // The merged view is a choice, not a row, so it sits at the top.
        return response()->json([
            'data' => $contexts->prepend([
                'id' => null,
                'key' => 'ALL',
                'name' => 'All contexts',
                'role' => MembershipRole::Owner->value,
                'is_active' => $this->workspaceService()->allContextsActive($user),
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        $context = $this->workspaceService()->createOwned($request->user(), $validated['name']);

        return response()->json([
            'data' => $this->present($context, false, MembershipRole::Owner->value),
        ], 201);
    }

    public function update(Request $request, WorkspaceContext $context): JsonResponse
    {
        $context = $this->ownedContext($request->user(), $context);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        return response()->json([
            'data' => $this->present(
                $this->workspaceService()->rename($context, $validated['name']),
                false,
                MembershipRole::Owner->value,
            ),
        ]);
    }

    /**
     * Remove a context. Its items are moved to another of the user's contexts,
     * or deleted when `delete_data` is set.
     */
    public function destroy(Request $request, WorkspaceContext $context): JsonResponse
    {
        $user = $request->user();
        $context = $this->ownedContext($user, $context);

        abort_if(
            $user->memberships()->count() <= 1,
            422,
            'Keep at least one workspace.',
        );

        $validated = $request->validate([
            'move_to' => ['sometimes', 'nullable', 'integer'],
            'delete_data' => ['sometimes', 'boolean'],
        ]);

        $moveTo = null;

        if (! ($validated['delete_data'] ?? false)) {
            $target = $validated['move_to'] ?? $user->memberships()
                ->where('workspace_context_id', '!=', $context->id)
                ->value('workspace_context_id');

            $moveTo = $this->ownedContext($user, WorkspaceContext::findOrFail($target));
        }

        $this->workspaceService()->delete($user, $context, $moveTo);

        return response()->json(null, 204);
    }

    public function activate(Request $request, WorkspaceContext $context): JsonResponse
    {
        $context = $this->memberContext($request->user(), $context);

        $this->workspaceService()->setActive($request->user(), $context);

        return response()->json([
            'data' => $this->present($context, true, MembershipRole::Owner->value),
        ]);
    }

    /** Switch to the merged "All contexts" view (ADR-001: owner only). */
    public function activateAll(Request $request): JsonResponse
    {
        $this->workspaceService()->setActiveAll($request->user());

        return response()->json([
            'data' => [
                'id' => null,
                'key' => 'ALL',
                'name' => 'All contexts',
                'is_active' => true,
            ],
        ]);
    }

    private function present(WorkspaceContext $context, bool $isActive, string $role): array
    {
        return [
            'id' => $context->id,
            'key' => $context->type,
            'name' => $context->name,
            'role' => $role,
            'is_active' => $isActive,
        ];
    }
}
