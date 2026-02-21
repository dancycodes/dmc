# F-186: Complaint-Triggered Payment Block — Summary

**Status**: Done | **Priority**: Must-have | **Retries**: 0

## Summary
When a complaint is filed, the system checks the cook's payment status. If unwithdrawable, the timer is paused and remaining seconds stored. If already withdrawable, the payment is flagged for review. On resolution: dismiss resumes the timer, refund cancels the clearance. Blocked/flagged amounts are excluded from withdrawal balance. Cook wallet and order detail views show clear block indicators with lock icons and badges.

## Key Files
- app/Services/PaymentBlockService.php
- database/migrations/2025_02_21_add_complaint_block_fields_to_order_clearances_table.php
- tests/Unit/Complaint/PaymentBlockServiceTest.php
- resources/views/cook/wallet/index.blade.php
- resources/views/cook/orders/show.blade.php
- resources/views/cook/wallet/withdraw.blade.php

## Test Results
- 42 unit tests in PaymentBlockServiceTest
- 59 scoped tests passing (0 failures)
- Playwright: 7/7 verification + 4/4 edge cases, responsive (375/768/1280px), light+dark themes

## Conventions Established
- PaymentBlockService pattern for complaint-payment integration
- OrderClearance complaint blocking fields (is_paused, is_flagged_for_review, blocked_at, unblocked_at, remaining_seconds_at_pause, complaint_id)
