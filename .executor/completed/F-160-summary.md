# F-160: Client Order List — Completed

## Summary
Client-facing order list page at /my-orders showing orders across all tenants. Active orders (non-terminal) pinned at top with distinct styling. Past orders below with status filtering, sorting, and pagination (15/page). Each order shows cook name with link to tenant domain. Empty state with Discover Cooks CTA. Responsive mobile cards + desktop table layout.

## Key Files
- `app/Http/Controllers/Client/OrderController.php` — Gale controller with fragment support
- `app/Services/ClientOrderService.php` — Service layer with active/past separation, filtering
- `app/Http/Requests/Client/ClientOrderListRequest.php` — Form Request validation
- `resources/views/client/orders/index.blade.php` — Responsive dual layout blade view
- `tests/Unit/Client/ClientOrderListUnitTest.php` — 26 Pest tests

## Retries
- IMPLEMENT: 0
- REVIEW: 0
- TEST: 0

## Test Results
- Verification: 8/8 PASS
- Edge Cases: 2/2 PASS
- Responsive: PASS (375px, 768px, 1280px)
- Theme: PASS (light + dark)
