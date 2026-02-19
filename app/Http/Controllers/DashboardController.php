<?php

namespace App\Http\Controllers;

use App\Services\CookDashboardService;
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
     * BR-157: Only accessible to cook/manager role (enforced by cook.access middleware)
     */
    public function cookDashboard(Request $request, CookDashboardService $dashboardService): mixed
    {
        $tenant = tenant();
        $user = $request->user();

        $dashboardData = $dashboardService->getDashboardData($tenant, $user);

        return gale()->view('cook.dashboard', [
            'tenant' => $tenant,
            'setupComplete' => $tenant?->isSetupComplete() ?? false,
            'todayOrders' => $dashboardData['todayOrders'],
            'weekRevenue' => $dashboardData['weekRevenue'],
            'activeMeals' => $dashboardData['activeMeals'],
            'pendingOrders' => $dashboardData['pendingOrders'],
            'recentOrders' => $dashboardData['recentOrders'],
            'recentNotifications' => $dashboardData['recentNotifications'],
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
            ]);
    }

    /**
     * Display the tenant landing page for public visitors.
     *
     * F-126: Tenant Landing Page Layout
     * BR-126: Only renders on tenant domains (enforced by root route dispatch)
     * BR-134: Publicly accessible without authentication
     */
    public function tenantHome(Request $request, TenantLandingService $landingService): mixed
    {
        $tenant = tenant();

        $landingData = $landingService->getLandingPageData($tenant);

        return gale()->view('tenant.home', $landingData, web: true);
    }
}
