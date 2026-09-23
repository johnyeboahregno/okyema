<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Concerns;

use App\Models\User;
use App\Models\WorkspaceContext;
use App\Services\WorkspaceContextService;

/**
 * Workspace/context isolation (ADR-001).
 *
 * Server-side only. A user who is not a member of a workspace gets a 404 so
 * the API never confirms that another owner's context exists.
 */
trait AuthorizesWorkspace
{
    protected function workspaceService(): WorkspaceContextService
    {
        return app(WorkspaceContextService::class);
    }

    /** The context, if this user is a member of it. */
    protected function memberContext(User $user, WorkspaceContext $context): WorkspaceContext
    {
        abort_unless(
            $this->workspaceService()->isMember($context, $user),
            404,
            'That workspace does not exist.',
        );

        return $context;
    }

    /**
     * The context, if this user owns it. Creating, renaming and removing are
     * reserved for the owner, so a delegated membership cannot reshape a
     * workspace it only has access to.
     */
    protected function ownedContext(User $user, WorkspaceContext $context): WorkspaceContext
    {
        $context = $this->memberContext($user, $context);

        abort_unless(
            $this->workspaceService()->isOwner($context, $user),
            404,
            'That workspace does not exist.',
        );

        return $context;
    }

    /** Resolve an owned context by id (404 for non-members). */
    protected function resolveContext(User $user, int $contextId): WorkspaceContext
    {
        return $this->memberContext($user, WorkspaceContext::findOrFail($contextId));
    }
}
