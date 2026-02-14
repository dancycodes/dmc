<?php

use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Support\Facades\App;

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

if (! function_exists('localized')) {
    /**
     * Get a localized column name based on the current app locale.
     *
     * Usage: localized('name') returns 'name_fr' when locale is 'fr'.
     */
    function localized(string $column): string
    {
        return $column.'_'.App::getLocale();
    }
}

if (! function_exists('availableLocales')) {
    /**
     * Get the list of available locales for the application.
     *
     * @return array<string>
     */
    function availableLocales(): array
    {
        return config('app.available_locales', ['en', 'fr']);
    }
}
