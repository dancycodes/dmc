# Mission Directive: F-144 -- Minimum Order Amount Validation

- **Spec Skill**: dancymeals-specs
- **Feature File**: references/F-144.md
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
- **Test Mode: fast** — This is an edge-case feature (type: edge-case). F-213 already implemented
  the frontend cart enforcement (disabled checkout button, Alpine computed isBelowMinimum).
  F-144's job is the SERVER-SIDE validation at order placement — ensuring a crafted POST request
  cannot bypass the minimum check.
- F-213: CookSettingsService::getMinimumOrderAmount() already exists. Cart view already has
  client-side enforcement. The settings page and UI are complete.
- CartController already passes minimumOrderAmount to cart view.

## What Needs to Be Done
1. Add server-side minimum order amount validation at the order submission step.
   - Find the order placement controller (likely OrderController or CartController place/checkout action)
   - Before creating the order, validate that cart subtotal ≥ minimum order amount
   - Reject with a Gale error/toast if minimum not met
2. Check if there's already a FormRequest for order placement — add the rule there
3. Write tests confirming server-side rejection works when minimum not met

## Key Business Rules
- BR-299: Minimum configured per cook in tenant settings (CookSettingsService)
- BR-300: Minimum vs food subtotal ONLY (before delivery fee, before discounts)
  - Note: F-213 scenario 5 says post-discount. Follow F-213 (post-discount) since it's the later spec.
  - The server-side check should use: food_subtotal - promo_discount ≥ minimum (excl delivery)
- BR-301: Error message: "Minimum order is {minimum} XAF. Add {remaining} XAF more to proceed."
- BR-302: Checkout blocked if minimum not met
- BR-303: Validation on cart view, quantity change, item removal (already done client-side; add server)
- BR-304: Skip if minimum is 0 or null
- BR-305: Localized via __()
- BR-306: remaining = minimum - cart_subtotal

## Edge Cases to Handle (Server-Side)
- Cart exactly equals minimum → valid
- 0 minimum → always valid (skip check)
- Very high minimum → correct formatted error message
- Craft POST to bypass frontend → server rejects with 422/Gale error

## Recent Error Patterns to Avoid
- gale_compliance (9 total): Controller responses via Gale (if returning errors from Gale controller)
- test_setup: Feature tests in tests/Feature/ must NOT include explicit uses() calls

## Git Workflow
Before finishing: git add -f .executor/executor.db && git commit -m "chore: executor state F-144"
