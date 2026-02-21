# F-193: Complaint Notifications — COMPLETE

**Status**: Done | **Retries**: Impl(0) Rev(0) Test(0)

## Summary
Central ComplaintNotificationService orchestrates all 4 complaint lifecycle events: submitted (push+DB to cook+managers), response (push+DB to client), escalated (push+DB to admin+client+cook), resolved (push+DB+email to client). Email only on resolution (BR-293). Manager permission: can-manage-complaints-escalated.

## Key Files
- app/Services/ComplaintNotificationService.php
- app/Notifications/ComplaintResolvedNotification.php
- app/Mail/ComplaintResolvedMail.php
- resources/views/emails/complaint-resolved.blade.php
- tests/Unit/Notification/ComplaintNotificationUnitTest.php

## Tests: 38 unit
