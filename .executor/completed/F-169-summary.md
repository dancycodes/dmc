# F-169: Cook Wallet Dashboard

## Result: DONE (0 retries)

## Summary
Cook wallet dashboard displaying total balance split into withdrawable/unwithdrawable amounts, recent transactions (last 10), earnings summary (total earned, withdrawn, pending), 6-month earnings bar chart, and withdraw button (active only when withdrawable > 0). Manager read-only access with can-manage-cook-wallet permission. All amounts in XAF format. Tenant-scoped with lazy wallet creation.

## Key Files
- `app/Models/CookWallet.php` — Model with lazy creation, formatting methods
- `app/Services/CookWalletService.php` — Business logic, balance recalculation, earnings data
- `app/Http/Controllers/Cook/WalletController.php` — Single index() with permission check
- `resources/views/cook/wallet/index.blade.php` — Dashboard with cards, chart, transactions
- `database/migrations/2026_02_21_042145_create_cook_wallets_table.php`
- `database/factories/CookWalletFactory.php` — Factory with states
- `tests/Unit/Cook/CookWalletDashboardUnitTest.php` — 26 unit tests

## Phases
- IMPLEMENT: 0 retries — 26 unit tests passing
- REVIEW: 0 retries — All compliance checks passed
- TEST: 0 retries — 8/8 verification, 3/3 edge cases, responsive PASS, theme PASS

## Conventions
- CookWallet lazy creation via getOrCreateForTenant()
- Alpine.js bar chart for earnings visualization (no external chart library)
