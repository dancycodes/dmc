# F-147: Location Not Available Flow

## Summary
When a client selects a quarter not in the cook's delivery areas, a warning card displays with the quarter name, cook contact options (WhatsApp with pre-filled message + phone), and a Switch to Pickup option. Selecting an available quarter dismisses the warning and resumes normal flow. Modified getDeliveryQuarters to return ALL quarters with availability flag.

## Key Files
- app/Services/CheckoutService.php — getCookContactInfo(), buildWhatsAppMessage(), hasPickupLocations(), modified getDeliveryQuarters()
- app/Http/Controllers/Tenant/CheckoutController.php — switchToPickup(), modified deliveryLocation()
- resources/views/tenant/checkout/delivery-location.blade.php — Warning card with contact options
- tests/Unit/Tenant/LocationNotAvailableUnitTest.php — 18 unit tests

## Retries
- Implement: 0, Review: 0, Test: 0

## Conventions
- WhatsApp deep link pattern: wa.me/{phone}?text={encoded_message} with placeholder replacement
- Availability flag pattern on quarter dropdown items
