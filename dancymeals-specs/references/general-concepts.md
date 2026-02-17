# DancyMeals — General Implementation Concepts

These are non-negotiable standards that apply to every feature. Any AI agent implementing a feature
must read this file alongside the feature's reference file.

---

## 1. Multi-Tenancy Architecture

DancyMeals uses a **single-database, multi-domain** architecture:

- **One PostgreSQL database** shared by all tenants. No separate databases per tenant.
- **Domain-based tenant resolution**: Middleware identifies the current tenant by parsing the
  request hostname (subdomain or custom domain).
- **Row-level isolation**: All tenant-scoped models include a `tenant_id` foreign key. Laravel
  global scopes enforce tenant isolation automatically. Never query tenant data without scoping.
- **Main domain** (dancymeals.com / dm.test): Discovery page + admin panel (/vault-entry/*).
- **Tenant domains** (cook1.dancymeals.com, cook1.cm, etc.): Cook's branded website.
- **Shared entities**: Users, roles, and permissions are global (not tenant-scoped).
- **Tenant-scoped entities**: Meals, orders, wallet transactions, locations, schedules, etc.
- **Local dev**: Use `herd link subdomain.website` to register subdomains with Laravel Herd.

---

## 2. Authentication & Authorization

### Cross-Domain Auth
- **One auth system** accessible on any domain (main or tenant).
- Users authenticate once and are authenticated across ALL domains simultaneously.
- When authenticating on a tenant domain, the tenant's branding is shown but the user knows
  it's their DancyMeals account.
- Session sharing across domains (cookie domain configuration or token-based approach).

### RBAC (Spatie Laravel Permission)
- **Roles**: super-admin, admin, cook, manager, client.
- **Permissions are granular**: Every view and action is protected by specific permissions.
- A role is a set of permissions. Super-admin has ALL permissions.
- Each user has **one role per tenant** (default: client).
- Cook can create manager roles with a subset of tenant-related permissions.
- Every user role inherits client capabilities (every role can do what a client does).
- Only active users (`is_active = true`) can perform actions.

### Admin Access
- Admin panel accessible ONLY via main domain at `/vault-entry` and `/vault-entry/*`.
- No tenant domain can access admin routes. Middleware enforces this.
- First super-admin created via artisan command.

---

## 3. UI/UX Design

All user interfaces use the **UI Designer skill**. No exceptions.

- **100% responsive** — mobile-first approach. 90%+ of users are on mobile.
- **Light and Dark mode** supported throughout.
- **Theme switcher** accessible in UI for light/dark toggle.
- **Tenant theme customization**: Cooks select from preset themes (Arctic, High Contrast,
  Minimal, Modern, Neo Brutalism, etc.), fonts (Inter, Roboto, Poppins, etc.), and border
  radiuses. The tenant site dynamically reflects the cook's choices.
- **Global navigation loader** on Gale requests.
- **Loading states** on server requests.
- **Toast notifications** via Gale for user feedback.

---

## 4. Reactivity (Gale)

**Gale is the reactive framework.** Use the Gale skill for:
- All controller methods and blade files
- SPA navigation (no full page reloads)
- Surgical DOM and state updates
- Real-time updates (SSE)
- Form submissions and validation
- Toast notifications
- Loading states

Full page reloads are acceptable ONLY for authentication state changes (login/logout).

---

## 5. Localization

- **Default language**: English. All code written in English.
- **Supported languages**: English and French.
- **Convention**: `__('Full text IN ENGLISH')` for all user-facing strings. Not key-based.
- **Database columns**: `column_en` and `column_fr` for translatable fields. If a value is
  provided for one translation, the other is compulsory.
- **Language switcher**: Available in UI, persisted in user preferences.
- **No hardcoded user-facing strings** anywhere in blade files or controller responses.

---

## 6. PWA

Every web view is part of the PWA:
- **Offline page**: Friendly "you're offline" page when connection lost. No offline data.
- **Install prompt**: Browser native prompt + in-app UI button/banner.
- **Push notifications**: Via webpush package. Permission prompt on first visit.
  Notification preferences manageable in user settings.
- **Web app manifest**: Proper icons, theme colors, display mode, app name.
- **Service worker**: Offline fallback + push notification receipt.

---

## 7. Notifications

Three channels, all active:
- **Push**: Real-time alerts via webpush. For time-sensitive events.
- **Database**: In-app notification history. Readable in user's notification center.
- **Email**: For critical events (order confirmation, payment receipt, account actions).

Users can configure notification preferences per channel per event type.

---

## 8. Payments (Flutterwave v3)

- **Payment methods**: MTN Mobile Money, Orange Money (Cameroon).
- **Flow**: Client initiates payment > redirected to Flutterwave > completes on mobile >
  webhook confirms > order status updates.
- **Commission**: Platform takes configurable percentage (default 10%) per cook.
  Uses Flutterwave split payments with subaccounts.
- **Wallet**: Client wallet for refunds (can be used for future orders, toggleable by admin).
  Cook wallet with withdrawable/unwithdrawable sections.
- **Withdrawal**: Cook requests withdrawal > Flutterwave Transfer API sends to mobile money.
  If transfer fails, task appears in admin manual payout queue.
- **Retry**: 15-minute window for payment retry on failure.

---

## 9. Security

- **Tenant isolation**: Global scopes on all tenant-scoped models. No data leakage.
- **Rate limiting**: Applied to all public endpoints, stricter on auth endpoints.
- **Honeypot**: Spatie Honeypot on all public forms.
- **RBAC**: Every route and action permission-checked.
- **Active status**: Middleware checks user active status on every authenticated request.
- **Input validation**: Form Request classes for all form submissions.
- **CSRF**: Laravel default CSRF protection.
- **XSS/SQL injection**: Follow Laravel best practices (Blade escaping, parameterized queries).

---

## 10. Activity Logging

- **Spatie Activitylog** on all significant user and system actions.
- Log: who did what, when, on which model, with what changes.
- Viewable in admin panel activity log viewer.
- Useful for audit trails and dispute resolution.

---

## 11. Testing

Two-phase mandatory testing:

### Phase 1: Playwright MCP Testing
- Cover every user-facing flow from feature specifications.
- Test UI interactions: navigation, forms, buttons, modals, responsiveness.
- Happy paths, alternate paths, error paths.
- **100% pass rate required** before Phase 2.

### Phase 2: Pest Feature Testing
- Feature tests for all endpoints.
- Business logic validation against spec business rules.
- Edge case coverage.
- Data integrity checks.

---

## 12. Code Standards

- **All blade files**: Consult UI Designer skill AND Gale skill.
- **All controller files**: Consult Gale skill.
- **Laravel Boost MCP** and **Context7 MCP**: Use extensively during implementation.
- Follow Laravel conventions: resource controllers, form requests, policies, events/listeners.
- Service layer for complex business logic.
- Laravel Simplifier plugin for code review before testing.

---

## 13. Data Architecture Conventions

- **Translatable fields**: `name_en`, `name_fr`, `description_en`, `description_fr`.
- **Money**: Store in integer (XAF cents) or decimal. Display formatted with XAF suffix.
- **Timestamps**: Use Laravel's `created_at`, `updated_at`. Add `completed_at`, `cancelled_at`
  etc. as needed for status tracking.
- **Soft deletes**: On entities that have historical references (orders, transactions, users).
- **UUIDs or IDs**: Follow existing project convention.
- **Enums**: For statuses (order status, payment status, complaint category, etc.).
