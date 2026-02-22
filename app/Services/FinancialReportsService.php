<?php

namespace App\Services;

use App\Models\CookWallet;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * FinancialReportsService — aggregates detailed financial data for admin reports.
 *
 * F-058: Financial Reports & Export
 * BR-143: Revenue = completed or delivered orders only
 * BR-144: Commission per order = order amount × cook's commission rate at time of order
 * BR-145: Pending payouts = cook wallet balances not yet withdrawn
 * BR-146: Failed payments = Flutterwave transactions with failed status
 * BR-149: All amounts in XAF
 * BR-150: Default date range = current month
 */
class FinancialReportsService
{
    /** Status values that count as revenue-generating */
    public const COMPLETED_STATUSES = ['completed', 'delivered', 'picked_up'];

    /** Default tab keys */
    public const TABS = ['overview', 'by_cook', 'pending_payouts', 'failed_payments'];

    /**
     * Get the current-month default date range.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public function getDefaultDateRange(): array
    {
        $now = Carbon::now();

        return [
            'start' => $now->copy()->startOfMonth(),
            'end' => $now->copy()->endOfMonth(),
        ];
    }

    /**
     * Parse and validate a date range from request inputs.
     * Falls back to current month if inputs are invalid.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public function parseDateRange(?string $startDate, ?string $endDate): array
    {
        try {
            if ($startDate && $endDate) {
                $start = Carbon::parse($startDate)->startOfDay();
                $end = Carbon::parse($endDate)->endOfDay();
                if ($start->lte($end)) {
                    return ['start' => $start, 'end' => $end];
                }
            }
        } catch (\Throwable) {
            // Fall through to default
        }

        return $this->getDefaultDateRange();
    }

    /**
     * Get summary metrics: gross revenue, commission, net payouts, pending payouts, failed count.
     * BR-143, BR-144, BR-145, BR-146
     *
     * @return array{gross_revenue: int, commission: int, net_payouts: int, pending_payouts: int, failed_count: int}
     */
    public function getSummaryMetrics(Carbon $start, Carbon $end, ?int $cookId = null): array
    {
        $query = Order::query()
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('completed_at', [$start, $end]);

        if ($cookId) {
            $query->where('cook_id', $cookId);
        }

        $grossRevenue = (int) $query->clone()->sum('grand_total');
        $commission = (int) $query->clone()->whereNotNull('commission_amount')->sum('commission_amount');
        $netPayouts = $grossRevenue - $commission;

        // BR-145: Pending payouts = cook wallet balances not yet withdrawn
        $pendingQuery = CookWallet::query();
        if ($cookId) {
            $tenant = Tenant::where('cook_id', $cookId)->first();
            if ($tenant) {
                $pendingQuery->where('tenant_id', $tenant->id);
            }
        }
        $pendingPayouts = (int) $pendingQuery->sum(DB::raw('total_balance'));

        // BR-146: Failed payments count
        $failedQuery = PaymentTransaction::query()->where('status', 'failed');
        if ($cookId) {
            $failedQuery->where('cook_id', $cookId);
        }
        $failedCount = $failedQuery->count();

        return [
            'gross_revenue' => $grossRevenue,
            'commission' => $commission,
            'net_payouts' => $netPayouts,
            'pending_payouts' => $pendingPayouts,
            'failed_count' => $failedCount,
        ];
    }

    /**
     * Get daily revenue overview table.
     * BR-143: Revenue = completed orders only
     *
     * @return Collection<int, array{date: string, gross_revenue: int, commission: int, net_payout: int, order_count: int}>
     */
    public function getOverviewData(Carbon $start, Carbon $end, ?int $cookId = null): Collection
    {
        $query = DB::table('orders')
            ->selectRaw('
                DATE(completed_at) as report_date,
                SUM(grand_total) as gross_revenue,
                SUM(COALESCE(commission_amount, 0)) as commission,
                COUNT(*) as order_count
            ')
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('completed_at', [$start, $end])
            ->groupByRaw('DATE(completed_at)')
            ->orderByRaw('DATE(completed_at) DESC');

        if ($cookId) {
            $query->where('cook_id', $cookId);
        }

        return $query->get()->map(fn ($row) => [
            'date' => Carbon::parse($row->report_date)->format('M j, Y'),
            'gross_revenue' => (int) $row->gross_revenue,
            'commission' => (int) $row->commission,
            'net_payout' => (int) $row->gross_revenue - (int) $row->commission,
            'order_count' => (int) $row->order_count,
        ]);
    }

    /**
     * Get revenue breakdown by cook.
     * BR-143: Revenue = completed orders only
     * BR-144: Commission per order = order.commission_amount (snapshot at time of order)
     *
     * @return Collection<int, array{cook_name: string, tenant_name: string, gross_revenue: int, commission_rate: float, commission: int, net_payout: int, order_count: int}>
     */
    public function getByCookData(Carbon $start, Carbon $end, ?int $cookId = null): Collection
    {
        $locale = app()->getLocale();
        $nameCol = $locale === 'fr' ? 'tenants.name_fr' : 'tenants.name_en';

        $query = DB::table('orders')
            ->join('tenants', 'orders.tenant_id', '=', 'tenants.id')
            ->leftJoin('users', 'tenants.cook_id', '=', 'users.id')
            ->selectRaw("
                tenants.cook_id,
                COALESCE(users.name, 'Unknown') as cook_name,
                COALESCE({$nameCol}, tenants.name_en) as tenant_name,
                tenants.id as tenant_id,
                SUM(orders.grand_total) as gross_revenue,
                SUM(COALESCE(orders.commission_amount, 0)) as commission,
                AVG(COALESCE(orders.commission_rate, 10)) as avg_commission_rate,
                COUNT(orders.id) as order_count
            ")
            ->whereIn('orders.status', self::COMPLETED_STATUSES)
            ->whereBetween('orders.completed_at', [$start, $end])
            ->groupBy('tenants.cook_id', 'users.name', $nameCol, 'tenants.name_en', 'tenants.id')
            ->orderByDesc('gross_revenue');

        if ($cookId) {
            $query->where('tenants.cook_id', $cookId);
        }

        return $query->get()->map(fn ($row) => [
            'cook_name' => $row->cook_name,
            'tenant_name' => $row->tenant_name,
            'tenant_id' => $row->tenant_id,
            'gross_revenue' => (int) $row->gross_revenue,
            'commission_rate' => round((float) $row->avg_commission_rate, 1),
            'commission' => (int) $row->commission,
            'net_payout' => (int) $row->gross_revenue - (int) $row->commission,
            'order_count' => (int) $row->order_count,
        ]);
    }

    /**
     * Get pending payouts by cook (cook wallet balances not yet withdrawn).
     * BR-145: Pending payouts = cook wallet balances
     *
     * @return Collection<int, array{cook_name: string, tenant_name: string, total_balance: int, withdrawable_balance: int, unwithdrawable_balance: int}>
     */
    public function getPendingPayoutsData(?int $cookId = null): Collection
    {
        $locale = app()->getLocale();
        $nameCol = $locale === 'fr' ? 'tenants.name_fr' : 'tenants.name_en';

        $query = DB::table('cook_wallets')
            ->join('tenants', 'cook_wallets.tenant_id', '=', 'tenants.id')
            ->leftJoin('users', 'cook_wallets.user_id', '=', 'users.id')
            ->selectRaw("
                cook_wallets.user_id,
                COALESCE(users.name, 'Unknown') as cook_name,
                COALESCE({$nameCol}, tenants.name_en) as tenant_name,
                tenants.id as tenant_id,
                cook_wallets.total_balance,
                cook_wallets.withdrawable_balance,
                cook_wallets.unwithdrawable_balance
            ")
            ->where('cook_wallets.total_balance', '>', 0)
            ->orderByDesc('cook_wallets.total_balance');

        if ($cookId) {
            $query->where('cook_wallets.user_id', $cookId);
        }

        return $query->get()->map(fn ($row) => [
            'cook_name' => $row->cook_name,
            'tenant_name' => $row->tenant_name,
            'tenant_id' => $row->tenant_id,
            'total_balance' => (int) $row->total_balance,
            'withdrawable_balance' => (int) $row->withdrawable_balance,
            'unwithdrawable_balance' => (int) $row->unwithdrawable_balance,
        ]);
    }

    /**
     * Get failed payment transactions.
     * BR-146: Failed payments = Flutterwave transactions with failed status
     *
     * @return Collection<int, array{order_number: string, client_name: string, amount: int, payment_method: string, failure_reason: string, created_at: string}>
     */
    public function getFailedPaymentsData(Carbon $start, Carbon $end, ?int $cookId = null): Collection
    {
        $query = DB::table('payment_transactions')
            ->leftJoin('orders', 'payment_transactions.order_id', '=', 'orders.id')
            ->leftJoin('users', 'payment_transactions.client_id', '=', 'users.id')
            ->selectRaw("
                COALESCE(orders.order_number, 'N/A') as order_number,
                orders.id as order_id,
                COALESCE(payment_transactions.customer_name, users.name, 'Unknown') as client_name,
                payment_transactions.amount,
                payment_transactions.payment_method,
                COALESCE(payment_transactions.response_message, '-') as failure_reason,
                payment_transactions.created_at
            ")
            ->where('payment_transactions.status', 'failed')
            ->whereBetween('payment_transactions.created_at', [$start, $end])
            ->orderByDesc('payment_transactions.created_at');

        if ($cookId) {
            $query->where('payment_transactions.cook_id', $cookId);
        }

        return $query->get()->map(fn ($row) => [
            'order_number' => $row->order_number,
            'order_id' => $row->order_id,
            'client_name' => $row->client_name,
            'amount' => (int) $row->amount,
            'payment_method' => $row->payment_method,
            'failure_reason' => $row->failure_reason,
            'created_at' => Carbon::parse($row->created_at)->format('M j, Y H:i'),
        ]);
    }

    /**
     * Get all cooks with tenant association (for filter dropdown).
     *
     * @return Collection<int, array{id: int, name: string, tenant_name: string}>
     */
    public function getCookOptions(): Collection
    {
        $locale = app()->getLocale();
        $nameCol = $locale === 'fr' ? 'tenants.name_fr' : 'tenants.name_en';

        return DB::table('tenants')
            ->join('users', 'tenants.cook_id', '=', 'users.id')
            ->selectRaw("users.id, users.name, COALESCE({$nameCol}, tenants.name_en) as tenant_name")
            ->whereNotNull('tenants.cook_id')
            ->where('tenants.is_active', true)
            ->orderBy('users.name')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'tenant_name' => $row->tenant_name,
            ]);
    }

    /**
     * Format an integer XAF amount as a string.
     * BR-149: All amounts in XAF
     */
    public static function formatXAF(int $amount): string
    {
        return number_format($amount, 0, '.', ',').' XAF';
    }
}
