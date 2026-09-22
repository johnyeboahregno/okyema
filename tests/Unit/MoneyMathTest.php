<?php

declare(strict_types=1);

use App\Services\MoneyMath;

test('it parses human money strings into integer minor units', function () {
    expect(MoneyMath::majorToMinor('342.50'))->toBe(34250);
    expect(MoneyMath::majorToMinor('550'))->toBe(55000);
    expect(MoneyMath::majorToMinor('1,200.00'))->toBe(120000);
    expect(MoneyMath::majorToMinor('-5.05'))->toBe(-505);
});

test('it formats minor units back to a display string', function () {
    expect(MoneyMath::minorToMajor(34250))->toBe('342.50');
    expect(MoneyMath::minorToMajor(-505))->toBe('-5.05');
    expect(MoneyMath::minorToMajor(9))->toBe('0.09');
});

test('it splits an amount exactly across weights', function () {
    $parts = MoneyMath::split(100, ['a' => 1, 'b' => 2]);

    expect(array_sum($parts))->toBe(100);
    expect($parts['b'])->toBeGreaterThanOrEqual($parts['a']);
});

test('it rejects invalid money', function () {
    MoneyMath::majorToMinor('12.3.4');
})->throws(InvalidArgumentException::class);
