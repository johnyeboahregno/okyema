<?php

declare(strict_types=1);

namespace App\Enums;

enum ConnectorStatus: string
{
    case Connected = 'connected';
    case Disconnected = 'disconnected';
    case Error = 'error';
    case NeedsReauth = 'needs_reauth';

    public function label(): string
    {
        return match ($this) {
            self::Connected => 'Connected',
            self::Disconnected => 'Disconnected',
            self::Error => 'Error',
            self::NeedsReauth => 'Needs re-authorisation',
        };
    }
}
