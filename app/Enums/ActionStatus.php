<?php

declare(strict_types=1);

namespace App\Enums;

enum ActionStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case Delegated = 'delegated';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In progress',
            self::Waiting => 'Waiting',
            self::Delegated => 'Delegated',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    /** "Overdue" is derived from due_date, never stored. */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled], true);
    }
}
