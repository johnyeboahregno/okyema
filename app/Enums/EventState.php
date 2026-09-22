<?php

declare(strict_types=1);

namespace App\Enums;

enum EventState: string
{
    case Confirmed = 'confirmed';
    case Tentative = 'tentative';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmed',
            self::Tentative => 'Tentative',
            self::Cancelled => 'Cancelled',
        };
    }
}
