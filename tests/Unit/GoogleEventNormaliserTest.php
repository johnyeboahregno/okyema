<?php

declare(strict_types=1);

use App\Enums\EventState;
use App\Services\Connectors\GoogleEventNormaliser;

$normaliser = new GoogleEventNormaliser;

it('normalises a timed Google event into canonical fields', function () use ($normaliser) {
    $payload = $normaliser->normalise([
        'id' => 'abc123',
        'etag' => '"revision-1"',
        'summary' => 'Board meeting',
        'description' => 'Quarterly review',
        'location' => 'Room 1',
        'status' => 'confirmed',
        'htmlLink' => 'https://calendar.google.com/event?eid=abc',
        'start' => ['dateTime' => '2026-09-23T09:00:00+01:00', 'timeZone' => 'Europe/London'],
        'end' => ['dateTime' => '2026-09-23T10:00:00+01:00', 'timeZone' => 'Europe/London'],
        'recurrence' => ['RRULE:FREQ=WEEKLY'],
        'recurringEventId' => 'master-1',
    ]);

    expect($payload['title'])->toBe('Board meeting');
    expect($payload['description'])->toBe('Quarterly review');
    expect($payload['location'])->toBe('Room 1');
    expect($payload['provider'])->toBe('google');
    expect($payload['provider_event_id'])->toBe('abc123');
    expect($payload['provider_revision'])->toBe('"revision-1"');
    expect($payload['state'])->toBe(EventState::Confirmed);
    expect($payload['is_all_day'])->toBeFalse();
    expect($payload['timezone'])->toBe('Europe/London');
    expect($payload['external_url'])->toContain('calendar.google.com');
    expect($payload['recurrence_rule'])->toBe('RRULE:FREQ=WEEKLY');
    expect($payload['recurrence_id'])->toBe('master-1');

    // 09:00 +01:00 (BST) normalises to 08:00 UTC.
    expect($payload['starts_at']->toIso8601String())->toBe('2026-09-23T08:00:00+00:00');
    expect($payload['ends_at']->toIso8601String())->toBe('2026-09-23T09:00:00+00:00');
});

it('normalises an all-day Google event', function () use ($normaliser) {
    $payload = $normaliser->normalise([
        'id' => 'day-1',
        'summary' => 'Company offsite',
        'start' => ['date' => '2026-09-25'],
        'end' => ['date' => '2026-09-26'],
    ]);

    expect($payload['is_all_day'])->toBeTrue();
    expect($payload['starts_at']->toIso8601String())->toBe('2026-09-25T00:00:00+00:00');
    expect($payload['state'])->toBe(EventState::Confirmed);
});

it('maps Google status to canonical states', function (string $status, EventState $expected) use ($normaliser) {
    $payload = $normaliser->normalise([
        'id' => 'e',
        'summary' => 'X',
        'status' => $status,
        'start' => ['dateTime' => '2026-09-23T09:00:00Z'],
        'end' => ['dateTime' => '2026-09-23T10:00:00Z'],
    ]);

    expect($payload['state'])->toBe($expected);
})->with([
    'tentative' => ['tentative', EventState::Tentative],
    'cancelled' => ['cancelled', EventState::Cancelled],
]);

it('uses a placeholder title for untitled events', function () use ($normaliser) {
    $payload = $normaliser->normalise([
        'id' => 'e',
        'start' => ['dateTime' => '2026-09-23T09:00:00Z'],
        'end' => ['dateTime' => '2026-09-23T10:00:00Z'],
    ]);

    expect($payload['title'])->toBe('(No title)');
});
