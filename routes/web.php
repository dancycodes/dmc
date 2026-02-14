<?php

use App\Services\TenantService;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Routes are organized by domain context. The ResolveTenant middleware
| (registered globally) resolves the tenant before routes are handled.
| The EnsureMainDomain and EnsureTenantDomain middleware enforce context.
|
*/

// Root route dispatches based on domain context
Route::get('/', function () {
    if (app(TenantService::class)->isTenantDomain()) {
        $tenant = tenant();

        return response()->json([
            'tenant' => $tenant?->name,
            'slug' => $tenant?->slug,
        ]);
    }

    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Main Domain Routes
|--------------------------------------------------------------------------
|
| Routes accessible only on the main domain (dm.test / dancymeals.com).
| Admin panel and other main-domain-only features live here.
|
*/
Route::middleware('main.domain')->group(function () {
    // Admin routes will be registered here by F-043
    // Route::prefix('vault-entry')->group(function () { ... });
});

/*
|--------------------------------------------------------------------------
| Tenant Domain Routes
|--------------------------------------------------------------------------
|
| Routes accessible only on tenant domains (cook.dm.test / cook.cm).
| Cook landing pages, ordering, and tenant-specific features live here.
|
*/
Route::middleware('tenant.domain')->group(function () {
    // Tenant-specific routes will be added by later features (F-126, etc.)
});
