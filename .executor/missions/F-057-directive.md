# Mission Directive: F-057 -- Platform Analytics Dashboard

- **Spec Skill**: dancymeals-specs
- **Feature File**: references/F-057.md
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
- F-043: Admin panel layout exists — read it for the admin layout structure, routes, and
  access patterns. Analytics dashboard is a page within the admin panel.
- Read existing admin controllers to understand naming conventions (e.g., `/vault-entry` prefix for admin).
- For charts: Use a JS chart library already available in the project if possible. Check package.json
  for Chart.js, ApexCharts, or similar. If none exists, use lightweight inline SVG or ASCII charts
  as a fallback — do NOT install new npm packages without user approval.
- Data aggregation should be server-side via Eloquent. No raw SQL.

## Key Business Rules
- BR-135: Revenue = completed/delivered orders only
- BR-136: Commission earned = sum of platform commission from completed orders
- BR-137: Active tenants = tenants with status "active"
- BR-138: Active users = users who logged in during selected period (check users.last_login_at or similar)
- BR-139: New registrations = users.created_at in period
- BR-140: Periods: Today, This Week, This Month, This Year, Custom Range
- BR-141: Custom range max 1 year
- BR-142: Top cooks/meals ranked by revenue in period

## Architecture
- PlatformAnalyticsController with index action (Gale, period filter parameter)
- PlatformAnalyticsService for data aggregation
- Route: GET /vault-entry/analytics
- Gale: period change updates metrics + charts without page reload
- Charts: check existing packages. Use Chart.js if available; otherwise simple Tailwind bar/sparkline

## UI/UX
- Summary cards (6): Revenue, Commission, Orders, Active Tenants, Active Users, New Users
  — each shows value + % change vs previous equivalent period
- Time period selector: segmented control (Today/Week/Month/Year/Custom)
- Revenue area chart (daily ≤3mo, weekly >3mo)
- Orders bar chart (daily/weekly)
- Top 10 Cooks: ranked list with name, revenue, order count
- Top 10 Meals: ranked list with meal name, order count, revenue
- Gale updates all on period change
- Mobile: cards stack, charts full-width

## Recent Error Patterns to Avoid
- gale_compliance (9 total): All controller responses via Gale. No plain returns.
- ui_compliance (7 total): Mobile-first, dark mode, translations on ALL strings.
- business_logic (6 total): Revenue = completed orders ONLY.

## Git Workflow
Before finishing: git add -f .executor/executor.db && git commit -m "chore: executor state F-057"
