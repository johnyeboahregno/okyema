<?php

declare(strict_types=1);

use App\Services\Receipts\ReceiptNaming;
use Carbon\CarbonImmutable;

$naming = new ReceiptNaming;

it('builds the deterministic Drive folder', function () use ($naming) {
    expect($naming->folder('REGNO', CarbonImmutable::parse('2026-09-22')))
        ->toBe('Business Receipts/REGNO/2026/2026-09');
});

it('builds the deterministic filename with integer minor units', function () use ($naming) {
    expect($naming->filename(
        CarbonImmutable::parse('2026-09-22'),
        'Costa Coffee',
        'GBP',
        1450,
        'ab12cd34',
        'jpg',
    ))->toBe('2026-09-22_costa-coffee_GBP_1450_ab12cd34.jpg');
});

it('slugifies merchant names safely', function () use ($naming) {
    expect($naming->filename(CarbonImmutable::parse('2026-09-22'), '  Pret A Manger!  ', 'GBP', 100, 'x', 'png'))
        ->toBe('2026-09-22_pret-a-manger_GBP_100_x.png');
});
