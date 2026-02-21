# F-171: Withdrawable Timer Logic

## Result: DONE (0 retries)

## Summary
Implemented the withdrawable timer system that holds cook earnings for a configurable period before making them withdrawable. Features: OrderClearance model tracking hold periods, pause/resume on complaint lifecycle, cancellation on refund, scheduled command processing eligible clearances every 5 minutes, consolidated notifications per cook, setting snapshot at creation time (BR-341).

## Key Files
- `app/Models/OrderClearance.php` — Timer state tracking with boolean flags
- `app/Services/OrderClearanceService.php` — Core service: create, pause, resume, cancel, process
- `app/Notifications/FundsWithdrawableNotification.php` — Push+DB notification
- `app/Console/Commands/ProcessWithdrawableTimersCommand.php` — Scheduled artisan command
- `database/migrations/2026_02_21_051112_create_order_clearances_table.php`
- `database/factories/OrderClearanceFactory.php` — Factory with states
- `tests/Unit/Cook/WithdrawableTimerLogicUnitTest.php` — 41 unit tests
- `tests/Feature/Cook/WithdrawableTimerLogicFeatureTest.php` — 9 feature tests

## Phases
- IMPLEMENT: 0 retries — 41 unit + 9 feature tests, 173 scoped tests passing
- REVIEW: 0 retries — All compliance checks passed
- TEST: 0 retries — 8/8 verification, 6/6 edge cases

## Conventions
- OrderClearance uses boolean flags (is_cleared, is_paused, is_cancelled)
- Scheduled commands use dancymeals: prefix
- Timer pause stores remaining_seconds_at_pause for accurate resumption
