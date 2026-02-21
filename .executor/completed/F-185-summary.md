# F-185: Complaint Auto-Escalation

## Summary
Automatic escalation of unresolved complaints after 24 hours. Scheduled command runs every 15 minutes, finds open complaints older than 24h without cook response, transitions them to escalated status. Sends notifications to admin (N-011), client (BR-212), and cook (BR-213). Activity logged with causedByAnonymous(). Uses chunkById for batch processing. Idempotent — already-escalated complaints are skipped.

## Key Files
- `app/Services/ComplaintEscalationService.php` — processOverdueComplaints(), escalateComplaint()
- `app/Console/Commands/EscalateOverdueComplaintsCommand.php` — scheduled every 15 min
- `app/Notifications/ComplaintEscalatedAdminNotification.php` — N-011
- `app/Notifications/ComplaintEscalatedClientNotification.php` — BR-212
- `app/Notifications/ComplaintEscalatedCookNotification.php` — BR-213
- `tests/Unit/Complaint/ComplaintAutoEscalationUnitTest.php` — 23 unit tests
- `tests/Feature/Complaint/ComplaintAutoEscalationFeatureTest.php` — 26 feature tests

## Verification
- 8/8 verification steps passed
- 3/3 edge cases passed
- Responsive: SKIPPED (backend-only)
- Theme: SKIPPED (backend-only)
- Bugs fixed: 0
- Retries: Impl(0) + Rev(0) + Test(0)
