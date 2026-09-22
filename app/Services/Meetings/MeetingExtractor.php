<?php

declare(strict_types=1);

namespace App\Services\Meetings;

use Carbon\CarbonImmutable;

/**
 * Deterministic meeting-note extractor.
 *
 * Parses notes for simple, documented markers and never invents content. It is
 * the offline baseline for the AI path: when Regno AI is disabled or returns
 * something unusable, this is what runs.
 *
 * Supported markers (case-insensitive):
 *   Decision: <title>
 *   Action:   <title> [@owner] [by YYYY-MM-DD] [#high|#medium|#low]
 */
final class MeetingExtractor
{
    /**
     * @param  list<string>  $bodies  note bodies
     * @return array{
     *     decisions: list<array{title: string, quote: string}>,
     *     actions: list<array{title: string, owner: ?string, due_date: ?string, priority: ?string, quote: string}>
     * }
     */
    public function extract(array $bodies): array
    {
        $decisions = [];
        $actions = [];

        foreach ($bodies as $body) {
            foreach (preg_split('/\r\n|\r|\n/', (string) $body) ?: [] as $line) {
                $line = trim($line);

                if (preg_match('/^Decision\s*:\s*(.+)$/i', $line, $m) === 1) {
                    $title = trim($m[1]);

                    if ($title !== '') {
                        $decisions[] = ['title' => $title, 'quote' => $line];
                    }

                    continue;
                }

                if (preg_match('/^Action\s*:\s*(.+)$/i', $line, $m) === 1) {
                    $parsed = $this->parseAction(trim($m[1]));

                    if ($parsed['title'] !== '') {
                        $parsed['quote'] = $line;
                        $actions[] = $parsed;
                    }
                }
            }
        }

        return ['decisions' => $decisions, 'actions' => $actions];
    }

    /**
     * A factual, non-fabricated summary of the captured notes.
     *
     * @param  list<string>  $bodies
     */
    public function summarise(array $bodies, int $decisionCount, int $actionCount): string
    {
        $words = 0;

        foreach ($bodies as $body) {
            $words += count(preg_split('/\s+/', trim((string) $body)) ?: []);
        }

        return sprintf(
            'Meeting notes captured (%d note%s, %d word%s). %d decision%s and %d action%s extracted.',
            count($bodies),
            count($bodies) === 1 ? '' : 's',
            $words,
            $words === 1 ? '' : 's',
            $decisionCount,
            $decisionCount === 1 ? '' : 's',
            $actionCount,
            $actionCount === 1 ? '' : 's',
        );
    }

    /**
     * @return array{title: string, owner: ?string, due_date: ?string, priority: ?string}
     */
    private function parseAction(string $text): array
    {
        $priority = null;
        $dueDate = null;
        $owner = null;

        if (preg_match('/#(high|medium|low)\b/i', $text, $m) === 1) {
            $priority = strtolower($m[1]);
            $text = str_replace($m[0], '', $text);
        }

        if (preg_match('/\bby\s+(\d{4}-\d{2}-\d{2})\b/i', $text, $m) === 1) {
            try {
                $dueDate = CarbonImmutable::parse($m[1])->toDateString();
            } catch (\Throwable) {
                $dueDate = null;
            }
            $text = str_replace($m[0], '', $text);
        }

        if (preg_match('/@([A-Za-z][A-Za-z0-9 .\-]*)/', $text, $m) === 1) {
            $owner = trim($m[1]);
            $text = str_replace($m[0], '', $text);
        }

        return [
            'title' => trim(preg_replace('/\s+/', ' ', $text) ?? ''),
            'owner' => $owner,
            'due_date' => $dueDate,
            'priority' => $priority,
        ];
    }
}
