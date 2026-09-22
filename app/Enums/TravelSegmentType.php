<?php

declare(strict_types=1);

namespace App\Enums;

enum TravelSegmentType: string
{
    case Flight = 'flight';
    case Train = 'train';
    case Hotel = 'hotel';
    case Ground = 'ground';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Flight => 'Flight',
            self::Train => 'Train',
            self::Hotel => 'Hotel',
            self::Ground => 'Ground transport',
            self::Other => 'Other',
        };
    }
}
