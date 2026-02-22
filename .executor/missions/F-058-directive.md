# Mission Directive: F-058 -- Financial Reports & Export

- **Spec Skill**: dancymeals-specs
- **Feature File**: references/F-058.md
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
- F-057: PlatformAnalyticsService already exists — reuse/extend it for financial data queries.
  Admin panel route prefix is /vault-entry. Read existing admin controllers for conventions.
- For CSV export: use Laravel's built-in response()->streamDownload() with manual CSV generation.
  No external packages needed.
- For PDF export: check if dompdf/barryvdh-laravel-dompdf is installed in composer.json.
  If not, use a simple HTML-to-PDF approach or fallback to a well-formatted HTML download.
  Do NOT install new packages without checking first.

## Key Business Rules
- BR-143: Revenue = completed or delivered orders only
- BR-144: Commission per order = order amount × cook's commission rate at time of order
  (check if orders table stores commission_rate snapshot or read from cook wallet transactions)
- BR-145: Pending payouts = cook wallet unwithdrawable + withdrawable balances not yet withdrawn
- BR-146: Failed payments = Flutterwave transactions with failed status
- BR-147: CSV = ALL rows matching filters (not paginated)
- BR-148: PDF = first 500 rows + note if >500; summary header
- BR-149: All amounts in XAF
- BR-150: Default date range = current month

## Architecture
- FinancialReportsController (Admin) with index + exportCsv + exportPdf actions
- FinancialReportsService for data aggregation (extending or delegating to PlatformAnalyticsService)
- Route: GET /vault-entry/finance/reports, GET /vault-entry/finance/reports/export-csv,
  GET /vault-entry/finance/reports/export-pdf
- Tabs: Overview (daily table) | By Cook | Pending Payouts | Failed Payments
- Gale: tab switch + filter changes update table without page reload
- Export endpoints: plain Laravel responses (not Gale)

## UI/UX
- Summary cards (5): Gross Revenue, Platform Commission, Net Payouts, Pending Payouts, Failed Count
- Tab nav: Overview | By Cook | Pending Payouts | Failed Payments
- Date range + cook filter above table
- Export CSV + Export PDF buttons (top-right)
- Striped table rows, horizontal scroll on mobile
- Loading indicator during generation

## Recent Error Patterns to Avoid
- gale_compliance (9 total): All controller responses via Gale (except export endpoints)
- ui_compliance (7 total): Mobile-first, dark mode, translations on ALL strings.

## Git Workflow
Before finishing: git add -f .executor/executor.db && git commit -m "chore: executor state F-058"
