# F-216: Cook Promo Code Edit — Complete

**Priority**: Should-have
**Completed**: 2026-02-22
**Retries**: Impl(1) Rev(1) Test(0)

## Summary
Edit inline modal on promo codes list. Immutable fields (code, discount_type) shown read-only with lock icons. Editable: discount_value, min order, max_uses, max_uses_per_client, starts_at, ends_at. Usage warning (amber) and exhaustion warning (red). Activity logged. Fragment list update on save.

## Key Files
- `app/Http/Controllers/Cook/PromoCodeController.php`
- `app/Services/PromoCodeService.php`
- `app/Http/Requests/Cook/UpdatePromoCodeRequest.php`
- `resources/views/cook/promo-codes/index.blade.php`
- `tests/Unit/PromoCodeTest.php` (21 unit tests)

## Bugs Fixed
- Edit Gale action requires POST route (not GET)
- `Schema::hasTable('promo_code_usages')` guard added — forward-compatible with F-218
