<?php

declare(strict_types=1);

namespace App\Enums;

enum TripStatus: string
{
    case Planned = 'planned';
    case Booked = 'booked';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::Booked => 'Booked',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }
}
