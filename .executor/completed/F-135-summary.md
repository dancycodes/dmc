# F-135: Meal Search Bar — Completed

## Summary
Live meal search bar on tenant landing pages with 300ms debounce via Gale $navigate with fragment updates. Searches across meal names (en/fr), descriptions (en/fr), component names, and tag names using PostgreSQL ILIKE. Clear button restores full grid. SQL wildcards sanitized. Min 2 chars trigger.

## Key Files
- `app/Http/Controllers/Tenant/MealSearchController.php` — Search endpoint with fragment response
- `app/Http/Requests/Tenant/MealSearchRequest.php` — Form Request with sanitization
- `app/Services/TenantLandingService.php` — Added searchMeals() method
- `resources/views/tenant/_meal-search.blade.php` — Search bar partial
- `resources/views/tenant/_meals-grid.blade.php` — Fragment wrapper for partial DOM updates
- `tests/Unit/Tenant/MealSearchBarUnitTest.php` — 19 unit tests

## Bug Fixed
- Fragment @php block must be inside @fragment tags for Gale fragment-only rendering

## Retries: 0 (implement: 0, review: 0, test: 0)
