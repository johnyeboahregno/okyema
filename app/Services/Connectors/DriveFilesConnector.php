<?php

declare(strict_types=1);

namespace App\Services\Connectors;

use App\Models\Receipt;
use RuntimeException;

/**
 * Google Drive adapter for the receipt workflow (files.write capability).
 * Requires a stored access token; without credentials it refuses loudly so the
 * filing step is recorded as pending rather than faked.
 */
final class DriveFilesConnector
{
    public function capabilities(): array
    {
        return ['files.read', 'files.write'];
    }

    /**
     * @return array{file_id: string, link: string}
     */
    public function upload(string $folder, string $filename, string $contents, string $mime, ?string $token): array
    {
        if ($token === null || $token === '') {
            throw new RuntimeException('Google Drive is not connected. No access token is stored.');
        }

        // A real implementation resolves/creates the folder by path and uploads
        // the file with the given name and MIME type, then returns the file id
        // and its canonical link. Kept as a boundary so domain code never sees
        // the Drive SDK.
        throw new RuntimeException('Drive upload requires a connected Google account (files.write).');
    }
}
