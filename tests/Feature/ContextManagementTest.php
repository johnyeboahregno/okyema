<?php

declare(strict_types=1);

use App\Models\Meeting;
use App\Models\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a user can create their own context', function () {
    $user = $this->makeUser();

    $this->actingAs($user)->postJson('/api/contexts', ['name' => 'Acme Ltd'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Acme Ltd')
        ->assertJsonPath('data.key', 'ACME_LTD');

    expect($user->workspaceContexts()->count())->toBe(4);
});

test('two users can use the same context name without sharing a row', function () {
    $owner = $this->makeUser(['email' => 'owner@okyema.test']);
    $other = $this->makeUser(['email' => 'other@okyema.test']);

    $this->actingAs($owner)->postJson('/api/contexts', ['name' => 'Acme Ltd'])
        ->assertCreated()
        ->assertJsonPath('data.key', 'ACME_LTD');

    // Same key, different owner — the unique index is per user, not global.
    $this->actingAs($other)->postJson('/api/contexts', ['name' => 'Acme Ltd'])
        ->assertCreated()
        ->assertJsonPath('data.key', 'ACME_LTD');

    expect(WorkspaceContext::where('name', 'Acme Ltd')->count())->toBe(2);
});

test('each registration seeds its own copies of the default contexts', function () {
    $first = $this->makeUser(['email' => 'first@okyema.test']);
    $second = $this->makeUser(['email' => 'second@okyema.test']);

    expect($first->workspaceContexts()->count())->toBe(3);
    expect($second->workspaceContexts()->count())->toBe(3);
    expect(WorkspaceContext::count())->toBe(6);

    $regno = WorkspaceContext::where('user_id', $first->id)->where('type', 'REGNO')->firstOrFail();

    expect($regno->user_id)->toBe($first->id);
});

test('a user can rename a context they own', function () {
    $user = $this->makeUser();
    $context = $user->workspaceContexts()->firstOrFail();

    $this->actingAs($user)->patchJson("/api/contexts/{$context->id}", ['name' => 'Regno Group'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Regno Group');
});

test('a non-owner cannot rename or delete a context', function () {
    $owner = $this->makeUser(['email' => 'owner@okyema.test']);
    $intruder = $this->makeUser(['email' => 'intruder@okyema.test']);

    $context = $owner->workspaceContexts()->firstOrFail();

    $this->actingAs($intruder)->patchJson("/api/contexts/{$context->id}", ['name' => 'Nope'])
        ->assertNotFound();

    $this->actingAs($intruder)->deleteJson("/api/contexts/{$context->id}", ['delete_data' => true])
        ->assertNotFound();
});

test('deleting a context moves its items to another context', function () {
    $user = $this->makeUser();
    $regno = $user->workspaceContexts()->where('type', 'REGNO')->firstOrFail();
    $launchpad = $user->workspaceContexts()->where('type', 'LAUNCHPAD')->firstOrFail();

    $this->actingAs($user)->postJson('/api/meetings', ['title' => 'Regno kickoff'])->assertCreated();

    expect(Meeting::where('workspace_context_id', $regno->id)->count())->toBe(1);

    $this->actingAs($user)
        ->deleteJson("/api/contexts/{$regno->id}", ['move_to' => $launchpad->id])
        ->assertNoContent();

    expect(Meeting::where('workspace_context_id', $launchpad->id)->count())->toBe(1);
    expect(WorkspaceContext::where('id', $regno->id)->exists())->toBeFalse();
});

test('deleting a context can delete its items instead', function () {
    $user = $this->makeUser();
    $regno = $user->workspaceContexts()->where('type', 'REGNO')->firstOrFail();

    $this->actingAs($user)->postJson('/api/meetings', ['title' => 'Regno kickoff'])->assertCreated();

    $this->actingAs($user)->deleteJson("/api/contexts/{$regno->id}", ['delete_data' => true])
        ->assertNoContent();

    expect(Meeting::count())->toBe(0);
});

test('deleting the context that was active falls back to another one', function () {
    $user = $this->makeUser();
    $regno = $user->workspaceContexts()->where('type', 'REGNO')->firstOrFail();
    $launchpad = $user->workspaceContexts()->where('type', 'LAUNCHPAD')->firstOrFail();

    // REGNO is active on registration.
    $this->actingAs($user)->deleteJson("/api/contexts/{$regno->id}", ['move_to' => $launchpad->id])
        ->assertNoContent();

    expect($user->fresh()->memberships()->where('is_active_context', true)->value('workspace_context_id'))
        ->toBe($launchpad->id);
});

test('the last remaining context cannot be deleted', function () {
    $user = $this->makeUser();

    $contexts = $user->workspaceContexts()->get();

    // Remove two of the three, then the final one is refused.
    foreach ($contexts->take(2) as $context) {
        $this->actingAs($user)->deleteJson("/api/contexts/{$context->id}", ['delete_data' => true])
            ->assertNoContent();
    }

    $last = $user->workspaceContexts()->firstOrFail();

    $this->actingAs($user)->deleteJson("/api/contexts/{$last->id}", ['delete_data' => true])
        ->assertStatus(422);
});

test('the merged view can be activated', function () {
    $user = $this->makeUser();

    $this->actingAs($user)->postJson('/api/contexts/all/activate')
        ->assertOk()
        ->assertJsonPath('data.key', 'ALL');

    $contexts = collect($this->actingAs($user)->getJson('/api/contexts')->json('data'));

    expect($contexts->firstWhere('key', 'ALL')['is_active'])->toBeTrue();
    expect($contexts->where('is_active', true))->toHaveCount(1);

    // Switching to a real context leaves the merged view.
    $launchpad = $user->workspaceContexts()->where('type', 'LAUNCHPAD')->firstOrFail();

    $this->actingAs($user)->postJson("/api/contexts/{$launchpad->id}/activate")->assertOk();

    expect($user->fresh()->profile->all_contexts_active)->toBeFalse();
});
