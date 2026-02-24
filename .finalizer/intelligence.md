## App Intelligence
Base URL: https://dmc.test
App Name: DancyMeals

## Domains
- Main: https://dmc.test (discovery + admin /vault-entry)
- Tenant latifa: https://latifa.dmc.test (Latifa Kitchen, active)
- Tenant powel: https://powel.dmc.test (Chef Powel, active)
- Tenant mama-ngono: https://mama-ngono.dmc.test (Mama Ngono Dishes, active)
- Tenant mariette: https://mariette.cm (Chez Mariette, custom domain, active)
- Tenant closed-cook: https://closed-cook.dmc.test (Closed Cook, INACTIVE)

## Test Credentials
All passwords: `password`
- super-admin: superadmin@dmc.test / password
- admin: admin@dmc.test / password
- cook (latifa): cook@latifa.test / password (tenant: latifa.dmc.test)
- cook (powel): cook@powel.test / password (tenant: powel.dmc.test)
- client: client@dmc.test / password
- manager (latifa): manager@latifa.test / password (tenant: latifa.dmc.test)

## Routes
- Login: https://dmc.test/login (GET)
- Register: https://dmc.test/register (GET)
- Logout: POST https://dmc.test/logout
- Admin dashboard: https://dmc.test/vault-entry
- Cook dashboard: https://latifa.dmc.test/dashboard
- Discovery (home): https://dmc.test

## Key Assets
- favicon: /public/favicon.ico (exists: yes)
- PWA icons: /public/icons/icon-192x192.png (exists: yes)
- manifest: /public/manifest.json (exists: yes)
- service worker: /public/service-worker.js (exists: yes)
- offline page: /public/offline.html (exists: yes)

## Layouts
- app.blade.php — main public layout
- auth.blade.php — auth pages
- admin.blade.php — admin panel
- cook-dashboard.blade.php — cook dashboard
- main-public.blade.php — discovery/public pages
- tenant-public.blade.php — tenant landing pages

## Reactivity
Gale (SSE + Alpine.js) — server-driven reactive framework
Convention: ALL controllers and blade files use Gale. No Livewire, Inertia, HTMX.

## Multi-Tenancy
Yes. Domain-based resolution via ResolveTenant middleware.
- Main domain (dmc.test) = discovery + admin
- Subdomain ({slug}.dmc.test) = tenant cook site
- Custom domain support also exists

## Roles
super-admin, admin, cook, manager, client
One role per tenant per user. Default: client.

## Locales
Default: en. Secondary: fr. Language switcher available.
DB: column_en / column_fr pattern for translatable fields.

## DB Record Counts
- users: 6
- tenants: 5 (4 active, 1 inactive)
- meals: 0 (no seeded meals)
- orders: 0 (no seeded orders)
