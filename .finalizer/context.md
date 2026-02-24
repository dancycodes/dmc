# Finalizer Context
## Sweep Branch: finalizer/sweep-2026-02-23
## Phase: ARCHITECTURE_REVIEW
## Last: F-219 (all features complete)
## Progress: 178/219 tested | 178 passed | 0 failed | 18 skipped (backend-only) | 23 N/A
## Bugs so far: 35 found, 35 fixed (feature sweep) + 7 audit = 42 total
## Global Audit: PASSED (7 found, 7 fixed)
## Architecture Review: in_progress
## Pest: pending
## Key [ARCH]:
##   - gale()->redirect()->back() anti-pattern in controllers (F-037)
##   - x-sync vs client state racing on modal confirmation inputs (F-052-059)
##   - Stat cards stale on tab switch/fragment updates (F-065)
##   - Alpine x-for + x-model pre-selection timing on edit forms (F-094)
##   - selling_unit column name misleading (F-121) — stores FK as string
##   - Push notification prompt shows on every page load after 2s delay (F-014)
##   - Tenant isolation uses named scope not global scope — explicit calls required (F-004)
##   - Cook wallet can go negative on refund exceeding unwithdrawable balance (F-174)
##   - Commission calc basis (subtotal vs grand_total) differs by payment method (F-175)
