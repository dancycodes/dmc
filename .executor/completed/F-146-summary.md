# F-146: Order Total Calculation & Summary

## Summary
Receipt-style order summary page at /checkout/summary showing itemized cart contents grouped by meal, delivery fee or pickup-free indicator, forward-compatible promo discount stub, and grand total in XAF. Includes price change detection, mobile sticky bottom bar, step indicator, Edit Cart link, and Proceed to Payment CTA.

## Key Files
- resources/views/tenant/checkout/summary.blade.php — Receipt-style summary page
- app/Services/CheckoutService.php — getOrderSummary(), detectPriceChanges(), getSummaryBackUrl()
- app/Http/Controllers/Tenant/CheckoutController.php — summary() method
- tests/Unit/Tenant/OrderTotalSummaryUnitTest.php — 18 unit tests

## Retries
- Implement: 0, Review: 0, Test: 0
