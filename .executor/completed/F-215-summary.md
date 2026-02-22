# F-215: Cook Promo Code Creation — Complete

**Priority**: Should-have
**Completed**: 2026-02-22
**Retries**: Impl(0) Rev(0) Test(0)

## Summary
Cook promo code creation at /dashboard/promo-codes. Gale modal form, auto-uppercase code input, percentage/fixed discount types, optional end date, unlimited/limited use counts. Tenant-scoped, activity logged. List updates via Gale fragment on creation. Mobile card + desktop table layout.

## Key Files
- `app/Models/PromoCode.php`
- `app/Services/PromoCodeService.php`
- `app/Http/Controllers/Cook/PromoCodeController.php`
- `resources/views/cook/promo-codes/index.blade.php`
- `database/migrations/2026_02_22_153548_create_promo_codes_table.php`
- `tests/Unit/PromoCodeTest.php` (12 unit tests)

## Bug Fixed
- Stale Gale validation messages persisted on modal reopen — fixed by `this.messages = {}` in `closeModal()`
