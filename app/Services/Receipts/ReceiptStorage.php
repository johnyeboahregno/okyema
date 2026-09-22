<?php

declare(strict_types=1);

namespace App\Services\Receipts;

use Illuminate\Http\UploadedFile;

/**
 * Preserves the original receipt file, byte-for-byte, under the private
 * storage disk. OCR/enhancement derivatives are never written here.
 */
final class ReceiptStorage
{
    /**
     * @return array{path: string, hash: string, mime: ?string, size: int}
     */
    public function store(UploadedFile $file): array
    {
        $contents = $file->getContent();
        $hash = hash('sha256', $contents);
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');

        $path = sprintf(
            'receipts/%s/%s/%s.%s',
            now()->format('Y'),
            now()->format('m'),
            $hash,
            preg_replace('/[^a-z0-9]/', '', $extension) ?: 'bin',
        );

        $file->storeAs(dirname($path), basename($path), 'local');

        return [
            'path' => $path,
            'hash' => $hash,
            'mime' => $file->getMimeType(),
            'size' => strlen($contents),
        ];
    }
}
