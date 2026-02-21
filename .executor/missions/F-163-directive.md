# Mission Directive: F-163 -- Order Cancellation Refund Processing

- **Spec Skill**: dancymeals-specs
- **Feature File**: references/F-163.md
- **Project Dir**: C:/Users/pc/Herd/dmc
- **App URL**: https://dmc.test
- **Test Mode**: fast
- **Gate Scripts**:
  - gate_implement: C:/Users/pc/.claude/skills/project-executor/scripts/gate_implement.py
  - gate_review: C:/Users/pc/.claude/skills/project-executor/scripts/gate_review.py
  - gate_test: C:/Users/pc/.claude/skills/project-executor/scripts/gate_test.py
- **Available Plugins**: laravel-simplifier: true
- **Retry Context**:

## Context
- **Test Mode: fast** — No blade views in this feature; backend-only service logic.
- F-162: OrderCancellationService already dispatches a refund job placeholder. This feature
  implements the actual refund processing job/service hooked into that dispatch.
- F-167: Client Wallet Refund Credit — check if client wallet credit mechanism exists (may be
  a placeholder or partial). If not, implement what's needed here.
- F-166: Client wallet (client_wallets table with balance column)
- F-169: Cook wallet (cook_wallets table with unwithdrawable_amount column)

## Key Business Rules
- BR-248: Full order amount (subtotal + delivery fee) → client wallet (not mobile money)
- BR-249: Refunds ALWAYS go to wallet, never back to mobile money
- BR-250: Cook's unwithdrawable_amount decremented by order total
- BR-251: No commission on cancelled orders
- BR-252: Client wallet transaction (type: refund, credit)
- BR-253: Cook wallet transaction (type: order_cancelled, debit from unwithdrawable)
- BR-254: Order status: Cancelled → Refunded; set orders.refunded_at
- BR-255: Client notified (push + DB + email: N-008)
- BR-256: Automatic and immediate upon cancellation
- BR-257: Wallet balance cannot go negative (refunds add to balance)
- BR-258: All amounts in XAF
- BR-259: Log via Spatie Activitylog

## Architecture Notes
- Implement as a queued job: `ProcessOrderRefund` (dispatched from OrderCancellationService)
- Wrap entire refund in a database transaction for atomicity
- Idempotent: if order is already Refunded, skip (no duplicate refund)
- Check existing wallet models/tables before creating new ones

## Edge Cases
- Cook unwithdrawable < order amount: log error + alert admin (do not throw)
- Mid-way failure: DB transaction rolls back all changes
- Duplicate request: idempotent check on order status
- Free order (0 XAF): process with 0 amount, still move to Refunded
- Missing client wallet: create it on the fly

## Recent Error Patterns to Avoid
- gale_compliance (9 total): All controller responses via Gale (N/A here — backend only)
- business_logic (6 total): Read spec carefully for exact BRs
- test_setup (3 total): Feature tests in tests/Feature/ must NOT include explicit uses() calls

## Git Workflow
Before finishing: git add -f .executor/executor.db && git commit -m "chore: executor state F-163"
