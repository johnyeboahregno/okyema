<?php

declare(strict_types=1);

use App\Enums\ConnectorStatus;
use App\Models\ConnectorAccount;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\SyncCursor;
use App\Models\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('calendar:sync pulls every connected account and mirrors meetings', function () {
    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();

    ConnectorAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'external_account_id' => 'john@okyema.test',
        'status' => 'connected',
        'access_token' => 'token',
    ]);

    Http::fake([
        'www.googleapis.com/calendar/v3/users/me/calendarList*' => Http::response(['items' => []]),
        'www.googleapis.com/*' => Http::response([
            'items' => [
                [
                    'id' => 'evt-1',
                    'summary' => 'Board meeting',
                    'location' => 'HQ',
                    'start' => ['dateTime' => '2026-09-23T09:00:00+01:00', 'timeZone' => 'Europe/London'],
                    'end' => ['dateTime' => '2026-09-23T10:00:00+01:00', 'timeZone' => 'Europe/London'],
                    'status' => 'confirmed',
                    'etag' => '1',
                ],
            ],
            'nextSyncToken' => 'sync-1',
        ], 200),
    ]);

    $this->artisan('calendar:sync')->assertSuccessful();

    expect(Event::count())->toBe(1)
        ->and(Meeting::count())->toBe(1);

    $meeting = Meeting::first();
    expect($meeting->title)->toBe('Board meeting')
        ->and($meeting->workspace_context_id)->toBe($regno->id);
});

test('calendar:sync skips accounts that are not connected', function () {
    $user = $this->makeUser();

    ConnectorAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'external_account_id' => 'john@okyema.test',
        'status' => ConnectorStatus::Disconnected->value,
        'access_token' => 'token',
    ]);

    Http::fake();

    $this->artisan('calendar:sync')->assertSuccessful();

    Http::assertNothingSent();
    expect(Event::count())->toBe(0);
});

test('calendar:sync pulls secondary and shared calendars with their own cursors', function () {
    $user = $this->makeUser();

    $account = ConnectorAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'external_account_id' => 'john@okyema.test',
        'status' => 'connected',
        'access_token' => 'token',
    ]);

    $event = fn (string $id, string $title) => [
        'id' => $id,
        'summary' => $title,
        'start' => ['dateTime' => '2026-09-23T09:00:00Z'],
        'end' => ['dateTime' => '2026-09-23T10:00:00Z'],
        'status' => 'confirmed',
    ];

    Http::fake([
        'www.googleapis.com/calendar/v3/users/me/calendarList*' => Http::response(['items' => [
            ['id' => 'john@okyema.test', 'summary' => 'John', 'primary' => true],
            ['id' => 'team@group.calendar.google.com', 'summary' => 'Team', 'timeZone' => 'Europe/London'],
        ]]),
        'www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::response([
            'items' => [$event('p-1', 'Mine')], 'nextSyncToken' => 'sync-primary',
        ]),
        'www.googleapis.com/calendar/v3/calendars/team%40group.calendar.google.com/events*' => Http::response([
            'items' => [$event('t-1', 'Shared')], 'nextSyncToken' => 'sync-team',
        ]),
    ]);

    $this->artisan('calendar:sync')->assertSuccessful();

    expect(Event::count())->toBe(2)
        ->and($account->calendars()->count())->toBe(2)
        ->and(SyncCursor::where('connector_account_id', $account->id)->pluck('cursor', 'resource_type')->all())
        ->toBe(['events' => 'sync-primary', 'events:team@group.calendar.google.com' => 'sync-team']);
});

test('an expired Google access token is refreshed before syncing', function () {
    $user = $this->makeUser();
    config()->set('services.connectors.google.client_id', 'cid');
    config()->set('services.connectors.google.client_secret', 'secret');

    $account = ConnectorAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'external_account_id' => 'john@okyema.test',
        'status' => 'connected',
        'access_token' => 'old',
        'refresh_token' => 'rt',
        'expires_at' => now()->subHour(),
    ]);

    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'new', 'expires_in' => 3600]),
        'www.googleapis.com/*' => Http::response(['items' => []]),
    ]);

    $this->artisan('calendar:sync')->assertSuccessful();

    expect($account->fresh()->access_token)->toBe('new');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'calendarList') && $r->hasHeader('Authorization', 'Bearer new'));
});
