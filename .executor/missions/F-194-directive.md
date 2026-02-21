# Mission Directive: F-194 -- Payment Notifications

- **Spec Skill**: dancymeals-specs
- **Feature File**: references/F-194.md
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
- Notification pattern (F-191/F-192/F-193): Central service, BasePushNotification (push+DB), BaseMailableNotification (queued email)
- Dispatch notifications OUTSIDE DB::transaction (BR-287 pattern)
- Mail::fake() + assertQueued (not assertSent) for ShouldQueue mailables in tests
- F-151 is the payment/checkout feature — check CheckoutController and payment webhook flow for integration points

## Git Note for Orchestrator
After agent completes, commit executor.db on feature branch before switching to main:
  git add -f .executor/executor.db && git commit -m "chore: executor state"
Then merge normally without stashing.
