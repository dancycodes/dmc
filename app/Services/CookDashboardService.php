<?php

namespace App\Services;

use App\Models\Meal;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CookDashboardService
{
    /**
     * Get all dashboard data for a tenant.
     *
     * F-077: Cook Dashboard Home
     * Forward-compatible with orders/notifications tables (not yet created).
     *
     * @return array{
     *     todayOrders: array<string, int>,
     *     weekRevenue: int,
     *     activeMeals: int,
     *     pendingOrders: int,
     *     recentOrders: array<mixed>,
     *     recentNotifications: array<mixed>
     * }
     */
    public function getDashboardData(Tenant $tenant, \App\Models\User $user): array
    {
        return [
            'todayOrders' => $this->getTodayOrdersByStatus($tenant),
            'weekRevenue' => $this->getWeekRevenue($tenant),
            'activeMeals' => $this->getActiveMealsCount($tenant),
            'pendingOrders' => $this->getPendingOrdersCount($tenant),
            'recentOrders' => $this->getRecentOrders($tenant),
            'recentNotifications' => $this->getRecentNotifications($user),
        ];
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
            ->sum('total_amount');
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
            ->leftJoin('users', 'orders.user_id', '=', 'users.id')
            ->select(
                'orders.id',
                'orders.order_number',
                'orders.status',
                'orders.total_amount',
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
     * Format a monetary value in XAF format.
     *
     * BR-172: All monetary values displayed in XAF with proper formatting.
     */
    public static function formatXAF(int $amount): string
    {
        return number_format($amount, 0, '.', ',').' XAF';
    }
}
