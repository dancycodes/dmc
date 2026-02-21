# F-192: Order Status Update Notifications — COMPLETE

**Status**: Done | **Retries**: Impl(1) Rev(0) Test(0)

## Summary
Push+DB notifications sent to the client for every order status change. Email notifications sent only for key statuses (confirmed, ready_for_pickup, out_for_delivery, delivered, picked_up, completed). All notifications are queued (ShouldQueue) and dispatched after the DB transaction commits (BR-287). The client action URL links to /my-orders/{id} on the main domain (BR-285). Delivered/picked_up/completed emails include a Rate Your Order CTA (BR-283).

Critical infrastructure fix: created the missing notifications table migration that was causing PostgreSQL transaction poisoning in 30+ existing tests.

## Key Files
- app/Notifications/OrderStatusUpdateNotification.php
- app/Mail/OrderStatusUpdateMail.php
- app/Services/OrderStatusNotificationService.php
- resources/views/emails/order-status-update.blade.php
- database/migrations/2026_02_21_194525_create_notifications_table.php
- app/Services/OrderStatusService.php
- tests/Unit/Notification/OrderStatusUpdateNotificationUnitTest.php

## Tests: 56 unit + 5 affected
