<?php

declare(strict_types=1);

use App\Services\CalendarConflicts;
use Carbon\CarbonImmutable;

$base = CarbonImmutable::parse('2026-09-23T09:00:00+00:00');

it('flags events that overlap', function () use ($base) {
    $conflicts = CalendarConflicts::detect([
        ['id' => 'a', 'starts_at' => $base, 'ends_at' => $base->addMinutes(60)],
        ['id' => 'b', 'starts_at' => $base->addMinutes(30), 'ends_at' => $base->addMinutes(90)],
        ['id' => 'c', 'starts_at' => $base->addMinutes(120), 'ends_at' => $base->addMinutes(180)],
    ]);

    expect($conflicts)->toBe(['b']);
});

it('does not flag adjacent events as conflicts', function () use ($base) {
    $conflicts = CalendarConflicts::detect([
        ['id' => 'a', 'starts_at' => $base, 'ends_at' => $base->addMinutes(60)],
        ['id' => 'b', 'starts_at' => $base->addMinutes(60), 'ends_at' => $base->addMinutes(120)],
    ]);

    expect($conflicts)->toBe([]);
});

it('ignores events without an end time', function () use ($base) {
    $conflicts = CalendarConflicts::detect([
        ['id' => 'a', 'starts_at' => $base, 'ends_at' => $base->addMinutes(60)],
        ['id' => 'b', 'starts_at' => $base->addMinutes(10), 'ends_at' => null],
    ]);

    expect($conflicts)->toBe([]);
});
