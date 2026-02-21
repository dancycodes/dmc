<?php

namespace App\Http\Controllers;

use App\Services\CookDashboardService;
use App\Services\ManagerDashboardService;
use App\Services\TenantLandingService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard at /vault-entry.
     */
    public function adminDashboard(Request $request): mixed
    {
        return gale()->view('admin.dashboard', [], web: true);
    }

    /**
     * Display the cook/manager dashboard on tenant domains.
     *
     * F-076: Cook Dashboard Layout & Navigation
     * F-077: Cook Dashboard Home — at-a-glance business overview
     * F-211: Manager Dashboard Access — manager-specific data and states
     * BR-157: Only accessible to cook/manager role (enforced by cook.access middleware)
     * BR-486: Manager with no permissions sees a "no permissions" message
     * BR-487: Managers with multiple tenants see a tenant switcher
     */
    public function cookDashboard(Request $request, CookDashboardService $dashboardService, ManagerDashboardService $managerDashboardService): mixed
    {
        $tenant = tenant();
        $user = $request->user();

        $dashboardData = $dashboardService->getDashboardData($tenant, $user);

        // F-211: Determine if the current user is a manager (not the cook)
        $isManager = $managerDashboardService->isManager($user, $tenant);
        $hasAnyPermission = true;
        $managedTenants = collect();
        $managedTenantsWithUrls = [];

        if ($isManager) {
            $hasAnyPermission = $managerDashboardService->hasAnyPermission($user);
            $managedTenants = $managerDashboardService->getManagedTenants($user);

            // Build tenant switcher data with URLs (BR-487, BR-488, BR-489)
            foreach ($managedTenants as $managedTenant) {
                $managedTenantsWithUrls[] = [
                    'id' => $managedTenant->id,
                    'name' => $managedTenant->name,
                    'first_letter' => $managedTenant->first_letter,
                    'is_active' => $managedTenant->is_active,
                    'dashboard_url' => $managerDashboardService->getTenantDashboardUrl($managedTenant),
                    'is_current' => $managedTenant->id === $tenant->id,
                ];
            }
        }

        return gale()->view('cook.dashboard', [
            'tenant' => $tenant,
            'setupComplete' => $tenant?->isSetupComplete() ?? false,
            'todayOrders' => $dashboardData['todayOrders'],
            'weekRevenue' => $dashboardData['weekRevenue'],
            'activeMeals' => $dashboardData['activeMeals'],
            'pendingOrders' => $dashboardData['pendingOrders'],
            'recentOrders' => $dashboardData['recentOrders'],
            'recentNotifications' => $dashboardData['recentNotifications'],
            'ratingStats' => $dashboardData['ratingStats'],
            // F-211: Manager-specific data
            'isManager' => $isManager,
            'managerHasAnyPermission' => $hasAnyPermission,
            'managedTenants' => $managedTenantsWithUrls,
        ], web: true);
    }

    /**
     * Refresh dashboard stats via Gale polling.
     *
     * F-077: BR-170 — Dashboard data updates in real-time via Gale SSE.
     * Called by x-interval on the dashboard view.
     */
    public function refreshDashboardStats(Request $request, CookDashboardService $dashboardService): mixed
    {
        $tenant = tenant();
        $user = $request->user();

        $dashboardData = $dashboardService->getDashboardData($tenant, $user);

        $ratingStats = $dashboardData['ratingStats'];

        return gale()
            ->componentState('stat-today-orders', [
                'total' => array_sum($dashboardData['todayOrders']),
                'pending' => $dashboardData['todayOrders']['pending'],
                'confirmed' => $dashboardData['todayOrders']['confirmed'],
                'preparing' => $dashboardData['todayOrders']['preparing'],
                'ready' => $dashboardData['todayOrders']['ready'],
            ])
            ->componentState('stat-week-revenue', [
                'value' => $dashboardData['weekRevenue'],
                'formatted' => CookDashboardService::formatXAF($dashboardData['weekRevenue']),
            ])
            ->componentState('stat-active-meals', [
                'value' => $dashboardData['activeMeals'],
            ])
            ->componentState('stat-pending-orders', [
                'value' => $dashboardData['pendingOrders'],
            ])
            ->componentState('stat-cook-rating', [
                'average' => $ratingStats['average'],
                'count' => $ratingStats['count'],
                'hasRating' => $ratingStats['hasRating'],
                'trend' => $ratingStats['trend'],
            ]);
    }

    /**
     * Display the tenant landing page for public visitors.
     *
     * F-126: Tenant Landing Page Layout
     * F-128: Available Meals Grid Display
     * BR-126: Only renders on tenant domains (enforced by root route dispatch)
     * BR-134: Publicly accessible without authentication
     */
    public function tenantHome(Request $request, TenantLandingService $landingService): mixed
    {
        $tenant = tenant();
        $page = max(1, (int) $request->query('page', 1));

        $landingData = $landingService->getLandingPageData($tenant, $page);

        return gale()->view('tenant.home', $landingData, web: true);
    }
}
