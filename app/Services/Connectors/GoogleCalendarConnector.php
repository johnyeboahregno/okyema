<?php

declare(strict_types=1);

namespace App\Services\Connectors;

use App\Enums\ConnectorProvider;
use App\Models\ConnectorAccount;
use App\Services\Connectors\Contracts\CalendarConnector;
use App\Services\Connectors\Contracts\ListsCalendars;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Calendar adapter. Talks to the Calendar API over HTTPS and maps the
 * response through GoogleEventNormaliser. Requires a configured account with
 * an access token; without credentials it refuses loudly rather than faking a
 * successful sync.
 */
final class GoogleCalendarConnector implements CalendarConnector, ListsCalendars
{
    public function __construct(
        private readonly GoogleEventNormaliser $normaliser,
        private readonly ConnectorOAuthService $oauth,
    ) {}

    public function provider(): ConnectorProvider
    {
        return ConnectorProvider::Google;
    }

    public function capabilities(): array
    {
        return ['calendar.read', 'calendar.write'];
    }

    public function syncEvents(ConnectorAccount $account, ?string $cursor): SyncResult
    {
        return $this->syncCalendar($account, 'primary', $cursor);
    }

    public function listCalendars(ConnectorAccount $account): array
    {
        $token = $this->token($account);
        $calendars = [];
        $pageToken = null;

        do {
            $response = Http::withToken($token)
                ->get('https://www.googleapis.com/calendar/v3/users/me/calendarList', array_filter([
                    'minAccessRole' => 'reader',
                    'maxResults' => 250,
                    'pageToken' => $pageToken,
                ]));

            if ($response->failed()) {
                throw new RequestException($response);
            }

            foreach ($response->json('items', []) as $item) {
                if (! isset($item['id'])) {
                    continue;
                }

                $primary = (bool) ($item['primary'] ?? false);

                $calendars[] = [
                    'id' => $primary ? 'primary' : (string) $item['id'],
                    'name' => (string) ($item['summaryOverride'] ?? $item['summary'] ?? $item['id']),
                    'timezone' => $item['timeZone'] ?? null,
                    'colour' => $item['backgroundColor'] ?? null,
                    'is_primary' => $primary,
                ];
            }

            $pageToken = $response->json('nextPageToken');
        } while ($pageToken);

        return $calendars;
    }

    public function syncCalendar(ConnectorAccount $account, string $calendarId, ?string $cursor): SyncResult
    {
        $token = $this->token($account);
        $url = 'https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($calendarId).'/events';

        $items = [];
        $pageToken = null;
        $nextSyncToken = null;

        do {
            $response = Http::withToken($token)->get($url, array_filter([
                'singleEvents' => 'true',
                'maxResults' => 250,
                'syncToken' => $cursor,
                'pageToken' => $pageToken,
            ]));

            // 410 Gone: the stored sync token expired; start over with a full sync.
            if ($response->status() === 410 && $cursor !== null) {
                return $this->syncCalendar($account, $calendarId, null);
            }

            if ($response->failed()) {
                throw new RequestException($response);
            }

            foreach ($response->json('items', []) as $event) {
                $items[] = $this->normaliser->normalise($event);
            }

            $pageToken = $response->json('nextPageToken');
            $nextSyncToken = $response->json('nextSyncToken') ?? $nextSyncToken;
        } while ($pageToken);

        return new SyncResult(items: $items, nextCursor: $nextSyncToken);
    }

    private function token(ConnectorAccount $account): string
    {
        if ($account->access_token === null || $account->access_token === '') {
            throw new RuntimeException('Google Calendar is not connected. No access token is stored.');
        }

        return $this->oauth->freshAccessToken($account);
    }

    /**
     * Insert an event into the given calendar (the account's primary by default).
     *
     * @param  array{title: string, description: ?string, location: ?string, starts_at: ?string, ends_at: ?string, timezone: string, calendar_id?: string}  $event
     * @return array{id: string, url: string, title: string}
     */
    public function createEvent(ConnectorAccount $account, array $event): array
    {
        $token = $this->token($account);

        $timezone = $event['timezone'] !== '' ? $event['timezone'] : 'UTC';

        $response = Http::withToken($token)
            ->post('https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($event['calendar_id'] ?? 'primary').'/events', [
                'summary' => $event['title'],
                'description' => $event['description'] ?: null,
                'location' => $event['location'] ?: null,
                'start' => ['dateTime' => $event['starts_at'], 'timeZone' => $timezone],
                'end' => ['dateTime' => $event['ends_at'], 'timeZone' => $timezone],
            ]);

        if ($response->failed()) {
            throw new RequestException($response);
        }

        return [
            'id' => (string) $response->json('id'),
            'url' => (string) ($response->json('htmlLink') ?? ''),
            'title' => (string) ($response->json('summary') ?? $event['title']),
        ];
    }
}
