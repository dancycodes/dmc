# F-178: Rating & Review Display on Meal

## Summary
Rating and review display on meal detail page. Shows average rating (X.X/5) with star visualization, total review count, star distribution bars, and paginated individual reviews (10 per page) sorted newest first. Each review shows privacy-formatted client name (first name + last initial), star rating, review text if any, and relative date. Empty state for meals with no reviews. Load more via Gale fragment. Responsive and dark mode support.

## Key Files
- `resources/views/tenant/_meal-reviews.blade.php` — Reviews partial with Gale fragment
- `app/Services/RatingService.php` — getMealReviews, getMealRatingStats, formatReviewForDisplay, formatClientName, getMealReviewDisplayData
- `app/Http/Controllers/Tenant/MealDetailController.php` — show() with reviewData, loadMoreReviews()
- `resources/views/tenant/meal-detail.blade.php` — Reviews section below locations
- `tests/Unit/Tenant/MealReviewDisplayUnitTest.php` — 21 unit tests

## Verification
- 6/6 verification steps passed
- 5/5 edge cases passed
- Responsive: PASS (375px, 768px, 1280px)
- Theme: PASS (light + dark)
- Bugs fixed: 0
- Retries: Impl(0) + Rev(0) + Test(0)

## Conventions
- PostgreSQL JSONB containment operator for linking ratings to meals via order items_snapshot
- Client name privacy: first name + last initial via RatingService::formatClientName()
- Gale fragment pattern for paginated load-more
