# F-175: Commission Deduction on Completion — Completed

## Summary
Automatic commission deduction from cook earnings when orders complete. Calculates commission
based on tenant-configured rate (default 10%), deducts from subtotal only (not delivery fee),
records informational wallet transaction, and triggers clearance timer for withdrawable funds.

## Key Files
- `app/Services/CommissionDeductionService.php` — Core commission calculation and deduction logic
- `app/Services/OrderStatusService.php` — Calls CommissionDeductionService on order completion
- `app/Models/Order.php` — Added commission_amount and commission_rate fields
- `database/migrations/2026_02_21_090456_add_commission_fields_to_orders_table.php`

## Test Coverage
- 24 unit tests + 6 feature tests, 155 assertions
- Edge cases: 0% rate, fractional rounding, 50% rate, zero subtotal, missing cook, cancelled order

## Retries
- IMPLEMENT: 0, REVIEW: 0, TEST: 0
