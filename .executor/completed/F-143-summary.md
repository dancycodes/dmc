# F-143: Order Phone Number — Completed

## Summary
Checkout phone step between location selection and order summary. Pre-fills from user profile,
allows per-order override without updating profile. Validates Cameroon format (+237 + 9 digits
starting with 6/7/2). Includes +237 non-editable prefix, "Same as my profile" button, privacy
notice, step indicator, mobile sticky action bar.

## Metrics
- Implement retries: 0
- Review retries: 0
- Test retries: 0
- Tests written: 34 unit tests
- Bugs found/fixed: 0

## Key Files
- app/Services/CheckoutService.php — setPhone, getPhone, getPrefilledPhone, getPhoneStepBackUrl
- app/Http/Controllers/Tenant/CheckoutController.php — phoneNumber, savePhoneNumber
- resources/views/tenant/checkout/phone.blade.php — phone step UI
- tests/Unit/Tenant/OrderPhoneNumberUnitTest.php — 34 unit tests
