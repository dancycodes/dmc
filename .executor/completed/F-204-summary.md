# F-204: Client Spending & Order Stats — Complete

**Priority**: Should-have
**Completed**: 2026-02-22
**Retries**: Impl(0) Rev(0) Test(0)

## Summary
/my-stats page for authenticated clients. Summary cards (total spent, this month, total orders), top 5 cooks by order count with cross-domain links, top 5 meals by order count. Handles deleted meals, inactive cooks, and zero-order empty state. Linked from main nav and tenant dropdown.

## Key Files
- `app/Services/ClientSpendingStatsService.php`
- `app/Http/Controllers/Client/SpendingStatsController.php`
- `resources/views/client/stats/index.blade.php`
- `tests/Unit/Client/ClientSpendingStatsServiceTest.php` (4 unit tests)

## Bug Fixed
- `custom_domain` column used instead of non-existent `domain` in Tenant queries
