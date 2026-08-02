<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-skip queue entries whose court call was not confirmed within 2 minutes.
Schedule::command('queue:skip-expired-calls')->everyMinute();

// Close visitor sessions that went idle so average session lengths stay honest.
Schedule::command('visitor-sessions:close-stale')->everyFiveMinutes();
