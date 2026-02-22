# Mission Directive: F-201 — Cook Order Analytics

- **Spec Skill**: dancymeals-specs
- **Feature File**: references/F-201.md
- **Project Dir**: C:/Users/pc/Herd/dmc
- **App URL**: https://dmc.test
- **Test Mode**: full
- **Gate Scripts**:
  - gate_implement: C:/Users/pc/.claude/skills/project-executor/scripts/gate_implement.py
  - gate_review: C:/Users/pc/.claude/skills/project-executor/scripts/gate_review.py
  - gate_test: C:/Users/pc/.claude/skills/project-executor/scripts/gate_test.py
- **Available Plugins**: laravel-simplifier: true
- **Retry Context**: (none)

## Context from F-200 (Cook Revenue Analytics — just completed)
- CookRevenueAnalyticsService pattern: pure service with resolveDateRange/resolveGranularity methods
- Analytics route at /dashboard/analytics (revenue tab) — order analytics likely at same location with tabs
- PostgreSQL jsonb_array_elements for JSONB column querying
- Auto-granularity: daily <=31 days, weekly <=183 days, monthly >183 days
- XAF formatting: number_format(amount, 0, '.', ',') . ' XAF'
