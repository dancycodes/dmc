<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * BR-140: Scheduled cleanup removes activity log entries older than the retention period.
 * Runs weekly on Sunday at 3:00 AM Africa/Douala timezone.
 * Uses --force to skip confirmation prompt in production.
 */
Schedule::command('activitylog:clean --force')->weekly()->sundays()->at('03:00');
