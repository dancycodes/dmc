# F-206: Admin Cook Performance Metrics — Complete

**Priority**: Should-have
**Completed**: 2026-02-22
**Retries**: Impl(0) Rev(0) Test(0)

## Summary
Admin cook performance table at /vault-entry/analytics/performance. Paginated table (25/page) of all cooks with 7 sortable columns: Cook Name, Region (derived from most common delivery town via PostgreSQL DISTINCT ON), Total Orders, Total Revenue (XAF), Avg Rating, Complaint Count, Avg Response Time. 7 period presets + custom date range, status/region filters, search by name. Color-coded metrics. Mobile card + desktop table layout.

## Key Files
- `app/Services/CookPerformanceService.php`
- `app/Http/Controllers/Admin/CookPerformanceController.php`
- `app/Http/Requests/Admin/CookPerformanceRequest.php`
- `resources/views/admin/analytics/performance.blade.php`
- `tests/Unit/Admin/CookPerformanceServiceTest.php` (23 unit tests)
- `tests/Feature/Admin/CookPerformanceTest.php` (15 feature tests)

## Bug Fixed
- Alpine x-model on HTML select returns string `"0"` (truthy) — fixed with `parseInt(this.regionId) > 0` check
