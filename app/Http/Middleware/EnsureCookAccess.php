<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureCookAccess
{
    /**
     * Ensure the authenticated user has cook or manager role for the current tenant.
     *
     * BR-157: Only users with cook or manager role for the current tenant can access the dashboard.
     * BR-156: Dashboard routes are only accessible on tenant domains (handled by tenant.domain middleware).
     *
     * This middleware runs AFTER auth and tenant.domain middleware, so both the user
     * and tenant are guaranteed to be resolved. It checks that the user has the cook
     * role (is the tenant's assigned cook) or has a manager role with permissions
     * configured for this tenant.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenant = tenant();

        // Must have both an authenticated user and a resolved tenant
        if (! $user || ! $tenant) {
            abort(403);
        }

        // Check if user is the cook assigned to this tenant
        if ($tenant->cook_id === $user->id) {
            return $next($request);
        }

        // Check if user has the cook role (fallback for super-admin or direct role assignment)
        if ($user->hasRole('cook') && $tenant->cook_id === $user->id) {
            return $next($request);
        }

        // Check if user is a manager scoped to this specific tenant (F-209)
        // Uses tenant_managers pivot table for proper per-tenant scoping
        $isManagerForTenant = DB::table('tenant_managers')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($isManagerForTenant) {
            return $next($request);
        }

        // Super-admin can access any dashboard
        if ($user->hasRole('super-admin')) {
            return $next($request);
        }

        abort(403);
    }
}
