<?php

namespace App\Http\Middleware;

use App\Services\ManagerDashboardService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * F-211: Manager Dashboard Access
 *
 * Enforces permission-based access control for managers on the cook dashboard.
 *
 * This middleware runs AFTER the auth + cook.access middleware chain.
 * If the user is the cook (not a manager), this middleware is a no-op pass-through.
 * If the user is a manager, it checks whether they have the required permission
 * for the requested path.
 *
 * BR-485: Direct URL access to an unpermitted section returns 403 Forbidden.
 * BR-491: Cook-reserved sections are always 403 for managers.
 * BR-490: Each section visit is logged.
 */
class EnsureManagerSectionAccess
{
    public function __construct(
        private readonly ManagerDashboardService $managerDashboardService
    ) {}

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenant = tenant();

        // If no user or tenant, let the upstream middleware handle it
        if (! $user || ! $tenant) {
            return $next($request);
        }

        // Cooks, super-admins: pass through unrestricted
        if ($tenant->cook_id === $user->id || $user->hasRole('super-admin')) {
            return $next($request);
        }

        // For managers: enforce section-level access control
        if ($this->managerDashboardService->isManager($user, $tenant)) {
            $requestPath = ltrim($request->path(), '/');

            if (! $this->managerDashboardService->managerCanAccessPath($user, $requestPath)) {
                abort(403, __('You do not have permission to access this section.'));
            }

            // BR-490: Log section access on GET requests only (not POST actions)
            if ($request->isMethod('GET')) {
                $section = $this->managerDashboardService->getSectionName($requestPath);
                $this->managerDashboardService->logSectionAccess($user, $tenant, $section);
            }
        }

        return $next($request);
    }
}
