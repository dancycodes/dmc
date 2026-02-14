<?php

namespace App\Providers;

use App\Services\TenantService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Grant super-admin role all permissions via Gate::before.
        // Returns null (not false) to avoid interfering with normal policy operations.
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });
    }
}
