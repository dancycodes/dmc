# Mission Directive: F-214 -- Cook Theme Selection

- **Spec Skill**: dancymeals-specs
- **Feature File**: references/F-214.md
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
- F-011: Tenant Theme Customization Infrastructure — read it to understand how themes, fonts, and
  border radii are currently stored and applied (CSS variables, tenant settings JSON keys).
  Read: `app/Http/Middleware/ApplyTenantTheme.php` or similar, and any existing theme config.
- F-212/F-213: CookSettingsController and settings view already exist. Add an "Appearance" section
  to the EXISTING settings page rather than creating a new page.
  - `app/Http/Controllers/Cook/CookSettingsController.php`
  - `app/Services/CookSettingsService.php`
  - `resources/views/cook/settings/index.blade.php`

## Key Business Rules
- BR-520: Themes: Arctic, High Contrast, Minimal, Modern (default), Neo Brutalism (+ any from F-011)
- BR-521: Fonts: Inter (default), Roboto, Poppins, Nunito, Open Sans, Montserrat
- BR-522: Border radius: none (0px), small (4px), medium (8px, default), large (12px), full (16px+)
- BR-523: Live preview updates via Gale as selections change (before saving)
- BR-524: Changes apply to tenant domain immediately on save
- BR-525: Stored in tenant.settings JSON: `theme`, `font`, `border_radius` keys
- BR-526: Cook only (not managers)
- BR-527: "Reset to Default" = Modern + Inter + medium
- BR-528: Preview shows light AND dark mode variants
- BR-529: Theme applies to tenant public site ONLY (not dashboard)
- BR-530: Log changes via Spatie Activitylog with old/new values
- BR-531: All strings via __()
- BR-532: Gale handles preview and save without page reloads

## UI/UX
- Appearance section in settings page (tab or section heading)
- Theme grid: visual color swatch cards (4-6 circles per card), theme name, selected = checkmark + border
- Font: dropdown where each option is rendered in its own font
- Border radius: segmented control (none | small | medium | large | full) with visual icons
- Preview: miniature landing page section (hero + card + button) with selected theme/font/radius
- Preview has light/dark toggle
- "Save Appearance" + "Reset to Default" (text button)
- Mobile: 2-column theme grid, preview below selectors

## Recent Error Patterns to Avoid
- gale_compliance (9 total): All controller responses via Gale. No plain returns.
- ui_compliance (7 total): Mobile-first, dark mode, translations on ALL strings.
- business_logic (6 total): Read F-011 carefully for existing theme infrastructure.

## Git Workflow
Before finishing: git add -f .executor/executor.db && git commit -m "chore: executor state F-214"
