<?php

declare(strict_types=1);

namespace App\Enums;

enum NoteSourceType: string
{
    case Manual = 'manual';
    case Transcript = 'transcript';
    case Import = 'import';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Transcript => 'Transcript',
            self::Import => 'Import',
        };
    }
}
