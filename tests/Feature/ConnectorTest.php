<?php

declare(strict_types=1);

use App\Enums\ConnectorProvider;
use App\Enums\ConnectorStatus;
use App\Models\ConnectorAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the connectors endpoint returns an empty list when nothing is connected', function () {
    $user = $this->makeUser();

    $this->actingAs($user)->getJson('/api/connectors')
        ->assertOk()
        ->assertJsonPath('data', []);
});

test('the connectors endpoint lists a connected account without exposing tokens', function () {
    $user = $this->makeUser();

    ConnectorAccount::create([
        'user_id' => $user->id,
        'provider' => ConnectorProvider::Google,
        'status' => ConnectorStatus::Connected,
        'access_token' => 'super-secret',
    ]);

    $response = $this->actingAs($user)->getJson('/api/connectors')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.provider', 'google')
        ->assertJsonPath('data.0.status', 'connected')
        ->assertJsonPath('data.0.capabilities', ['calendar.read']);

    $response->assertJsonMissing(['access_token' => 'super-secret']);
});
