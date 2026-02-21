# Mission Directive: F-211 -- Manager Dashboard Access

- **Spec Skill**: dancymeals-specs
- **Feature File**: references/F-211.md
- **Project Dir**: C:/Users/pc/Herd/dmc
- **App URL**: https://dmc.test
- **Test Mode**: full
- **Gate Scripts**:
  - gate_implement: C:/Users/pc/.claude/skills/project-executor/scripts/gate_implement.py
  - gate_review: C:/Users/pc/.claude/skills/project-executor/scripts/gate_review.py
  - gate_test: C:/Users/pc/.claude/skills/project-executor/scripts/gate_test.py
- **Available Plugins**: laravel-simplifier: true
- **Retry Context**:

## Context from Predecessors
- F-209: tenant_managers pivot table for per-tenant scoping. ManagerService for invite/remove. EnsureCookAccess middleware handles tenant-scoped manager check.
- F-210: 7 delegatable permissions in 4 groups (Business Operations, Coverage, Insights, Engagement). ManagerPermissionService.DELEGATABLE_PERMISSIONS. Direct Spatie permissions per user. Manager sidebar built dynamically from hasDirectPermission().
- F-076: Cook dashboard structure — check how the cook dashboard sidebar/layout is built to reuse for manager.

## Recent Error Patterns to Avoid
- gale_compliance (9 total): All controller responses via Gale. No plain returns.
- ui_compliance (7 total): Mobile-first, dark mode, translations on ALL strings.
- business_logic (6 total): Read spec carefully for exact business rules.

## Git Workflow
Before finishing: git add -f .executor/executor.db && git commit -m "chore: executor state"
