<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a new user has not dismissed the tour', function () {
    $user = $this->makeUser();

    $me = $this->actingAs($user)->getJson('/api/me')->assertOk()->json('data');

    expect($me['profile']['onboarding_dismissed_at'])->toBeNull();
});

test('finishing the tour is remembered on the profile', function () {
    $user = $this->makeUser();

    $this->actingAs($user)
        ->patchJson('/api/profile', ['onboarding_dismissed' => true])
        ->assertOk();

    expect($user->profile()->first()?->onboarding_dismissed_at)->not->toBeNull();

    // It follows the user to any device, because /api/me exposes it.
    $me = $this->actingAs($user)->getJson('/api/me')->assertOk()->json('data');
    expect($me['profile']['onboarding_dismissed_at'])->not->toBeNull();
});

test('replaying the tour clears the dismissal flag', function () {
    $user = $this->makeUser();
    $user->profile()->update(['onboarding_dismissed_at' => now()]);

    $this->actingAs($user)
        ->patchJson('/api/profile', ['onboarding_dismissed' => false])
        ->assertOk();

    expect($user->profile()->first()?->onboarding_dismissed_at)->toBeNull();
});

test('the dismissal flag must be a boolean', function () {
    $user = $this->makeUser();

    $this->actingAs($user)
        ->patchJson('/api/profile', ['onboarding_dismissed' => 'maybe'])
        ->assertUnprocessable();
});

test('updating the profile leaves the dismissal flag alone when it is omitted', function () {
    $user = $this->makeUser();
    $user->profile()->update(['onboarding_dismissed_at' => now()]);

    $this->actingAs($user)
        ->patchJson('/api/profile', ['display_name' => 'John'])
        ->assertOk();

    expect($user->profile()->first()?->onboarding_dismissed_at)->not->toBeNull();
});

test('the app shell renders the tour', function () {
    $user = $this->makeUser();

    $this->actingAs($user)->get('/')
        ->assertOk()
        ->assertSee('Welcome to Okyema', false)
        ->assertSee('Your workspaces', false)
        ->assertSee('Never show me this again', false)
        ->assertSee('Skip for now', false)
        ->assertSee('Show me the tour', false)
        ->assertSee('tour__spot', false);
});
