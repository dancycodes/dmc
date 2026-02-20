# F-150: Flutterwave Payment Initiation

## Summary
Flutterwave v3 mobile money payment initiation with split payments, order creation, waiting UI with countdown timer, timeout and cancel flows. Creates orders from checkout session, initiates charges with cook subaccount + configurable commission. Payment waiting page with 15-minute countdown, status polling via x-interval.5s.visible.

## Key Files
- app/Models/Order.php — Order model with generateOrderNumber(), status constants
- app/Services/FlutterwaveService.php — Flutterwave v3 API client
- app/Services/PaymentService.php — Payment orchestration with split payment config
- app/Http/Controllers/Tenant/CheckoutController.php — initiatePayment, paymentWaiting, checkPaymentStatus, cancelPayment
- resources/views/tenant/checkout/payment-waiting.blade.php — Waiting UI with countdown
- database/migrations/2026_02_20_010215_create_orders_table.php — Orders table
- database/factories/OrderFactory.php — Factory with payment states
- tests/Unit/Tenant/FlutterwavePaymentInitiationUnitTest.php — 32 unit tests

## Retries
- Implement: 0, Review: 0, Test: 0
