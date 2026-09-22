<?php

declare(strict_types=1);

use App\Enums\ConnectorProvider;
use App\Models\ConnectorAccount;
use App\Models\Event;
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
