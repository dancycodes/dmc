# Mission Directive: F-213 -- Minimum Order Amount Configuration

- **Spec Skill**: dancymeals-specs
- **Feature File**: references/F-213.md
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
- F-212: CookSettingsController and CookSettingsService already exist at:
  - `app/Http/Controllers/Cook/CookSettingsController.php`
  - `app/Services/CookSettingsService.php`
  - `resources/views/cook/settings/index.blade.php`
  - Add `minimum_order_amount` setting to the EXISTING settings page/controller — do NOT create new ones.
- Setting stored in tenant.settings JSON column (same pattern as cancellation_window_minutes).
- F-139: Cart view (order cart management) — add minimum enforcement there.
- F-126/F-128: Tenant landing page — show minimum when > 0.

## Key Business Rules
- BR-507: Default 0 XAF (no minimum)
- BR-508: Range 0–100,000 XAF inclusive
- BR-509: Must be integer (whole number)
- BR-510: Cart total (after promo) below minimum → checkout button disabled
- BR-511: Disabled checkout shows "Add X XAF more to meet the minimum order of Y XAF"
- BR-512: Displayed on tenant landing page (when > 0)
- BR-513: Displayed in cart view
- BR-514: 0 = no minimum (no mention anywhere in UI)
- BR-515: Only cook can modify (not managers)
- BR-516: Minimum evaluated against food subtotal AFTER promo discount, EXCLUDING delivery fees
- BR-517: Log changes via Spatie Activitylog with old/new values
- BR-518: All strings via __()
- BR-519: Gale reactively updates checkout button as cart changes

## UI/UX
- Settings page: add "Minimum Order Amount" field near cancellation window setting
  - Number input with "XAF" label, helper text: "Set the minimum amount clients must order. Set to 0 for no minimum."
- Cart: checkout button disabled with message when below minimum; Gale reactive
- Landing page: "Minimum order: 2,000 XAF" info badge (hidden when 0)
- XAF formatted with thousand separators (2,000 XAF not 2000 XAF)

## Recent Error Patterns to Avoid
- gale_compliance (9 total): All controller responses via Gale. No plain returns.
- ui_compliance (7 total): Mobile-first, dark mode, translations on ALL strings.
- business_logic (6 total): Read spec carefully for exact BRs. BR-516: delivery fee excluded.

## Git Workflow
Before finishing: git add -f .executor/executor.db && git commit -m "chore: executor state F-213"
