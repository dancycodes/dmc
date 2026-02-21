# F-172: Cook Withdrawal Request

## Result: DONE (0 retries)

## Summary
Cook withdrawal request form with atomic balance deduction, daily limit enforcement, mobile money provider auto-detection from Cameroon phone prefixes, confirmation modal, Alpine.js real-time validation. Platform settings for min/max withdrawal amounts.

## Key Files
- `app/Models/WithdrawalRequest.php` — Model with status/provider constants
- `app/Services/WithdrawalRequestService.php` — Atomic withdrawal with lockForUpdate
- `app/Http/Requests/Cook/StoreWithdrawalRequest.php` — Form request validation
- `resources/views/cook/wallet/withdraw.blade.php` — Alpine.js form with confirmation modal
- `database/migrations/2026_02_21_054332_create_withdrawal_requests_table.php`
- `database/factories/WithdrawalRequestFactory.php`
- `tests/Unit/Cook/WithdrawalRequestUnitTest.php` — 26 unit tests

## Phases
- IMPLEMENT: 0 retries — 26 unit tests, 125 scoped tests passing
- REVIEW: 0 retries — All compliance checks passed
- TEST: 0 retries — 8/8 verification, 3/3 edge cases, 2 bugs fixed, responsive PASS, theme PASS
