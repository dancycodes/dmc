# F-149: Payment Method Selection

## Summary
Payment step in checkout flow. Clients choose from MTN Mobile Money, Orange Money, or Wallet Balance. Includes saved payment method pills with masked phone numbers, phone number input with +237 prefix and Cameroon format validation, wallet visibility/enablement based on admin settings and balance. Forward-compatible wallet integration for F-166, redirects to F-150 (Flutterwave) or F-153 (wallet payment).

## Key Files
- app/Http/Controllers/Tenant/CheckoutController.php — paymentMethod(), savePaymentMethod()
- app/Services/CheckoutService.php — setPaymentMethod(), getPaymentOptions(), getPaymentPrefillPhone(), validatePaymentSelection(), getWalletOption()
- resources/views/tenant/checkout/payment.blade.php — Payment selection UI
- tests/Unit/Tenant/PaymentMethodSelectionUnitTest.php — 24 unit tests

## Retries
- Implement: 0, Review: 0, Test: 0
