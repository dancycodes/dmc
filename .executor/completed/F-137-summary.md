# F-137: Meal Sort Options — Complete

**Priority**: Should-have
**Retries**: 0 (Implement: 0, Review: 0, Test: 0)

## Summary
5-option meal sort on tenant landing: Most Popular (default, JSONB popularity subquery), Price Low-High, Price High-Low, Newest First, A to Z. Sort dropdown outside fragment; dispatches apply-sort events to search/filter components for URL preservation. Combines with filters and search.

## Key Files
- resources/views/tenant/_meal-sort.blade.php
- app/Http/Requests/Tenant/MealSearchRequest.php (SORT_OPTIONS, DEFAULT_SORT)
- app/Services/TenantLandingService.php (applySortOrder)
- app/Http/Controllers/Tenant/MealSearchController.php
- tests/Unit/Tenant/MealSortOptionsUnitTest.php

## Test Results
- 7/7 verification steps passed
- 3/3 edge cases passed
- Responsive: PASS (375/768/1280)
- Theme: PASS (dark/light)
