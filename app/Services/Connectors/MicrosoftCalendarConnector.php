<?php

declare(strict_types=1);

namespace App\Services\Connectors;

use App\Enums\ConnectorProvider;
use App\Models\ConnectorAccount;
use App\Services\Connectors\Contracts\CalendarConnector;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Microsoft Outlook Calendar adapter (Microsoft Graph). Shares the canonical
 * payload contract with the Google adapter. Requires a configured account with
 * an access token; without credentials it refuses loudly.
 */
final class MicrosoftCalendarConnector implements CalendarConnector
{
    public function provider(): ConnectorProvider
    {
        return ConnectorProvider::Microsoft;
    }

    public function capabilities(): array
    {
        return ['calendar.read'];
    }

    public function syncEvents(ConnectorAccount $account, ?string $cursor): SyncResult
    {
        $token = $account->access_token;

        if ($token === null || $token === '') {
            throw new RuntimeException('Microsoft Calendar is not connected. No access token is stored.');
        }

        // Graph delta query: cursor is the $deltatoken from the previous run.
        $url = $cursor !== null && $cursor !== ''
            ? "https://graph.microsoft.com/v1.0/me/calendarView/delta?\$deltatoken={$cursor}"
            : 'https://graph.microsoft.com/v1.0/me/calendarView/delta?startDateTime=2000-01-01T00:00:00Z';

        $response = Http::withToken($token)->get($url);

        if ($response->failed()) {
            throw new RequestException($response);
        }

        $items = $response->json('value', []);

        return new SyncResult(
            items: array_map(fn (array $event) => $this->normalise($event), $items),
            nextCursor: $this->extractDeltaToken($response->json('@odata.nextLink') ?? $response->json('@odata.deltaLink')),
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalise(array $raw): array
    {
        $start = $raw['start'] ?? [];
        $end = $raw['end'] ?? [];

        return [
            'title' => trim((string) ($raw['subject'] ?? '')) !== '' ? (string) $raw['subject'] : '(No title)',
            'description' => $raw['bodyPreview'] ?? null,
            'location' => isset($raw['location']['displayName']) ? (string) $raw['location']['displayName'] : null,
            'starts_at' => $this->parse($start['dateTime'] ?? $start['date']),
            'ends_at' => $this->parse($end['dateTime'] ?? $end['date']),
            'is_all_day' => $raw['isAllDay'] ?? false,
            'state' => $this->state($raw),
            'timezone' => $start['timeZone'] ?? null,
            'provider' => 'microsoft',
            'provider_event_id' => $raw['id'] ?? null,
            'provider_revision' => $raw['@removed'] ?? null ? null : ($raw['etag'] ?? null),
            'external_url' => $raw['webLink'] ?? null,
            'recurrence_rule' => null,
            'recurrence_id' => $raw['seriesMasterId'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function state(array $raw): string
    {
        if (isset($raw['@removed'])) {
            return 'cancelled';
        }

        if (($raw['isCancelled'] ?? false) === true) {
            return 'cancelled';
        }

        if (($raw['showAs'] ?? null) === 'tentative') {
            return 'tentative';
        }

        return 'confirmed';
    }

    private function parse(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value)->utc();
    }

    private function extractDeltaToken(?string $link): ?string
    {
        if ($link === null || $link === '') {
            return null;
        }

        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

        return isset($query['$deltatoken']) && is_string($query['$deltatoken']) ? $query['$deltatoken'] : null;
    }
}
