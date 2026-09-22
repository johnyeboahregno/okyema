<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a user can update their profile timezone and display name', function () {
    $user = $this->makeUser();

    $this->actingAs($user)->patchJson('/api/profile', [
        'display_name' => 'John',
        'timezone' => 'Europe/London',
    ])->assertOk()
        ->assertJsonPath('data.display_name', 'John')
        ->assertJsonPath('data.timezone', 'Europe/London');
});

test('profile update rejects an invalid timezone length', function () {
    $user = $this->makeUser();

    $this->actingAs($user)->patchJson('/api/profile', ['timezone' => str_repeat('x', 200)])
        ->assertUnprocessable();
});
