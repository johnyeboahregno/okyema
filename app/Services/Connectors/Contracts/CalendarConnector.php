<?php

declare(strict_types=1);

namespace App\Services\Connectors\Contracts;

use App\Enums\ConnectorProvider;
use App\Models\ConnectorAccount;
use App\Services\Connectors\SyncResult;

/**
 * A calendar connector adapter (ADR-002). Provider SDKs / APIs live behind
 * this interface only; domain services and the UI never see a provider type.
 */
interface CalendarConnector
{
    public function provider(): ConnectorProvider;

    /**
     * Capabilities this connector grants, e.g. `calendar.read`.
     *
     * @return list<string>
     */
    public function capabilities(): array;

    /**
     * Fetch events since (or after) the given cursor. Returns canonical event
     * payloads plus the cursor to store for the next run. Must be idempotent:
     * the caller may receive the same event more than once.
     */
    public function syncEvents(ConnectorAccount $account, ?string $cursor): SyncResult;
}
