# F-213: Minimum Order Amount Configuration — Completion Summary

**Priority**: Must-have
**Status**: Done
**Retries**: 0 (implement: 0, review: 0, test: 0)

## What Was Built
Cook-configurable minimum order amount (0–100,000 XAF) in tenant settings. Cart enforces
minimum against food subtotal after promo (excludes delivery fee). Gale reactive checkout
button with "Add X XAF more" message. Landing page badge (hidden when 0).

## Key Files
- `app/Services/CookSettingsService.php` (MINIMUM_ORDER_AMOUNT_KEY, get/update methods)
- `app/Http/Controllers/Cook/CookSettingsController.php` (updateMinimumOrderAmount)
- `app/Http/Requests/Cook/UpdateMinimumOrderRequest.php`
- `resources/views/cook/settings/index.blade.php` (form card added)
- `resources/views/tenant/cart.blade.php` (minimum enforcement)
- `resources/views/tenant/home.blade.php` (badge display)
- `tests/Unit/CookSettingsMinimumOrderTest.php`

## Test Results
- Unit tests: 7 passing
- Playwright: 10/10 steps, 3/3 edge cases, responsive, dark mode all PASS
