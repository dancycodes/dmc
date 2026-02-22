# Mission Directive: F-031 -- Profile Photo Upload

- **Spec Skill**: dancymeals-specs
- **Feature File**: references/F-031.md
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
- F-030: Profile view exists — read it to understand the User model and profile page structure.
  Look at: `app/Http/Controllers/Auth/ProfileController.php` or similar,
  and the profile view file (likely `resources/views/auth/profile/...`).
- F-016: Base layout nav — find where the user avatar is rendered in nav and update it to show photo.
- Check if User model has a `photo` column already; if not, create a migration to add it.
- Intervention Image is installed — use it for 256x256 center-cropped resize. Check version:
  `composer.json` — may be intervention/image v2 or v3. Use the correct API for the version.
- Storage: use `public` disk, store in `photos/users/` directory.

## Key Business Rules
- BR-103: Accept JPG/JPEG, PNG, WebP only
- BR-104: Max 2MB
- BR-105: Resize/crop to 256×256 square
- BR-106: Preview before save (client-side FileReader preview via Alpine)
- BR-107: Replace old file — delete old from storage on new upload
- BR-108: Remove photo → default avatar (initials or icon)
- BR-109: Use Intervention Image (or Spatie Media Library if already set up for users)
- BR-110: Nav avatar updates via Gale on save (no page reload)
- BR-111: Activity logged on upload and remove

## UI/UX
- "Change Photo" hover button on avatar area (desktop) / button below (mobile)
- Client-side circular preview via FileReader before upload
- Gale x-files for file upload (FormData multipart)
- "Remove Photo" secondary/danger button (with confirmation)
- Upload progress indicator
- Light/dark mode

## Important: Gale File Upload
- For file uploads, Gale uses x-files directive — check gale skill for correct pattern
- Use $request->validate() (not $request->validateState()) when files are present
  (known lesson from F-183: Gale auto-converts to multipart FormData with files)

## Recent Error Patterns to Avoid
- gale_compliance (9 total): All controller responses via Gale. No plain returns.
- ui_compliance (7 total): Mobile-first, dark mode, translations on ALL strings.
- validation (1): Use $request->validate() not $request->validateState() for file uploads

## Git Workflow
Before finishing: git add -f .executor/executor.db && git commit -m "chore: executor state F-031"
