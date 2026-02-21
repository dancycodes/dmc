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
- **Retry Context**:

## Context
- F-076: Cook dashboard — check existing Settings section structure if present
- This feature involves cook configuring a cancellation window (time limit clients can cancel their own orders). Likely a settings/preferences page in the cook dashboard.

## Recent Error Patterns to Avoid
- gale_compliance (9 total): All controller responses via Gale. No plain returns.
- ui_compliance (7 total): Mobile-first, dark mode, translations on ALL strings.
- business_logic (6 total): Read spec carefully for exact BRs.

## Git Workflow
Before finishing: git add -f .executor/executor.db && git commit -m "chore: executor state"
