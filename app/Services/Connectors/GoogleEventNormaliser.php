<?php

declare(strict_types=1);

namespace App\Services\Connectors;

use App\Enums\EventState;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Maps a raw Google Calendar API event into the canonical event payload.
 * Pure and DB-free, so it is covered by fixture tests with secrets removed.
 */
final class GoogleEventNormaliser
{
    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public function normalise(array $raw): array
    {
        $start = $raw['start'] ?? [];
        $end = $raw['end'] ?? [];

        $allDay = isset($start['date']) && ! isset($start['dateTime']);

        $startsAt = $this->resolveTime($start, $allDay);
        $endsAt = $this->resolveTime($end, $allDay);

        return [
            'title' => $this->title($raw),
            'description' => $raw['description'] ?? null,
            'location' => $raw['location'] ?? null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'is_all_day' => $allDay,
            'state' => $this->state($raw['status'] ?? 'confirmed'),
            'timezone' => $start['timeZone'] ?? null,
            'provider' => 'google',
            'provider_event_id' => $raw['id'] ?? null,
            'provider_revision' => $this->revision($raw),
            'external_url' => $raw['htmlLink'] ?? null,
            'recurrence_rule' => ($raw['recurrence'] ?? [])[0] ?? null,
            'recurrence_id' => $raw['recurringEventId'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $when
     */
    private function resolveTime(array $when, bool $allDay): CarbonImmutable
    {
        if ($allDay) {
            $date = (string) ($when['date'] ?? '');

            if ($date === '') {
                throw new InvalidArgumentException('All-day events must carry a date.');
            }

            return CarbonImmutable::parse($date, 'UTC')->startOfDay();
        }

        $dateTime = (string) ($when['dateTime'] ?? '');

        if ($dateTime === '') {
            throw new InvalidArgumentException('Timed events must carry a dateTime.');
        }

        // dateTime is RFC3339 with an offset; parsing converts to UTC.
        return CarbonImmutable::parse($dateTime)->utc();
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function title(array $raw): string
    {
        $summary = trim((string) ($raw['summary'] ?? ''));

        return $summary !== '' ? $summary : '(No title)';
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function revision(array $raw): ?string
    {
        $etag = (string) ($raw['etag'] ?? '');

        return $etag !== '' ? $etag : null;
    }

    private function state(string $status): EventState
    {
        return EventState::tryFrom(strtolower($status)) ?? EventState::Confirmed;
    }
}
