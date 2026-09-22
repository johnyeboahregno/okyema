<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The three information boundaries every stored object belongs to.
 * The enum values are the canonical, provider-neutral keys.
 */
enum WorkspaceContextType: string
{
    case Regno = 'REGNO';
    case Launchpad = 'LAUNCHPAD';
    case Personal = 'PERSONAL';

    public function label(): string
    {
        return match ($this) {
            self::Regno => 'Regno',
            self::Launchpad => 'Launchpad',
            self::Personal => 'Personal',
        };
    }
}
