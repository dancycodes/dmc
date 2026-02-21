# Mission Directive: F-212 -- Cancellation Window Configuration

- **Spec Skill**: dancymeals-specs
- **Feature File**: references/F-212.md
- **Project Dir**: C:/Users/pc/Herd/dmc
- **App URL**: https://dmc.test
- **Test Mode**: full
- **Gate Scripts**:
  - gate_implement: C:/Users/pc/.claude/skills/project-executor/scripts/gate_implement.py
  - gate_review: C:/Users/pc/.claude/skills/project-executor/scripts/gate_review.py
  - gate_test: C:/Users/pc/.claude/skills/project-executor/scripts/gate_test.py
- **Available Plugins**: laravel-simplifier: true
- **Retry Context**: IMPLEMENT and REVIEW phases are COMPLETE (both gates passed). Resume from TEST phase only.

## Resuming from TEST Phase

IMPLEMENT and REVIEW are already done. Do NOT re-do them. Go straight to TEST phase:
1. Use Playwright MCP to verify the UI end-to-end at https://dmc.test
2. Run scoped Pest tests: tests/Unit/Cook/CookSettingsUnitTest.php
3. Run gate_test.py against your TEST report
4. Save TEST report via save_report() MCP tool
5. If gate passes, call complete_feature() MCP tool
6. Before finishing: git add -f .executor/executor.db && git commit (if uncommitted changes exist)

## Test Scope
- Own tests: tests/Unit/Cook/CookSettingsUnitTest.php
- Affected tests: none

## Context
- F-076: Cook dashboard — existing Settings section in cook dashboard
- Cook configures cancellation window (minutes) for client order cancellations
- Setting stored in tenant.settings JSON column via CookSettingsService
- Orders snapshot the window at creation time (cancellation_window_minutes column)

## Key Files Created
- app/Http/Controllers/Cook/CookSettingsController.php
- app/Http/Requests/Cook/UpdateCancellationWindowRequest.php
- app/Services/CookSettingsService.php
- resources/views/cook/settings/index.blade.php
- database/migrations/2026_02_21_222427_add_cancellation_window_minutes_to_orders_table.php
- tests/Unit/Cook/CookSettingsUnitTest.php

## Key Files Modified
- routes/web.php (cook.settings.index and cook.settings.update-cancellation-window routes)
- app/Models/Order.php (cancellation_window_minutes in fillable/casts)
- app/Models/Tenant.php (getCancellationWindowMinutes() method)
- app/Services/ClientOrderService.php (uses order snapshot + CookSettingsService key)
- app/Services/TenantLandingService.php (cancellationWindowMinutes in getLandingPageData())
- resources/views/tenant/home.blade.php (cancellation policy section)
- lang/en.json / lang/fr.json (20 translation keys each)

## Recent Error Patterns to Avoid
- gale_compliance (9 total): All controller responses via Gale. No plain returns.
- ui_compliance (7 total): Mobile-first, dark mode, translations on ALL strings.
- business_logic (6 total): Read spec carefully for exact BRs.

## Git Workflow
Before finishing: git add -f .executor/executor.db && git commit -m "chore: executor state F-212"
