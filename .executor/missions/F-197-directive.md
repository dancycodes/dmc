# Mission Directive: F-197 — Favorite Meal Toggle

- **Spec Skill**: dancymeals-specs
- **Feature File**: references/F-197.md
- **Project Dir**: C:/Users/pc/Herd/dmc
- **App URL**: https://dmc.test
- **Test Mode**: full
- **Gate Scripts**:
  - gate_implement: C:/Users/pc/.claude/skills/project-executor/scripts/gate_implement.py
  - gate_review: C:/Users/pc/.claude/skills/project-executor/scripts/gate_review.py
  - gate_test: C:/Users/pc/.claude/skills/project-executor/scripts/gate_test.py
- **Available Plugins**: laravel-simplifier: true
- **Retry Context**: (none)

## Context from F-196 (Favorite Cook Toggle — just completed)
- Per-component x-data scopes used for independent toggle state on repeated cards
- Pivot tables with only created_at: use withPivot('created_at') NOT withTimestamps()
- Always use $tenant->slug (not $tenant->id) for route generation
- allRelatedIds() preferred over pluck() to bypass select() constraints on relationships
