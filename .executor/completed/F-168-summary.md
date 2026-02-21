# F-168: Client Wallet Payment for Orders

## Result: DONE (0 retries)

## Summary
Added wallet payment option to checkout. Clients can pay fully from wallet (no Flutterwave redirect) or partially (wallet + mobile money via Flutterwave). Wallet hidden when balance is 0 or admin disables. Atomic deductions with pessimistic locking. Wallet reversal on mobile money failure (BR-308).

## Key Files
- `app/Services/WalletPaymentService.php` — Deduct/reversal methods
- `app/Services/CheckoutService.php` — Wallet option building and validation
- `app/Http/Controllers/Tenant/CheckoutController.php` — Payment flow orchestration
- `app/Services/PaymentService.php` — Charge amount = grand_total - wallet_amount
- `app/Services/WebhookService.php` — Wallet reversal on failure
- `resources/views/tenant/checkout/payment.blade.php` — Wallet UI toggle
- `database/migrations/2026_02_21_033515_add_wallet_amount_to_orders_table.php`
- `tests/Unit/Client/WalletPaymentForOrdersUnitTest.php` — 14 unit tests

## Phases
- IMPLEMENT: 0 retries — 14 unit tests, 127 scoped tests passing
- REVIEW: 0 retries — All compliance checks passed
- TEST: 0 retries — 6/6 verification, 3/3 edge cases, responsive PASS, theme PASS

## Conventions
- Partial wallet payment uses wallet_{provider} as payment_provider value
- Wallet deduction before external payment with reversal on failure pattern
