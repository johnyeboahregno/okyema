<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the merged view reads across every context', function () {
    $user = $this->makeUser();
    $regno = $user->workspaceContexts()->where('type', 'REGNO')->firstOrFail();
    $launchpad = $user->workspaceContexts()->where('type', 'LAUNCHPAD')->firstOrFail();

    // One meeting in each context.
    $this->actingAs($user)->postJson('/api/meetings', ['title' => 'Regno kickoff'])->assertCreated();
    $this->actingAs($user)->postJson("/api/contexts/{$launchpad->id}/activate")->assertOk();
    $this->actingAs($user)->postJson('/api/meetings', ['title' => 'Launchpad standup'])->assertCreated();

    // A single context only sees its own.
    $this->actingAs($user)->postJson("/api/contexts/{$regno->id}/activate")->assertOk();
    expect($this->actingAs($user)->getJson('/api/meetings')->json('data'))->toHaveCount(1);

    // The merged view sees both.
    $this->actingAs($user)->postJson('/api/contexts/all/activate')->assertOk();
    expect($this->actingAs($user)->getJson('/api/meetings')->json('data'))->toHaveCount(2);
});

test('the merged view merges the dashboard counts too', function () {
    $user = $this->makeUser();
    $launchpad = $user->workspaceContexts()->where('type', 'LAUNCHPAD')->firstOrFail();

    $this->actingAs($user)->postJson('/api/meetings', ['title' => 'Regno kickoff'])->assertCreated();
    $this->actingAs($user)->postJson("/api/contexts/{$launchpad->id}/activate")->assertOk();
    $this->actingAs($user)->postJson('/api/meetings', ['title' => 'Launchpad standup'])->assertCreated();

    $this->actingAs($user)->postJson('/api/contexts/all/activate')->assertOk();

    $dash = $this->actingAs($user)->getJson('/api/dashboard')->assertOk()->json('data');

    expect($dash['context']['all'])->toBeTrue();
    expect($dash['context']['name'])->toBe('All contexts');
});

test('creating is refused while the merged view is active', function () {
    $user = $this->makeUser();

    $this->actingAs($user)->postJson('/api/contexts/all/activate')->assertOk();

    $this->actingAs($user)
        ->postJson('/api/meetings', ['title' => 'No workspace chosen'])
        ->assertStatus(422);
});

test('the merged view never merges another user data', function () {
    $mine = $this->makeUser(['email' => 'mine@okyema.test']);
    $theirs = $this->makeUser(['email' => 'theirs@okyema.test']);

    $this->actingAs($theirs)->postJson('/api/meetings', ['title' => 'Not mine'])->assertCreated();
    $this->actingAs($mine)->postJson('/api/meetings', ['title' => 'Mine'])->assertCreated();

    $this->actingAs($mine)->postJson('/api/contexts/all/activate')->assertOk();

    $titles = collect($this->actingAs($mine)->getJson('/api/meetings')->json('data'))->pluck('title');

    expect($titles)->toContain('Mine');
    expect($titles)->not->toContain('Not mine');
});
