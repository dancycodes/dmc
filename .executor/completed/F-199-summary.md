# F-199: Reorder from Past Order — Complete

**Priority**: Should-have
**Completed**: 2026-02-22
**Retries**: Impl(0) Rev(0) Test(0)

## Summary
Reorder button on eligible past order detail pages (completed/delivered/picked-up). Pre-fills cart with current prices. Shows price change banners on cart page. Cart conflict modal for cross-tenant reorders. Graceful handling of unavailable items/inactive tenants.

## Key Files
- `app/Services/ReorderService.php`
- `app/Http/Controllers/Client/OrderController.php` (reorder() method)
- `resources/views/client/orders/show.blade.php` (reorder button + conflict modal)
- `resources/views/tenant/cart.blade.php` (reorder warnings + price change banners)
- `tests/Unit/Client/ReorderServiceUnitTest.php` (15 unit tests)

## Convention Established
- When x-interval polling coexists with a button action on the same page, use local Alpine state variable (isReordering) not global $fetching() to track loading state

## Bugs Fixed
1. Reorder button stuck in "Preparing..." on page load — x-interval fires $fetching() globally; fixed with local isReordering state variable
