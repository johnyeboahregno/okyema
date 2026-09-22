<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Finds a CA certificate bundle for outbound HTTPS requests.
 *
 * PHP on Windows ships without one, so Guzzle/Socialite calls fail with
 * "cURL error 60: SSL certificate problem". Look for a bundle already on the
 * machine (Git for Windows, XAMPP, Laragon) and let `CA_BUNDLE_PATH` override
 * the lot. Faithful copy of the SIKA helper.
 */
final class CaBundle
{
    public static function path(): ?string
    {
        foreach (self::candidates() as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public static function guzzleOptions(array $options = []): array
    {
        if (array_key_exists('verify', $options)) {
            return $options;
        }

        return $options + ['verify' => self::path() ?? true];
    }

    public static function unavailable(): bool
    {
        return self::path() === null;
    }

    /**
     * @return array<int, string|null>
     */
    private static function candidates(): array
    {
        return array_filter([
            config('okyema.http.ca_bundle'),
            getenv('CA_BUNDLE_PATH') ?: null,
            ini_get('openssl.cafile') ?: null,
            ini_get('curl.cainfo') ?: null,
            base_path('cacert.pem'),
            storage_path('app/cacert.pem'),
            storage_path('cacert.pem'),
            ...self::commonLocationBundles(),
        ], fn ($candidate) => is_string($candidate) && $candidate !== '');
    }

    /**
     * @return array<int, string>
     */
    private static function commonLocationBundles(): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return ['/etc/ssl/certs/ca-certificates.crt', '/etc/pki/tls/certs/ca-bundle.crt'];
        }

        $programFiles = getenv('ProgramFiles') ?: 'C:\\Program Files';
        $programFilesX86 = getenv('ProgramFiles(x86)') ?: 'C:\\Program Files (x86)';

        return [
            $programFiles.'\\Git\\mingw64\\etc\\ssl\\certs\\ca-bundle.crt',
            $programFiles.'\\Git\\usr\\ssl\\certs\\ca-bundle.crt',
            $programFiles.'\\Git\\mingw64\\ssl\\certs\\ca-bundle.crt',
            $programFilesX86.'\\Git\\mingw64\\etc\\ssl\\certs\\ca-bundle.crt',
            $programFilesX86.'\\Git\\usr\\ssl\\certs\\ca-bundle.crt',
            'C:\\php\\extras\\ssl\\cacert.pem',
            'C:\\xampp\\php\\cacert.pem',
            'C:\\laragon\\etc\\ssl\\cacert.pem',
            'C:\\tools\\php\\extras\\ssl\\cacert.pem',
        ];
    }
}
