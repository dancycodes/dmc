# F-095: Delete Pickup Location — Summary

## Result: DONE (0 retries)

## What Was Built
- Delete functionality for pickup locations with confirmation dialog
- Confirmation modal shows location name per BR-302
- Active order blocking (forward-compatible via Schema::hasTable)
- Completed orders don't block deletion
- Success toast notification via Gale
- List updates without page reload
- Activity logging on deletion
- Permission-gated by `can-manage-locations`

## Key Files
- `app/Http/Controllers/Cook/PickupLocationController.php` — destroy() method
- `app/Services/DeliveryAreaService.php` — removePickupLocation() with structured return
- `resources/views/cook/locations/pickup.blade.php` — Updated confirmation modal
- `routes/web.php` — DELETE route
- `tests/Unit/Cook/DeletePickupLocationUnitTest.php` — 51 unit tests

## Gate Results
- IMPLEMENT: PASS
- REVIEW: PASS (0 violations)
- TEST: PASS (4/4 verifications, 2/2 edge cases, responsive PASS, theme PASS)

## Conventions
- Structured array return from service delete methods: {success, error, entity_name, model}
- Forward-compatible active order checking via Schema::hasTable
