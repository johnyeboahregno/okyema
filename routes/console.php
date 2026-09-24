<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Connected calendars are pulled every fifteen minutes so agenda, timeline
// and the meetings workspace stay current. Each account syncs idempotently
// through its provider cursor, so overlap is harmless but still prevented.
Schedule::command('calendar:sync')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
