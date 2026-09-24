<?php

declare(strict_types=1);

use App\Support\UiMode;

test('ui mode resolves to classic when unset', function () {
    config()->set('okyema.ui.mode', null);

    expect(UiMode::resolve())->toBe('classic');
});

test('ui mode resolves simple', function () {
    config()->set('okyema.ui.mode', 'simple');

    expect(UiMode::resolve())->toBe('simple');
});

test('an invalid ui mode falls back to classic', function () {
    config()->set('okyema.ui.mode', 'banana');

    expect(UiMode::resolve())->toBe('classic');
});
