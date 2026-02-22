<?php

namespace App\Services;

use App\Models\Meal;
use App\Models\Rating;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CookDashboardService
{
    /**
     * Get all dashboard data for a tenant.
     *
     * F-077: Cook Dashboard Home
     * F-078: Cook Quick Actions Panel — quickActions key added
     * F-179: Cook Overall Rating Calculation — rating stat card
     * Forward-compatible with orders/notifications tables (not yet created).
     *
     * @return array{
     *     todayOrders: array<string, int>,
     *     weekRevenue: int,
     *     activeMeals: int,
     *     pendingOrders: int,
     *     recentOrders: array<mixed>,
     *     recentNotifications: array<mixed>,
     *     ratingStats: array{average: float, count: int, hasRating: bool, trend: string},
     *     quickActions: array<int, array{id: string, label: string, url: string, icon: string, color: string, badge: string|null}>
     * }
     */
    public function getDashboardData(Tenant $tenant, User $user): array
    {
        $pendingOrders = $this->getPendingOrdersCount($tenant);

        return [
            'todayOrders' => $this->getTodayOrdersByStatus($tenant),
            'weekRevenue' => $this->getWeekRevenue($tenant),
            'activeMeals' => $this->getActiveMealsCount($tenant),
            'pendingOrders' => $pendingOrders,
            'recentOrders' => $this->getRecentOrders($tenant),
            'recentNotifications' => $this->getRecentNotifications($user),
            'ratingStats' => $this->getRatingStats($tenant),
            'quickActions' => $this->getQuickActions($tenant, $user, $pendingOrders),
        ];
    }

    /**
     * Build the contextual quick actions panel data.
     *
     * F-078: Cook Quick Actions Panel
     * BR-174: Default actions: Create New Meal, View Pending Orders, Update Availability, View Wallet
     * BR-175: If setup is incomplete, "Complete Setup" appears first with accent color
     * BR-176: "View Pending Orders" shows current pending count (capped at "99+")
     * BR-177: Actions filtered by user permissions (hidden when not permitted)
     * BR-178: Actions navigate via Gale (no page reload) — handled in the view
     *
     * @param  int  $pendingOrders  Pre-computed pending count to avoid double query
     * @return array<int, array{id: string, label: string, url: string, icon: string, color: string, badge: string|null}>
     */
    public function getQuickActions(Tenant $tenant, User $user, int $pendingOrders = 0): array // @phpstan-ignore-line
    {
        $setupComplete = $tenant->isSetupComplete();
        $isManager = $tenant->cook_id !== $user->id;

        // Helper: manager permission check (managers use hasDirectPermission; cooks have all)
        $canAccess = function (string $permission) use ($isManager, $user): bool {
            if (! $isManager) {
                return true;
            }

            return $user->hasDirectPermission($permission);
        };

        // BR-176: Pending count badge — cap at "99+"
        $pendingBadge = $pendingOrders > 99 ? '99+' : (string) $pendingOrders;

        $actions = [];

        // BR-175: Setup incomplete action — always first, always visible
        if (! $setupComplete) {
            $actions[] = [
                'id' => 'complete-setup',
                'label_key' => 'Complete Setup',
                'path' => '/dashboard/setup',
                'icon' => 'setup',
                'color' => 'warning',
                'badge' => null,
            ];
        }

        // Create New Meal — requires can-manage-meals
        if ($canAccess('can-manage-meals')) {
            $actions[] = [
                'id' => 'create-meal',
                'label_key' => 'Create New Meal',
                'path' => '/dashboard/meals/create',
                'icon' => 'plus-circle',
                'color' => 'primary',
                'badge' => null,
            ];
        }

        // View Pending Orders — requires can-manage-orders
        if ($canAccess('can-manage-orders')) {
            $actions[] = [
                'id' => 'pending-orders',
                'label_key' => 'View Pending Orders',
                'path' => '/dashboard/orders?status=pending',
                'icon' => 'clock',
                'color' => 'info',
                'badge' => $pendingBadge,
            ];
        }

        // Update Availability — requires can-manage-meals
        if ($canAccess('can-manage-meals')) {
            $actions[] = [
                'id' => 'update-availability',
                'label_key' => 'Update Availability',
                'path' => '/dashboard/meals',
                'icon' => 'toggle',
                'color' => 'success',
                'badge' => null,
            ];
        }

        // View Wallet — cook-reserved (never for managers)
        if (! $isManager) {
            $actions[] = [
                'id' => 'view-wallet',
                'label_key' => 'View Wallet',
                'path' => '/dashboard/wallet',
                'icon' => 'wallet',
                'color' => 'secondary',
                'badge' => null,
            ];
        }

        return $actions;
    }

    /**
     * Format a pending order count for display, capped at "99+".
     *
     * F-078: BR-176 and edge case (99+ display).
     */
    public static function formatPendingCount(int $count): string
    {
        return $count > 99 ? '99+' : (string) $count;
    }

    /**
     * Get today's orders grouped by status.
     *
     * BR-165: Today's orders summary shows counts grouped by status:
     * Pending, Confirmed, Preparing, Ready.
     * BR-166: Uses Africa/Douala timezone.
     *
     * @return array<string, int>
     */
    public function getTodayOrdersByStatus(Tenant $tenant): array
    {
        $statuses = [
            'pending' => 0,
            'confirmed' => 0,
            'preparing' => 0,
            'ready' => 0,
        ];

        if (! Schema::hasTable('orders')) {
            return $statuses;
        }

        $todayStart = Carbon::now('Africa/Douala')->startOfDay()->utc();

        $counts = DB::table('orders')
            ->where('tenant_id', $tenant->id)
            ->where('created_at', '>=', $todayStart)
            ->whereIn('status', array_keys($statuses))
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return array_merge($statuses, $counts);
    }

    /**
     * Get this week's revenue from completed orders.
     *
     * BR-166: "This week" calculates from Monday 00:00 to current time in Africa/Douala timezone.
     * BR-167: Revenue counts only completed (delivered/picked up) orders for this tenant.
     * BR-172: All monetary values in XAF.
     */
    public function getWeekRevenue(Tenant $tenant): int
    {
        if (! Schema::hasTable('orders')) {
            return 0;
        }

        $weekStart = Carbon::now('Africa/Douala')->startOfWeek(Carbon::MONDAY)->utc();

        return (int) DB::table('orders')
            ->where('tenant_id', $tenant->id)
            ->where('created_at', '>=', $weekStart)
            ->whereIn('status', ['delivered', 'picked_up', 'completed'])
            ->sum('grand_total');
    }

    /**
     * Get the count of active meals for this tenant.
     *
     * BR-168: Active meals count reflects meals with is_active = true.
     */
    public function getActiveMealsCount(Tenant $tenant): int
    {
        return Meal::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->count();
    }

    /**
     * Get the count of pending orders for this tenant.
     *
     * Used for the "Pending Orders" stat card.
     */
    public function getPendingOrdersCount(Tenant $tenant): int
    {
        if (! Schema::hasTable('orders')) {
            return 0;
        }

        return DB::table('orders')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'pending')
            ->count();
    }

    /**
     * Get the last 5 recent orders regardless of status.
     *
     * BR-169: Recent orders list shows the last 5 orders regardless of status, newest first.
     *
     * @return array<mixed>
     */
    public function getRecentOrders(Tenant $tenant): array
    {
        if (! Schema::hasTable('orders')) {
            return [];
        }

        return DB::table('orders')
            ->where('orders.tenant_id', $tenant->id)
            ->leftJoin('users', 'orders.client_id', '=', 'users.id')
            ->select(
                'orders.id',
                'orders.order_number',
                'orders.status',
                'orders.grand_total',
                'orders.created_at',
                'users.name as customer_name',
            )
            ->orderByDesc('orders.created_at')
            ->limit(5)
            ->get()
            ->map(function ($order) {
                $order->created_at = Carbon::parse($order->created_at);
                $order->time_ago = $order->created_at->diffForHumans();

                return $order;
            })
            ->toArray();
    }

    /**
     * Get the 3 most recent unread notifications for the user.
     *
     * BR-171: Notifications shown are the most recent 3 unread notifications.
     *
     * @return array<mixed>
     */
    public function getRecentNotifications(\App\Models\User $user): array
    {
        if (! Schema::hasTable('notifications')) {
            return [];
        }

        return DB::table('notifications')
            ->where('notifiable_type', \App\Models\User::class)
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->orderByDesc('created_at')
            ->limit(3)
            ->get()
            ->map(function ($notification) {
                $data = json_decode($notification->data, true) ?? [];
                $notification->message = $data['message'] ?? $data['title'] ?? __('New notification');
                $notification->icon = $data['icon'] ?? 'bell';
                $notification->url = $data['url'] ?? '#';
                $notification->created_at = Carbon::parse($notification->created_at);
                $notification->time_ago = $notification->created_at->diffForHumans();

                return $notification;
            })
            ->toArray();
    }

    /**
     * Get total count of today's orders (sum of all statuses).
     */
    public function getTodayOrdersTotal(Tenant $tenant): int
    {
        $statuses = $this->getTodayOrdersByStatus($tenant);

        return array_sum($statuses);
    }

    /**
     * F-179: Get the cook's overall rating stats for the dashboard.
     *
     * BR-417: Simple average of all ratings.
     * BR-418: X.X/5 with one decimal place.
     * BR-421: Reads cached values from tenant settings.
     * BR-423: Returns hasRating=false for zero ratings.
     * Scenario 5: Stat card with trend indicator (up/down vs. last 30 days).
     *
     * @return array{average: float, count: int, hasRating: bool, trend: string}
     */
    public function getRatingStats(Tenant $tenant): array
    {
        $average = (float) ($tenant->getSetting('average_rating', 0));
        $count = (int) ($tenant->getSetting('total_ratings', 0));
        $hasRating = $count > 0;

        // Trend calculation: compare current average to 30-day-ago average
        $trend = 'stable';

        if ($hasRating && Schema::hasTable('ratings')) {
            $thirtyDaysAgo = Carbon::now()->subDays(30);
            $oldStats = Rating::query()
                ->where('tenant_id', $tenant->id)
                ->where('created_at', '<', $thirtyDaysAgo)
                ->selectRaw('AVG(stars) as average_rating, COUNT(*) as total_ratings')
                ->first();

            $oldAverage = round((float) ($oldStats->average_rating ?? 0), 1);
            $oldCount = (int) ($oldStats->total_ratings ?? 0);

            if ($oldCount > 0 && $average > $oldAverage) {
                $trend = 'up';
            } elseif ($oldCount > 0 && $average < $oldAverage) {
                $trend = 'down';
            }
        }

        return [
            'average' => $average,
            'count' => $count,
            'hasRating' => $hasRating,
            'trend' => $trend,
        ];
    }

    /**
     * Format a monetary value in XAF format.
     *
     * BR-172: All monetary values displayed in XAF with proper formatting.
     */
    public static function formatXAF(int $amount): string
    {
        return number_format($amount, 0, '.', ',').' XAF';
    }
}
