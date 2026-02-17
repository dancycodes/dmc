# F-050: User Management List & Search

## Summary
User Management List & Search page at /vault-entry/users. Paginated table (20/page) showing all platform users with search (name/email/phone with +237 normalization), role filter, status filter, column sorting. Summary cards (Total/Active/Inactive/New This Month). Mobile-responsive card layout. Role badges color-coded per role. Last login relative time via diffForHumans(). Gale fragment-based navigation for search/filter/sort without page reload.

## Key Files
- `app/Http/Controllers/Admin/UserController.php` — Controller with search, filter, sort, pagination
- `resources/views/admin/users/index.blade.php` — Blade view with table/card responsive layout
- `database/migrations/2026_02_16_213642_add_last_login_at_to_users_table.php` — last_login_at column
- `tests/Unit/Admin/UserManagementListUnitTest.php` — 23 unit tests

## Retries
- IMPLEMENT: 0
- REVIEW: 1
- TEST: 0

## Gate Results
- gate_implement: PASS
- gate_review: PASS
- gate_test: PASS (5/5 verifications, 1/1 edge cases, responsive PASS, theme PASS)
