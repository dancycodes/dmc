# Mission Directive: F-041 -- Notification Preferences Management

- **Spec Skill**: dancymeals-specs
- **Feature File**: references/F-041.md
- **Project Dir**: C:/Users/pc/Herd/dmc
- **App URL**: https://dmc.test
- **Test Mode**: full
- **Gate Scripts**:
  - gate_implement: C:/Users/pc/.claude/skills/project-executor/scripts/gate_implement.py
  - gate_review: C:/Users/pc/.claude/skills/project-executor/scripts/gate_review.py
  - gate_test: C:/Users/pc/.claude/skills/project-executor/scripts/gate_test.py
- **Available Plugins**: laravel-simplifier: true
- **Retry Context**:

## Context
- F-030: Profile view exists — find where the link to notification preferences should go.
- F-014: Push notification infrastructure — check if NotificationPreference model/table exists.
  If not, create it: `notification_preferences` table with user_id, notification_type (enum),
  push_enabled (bool, default true), email_enabled (bool, default true).
- F-191–F-195: Notification features already check preferences where relevant. This feature
  is the UI for managing them.

## Key Business Rules
- BR-175: Types: orders, payments, complaints, promotions, system
- BR-176: Toggleable: push + email channels
- BR-177: Database channel always ON, non-interactive
- BR-178: Default all ON for new users (first visit generates defaults)
- BR-179: Global per user (not tenant-scoped)
- BR-180: Notifications check preferences before sending push/email
- BR-181: Changes take effect immediately
- BR-182: Push only toggleable if browser push permission granted; else show "Permission required"
- BR-183: All text via __()

## Architecture
- NotificationPreference model + migration (if not exists)
- NotificationPreferencesController with index + update actions
- Route: GET/POST /profile/notification-preferences
- Gale: save without page reload, toast on success
- Helper: User->getNotificationPreference($type) or static lookup

## UI/UX
- Matrix table: rows = notification types, columns = Push | Email | Database (always ON)
- Switch-style toggles (not checkboxes)
- Database column: muted/grayed, always-on indicator
- Mobile: card-per-type layout (stacked)
- Brief description per type row
- Save button + loading state
- Push "Permission required" when browser permission not granted (Alpine check via `Notification.permission`)

## Recent Error Patterns to Avoid
- gale_compliance (9 total): All controller responses via Gale. No plain returns.
- ui_compliance (7 total): Mobile-first, dark mode, translations on ALL strings.

## Git Workflow
Before finishing: git add -f .executor/executor.db && git commit -m "chore: executor state F-041"
