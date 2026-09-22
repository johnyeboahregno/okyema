<?php

declare(strict_types=1);

namespace App\Services\Receipts;

use Carbon\CarbonImmutable;

/**
 * Deterministic Drive destination for a receipt (ADR-005).
 *
 *   Business Receipts/{Workspace}/{YYYY}/{YYYY-MM}/
 *   {YYYY-MM-DD}_{merchant}_{currency}_{amount-minor}_{short-id}.{ext}
 */
final class ReceiptNaming
{
    public function folder(string $workspaceKey, CarbonImmutable $date): string
    {
        return sprintf(
            'Business Receipts/%s/%s/%s',
            $workspaceKey,
            $date->format('Y'),
            $date->format('Y-m'),
        );
    }

    public function filename(
        CarbonImmutable $date,
        string $merchant,
        string $currency,
        int $amountMinor,
        string $shortId,
        string $ext,
    ): string {
        return sprintf(
            '%s_%s_%s_%d_%s.%s',
            $date->format('Y-m-d'),
            $this->slug($merchant),
            strtoupper($currency),
            $amountMinor,
            $shortId,
            $ext,
        );
    }

    private function slug(string $merchant): string
    {
        $slug = strtolower(trim($merchant));
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'merchant';
    }
}
