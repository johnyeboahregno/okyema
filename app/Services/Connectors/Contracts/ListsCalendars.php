<?php

declare(strict_types=1);

namespace App\Services\Connectors\Contracts;

use App\Models\ConnectorAccount;
use App\Services\Connectors\SyncResult;

/**
 * Implemented by connectors whose account can expose more than one calendar
 * (secondary, shared and subscribed calendars alongside the primary one).
 */
interface ListsCalendars
{
    /**
     * Every calendar the account can read. The account's own primary calendar
     * is reported with id `primary` so existing rows keep matching.
     *
     * @return list<array{id: string, name: string, timezone: ?string, colour: ?string, is_primary: bool}>
     */
    public function listCalendars(ConnectorAccount $account): array;

    /**
     * Fetch events for one calendar since the given cursor.
     */
    public function syncCalendar(ConnectorAccount $account, string $calendarId, ?string $cursor): SyncResult;
}
