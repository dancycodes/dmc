<?php

namespace App\Providers;

use App\Services\TenantService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->configureRateLimiting();
    }

    /**
     * Configure the rate limiters for the application.
     *
     * Three tiers:
     * - strict: 5/min — authentication, password reset, email verification (BR-151, BR-159)
     * - moderate: 60/min — API endpoints, authenticated actions (BR-152)
     * - generous: 120/min — public page browsing (BR-153)
     *
     * Per-user for authenticated requests (BR-156), per-IP for unauthenticated (BR-155).
     */
    private function configureRateLimiting(): void
    {
        // BR-151, BR-159: Strict tier for authentication endpoints (5 requests/min per IP)
        RateLimiter::for('strict', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // BR-152, BR-155, BR-156: Moderate tier for API/authenticated endpoints (60 requests/min)
        RateLimiter::for('moderate', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // BR-153, BR-155: Generous tier for public page browsing (120 requests/min per IP)
        RateLimiter::for('generous', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });
    }
}
