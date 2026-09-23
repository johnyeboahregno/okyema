<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MembershipRole;
use App\Models\User;
use App\Models\WorkspaceContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Workspace/context boundaries (ADR-001). Contexts belong to a user and can be
 * created, renamed and removed by their owner. The active context is the one
 * the UI is working in, or null for the merged "All contexts" view.
 */
class WorkspaceContextService
{
    /** Every table that carries a workspace_context_id of its own. */
    private const SCOPED_TABLES = [
        'calendars',
        'events',
        'meetings',
        'notes',
        'summaries',
        'decisions',
        'action_items',
        'expenses',
        'receipts',
        'people',
        'conversations',
        'message_references',
        'approval_requests',
        'drafts',
        'trips',
        'document_references',
        'automation_rules',
    ];

    public function isMember(WorkspaceContext $context, User $user): bool
    {
        return $context->memberships()->where('user_id', $user->id)->exists();
    }

    public function isOwner(WorkspaceContext $context, User $user): bool
    {
        return $context->memberships()
            ->where('user_id', $user->id)
            ->where('role', MembershipRole::Owner->value)
            ->exists();
    }

    /**
     * Create this user's default contexts and mark the first active. Runs once
     * per user; contexts are owned, so no two users share a row.
     */
    public function seedDefaultsFor(User $user): void
    {
        foreach (config('okyema.workspaces.defaults') as $index => $workspace) {
            $context = $this->createOwned($user, $workspace['name'], $workspace['key'], true);

            $user->memberships()
                ->where('workspace_context_id', $context->id)
                ->update(['is_active_context' => $index === 0]);
        }
    }

    /** Create a context and make the user its owner. */
    public function createOwned(User $user, string $name, ?string $key = null, bool $isDefault = false): WorkspaceContext
    {
        $context = WorkspaceContext::create([
            'user_id' => $user->id,
            'type' => $key ?: $this->uniqueKey($user, $name),
            'name' => $name,
            'is_default' => $isDefault,
        ]);

        $user->memberships()->create([
            'workspace_context_id' => $context->id,
            'role' => MembershipRole::Owner,
            'is_active_context' => false,
        ]);

        $user->forgetOwnedWorkspaceIds();

        return $context;
    }

    public function rename(WorkspaceContext $context, string $name): WorkspaceContext
    {
        $context->update(['name' => $name]);

        return $context->refresh();
    }

    /**
     * Remove a context, moving its items to `$moveTo` — or deleting them when no
     * destination is given. The user is moved off it first, so it can never stay
     * active after it is gone.
     */
    public function delete(User $user, WorkspaceContext $context, ?WorkspaceContext $moveTo = null): void
    {
        DB::transaction(function () use ($user, $context, $moveTo) {
            foreach (self::SCOPED_TABLES as $table) {
                $rows = DB::table($table)->where('workspace_context_id', $context->id);

                $moveTo === null
                    ? $rows->delete()
                    : $rows->update(['workspace_context_id' => $moveTo->id]);
            }

            $context->memberships()->delete();
            $context->delete();

            $user->forgetOwnedWorkspaceIds();

            if ($moveTo !== null) {
                $this->setActive($user, $moveTo);

                return;
            }

            $fallback = $user->workspaceContexts()->first();

            $fallback === null
                ? $this->setAllContexts($user, true)
                : $this->setActive($user, $fallback);
        });
    }

    /** True when the merged "All contexts" view is the active one. */
    public function allContextsActive(User $user): bool
    {
        return (bool) $user->profile?->all_contexts_active;
    }

    /** The active context, or null for the merged "All contexts" view. */
    public function activeContext(User $user): ?WorkspaceContext
    {
        if ($this->allContextsActive($user)) {
            return null;
        }

        $active = $user->memberships()
            ->where('is_active_context', true)
            ->with('workspaceContext')
            ->first()?->workspaceContext;

        return $active ?? $user->workspaceContexts()->first();
    }

    /**
     * A concrete context for writes: the merged view cannot own new rows, so
     * creating anything needs a workspace to put it in.
     */
    public function activeContextOrFail(User $user): WorkspaceContext
    {
        $context = $this->activeContext($user);

        abort_if($context === null, 422, 'Choose a workspace before creating anything.');

        return $context;
    }

    public function setActive(User $user, WorkspaceContext $context): void
    {
        DB::transaction(function () use ($user, $context) {
            $user->memberships()->update(['is_active_context' => false]);
            $user->memberships()
                ->where('workspace_context_id', $context->id)
                ->update(['is_active_context' => true]);

            $this->setAllContexts($user, false);
        });
    }

    /** Switch to the merged "All contexts" view. */
    public function setActiveAll(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->memberships()->update(['is_active_context' => false]);

            $this->setAllContexts($user, true);
        });
    }

    private function setAllContexts(User $user, bool $active): void
    {
        $profile = $user->profile()->firstOrCreate(
            ['user_id' => $user->id],
            ['display_name' => $user->name, 'currency' => config('okyema.currency.code')],
        );

        $profile->update(['all_contexts_active' => $active]);

        // Keep the in-memory relation in step: the same instance is reused for
        // the rest of the request and would otherwise report a stale value.
        $user->setRelation('profile', $profile);
    }

    /** A key that is unique for this user, derived from the name. */
    private function uniqueKey(User $user, string $name): string
    {
        $base = Str::upper(Str::slug($name, '_')) ?: 'WORKSPACE';
        $key = $base;
        $suffix = 2;

        while (WorkspaceContext::where('user_id', $user->id)->where('type', $key)->exists()) {
            $key = $base.'_'.$suffix++;
        }

        return $key;
    }
}
