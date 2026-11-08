<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The three offers: record new moments, e-mail the windows that just opened.
// Needs the scheduler running (docker compose: `scheduler`; production:
// deploy/supervisor/construye-scheduler.conf).
Schedule::command('offers:send')->everyTenMinutes()->withoutOverlapping();
