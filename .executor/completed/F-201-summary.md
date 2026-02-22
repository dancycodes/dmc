# F-201: Cook Order Analytics — Complete

**Priority**: Should-have
**Completed**: 2026-02-22
**Retries**: Impl(0) Rev(0) Test(0)

## Summary
Order analytics dashboard at /dashboard/analytics/orders. Summary cards (total, avg value, top meal), order count over time bar chart, status distribution stacked bar, popular meals ranking, peak hours heatmap (Africa/Douala TZ). Tab navigation shared with revenue analytics page.

## Key Files
- `app/Services/CookOrderAnalyticsService.php`
- `app/Http/Controllers/Cook/OrderAnalyticsController.php`
- `resources/views/cook/analytics/orders.blade.php`
- `tests/Unit/CookOrderAnalyticsServiceTest.php` (11 unit tests)
