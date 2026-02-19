# F-142: Pickup Location Selection — Completed

## Summary
Checkout step for pickup orders: radio-card selection of cook's pickup locations. Each card
shows name, full address (quarter, town), and Free badge. Single location auto-selected.
Session persistence. Consistent UI pattern with delivery-method and delivery-location steps.

## Metrics
- Implement retries: 0
- Review retries: 0
- Test retries: 0
- Tests written: 16 unit tests
- Bugs found/fixed: 0

## Key Files
- app/Services/CheckoutService.php — getPickupLocations, setPickupLocation, validatePickupLocation
- app/Http/Controllers/Tenant/CheckoutController.php — pickupLocation, savePickupLocation
- resources/views/tenant/checkout/pickup-location.blade.php — radio-card UI
- tests/Unit/Tenant/PickupLocationSelectionUnitTest.php — 16 unit tests
