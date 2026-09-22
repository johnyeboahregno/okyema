<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * Pure, DB-free money arithmetic.
 *
 * Every monetary amount is an integer number of minor units (pence for GBP).
 * Floating-point arithmetic is never used. Faithful to SIKA's MoneyMath.
 */
final class MoneyMath
{
    private const MINOR_UNIT = 100;

    /**
     * Convert a human-entered amount ("£342.50", "550", "1,200.00") into
     * integer minor units. String parsing avoids floating-point error.
     */
    public static function majorToMinor(string $amount): int
    {
        $amount = trim($amount);
        $sign = 1;

        if (str_starts_with($amount, '-')) {
            $sign = -1;
            $amount = substr($amount, 1);
        }

        $clean = preg_replace('/[^0-9.]/', '', $amount);
        if ($clean === null || $clean === '' || substr_count($clean, '.') > 1) {
            throw new InvalidArgumentException("Invalid money amount [{$amount}].");
        }

        [$whole, $fraction] = array_pad(explode('.', $clean, 2), 2, '');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        return $sign * ((int) $whole * self::MINOR_UNIT + (int) $fraction);
    }

    /**
     * Format integer minor units as a display string ("342.50").
     */
    public static function minorToMajor(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $abs = abs($minor);
        $whole = intdiv($abs, self::MINOR_UNIT);
        $fraction = str_pad((string) ($abs % self::MINOR_UNIT), 2, '0', STR_PAD_LEFT);

        return $sign.$whole.'.'.$fraction;
    }

    /**
     * Split an amount across weights so the parts sum EXACTLY to $amount.
     * Largest-remainder method with integer-only arithmetic.
     *
     * @param  array<int|string, int>  $weights  non-negative integer weights
     * @return array<int|string, int>
     */
    public static function split(int $amount, array $weights): array
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Amount must be non-negative.');
        }

        $total = array_sum($weights);
        if ($total <= 0) {
            throw new InvalidArgumentException('Total weight must be positive.');
        }

        $result = [];
        $remainders = [];

        foreach ($weights as $key => $weight) {
            $numerator = $amount * (int) $weight;
            $result[$key] = intdiv($numerator, $total);
            $remainders[$key] = $numerator % $total;
        }

        $left = $amount - array_sum($result);

        arsort($remainders);
        $keys = array_keys($remainders);

        for ($i = 0; $i < $left; $i++) {
            $result[$keys[$i]]++;
        }

        return $result;
    }
}
