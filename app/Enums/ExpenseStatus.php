<?php

declare(strict_types=1);

namespace App\Enums;

enum ExpenseStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Submitted = 'submitted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Confirmed => 'Confirmed',
            self::Submitted => 'Submitted',
        };
    }
}
