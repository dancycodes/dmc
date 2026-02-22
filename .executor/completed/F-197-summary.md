# F-197: Favorite Meal Toggle — Complete

**Priority**: Should-have
**Completed**: 2026-02-22
**Retries**: Impl(0) Rev(0) Test(0)

## Summary
Authenticated users can toggle favorite meal status from meal cards (discovery/home grids) and meal detail pages. Gale SSE updates heart button in-place without page reload. State persists across page loads. Guests see login redirect link. Composite PK ensures idempotency.

## Key Files
- `app/Http/Controllers/FavoriteMealController.php`
- `database/migrations/2026_02_22_114651_create_favorite_meals_table.php`
- `app/Models/User.php` (favoriteMeals relationship)
- `app/Http/Controllers/DashboardController.php` + `MealDetailController.php` + `MealSearchController.php`
- `resources/views/tenant/_meal-card.blade.php` (heart button)
- `resources/views/tenant/meal-detail.blade.php` (heart button)
- `tests/Unit/FavoriteMealTest.php` (6 unit tests)

## Conventions Reinforced
- allRelatedIds() over pluck() for BelongsToMany pivot IDs
- Per-card x-data scopes for independent toggle state
- withPivot('created_at') for pivot tables with only created_at
