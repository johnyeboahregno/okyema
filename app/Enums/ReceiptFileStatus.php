<?php

declare(strict_types=1);

namespace App\Enums;

enum ReceiptFileStatus: string
{
    case Stored = 'stored';
    case Filed = 'filed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Stored => 'Stored',
            self::Filed => 'Filed',
            self::Failed => 'Failed',
        };
    }
}
