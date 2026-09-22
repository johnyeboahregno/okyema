<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * Pure, DB-free calendar conflict detection.
 *
 * Spans are canonical events in UTC. Two events conflict when one starts
 * before the other ends (strict overlap). All-day events are ignored: they do
 * not block specific minutes of the day.
 */
final class CalendarConflicts
{
    /**
     * @param  list<array{id: int|string, starts_at: CarbonImmutable, ends_at: ?CarbonImmutable}>  $spans
     * @return list<int|string> ids involved in at least one overlap
     */
    public static function detect(array $spans): array
    {
        $timed = array_values(array_filter(
            $spans,
            fn (array $span) => $span['ends_at'] !== null,
        ));

        usort(
            $timed,
            fn (array $a, array $b) => $a['starts_at']->getTimestamp() <=> $b['starts_at']->getTimestamp(),
        );

        $conflicting = [];
        $maxEnd = null;

        foreach ($timed as $span) {
            if ($maxEnd !== null && $span['starts_at']->lt($maxEnd)) {
                $conflicting[$span['id']] = true;
            }

            $maxEnd = $maxEnd === null
                ? $span['ends_at']
                : ($span['ends_at']->gt($maxEnd) ? $span['ends_at'] : $maxEnd);
        }

        return array_keys($conflicting);
    }
}
