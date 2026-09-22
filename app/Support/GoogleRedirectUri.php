<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The redirect URI Google sends the user back to.
 *
 *  - `GOOGLE_REDIRECT_URI` always wins when it is set.
 *  - Without it, production derives the URI from `APP_URL`, never from the
 *    hostname the visitor happened to use.
 *  - Locally we follow whatever host the developer is browsing.
 */
final class GoogleRedirectUri
{
    public static function resolve(): string
    {
        $configured = trim((string) config('services.google.redirect'));

        if ($configured !== '') {
            return $configured;
        }

        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl !== '' && app()->environment('production')) {
            return $appUrl.'/auth/google/callback';
        }

        $secure = request()->isSecure() || str_starts_with($appUrl, 'https://');

        return url('/auth/google/callback', [], $secure);
    }

    public static function derived(): bool
    {
        return trim((string) config('services.google.redirect')) === '';
    }
}
