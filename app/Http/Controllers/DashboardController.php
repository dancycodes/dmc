<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CookDashboardService;
use App\Services\ManagerDashboardService;
use App\Services\TenantLandingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard at /vault-entry.
     */
    public function adminDashboard(Request $request): mixed
    {
        $totalTenants = Tenant::query()->count();
        $totalUsers = User::query()->count();
        $activeOrders = Order::query()
            ->whereIn('status', ['pending', 'confirmed', 'preparing', 'ready', 'picked_up'])
            ->count();
        $openComplaints = Complaint::query()
            ->where('status', 'open')
            ->count();

        return gale()->view('admin.dashboard', [
            'totalTenants' => $totalTenants,
            'totalUsers' => $totalUsers,
            'activeOrders' => $activeOrders,
            'openComplaints' => $openComplaints,
        ], web: true);
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
            // F-078: Quick actions panel — resolved with translated labels and absolute URLs
            'quickActions' => $this->resolveQuickActions($dashboardData['quickActions']),
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
     * F-078: BR-179 — Quick actions panel updates in real-time (pending count).
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
            ])
            // F-078: BR-179 — Quick actions panel real-time update
            ->componentState('quick-actions', [
                'actions' => $this->resolveQuickActions($dashboardData['quickActions']),
            ]);
    }

    /**
     * Resolve quick action definitions into view-ready format (translate labels, build URLs).
     *
     * F-078: Separates service layer (container-free) from view layer (needs translator + url()).
     * The service returns `label_key` (plain English string) and `path` (relative URL).
     * This method maps them to `label` (translated) and `url` (absolute URL).
     *
     * @param  array<int, array{id: string, label_key: string, path: string, icon: string, color: string, badge: string|null}>  $rawActions
     * @return array<int, array{id: string, label: string, url: string, icon: string, color: string, badge: string|null}>
     */
    private function resolveQuickActions(array $rawActions): array
    {
        return array_map(fn (array $action) => [
            'id' => $action['id'],
            'label' => __($action['label_key']),
            'url' => url($action['path']),
            'icon' => $action['icon'],
            'color' => $action['color'],
            'badge' => $action['badge'],
        ], $rawActions);
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

        // F-180: Pass current user for testimonial eligibility + duplicate detection.
        $currentUser = auth()->user();
        $landingData = $landingService->getLandingPageData($tenant, $page, $currentUser);

        // F-196: Resolve favorite state for the current user on this cook's tenant.
        // BR-327: Heart icon visually reflects current favorite state on page load.
        $isFavorited = false;
        $userFavoriteMealIds = [];
        if (Auth::check()) {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            if ($tenant?->cook_id) {
                $isFavorited = $user->hasFavoritedCook((int) $tenant->cook_id);
            }
            // F-197: Resolve user's favorited meal IDs for this tenant's meal cards.
            // BR-337: Heart icon visually reflects current favorite state on page load.
            $userFavoriteMealIds = $user->favoriteMeals()
                ->whereHas('tenant', fn ($q) => $q->where('id', $tenant?->id))
                ->allRelatedIds()
                ->toArray();
        }

        return gale()->view('tenant.home', array_merge($landingData, [
            'isFavorited' => $isFavorited,
            'isAuthenticated' => Auth::check(),
            'userFavoriteMealIds' => $userFavoriteMealIds,
            'isMealCardAuthenticated' => Auth::check(),
        ]), web: true);
    }
}
