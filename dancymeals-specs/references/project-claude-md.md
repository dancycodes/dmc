# DancyMeals — CLAUDE.md Content for Project Initialization

This content should be appended to the project's CLAUDE.md by the project-executor during
initialization. It ensures every agent session has tech stack and implementation standards loaded.

---

## DancyMeals — Implementation Reference

### Tech Stack

| Technology | Role |
|-----------|------|
| Laravel 12 | Backend framework |
| Gale (SSE + Alpine.js) | Server-driven reactivity — ALL controllers and blade files |
| Alpine.js v3 | Client-side interactivity |
| PostgreSQL | Database (Eloquent only, no raw SQL) |
| Tailwind CSS v4 | Styling (no custom CSS) |
| Flutterwave v3 | Payment gateway (MTN MoMo + Orange Money, Cameroon) |
| webpush | Push notifications |
| Spatie Permission | RBAC |
| Spatie Activitylog | Action logging |
| Spatie Honeypot | Bot protection |
| OpenStreetMap Nominatim | Location search |
| Intervention Image / Spatie Media Library | Image management |

**Excluded**: Livewire, Inertia, HTMX, React, Vue, Stripe, PayPal, MySQL, SQLite, custom CSS.

**AI Tooling**: Laravel Boost MCP, Context7 MCP, Playwright MCP, Laravel Simplifier plugin.

### Implementation Standards

**Multi-Tenancy**: Single DB, domain-based resolution middleware, row-level isolation via global
scopes on `tenant_id`. Main domain = discovery + admin (/vault-entry). Tenant domains = cook sites.

**Auth**: One auth system, all domains. Cross-domain session sharing. Users = DancyMeals accounts.
Roles: super-admin, admin, cook, manager, client. One role per tenant per user (default: client).

**Reactivity**: Gale for EVERYTHING. No full page reloads. SPA navigation, surgical DOM updates,
toast notifications, loading states. Full reload only for auth state changes.

**UI/UX**: Mobile-first (90%+ mobile users). Light/dark mode + theme switcher. Tenant-customizable
themes/fonts/radiuses. UI Designer skill for ALL interfaces. Global nav loader on Gale requests.

**Localization**: English default + French. `__('English text')` for all user-facing strings.
DB: `column_en`/`column_fr` for translatable fields. Language switcher in UI.

**PWA**: Offline page only. Install prompt (native + in-app). Push notifications via webpush.

**Notifications**: 3 channels — Push + Database + Email. Email for critical events only.

**Payments**: Flutterwave v3 split payments. Cook = subaccount. Commission configurable per cook
(default 10%). MTN MoMo + Orange Money. 15-min retry on failure.

**Security**: Rate limiting, Spatie Honeypot, tenant data isolation, active status enforcement,
Form Request validation, CSRF, no data leakage between tenants.

**Testing**: Phase 1 Playwright MCP (100% pass) then Phase 2 Pest feature tests.

**Code**: Laravel Simplifier before testing. Service layer for complex logic. Activity logging on
all significant actions. Use `php.bat` and `composer.bat` on Windows.

### Spec Skill Reference

This project's functional specification lives in the `dancymeals-specs` skill.
- To see all features: invoke the spec skill (reads SKILL.md with feature catalog)
- To read a specific feature: read `references/F-xxx.md` from the spec skill
- Tech stack details: `references/tech-stack.md`
- Implementation standards: `references/general-concepts.md`

### Available Skills

| Skill | When to Use |
|-------|-------------|
| gale | Every controller method and blade file (reactive framework) |
| ui-designer | Every user-facing interface (design system) |
| dancymeals-specs | Feature requirements lookup |
| pest-testing | Writing and running tests |
