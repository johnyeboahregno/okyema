<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests land on the Okyema splash', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Okyema')
        ->assertSee('chief of staff');
});

test('signed-in users get the app shell', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/')
        ->assertOk()
        ->assertSee('id="app"', false)
        ->assertSee('Today');
});

test('the login page renders', function () {
    $this->get('/login')->assertOk()->assertSee('Sign in');
});

test('the register page renders', function () {
    $this->get('/register')->assertOk()->assertSee('Create account');
});
