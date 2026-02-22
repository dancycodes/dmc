# F-217: Cook Promo Code Deactivation — Complete

**Priority**: Should-have
**Completed**: 2026-02-22
**Retries**: Impl(0) Rev(0) Test(1)

## Summary
Individual toggle switch and bulk deactivate with checkboxes on promo codes list. Filter tabs (All/Active/Inactive/Expired). Sort controls (Date/Usage/Expiry). Expired codes auto-detected from ends_at, shown dimmed with disabled toggle and no checkbox. Activity logged on all status changes.

## Key Files
- `app/Models/PromoCode.php` (computeIsExpired static method)
- `app/Services/PromoCodeService.php` (toggleStatus, bulkDeactivate)
- `app/Http/Controllers/Cook/PromoCodeController.php`
- `resources/views/cook/promo-codes/index.blade.php`
- `tests/Unit/PromoCodeTest.php` (30 unit tests total)

## Bug Fixed
- `@js()` Blade directive not compiled inside PHP string interpolation — pre-compute with `json_encode()` in `@php` block
