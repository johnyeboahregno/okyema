<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MembershipRole;
use App\Models\User;
use App\Models\WorkspaceContext;
use Illuminate\Support\Facades\DB;

/**
 * Workspace/context boundaries (ADR-001). Defaults are seeded once per user;
 * the active context is the one the UI is currently working in.
 */
class WorkspaceContextService
{
    public function isMember(WorkspaceContext $context, User $user): bool
    {
        return $context->memberships()->where('user_id', $user->id)->exists();
    }

    /**
     * Create the REGNO / LAUNCHPAD / PERSONAL contexts for a new user and mark
     * REGNO active. Runs inside the caller's transaction when one is open.
     */
    public function seedDefaultsFor(User $user): void
    {
        foreach (config('okyema.workspaces.defaults') as $index => $workspace) {
            $context = WorkspaceContext::firstOrCreate(
                ['type' => $workspace['key']],
                ['name' => $workspace['name'], 'is_default' => true],
            );

            $user->memberships()->firstOrCreate(
                ['workspace_context_id' => $context->id],
                [
                    'role' => MembershipRole::Owner,
                    'is_active_context' => $index === 0,
                ],
            );
        }
    }

    public function activeContext(User $user): WorkspaceContext
    {
        $active = $user->memberships()
            ->where('is_active_context', true)
            ->with('workspaceContext')
            ->first()?->workspaceContext;

        return $active ?? $user->workspaceContexts()->firstOrFail();
    }

    public function setActive(User $user, WorkspaceContext $context): void
    {
        DB::transaction(function () use ($user, $context) {
            $user->memberships()->update(['is_active_context' => false]);
            $user->memberships()
                ->where('workspace_context_id', $context->id)
                ->update(['is_active_context' => true]);
        });
    }
}
