# F-195: System Announcement Notifications — Complete

**Priority**: Should-have
**Completed**: 2026-02-22
**Retries**: Impl(0) Rev(1) Test(0)

## Summary
Admin panel for creating and dispatching system announcements. Supports immediate and scheduled delivery targeting all users, all cooks, all clients, or a specific tenant. Push + Database + Email channels. Scheduled artisan command runs every minute to dispatch pending announcements.

## Key Files
- `app/Models/Announcement.php`
- `app/Http/Controllers/Admin/AnnouncementController.php`
- `app/Services/AnnouncementService.php`
- `app/Notifications/SystemAnnouncementNotification.php`
- `app/Mail/SystemAnnouncementMail.php`
- `app/Console/Commands/DispatchScheduledAnnouncementsCommand.php`
- `resources/views/admin/announcements/` (index, create, edit)
- `tests/Unit/Admin/AnnouncementUnitTest.php` (22 tests)

## Conventions Established
- Blade data islands: `{!! json_encode([...], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) !!}` (never @json or {{ json_encode }})
- Tenant translatable columns: always `name_en`/`name_fr`, never `name`

## Bugs Fixed
1. `getActiveTenantsForDropdown()` used non-existent `name` column → fixed to `name_en`
2. Edit blade `@json([...])` caused Blade ParseError → fixed to `json_encode` with JSON_HEX flags
3. Cancel modal `x-for` wrapper caused Alpine warning → removed, used direct `confirmCancelId` reference
