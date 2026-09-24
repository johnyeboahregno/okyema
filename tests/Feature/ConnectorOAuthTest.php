<?php

declare(strict_types=1);

use App\Enums\ConnectorStatus;
use App\Models\ConnectorAccount;
use App\Models\SyncRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function googleCalendarConfigured(): void
{
    config([
        'services.connectors.google.client_id' => 'google-client',
        'services.connectors.google.client_secret' => 'google-secret',
    ]);
}

function microsoftCalendarConfigured(): void
{
    config([
        'services.connectors.microsoft.client_id' => 'ms-client',
        'services.connectors.microsoft.client_secret' => 'ms-secret',
    ]);
}

function redirectState(string $location): string
{
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    return (string) ($query['state'] ?? '');
}

test('the connector redirect sends the user to Google and stores a state', function () {
    $user = $this->makeUser();
    googleCalendarConfigured();

    $response = $this->actingAs($user)->get('/connectors/google/redirect');

    $location = (string) $response->headers->get('Location');
    expect($location)->toContain('https://accounts.google.com/o/oauth2/v2/auth');

    $state = redirectState($location);
    expect($state)->not->toBe('')
        ->and(session()->get('connector.oauth.google.state'))->toBe($state);
});

test('the connector redirect asks Microsoft for calendar read plus offline access', function () {
    $user = $this->makeUser();
    microsoftCalendarConfigured();

    $response = $this->actingAs($user)->get('/connectors/microsoft/redirect');

    $location = (string) $response->headers->get('Location');
    expect($location)->toContain('https://login.microsoftonline.com/common/oauth2/v2.0/authorize');

    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    expect($query['scope'])->toContain('Calendars.Read')
        ->and($query['scope'])->toContain('offline_access');
});

test('the connector redirect refuses when the provider is not configured', function () {
    $user = $this->makeUser();

    $this->actingAs($user)->get('/connectors/google/redirect')
        ->assertRedirect('/?tab=settings');

    expect(session()->get('connector_notice'))->toContain('not configured');
});

test('the callback stores a connected account and syncs it immediately', function () {
    $user = $this->makeUser();
    googleCalendarConfigured();

    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'at-1',
            'refresh_token' => 'rt-1',
            'expires_in' => 3600,
        ]),
        'www.googleapis.com/oauth2/v3/userinfo' => Http::response(['email' => 'john@okyema.test']),
        'www.googleapis.com/calendar/*' => Http::response([
            'items' => [],
            'nextSyncToken' => 'sync-1',
        ]),
    ]);

    $redirect = $this->actingAs($user)->get('/connectors/google/redirect');
    $state = redirectState((string) $redirect->headers->get('Location'));

    $this->actingAs($user)->get('/connectors/google/callback?code=auth-code&state='.urlencode($state))
        ->assertRedirect('/?tab=settings');

    $account = ConnectorAccount::first();
    expect($account)->not->toBeNull()
        ->and($account->status)->toBe(ConnectorStatus::Connected)
        ->and($account->provider->value)->toBe('google')
        ->and($account->external_account_id)->toBe('john@okyema.test')
        ->and($account->access_token)->toBe('at-1')
        ->and($account->refresh_token)->toBe('rt-1')
        ->and($account->scopes)->toBe(['https://www.googleapis.com/auth/calendar.readonly']);

    // The just-connected calendar is pulled in straight away.
    expect(SyncRun::count())->toBe(1)
        ->and($account->fresh()->last_synced_at)->not->toBeNull();
});

test('the callback rejects a mismatched state', function () {
    $user = $this->makeUser();
    googleCalendarConfigured();

    Http::fake();

    $this->actingAs($user)->get('/connectors/google/callback?code=auth-code&state=wrong')
        ->assertRedirect('/?tab=settings');

    expect(ConnectorAccount::count())->toBe(0);
});

test('a user can disconnect their own connector account', function () {
    $user = $this->makeUser();

    $account = ConnectorAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'external_account_id' => 'john@okyema.test',
        'status' => 'connected',
        'access_token' => 'at-1',
        'refresh_token' => 'rt-1',
    ]);

    $this->actingAs($user)->deleteJson("/api/connectors/{$account->id}")
        ->assertNoContent();

    $account->refresh();
    expect($account->status)->toBe(ConnectorStatus::Disconnected)
        ->and($account->access_token)->toBeNull()
        ->and($account->refresh_token)->toBeNull()
        ->and($account->revoked_at)->not->toBeNull();
});

test('a user cannot disconnect someone else connector account', function () {
    $owner = $this->makeUser();
    $intruder = $this->makeUser(['email' => 'intruder@okyema.test']);

    $account = ConnectorAccount::create([
        'user_id' => $owner->id,
        'provider' => 'google',
        'external_account_id' => 'john@okyema.test',
        'status' => 'connected',
    ]);

    $this->actingAs($intruder)->deleteJson("/api/connectors/{$account->id}")
        ->assertForbidden();
});
