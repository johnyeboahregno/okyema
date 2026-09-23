<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use App\Models\WorkspaceContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Workspace/context scoping for every owner-scoped domain model (ADR-001).
 *
 * A null context means the merged "All contexts" view, which is limited to the
 * contexts the user actually owns so a delegated membership can never leak one
 * workspace into another.
 */
trait BelongsToWorkspace
{
    /** @param Builder<static> $query */
    public function scopeForContext(Builder $query, User $user, ?WorkspaceContext $context): Builder
    {
        $query->where('user_id', $user->id);

        return $context === null
            ? $query->whereIn('workspace_context_id', $user->ownedWorkspaceIds())
            : $query->where('workspace_context_id', $context->id);
    }
}
