<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled AI digests (capability #5)
|--------------------------------------------------------------------------
| The weekly business health digest runs on the configured day/time. The
| command is idempotent, so a re-run for the same week will not duplicate a
| business's digest. Delivery is disabled entirely when
| ai.health.delivery.enabled is false.
*/
Schedule::command('ai:weekly-digests')
    ->weeklyOn(
        (int) config('ai.health.delivery.day_of_week', 1),
        (string) config('ai.health.delivery.time', '06:00'),
    )
    ->withoutOverlapping()
    ->name('ai:weekly-digests');
