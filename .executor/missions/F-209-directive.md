# Mission Directive: F-209 -- Cook Creates Manager Role

- **Spec Skill**: dancymeals-specs
- **Feature File**: references/F-209.md
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
- F-076 is the cook dashboard/team management prerequisite — check existing manager role structure
- F-006 is the user auth prerequisite — check how roles are assigned per tenant

## Git Workflow (Important)
Before finishing, commit executor.db on feature branch:
  git add -f .executor/executor.db && git commit -m "chore: executor state"
