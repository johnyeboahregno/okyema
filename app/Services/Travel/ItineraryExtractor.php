<?php

declare(strict_types=1);

namespace App\Services\Travel;

use Carbon\CarbonImmutable;

/**
 * Deterministic itinerary extractor for forwarded emails and uploaded
 * documents. It only recognises documented markers and never invents details;
 * the caller presents the result for the user to confirm before saving.
 *
 * Supported markers (case-insensitive):
 *   Trip: <title>
 *   Dates: YYYY-MM-DD to YYYY-MM-DD
 *   Flight|Train|Hotel|Ground: <details> [from <A> to <B>] [on YYYY-MM-DD]
 */
final class ItineraryExtractor
{
    /**
     * @return array{title: ?string, starts_on: ?string, ends_on: ?string, segments: list<array<string, mixed>>}
     */
    public function extract(string $text): array
    {
        $title = null;
        $startsOn = null;
        $endsOn = null;
        $segments = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);

            if (preg_match('/^Trip\s*:\s*(.+)$/i', $line, $m) === 1) {
                $title = trim($m[1]);

                continue;
            }

            if (preg_match('/^Dates\s*:\s*(\d{4}-\d{2}-\d{2})\s+to\s+(\d{4}-\d{2}-\d{2})/i', $line, $m) === 1) {
                $startsOn = $this->date($m[1]);
                $endsOn = $this->date($m[2]);

                continue;
            }

            if (preg_match('/^(Flight|Train|Hotel|Ground)\s*:\s*(.+)$/i', $line, $m) === 1) {
                $segments[] = $this->segment(strtolower($m[1]), trim($m[2]));
            }
        }

        return [
            'title' => $title,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'segments' => $segments,
        ];
    }

    /**
     * @return array{type: string, details: string, from_location: ?string, to_location: ?string, starts_at: ?string}
     */
    private function segment(string $type, string $details): array
    {
        $from = null;
        $to = null;
        $startsAt = null;

        if (preg_match('/\bfrom\s+(.+?)\s+to\s+(.+?)(?:\s+on\s+\d{4}-\d{2}-\d{2})?$/i', $details, $m) === 1) {
            $from = trim($m[1]);
            $to = trim($m[2]);
        }

        if (preg_match('/\bon\s+(\d{4}-\d{2}-\d{2})\b/i', $details, $m) === 1) {
            $startsAt = $this->date($m[1]);
        }

        return [
            'type' => $type,
            'details' => $details,
            'from_location' => $from,
            'to_location' => $to,
            'starts_at' => $startsAt,
        ];
    }

    private function date(string $value): ?string
    {
        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
