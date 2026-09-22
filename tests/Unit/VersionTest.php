<?php

declare(strict_types=1);

use App\Support\Version;

test('it bumps patch, minor and major versions', function () {
    expect(Version::bump('0.1.0', 'patch'))->toBe('0.1.1');
    expect(Version::bump('0.1.0', 'minor'))->toBe('0.2.0');
    expect(Version::bump('0.1.0', 'major'))->toBe('1.0.0');
});

test('it refuses an unknown release type', function () {
    Version::bump('0.1.0', 'banana');
})->throws(InvalidArgumentException::class);

test('it parses parts correctly', function () {
    expect(Version::parts('v1.2.3'))->toBe([1, 2, 3]);
    expect(Version::parts('1.2'))->toBe([1, 2, 0]);
});
