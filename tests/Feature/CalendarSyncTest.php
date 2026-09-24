<?php

declare(strict_types=1);

use App\Enums\ConnectorProvider;
use App\Models\ConnectorAccount;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\SyncCursor;
use App\Models\SyncRun;
use App\Models\WorkspaceContext;
use App\Services\Connectors\CalendarSyncService;
use App\Services\Connectors\Contracts\CalendarConnector;
use App\Services\Connectors\SyncResult;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function fakeCalendarConnector(): CalendarConnector
{
    $base = CarbonImmutable::parse('2026-09-23T09:00:00+00:00');

    return new class($base) implements CalendarConnector
    {
        public function __construct(private readonly CarbonImmutable $base) {}

        public function provider(): ConnectorProvider
        {
            return ConnectorProvider::Google;
        }

        public function capabilities(): array
        {
            return ['calendar.read'];
        }

        public function syncEvents(ConnectorAccount $account, ?string $cursor): SyncResult
        {
            return new SyncResult([
                [
                    'title' => 'Stand-up',
                    'starts_at' => $this->base,
                    'ends_at' => $this->base->addHour(),
                    'is_all_day' => false,
                    'state' => 'confirmed',
                    'provider' => 'google',
                    'provider_event_id' => 'evt-1',
                    'timezone' => 'Europe/London',
                ],
                [
                    'title' => '1:1',
                    'starts_at' => $this->base->addHours(2),
                    'ends_at' => $this->base->addHours(3),
                    'is_all_day' => false,
                    'state' => 'confirmed',
                    'provider' => 'google',
                    'provider_event_id' => 'evt-2',
                    'timezone' => 'Europe/London',
                ],
            ], 'cursor-next');
        }
    };
}

test('calendar sync is idempotent and records runs and the cursor', function () {
    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();

    $account = ConnectorAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'external_account_id' => 'john@okyema.test',
        'status' => 'connected',
    ]);

    $service = app(CalendarSyncService::class);
    $connector = fakeCalendarConnector();

    $service->sync($account, $regno, $connector);
    $service->sync($account, $regno, $connector);

    expect(Event::count())->toBe(2);
    expect(SyncRun::count())->toBe(2);
    expect(SyncRun::where('status', 'success')->count())->toBe(2);
    expect(SyncCursor::where('connector_account_id', $account->id)
        ->where('resource_type', 'events')
        ->value('cursor'))->toBe('cursor-next');
});

test('a failed sync records an error run and rethrows', function () {
    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();

    $account = ConnectorAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'external_account_id' => 'john@okyema.test',
        'status' => 'connected',
    ]);

    $connector = new class implements CalendarConnector
    {
        public function provider(): ConnectorProvider
        {
            return ConnectorProvider::Google;
        }

        public function capabilities(): array
        {
            return ['calendar.read'];
        }

        public function syncEvents(ConnectorAccount $account, ?string $cursor): SyncResult
        {
            throw new RuntimeException('provider down');
        }
    };

    $service = app(CalendarSyncService::class);

    expect(fn () => $service->sync($account, $regno, $connector))
        ->toThrow(RuntimeException::class);

    expect(SyncRun::where('status', 'error')->count())->toBe(1);
    expect(SyncRun::where('status', 'error')->first()->error_message)->toContain('provider down');
});

test('sync mirrors timed, non-cancelled events into the meetings workspace', function () {
    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();

    $account = ConnectorAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'external_account_id' => 'john@okyema.test',
        'status' => 'connected',
    ]);

    $base = CarbonImmutable::parse('2026-09-23T09:00:00+00:00');

    $connector = new class($base) implements CalendarConnector
    {
        public function __construct(private readonly CarbonImmutable $base) {}

        public function provider(): ConnectorProvider
        {
            return ConnectorProvider::Google;
        }

        public function capabilities(): array
        {
            return ['calendar.read'];
        }

        public function syncEvents(ConnectorAccount $account, ?string $cursor): SyncResult
        {
            return new SyncResult([
                [
                    'title' => 'Product review',
                    'location' => 'Room 4',
                    'starts_at' => $this->base,
                    'ends_at' => $this->base->addHour(),
                    'is_all_day' => false,
                    'state' => 'confirmed',
                    'provider' => 'google',
                    'provider_event_id' => 'meeting-1',
                ],
                [
                    'title' => 'Company offsite',
                    'starts_at' => $this->base->addDay(),
                    'ends_at' => null,
                    'is_all_day' => true,
                    'state' => 'confirmed',
                    'provider' => 'google',
                    'provider_event_id' => 'allday-1',
                ],
                [
                    'title' => 'Cancelled call',
                    'starts_at' => $this->base->addHours(2),
                    'ends_at' => $this->base->addHours(3),
                    'is_all_day' => false,
                    'state' => 'cancelled',
                    'provider' => 'google',
                    'provider_event_id' => 'cancelled-1',
                ],
            ], 'next');
        }
    };

    app(CalendarSyncService::class)->sync($account, $regno, $connector);

    expect(Meeting::count())->toBe(1);

    $meeting = Meeting::first();
    expect($meeting->title)->toBe('Product review')
        ->and($meeting->location)->toBe('Room 4')
        ->and($meeting->event_id)->not->toBeNull();
});

test('a cancelled event removes its mirrored meeting', function () {
    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();

    $account = ConnectorAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'external_account_id' => 'john@okyema.test',
        'status' => 'connected',
    ]);

    $base = CarbonImmutable::parse('2026-09-23T09:00:00+00:00');
    $eventId = 'meeting-1';

    $makeConnector = fn (string $state) => new class($base, $eventId, $state) implements CalendarConnector
    {
        public function __construct(
            private readonly CarbonImmutable $base,
            private readonly string $eventId,
            private readonly string $state,
        ) {}

        public function provider(): ConnectorProvider
        {
            return ConnectorProvider::Google;
        }

        public function capabilities(): array
        {
            return ['calendar.read'];
        }

        public function syncEvents(ConnectorAccount $account, ?string $cursor): SyncResult
        {
            return new SyncResult([
                [
                    'title' => 'Product review',
                    'starts_at' => $this->base,
                    'ends_at' => $this->base->addHour(),
                    'is_all_day' => false,
                    'state' => $this->state,
                    'provider' => 'google',
                    'provider_event_id' => $this->eventId,
                ],
            ], 'next');
        }
    };

    $service = app(CalendarSyncService::class);

    $service->sync($account, $regno, $makeConnector('confirmed'));
    expect(Meeting::count())->toBe(1);

    $service->sync($account, $regno, $makeConnector('cancelled'));
    expect(Meeting::count())->toBe(0);
});
