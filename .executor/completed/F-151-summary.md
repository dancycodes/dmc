# F-151: Payment Webhook Handling

## Summary
Flutterwave payment webhook endpoint with signature verification, successful payment flow (order to Paid + cook wallet credit + commission record), failed payment flow (order to Payment Failed), idempotent processing, and activity logging. Created wallet_transactions table and WalletTransaction model. Returns 200 OK for all valid-signature requests to prevent Flutterwave retries.

## Key Files
- app/Http/Controllers/Webhook/FlutterwaveWebhookController.php — Thin webhook controller
- app/Services/WebhookService.php — Webhook business logic
- app/Models/WalletTransaction.php — Wallet transaction model with TYPE constants
- database/migrations/2026_02_20_014234_create_wallet_transactions_table.php — Wallet transactions table
- tests/Unit/Payment/WebhookHandlingUnitTest.php — 20 unit tests
- tests/Feature/Payment/WebhookHandlingFeatureTest.php — 9 feature tests

## Retries
- Implement: 0, Review: 0, Test: 0
