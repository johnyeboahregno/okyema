<?php

declare(strict_types=1);

use App\Models\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a user can list their contexts with the active one flagged', function () {
    $user = $this->makeUser();

    $response = $this->actingAs($user)->getJson('/api/contexts');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(3);
    expect(collect($response->json('data'))->where('is_active', true))->toHaveCount(1);
});

test('activating a context moves the active flag', function () {
    $user = $this->makeUser();
    $launchpad = WorkspaceContext::where('type', 'LAUNCHPAD')->firstOrFail();

    $this->actingAs($user)->postJson("/api/contexts/{$launchpad->id}/activate")
        ->assertOk()
        ->assertJsonPath('data.key', 'LAUNCHPAD');

    $this->actingAs($user)->getJson('/api/contexts')
        ->assertJsonPath('data.1.is_active', true);
});

test('a non-member gets a 404 when activating another owner context', function () {
    $owner = $this->makeUser(['email' => 'owner@okyema.test']);
    $intruder = $this->makeUser(['email' => 'intruder@okyema.test']);

    $context = $owner->workspaceContexts()->firstOrFail();

    $intruder->memberships()->delete();

    $this->actingAs($intruder)->postJson("/api/contexts/{$context->id}/activate")
        ->assertNotFound();
});

test('the dashboard reports the active context', function () {
    $user = $this->makeUser();

    $this->actingAs($user)->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonPath('data.context.key', 'REGNO');
});

test('guests are rejected from every API resource', function () {
    $this->getJson('/api/dashboard')->assertUnauthorized();
    $this->getJson('/api/contexts')->assertUnauthorized();
});
