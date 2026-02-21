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

/*
 * F-152 BR-382: Auto-cancellation of orders that have exceeded the retry window.
 * Runs every minute to ensure timely cancellation of expired orders.
 */
Schedule::command('dancymeals:cancel-expired-orders')->everyMinute();

/*
 * F-171 BR-336: Scheduled job runs every 5 minutes to transition eligible
 * unwithdrawable funds to withdrawable after the hold period expires.
 */
Schedule::command('dancymeals:process-withdrawable-timers')->everyFiveMinutes();

/*
 * F-173 BR-356: Process pending withdrawal requests via Flutterwave Transfer API.
 * Runs every 2 minutes to promptly process new withdrawal requests.
 */
Schedule::command('dancymeals:process-withdrawals')->everyTwoMinutes();

/*
 * F-173 BR-360: Verify pending_verification transfers with Flutterwave.
 * Runs every 5 minutes to resolve transfers stuck in pending_verification.
 */
Schedule::command('dancymeals:verify-pending-transfers')->everyFiveMinutes();
