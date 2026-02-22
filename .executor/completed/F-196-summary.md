# F-196: Favorite Cook Toggle — Complete

**Priority**: Should-have
**Completed**: 2026-02-22
**Retries**: Impl(0) Rev(0) Test(0)

## Summary
Authenticated users can toggle favorite cook status on both the discovery page cook cards and tenant landing pages. Heart button updates in-place via Gale SSE. State persists across page loads. Guests see login redirect link. Idempotency guaranteed by composite PK.

## Key Files
- `database/migrations/2026_02_22_111502_create_favorite_cooks_table.php`
- `app/Http/Controllers/FavoriteCookController.php`
- `app/Models/User.php` (favoriteCooks relationship + hasFavoritedCook helper)
- `app/Http/Controllers/DiscoveryController.php` (allRelatedIds for state)
- `resources/views/discovery/_cook-card.blade.php` (heart button)
- `resources/views/tenant/home.blade.php` (heart button in hero)
- `tests/Unit/FavoriteCookTest.php` + `tests/Feature/Favorites/FavoriteCookTest.php`

## Conventions Established
- Pivot tables with only created_at: use withPivot('created_at') NOT withTimestamps()
- Per-component x-data scopes prevent shared state collisions on repeated cards
- Always use $tenant->slug (not $tenant->id) in route generation

## Bugs Fixed
1. Route 404: `$tenant->id` → `$tenant->slug` in both blade files
2. 500 error: `pluck('cook_user_id')` broken by select() constraint → `allRelatedIds()`
