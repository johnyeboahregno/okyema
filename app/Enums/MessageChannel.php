<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageChannel: string
{
    case Email = 'email';
    case Slack = 'slack';
    case Teams = 'teams';
    case Line = 'line';
    case Linear = 'linear';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Slack => 'Slack',
            self::Teams => 'Teams',
            self::Line => 'LINE',
            self::Linear => 'Linear',
        };
    }
}
