# F-093: Pickup Location List View — Summary

## Result: DONE (0 retries)

## What Was Built
- Pickup location list view displaying all locations for a cook's tenant
- Alphabetical sorting by locale-aware name (name_en/name_fr)
- Each card shows: name, town, quarter, address (truncated at 100 chars)
- "Free" badge on location cards
- Edit/Delete action buttons gated by `can-manage-locations` permission
- Delete confirmation modal with location name
- Empty state with "Add pickup location" button
- Full bilingual support (EN/FR)
- Responsive at 375/768/1280 breakpoints
- Dark/light theme support

## Key Files
- `app/Services/DeliveryAreaService.php` — `getPickupLocationsData()` with locale-aware sorting
- `resources/views/cook/locations/pickup.blade.php` — List view with cards, modal, empty state
- `app/Http/Controllers/Cook/PickupLocationController.php` — Controller with Gale responses
- `tests/Unit/Cook/PickupLocationListViewUnitTest.php` — 45 unit tests

## Gate Results
- IMPLEMENT: PASS (1 file created, 5 modified)
- REVIEW: PASS (0 violations)
- TEST: PASS (5/5 verifications, 3/3 edge cases, responsive PASS, theme PASS)

## Conventions
- Address truncation at 100 characters with mb_substr and ellipsis
- Null-safe guards for optional town/quarter relationships
- Delete confirmation modal pattern: Alpine state confirmDeleteId + confirmDeleteName
