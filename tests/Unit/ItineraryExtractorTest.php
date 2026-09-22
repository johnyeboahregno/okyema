<?php

declare(strict_types=1);

use App\Services\Travel\ItineraryExtractor;

it('extracts a trip and its segments from forwarded text', function () {
    $result = (new ItineraryExtractor)->extract(implode("\n", [
        'Trip: Regno offsite',
        'Dates: 2026-10-12 to 2026-10-14',
        'Flight: BA123 from London to Lisbon on 2026-10-12',
        'Hotel: The Lumiares on 2026-10-12',
        'Ground: Airport transfer on 2026-10-12',
    ]));

    expect($result['title'])->toBe('Regno offsite');
    expect($result['starts_on'])->toBe('2026-10-12');
    expect($result['ends_on'])->toBe('2026-10-14');
    expect($result['segments'])->toHaveCount(3);
    expect($result['segments'][0]['type'])->toBe('flight');
    expect($result['segments'][0]['from_location'])->toBe('London');
    expect($result['segments'][0]['to_location'])->toBe('Lisbon');
    expect($result['segments'][0]['starts_at'])->toBe('2026-10-12');
});

it('ignores unrecognised lines', function () {
    $result = (new ItineraryExtractor)->extract('No markers here at all.');

    expect($result['title'])->toBeNull();
    expect($result['segments'])->toBe([]);
});
