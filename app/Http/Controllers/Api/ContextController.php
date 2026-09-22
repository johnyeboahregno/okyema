<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

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
        $memberships = $request->user()->memberships()
            ->with('workspaceContext')
            ->get();

        return response()->json([
            'data' => $memberships->map(fn ($membership) => [
                'id' => $membership->workspaceContext->id,
                'key' => $membership->workspaceContext->type->value,
                'name' => $membership->workspaceContext->name,
                'role' => $membership->role->value,
                'is_active' => (bool) $membership->is_active_context,
            ]),
        ]);
    }

    public function activate(Request $request, WorkspaceContext $context): JsonResponse
    {
        $context = $this->memberContext($request->user(), $context);

        $this->workspaceService()->setActive($request->user(), $context);

        return response()->json([
            'data' => [
                'id' => $context->id,
                'key' => $context->type->value,
                'name' => $context->name,
            ],
        ]);
    }
}
