# Mission Directive: F-162 -- Order Cancellation

- **Spec Skill**: dancymeals-specs
- **Feature File**: references/F-162.md
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
- F-161: Client order detail page — add cancel button with countdown timer to that existing view
- F-212: Cook's cancellation window is stored in tenant settings (CookSettingsService), snapshotted
  at order creation time in `orders.cancellation_window_minutes`. Use the snapshot value for existing
  orders; fall back to CookSettingsService default if null.
- F-163: Cancellation triggers refund processing — dispatch a job or call a service placeholder
  (F-163 will implement the actual refund; F-162 just needs to dispatch the appropriate event/job)
- Order status flow: Pending Payment > Paid > Confirmed > Preparing > Ready > Out for Delivery /
  Ready for Pickup > Delivered / Picked Up > Completed. Cancellation only allowed at Paid or Confirmed.

## Key Business Rules
- BR-236: Cancellation only for Paid or Confirmed orders
- BR-237: Window from cook's setting (snapshot at order time), default 30 min
- BR-238: Timer starts from order's created_at
- BR-239: Cancel button disappears when window expires (client-side countdown + server validation)
- BR-240: Confirmation dialog required before cancellation
- BR-241: Server re-validates status AND time window before processing
- BR-242: Order status → Cancelled; set orders.cancelled_at
- BR-243: Dispatch refund processing (F-163 will handle; just dispatch/trigger from here)
- BR-244: Cook + manager(s) notified (push + DB: N-017)
- BR-245: Log via Spatie Activitylog
- BR-246: Client can only cancel their own orders
- BR-247: All strings via __()

## UI/UX
- Cancel button on client order detail page (F-161 view) — red/destructive styling
- Countdown format: "MM:SS remaining" under 1hr, "X hours Y minutes" for longer
- Timer updates every second (Alpine.js client-side countdown)
- Confirmation modal: warning icon, "Cancel this order? A full refund of {amount} XAF will be
  credited to your wallet." with "Keep Order" / "Cancel Order" buttons
- After cancellation: Gale reload to show Cancelled status
- Expiry note: "The cancellation window has expired. Contact the cook for assistance."

## Edge Cases
- Window = 0 minutes: no cancel button ever shown
- Order status changes to Preparing during attempt: server rejects
- Existing orders use cancellation_window_minutes snapshot (may be null → fall back to service default)

## Recent Error Patterns to Avoid
- gale_compliance (9 total): All controller responses via Gale. No plain returns.
- ui_compliance (7 total): Mobile-first, dark mode, translations on ALL strings.
- business_logic (6 total): Read spec carefully for exact BRs.

## Git Workflow
Before finishing: git add -f .executor/executor.db && git commit -m "chore: executor state F-162"
