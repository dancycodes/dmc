<?php

use App\Models\Tenant;
use App\Services\TenantService;

if (! function_exists('tenant')) {
    /**
     * Get the current tenant, or null if on the main domain.
     */
    function tenant(): ?Tenant
    {
        return app(TenantService::class)->get();
    }
}

if (! function_exists('tenantService')) {
    /**
     * Get the TenantService instance.
     */
    function tenantService(): TenantService
    {
        return app(TenantService::class);
    }
}
