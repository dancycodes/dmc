# F-096: Meal-Specific Location Override — Completed

## Summary
Meal-level location override with custom delivery fees. Cooks can toggle custom locations on a meal edit page, selecting specific delivery quarters with optional custom fees and/or pickup locations. Toggle off reverts to global settings.

## Key Files
- `app/Models/MealLocationOverride.php` — Model with meal/quarter/pickup relationships
- `app/Services/MealLocationOverrideService.php` — Business logic
- `app/Http/Controllers/Cook/MealLocationOverrideController.php` — Gale controller
- `app/Http/Requests/Cook/UpdateMealLocationOverrideRequest.php` — Validation
- `resources/views/cook/meals/_location-override.blade.php` — Alpine.js UI
- `database/migrations/2026_02_18_143943_create_meal_location_overrides_table.php` — DB table
- `tests/Unit/Cook/MealLocationOverrideUnitTest.php` — 32 unit tests

## Gate Results
- IMPLEMENT: PASS (0 retries)
- REVIEW: PASS (0 retries)
- TEST: PASS (0 retries, 7/7 verification, 3/3 edge cases, responsive+theme PASS)

## Conventions
- syncGaleState() pattern for complex nested forms bridging Alpine UI state to Gale
