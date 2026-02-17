# F-004: Multi-Tenant Domain Resolution — Completed

## Summary
Core multi-tenancy infrastructure. ResolveTenant middleware on every request. Tenant model with
slug/custom_domain/is_active. TenantService singleton. Domain-based route middleware (main.domain,
tenant.domain). Error pages for not-found/inactive tenants. Global tenant() helper.
110 Pest tests, 212 assertions.

## Key Files
- app/Models/Tenant.php, app/Services/TenantService.php
- app/Http/Middleware/{ResolveTenant,EnsureMainDomain,EnsureTenantDomain}.php
- bootstrap/app.php, app/helpers.php
- database/migrations/*_create_tenants_table.php, database/factories/TenantFactory.php

## Retries: Impl(0) Rev(0) Test(0)
