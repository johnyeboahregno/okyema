<?php

declare(strict_types=1);

use App\Enums\ConnectorStatus;
use App\Models\ConnectorAccount;
use App\Models\Event;
use App\Models\Meeting;
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
