<?php

declare(strict_types=1);

namespace App\Enums;

enum ConnectorProvider: string
{
    case Google = 'google';
    case Microsoft = 'microsoft';
    case Notion = 'notion';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::Microsoft => 'Microsoft',
            self::Notion => 'Notion',
        };
    }
}
