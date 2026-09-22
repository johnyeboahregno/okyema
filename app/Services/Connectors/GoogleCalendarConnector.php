<?php

declare(strict_types=1);

namespace App\Services\Connectors;

use App\Enums\ConnectorProvider;
use App\Models\ConnectorAccount;
use App\Services\Connectors\Contracts\CalendarConnector;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Calendar adapter. Talks to the Calendar API over HTTPS and maps the
 * response through GoogleEventNormaliser. Requires a configured account with
 * an access token; without credentials it refuses loudly rather than faking a
 * successful sync.
 */
final class GoogleCalendarConnector implements CalendarConnector
{
    public function __construct(
        private readonly GoogleEventNormaliser $normaliser,
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
        $token = $account->access_token;

        if ($token === null || $token === '') {
            throw new RuntimeException('Google Calendar is not connected. No access token is stored.');
        }

        $response = Http::withToken($token)
            ->get('https://www.googleapis.com/calendar/v3/calendars/primary/events', [
                'singleEvents' => 'true',
                'orderBy' => 'startTime',
                'maxResults' => 250,
                'syncToken' => $cursor,
            ]);

        if ($response->failed()) {
            throw new RequestException($response);
        }

        $items = $response->json('items', []);

        return new SyncResult(
            items: array_map(fn (array $event) => $this->normaliser->normalise($event), $items),
            nextCursor: $response->json('nextSyncToken'),
        );
    }
}
