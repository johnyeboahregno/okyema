<?php

declare(strict_types=1);

use App\Enums\MembershipRole;
use App\Models\User;
use App\Models\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a new user can register and gets a profile and the three workspace contexts', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'John Yeboah',
        'email' => 'john@okyema.test',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.user.name', 'John Yeboah');

    $user = User::where('email', 'john@okyema.test')->firstOrFail();

    expect($user->profile)->not->toBeNull();
    expect($user->memberships)->toHaveCount(3);
    expect($user->workspaceContexts()->count())->toBe(3);

    $keys = $user->workspaceContexts()->get()->pluck('type')->map->value->all();
    expect($keys)->toContain('REGNO', 'LAUNCHPAD', 'PERSONAL');
});

test('registration rejects a weak password', function () {
    $this->postJson('/api/register', [
        'name' => 'John Yeboah',
        'email' => 'john@okyema.test',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertUnprocessable();
});

test('a user can log in and fetch their profile', function () {
    $user = $this->makeUser([
        'name' => 'John Yeboah',
        'email' => 'john@okyema.test',
        'password' => 'secret123',
    ]);

    $this->postJson('/api/login', [
        'email' => 'JOHN@okyema.test',
        'password' => 'secret123',
    ])->assertOk()->assertJsonPath('data.user.email', 'john@okyema.test');

    $this->actingAs($user)->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'john@okyema.test');
});

test('a user is an owner of every seeded context', function () {
    $user = $this->makeUser();

    expect($user->memberships()->where('role', MembershipRole::Owner->value)->count())->toBe(3);
    expect(WorkspaceContext::count())->toBe(3);
});
