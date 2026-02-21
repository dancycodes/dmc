# Mission Directive: F-192 -- Order Status Update Notifications

- **Spec Skill**: dancymeals-specs
- **Feature File**: references/F-192.md
- **Project Dir**: C:/Users/pc/Herd/dmc
- **App URL**: https://dmc.test
- **Test Mode**: full
- **Gate Scripts**:
  - gate_implement: C:/Users/pc/.claude/skills/project-executor/scripts/gate_implement.py
  - gate_review: C:/Users/pc/.claude/skills/project-executor/scripts/gate_review.py
  - gate_test: C:/Users/pc/.claude/skills/project-executor/scripts/gate_test.py
- **Available Plugins**: laravel-simplifier: true
- **Retry Context**:

## Recent Error Patterns to Avoid
- gale_compliance (9 total): Always use Gale SSE patterns; never use Livewire/Inertia
- ui_compliance (7 total): Mobile-first, dark mode, translations on ALL user-facing strings
- business_logic (6 total): Read spec carefully for exact business rules
- F-191 pattern: For notifications, use OrderNotificationService pattern (central service resolves recipients, dispatches push/DB + queued email)
- For push+DB: BasePushNotification; for email: BaseMailableNotification (queued)
- Mail::fake() + assertQueued (not assertSent) for ShouldQueue mailables in tests
