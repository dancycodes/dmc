# F-145: Delivery Fee Calculation

## Summary
Delivery fee calculation logic in CheckoutService with reactive client-side display. Group fees override individual fees (BR-308), individual fees used when no group (BR-309), 0 fee displays as "Free delivery" (BR-310), format is "Delivery to {quarter}: {fee} XAF" (BR-311). Fees update reactively on quarter change (BR-312), are added to estimated total (BR-313), pickup orders always have 0 fee (BR-314). All text localized (BR-315).

## Key Files
- app/Services/CheckoutService.php — calculateDeliveryFee(), getStoredDeliveryFee(), getDeliveryFeeDisplayData()
- resources/views/tenant/checkout/delivery-location.blade.php — Alpine display methods
- tests/Unit/Tenant/DeliveryFeeCalculationUnitTest.php — 21 unit tests
- lang/en.json, lang/fr.json — Translation strings

## Retries
- Implement: 0, Review: 0, Test: 0

## Bug Fixed
- Double quotes in JS comment inside x-data attribute truncated the HTML attribute, preventing Alpine initialization.

## Convention
- Never use double quotes inside JavaScript comments within x-data HTML attributes.
