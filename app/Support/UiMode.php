<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Resolves the deployment's interface mode (OKYEMA_UI_MODE=classic|simple).
 *
 * The value is read at runtime from config('okyema.ui.mode') — the app has no
 * build step, so changing the environment variable and restarting PHP (or
 * re-caching config with `php artisan config:cache`) is all that is required.
 * Invalid or missing values fall back to 'classic' and are reported through
 * the application's normal configuration logging.
 */
final class UiMode
{
    public const CLASSIC = 'classic';

    public const SIMPLE = 'simple';

    /** @var list<string> */
    private const ALLOWED = [self::CLASSIC, self::SIMPLE];

    public static function resolve(): string
    {
        $mode = (string) config('okyema.ui.mode', self::CLASSIC);

        if (in_array($mode, self::ALLOWED, true)) {
            return $mode;
        }

        Log::warning('okyema.ui_mode.invalid', [
            'value' => $mode,
            'allowed' => self::ALLOWED,
            'falling_back_to' => self::CLASSIC,
        ]);

        return self::CLASSIC;
    }

    public static function isSimple(): bool
    {
        return self::resolve() === self::SIMPLE;
    }
}
