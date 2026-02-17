# F-017: Activity Logging Setup — Completed

## Summary
Configured Spatie Activitylog v4 with DancyMeals platform defaults: 90-day retention (BR-139), sensitive field exclusion (BR-138), reusable LogsActivityTrait with logFillable/logOnlyDirty/dontSubmitEmptyLogs defaults, applied to User and Tenant models, weekly scheduled cleanup (BR-140).

## Key Files
- `app/Traits/LogsActivityTrait.php` — Reusable trait wrapping Spatie's LogsActivity
- `config/activitylog.php` — 90-day retention, excluded sensitive attributes
- `app/Models/User.php` — LogsActivityTrait added
- `app/Models/Tenant.php` — LogsActivityTrait added
- `routes/console.php` — Weekly cleanup schedule (Sunday 03:00)
- `tests/Unit/ActivityLoggingConfigTest.php` — 14 unit tests
- `tests/Feature/ActivityLoggingTest.php` — 22 feature tests

## Stats
- Retries: Impl(0) + Rev(0) + Test(0)
- Tests: 36 new (64 assertions), 691 total passing
- Gates: All 3 passed post-hoc verification

## Conventions Established
- Use `LogsActivityTrait` (not Spatie's `LogsActivity` directly) on all models requiring audit logging
- Log name derived from model's table name
- Global sensitive field exclusion via `config('activitylog.excluded_attributes')`
- Models override `getAdditionalExcludedAttributes()` for model-specific exclusions
