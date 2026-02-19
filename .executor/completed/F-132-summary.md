# F-132: Schedule & Availability Display — Completed

## Summary
Schedule & Availability section on tenant landing page. 7-day weekly grid (desktop) with expandable cards (mobile). Shows order/delivery/pickup time windows, availability badge (Available Now / Closing Soon / Next Available / Closed), current day highlighting, multiple slots per day, timezone note.

## Key Files
- `app/Services/TenantLandingService.php` — Added 6 schedule display methods
- `resources/views/tenant/_schedule-section.blade.php` — Schedule section partial
- `resources/views/tenant/home.blade.php` — Includes schedule partial
- `tests/Unit/Tenant/ScheduleAvailabilityDisplayUnitTest.php` — 18 unit tests

## Bug Fixed
- Replaced x-collapse with x-transition (Gale's Alpine build doesn't include Collapse plugin)

## Retries: 0 (implement: 0, review: 0, test: 0)
## Convention: Don't use x-collapse in blade; use x-transition instead
