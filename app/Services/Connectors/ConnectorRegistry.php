<?php

declare(strict_types=1);

namespace App\Services\Connectors;

use App\Enums\ConnectorProvider;
use App\Services\Connectors\Contracts\CalendarConnector;

/**
 * Resolves the adapter for a provider. Provider code never leaks past here.
 */
final class ConnectorRegistry
{
    public function calendar(ConnectorProvider $provider): CalendarConnector
    {
        return match ($provider) {
            ConnectorProvider::Google => app(GoogleCalendarConnector::class),
            ConnectorProvider::Microsoft => app(MicrosoftCalendarConnector::class),
        };
    }
}
