# F-179: Cook Overall Rating Calculation

## Summary
Cook overall rating calculation using simple mean of all order star ratings. Rating cached in tenant settings JSON. Displayed on discovery cook cards (X.X format or "New"), tenant landing hero section, and cook dashboard stat card with trend indicator. Recalculated automatically when new ratings are submitted.

## Key Files
- `app/Services/RatingService.php` — recalculateCookRating(), getCachedCookRating()
- `app/Services/CookDashboardService.php` — getRatingStats() with trend calculation
- `resources/views/cook/dashboard.blade.php` — Rating stat card with x-component
- `resources/views/discovery/_cook-card.blade.php` — Rating display on cook cards
- `resources/views/tenant/home.blade.php` — Rating in hero section
- `tests/Unit/Rating/CookOverallRatingCalculationUnitTest.php` — 18 unit tests

## Verification
- 7/7 verification steps passed
- 3/3 edge cases passed
- Responsive: PASS (375px, 768px, 1280px)
- Theme: PASS (light + dark)
- Bugs fixed: 3 (pre-existing column name mismatches in CookDashboardService)
- Retries: Impl(0) + Rev(0) + Test(0)
