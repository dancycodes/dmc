# F-070: Discovery Sort Options — Complete

**Priority**: Should-have
**Retries**: 0 (Implement: 0, Review: 0, Test: 0)

## Summary
4-option sort dropdown on discovery page: Most Popular (default), Highest Rated, Newest, A-Z. LEFT JOIN subquery for aggregate sorts. NULLS LAST for unrated cooks. Rating sort tiebreaker by popularity. Locale-aware alphabetical sort with LOWER(). Sort combines with search and filters. 42 EN/FR translations.

## Key Files
- app/Services/DiscoveryService.php (applySort method)
- app/Http/Controllers/DiscoveryController.php
- app/Http/Requests/DiscoveryRequest.php
- resources/views/discovery/index.blade.php
- tests/Unit/Discovery/DiscoverySortOptionsUnitTest.php

## Test Results
- 7/7 verification steps passed
- 4/4 edge cases passed
- Responsive: PASS (375/768/1280)
- Theme: PASS (dark/light)
