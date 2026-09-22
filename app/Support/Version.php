<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Semantic version arithmetic for the one number that lives in
 * `config/okyema.php` → `okyema.app.version`.
 *
 *   MAJOR — a breaking change, or a redesign users must relearn
 *   MINOR — a new user-visible feature
 *   PATCH — a fix or a copy/layout tweak
 *
 * The version is the `?v=` cache-buster for the CSS and the PWA icons, so it
 * has to move on every release.
 */
final class Version
{
    public const TYPES = ['major', 'minor', 'patch'];

    /**
     * @param  string  $version  e.g. "0.1.0"
     * @param  string  $type  one of TYPES
     * @param  int  $step  how many to add (used when catching up)
     */
    public static function bump(string $version, string $type, int $step = 1): string
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Unknown release type [{$type}].");
        }

        if ($step < 1) {
            throw new InvalidArgumentException('Step must be at least 1.');
        }

        [$major, $minor, $patch] = self::parts($version);

        return match ($type) {
            'major' => ($major + $step).'.0.0',
            'minor' => $major.'.'.($minor + $step).'.0',
            'patch' => $major.'.'.$minor.'.'.($patch + $step),
        };
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function parts(string $version): array
    {
        $clean = ltrim(trim($version), 'vV');
        $pieces = explode('.', $clean);

        if (count($pieces) > 3 || $clean === '') {
            throw new InvalidArgumentException("[{$version}] is not a MAJOR.MINOR.PATCH version.");
        }

        $pieces = array_pad($pieces, 3, '0');

        foreach ($pieces as $piece) {
            if (! ctype_digit((string) $piece)) {
                throw new InvalidArgumentException("[{$version}] is not a MAJOR.MINOR.PATCH version.");
            }
        }

        return [(int) $pieces[0], (int) $pieces[1], (int) $pieces[2]];
    }
}
