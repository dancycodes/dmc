# DancyMeals — Tech Stack Reference

## Core Application Stack

| Technology | Role | Version |
|-----------|------|---------|
| Laravel | Backend framework, routing, ORM, queue, events | 12 (latest) |
| Gale | Server-driven reactive framework (SSE + Alpine.js) | Latest |
| Alpine.js | Client-side interactivity and DOM manipulation | v3 with plugins |
| PostgreSQL | Primary database (accessed through Laravel Eloquent) | Latest stable |
| Tailwind CSS | Utility-first styling framework | v4 |
| Flutterwave | Mobile money payment gateway (MTN MoMo + Orange Money) | v3 |
| laravel-notification-channels/webpush | PWA push notifications | Latest |
| Spatie Laravel Permission | Role-based access control | Latest |
| Spatie Laravel Activitylog | User/system action logging | Latest |
| Spatie Laravel Honeypot | Bot/spam form protection | Latest |
| Intervention Image or Spatie Media Library | Image upload, resize, format management | Latest |
| OpenStreetMap API (Nominatim) | Neighbourhood location autocomplete/search | Free tier |
| Laravel Herd | Local dev server with subdomain support via `herd link` | Latest |

---

## Flutterwave v3 Specifics

- **Payment Methods**: MTN Mobile Money, Orange Money (Cameroon — country code CM)
- **Split Payments**: Use Flutterwave subaccounts. Each cook tenant maps to a Flutterwave subaccount.
  Platform keeps commission (default 10%, configurable per cook). Cook receives remainder.
- **Split Type**: `percentage` — subaccount receives percentage of each transaction.
- **Transfers**: For cook withdrawals, use Flutterwave Transfers API to send funds to cook's
  mobile money number.
- **Webhooks**: Handle `charge.completed`, `transfer.completed`, `transfer.failed` events.
- **PHP SDK**: `flutterwavedev/flutterwave-v3` Composer package.
- **Test Keys**: Available in project `.env` for local development.

---

## AI Tooling Stack

### MCP Servers

| Server | Purpose |
|--------|---------|
| Laravel Boost | Laravel development patterns, schema inspection, tinker, docs search |
| Context7 | Stack documentation lookup for accurate syntax and API usage |
| Playwright | Browser automation for UI/UX and end-to-end testing |

### Plugins

| Plugin | Purpose |
|--------|---------|
| Laravel Simplifier | Code review and cleaning — run after implementations, before testing |

### Skills

| Skill | Purpose |
|-------|---------|
| Gale | Server-driven reactivity — consult for ALL controllers and blade files |
| UI Designer | UI/UX design — consult for ALL user-facing interface design |
| dancymeals-specs | This skill — feature requirements lookup |

---

## Stack Usage Rules

- The tech stack is non-negotiable. No alternative frameworks or libraries.
- Laravel Eloquent is the only database access method (no raw SQL unless absolutely unavoidable).
- Gale handles all server-driven reactivity. No full page reloads. SPA navigation via Gale.
- Alpine.js handles client-side DOM manipulation alongside Gale.
- Tailwind CSS v4 is the only styling approach (no custom CSS unless unavoidable).
- Flutterwave v3 is the only payment gateway (mobile money focused, Cameroon).
- Spatie packages are the standard for permissions, activity logging, and spam protection.
- Image handling via Intervention Image or Spatie Media Library (whichever is installed).
- OpenStreetMap Nominatim API for neighbourhood geolocation (free, no API key required).
- Laravel Herd for local development. Use `herd link subdomain.domain` for subdomain activation.
- Use `php.bat` and `composer.bat` for all CLI commands on Windows.

---

## Exclusions

| Technology | Why Excluded |
|-----------|-------------|
| Livewire | Gale is the chosen reactive framework |
| Inertia.js | Gale is the chosen reactive framework |
| HTMX | Gale is the chosen reactive framework |
| React / Vue | Alpine.js + Gale handles all frontend needs |
| Stripe / PayPal | Flutterwave is the only payment gateway (mobile money focus) |
| MySQL / SQLite | PostgreSQL is the only database |
| Custom CSS | Tailwind CSS v4 only |
| Electron / React Native | PWA only, no native apps |
