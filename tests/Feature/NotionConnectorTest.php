<?php

declare(strict_types=1);

use App\Enums\ConnectorProvider;
use App\Enums\ConnectorStatus;
use App\Models\ConnectorAccount;
use App\Services\Connectors\ConnectorOAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('the connectors endpoint exposes notion without leaking tokens', function () {
    $user = $this->makeUser();

    ConnectorAccount::create([
        'user_id' => $user->id,
        'provider' => ConnectorProvider::Notion,
        'status' => ConnectorStatus::Connected,
        'access_token' => 'super-secret',
    ]);

    $response = $this->actingAs($user)->getJson('/api/connectors')
        ->assertOk()
        ->assertJsonPath('data.0.provider', 'notion')
        ->assertJsonPath('data.0.capabilities', ['notion'])
        ->assertJsonPath('data.0.status', 'connected');

    $response->assertJsonMissing(['access_token' => 'super-secret']);
});

test('notion authorization url targets the notion oauth endpoint', function () {
    config()->set('services.connectors.notion.client_id', 'n-client');
    config()->set('services.connectors.notion.client_secret', 'n-secret');

    $url = app(ConnectorOAuthService::class)->authorizationUrl(ConnectorProvider::Notion, 'state-123');

    expect($url)
        ->toContain('https://api.notion.com/v1/oauth/authorize')
        ->toContain('client_id=n-client')
        ->toContain('owner=user');
});

test('notion token exchange uses basic auth and a notion-version header', function () {
    config()->set('services.connectors.notion.client_id', 'n-client');
    config()->set('services.connectors.notion.client_secret', 'n-secret');
    config()->set('okyema.notion.version', '2022-06-28');

    Http::fake(function ($request) {
        if ($request->url() === 'https://api.notion.com/v1/oauth/token') {
            expect($request->hasHeader('Authorization'))->toBeTrue();
            expect($request->hasHeader('Notion-Version', '2022-06-28'))->toBeTrue();

            return Http::response(['access_token' => 'ntok']);
        }

        return Http::response([]);
    });

    $tokens = app(ConnectorOAuthService::class)->exchange(ConnectorProvider::Notion, 'code-1');

    expect($tokens['access_token'])->toBe('ntok')
        ->and($tokens['expires_at'])->toBeNull();
});

test('notion account id reads the workspace id from users/me', function () {
    config()->set('okyema.notion.version', '2022-06-28');

    Http::fake(function ($request) {
        if (str_contains($request->url(), '/v1/users/me')) {
            return Http::response(['bot' => ['workspace_id' => 'ws-abc']]);
        }

        return Http::response([]);
    });

    $id = app(ConnectorOAuthService::class)->accountId(ConnectorProvider::Notion, 'ntok');

    expect($id)->toBe('ws-abc');
});
