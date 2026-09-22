<?php

declare(strict_types=1);

namespace App\Services\Connectors;

/**
 * The outcome of one connector sync: canonical event payloads and the cursor
 * to persist for the next run. Values only — no framework coupling.
 */
final readonly class SyncResult
{
    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
    ) {}
}
