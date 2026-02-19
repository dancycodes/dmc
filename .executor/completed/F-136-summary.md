# F-136: Meal Filters — Completed

## Summary
Tag multi-select filter (OR logic), availability filter (All/Available Now), price range filter
using starting price (MIN component price), all combinable with AND logic and F-135 search.
Desktop sidebar + mobile bottom sheet overlay. Active filter badge, clear filters button.

## Metrics
- Implement retries: 0
- Review retries: 0
- Test retries: 0
- Tests written: 27 unit tests
- Bugs found/fixed: 1 (price range filter used whereHas instead of MIN subquery)

## Key Files
- app/Services/TenantLandingService.php — getFilterData(), filterMeals(), applyAvailableNowFilter()
- app/Http/Controllers/Tenant/MealSearchController.php — search with filter support
- app/Http/Requests/Tenant/MealSearchRequest.php — filter validation + helpers
- resources/views/tenant/_meal-filters.blade.php — Alpine filter component
- resources/views/tenant/_meal-filters-content.blade.php — shared filter controls
- resources/views/tenant/_meal-search.blade.php — search bar with filter integration
- resources/views/tenant/_meals-grid.blade.php — filter-aware grid
- tests/Unit/Tenant/MealFiltersUnitTest.php — 27 unit tests

## Conventions Established
- Filter sidebar: desktop sidebar (hidden lg:block) + mobile floating button with bottom sheet
- Cross-component Alpine communication via $dispatch events + DOM data attributes
- Price range on aggregates: use raw SQL subquery, not whereHas
