<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected straight to login', function () {
    $this->get('/')->assertRedirect('/login');
});

test('signed-in users get the app shell', function () {
    config()->set('okyema.ui.mode', 'classic');

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

test('the simple interface renders when OKYEMA_UI_MODE=simple', function () {
    config()->set('okyema.ui.mode', 'simple');

    $user = User::factory()->create();

    $this->actingAs($user)->get('/')
        ->assertOk()
        ->assertSee('id="app"', false)
        ->assertSee('simple-app', false);
});

test('an invalid OKYEMA_UI_MODE renders the classic interface', function () {
    config()->set('okyema.ui.mode', 'banana');

    $user = User::factory()->create();

    $this->actingAs($user)->get('/')
        ->assertOk()
        ->assertSee('id="app"', false)
        ->assertSee('Today');
});

test('guests are redirected to login in simple mode too', function () {
    config()->set('okyema.ui.mode', 'simple');

    $this->get('/')->assertRedirect('/login');
});
