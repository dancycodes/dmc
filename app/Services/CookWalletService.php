<?php

namespace App\Services;

use App\Models\CookWallet;
use App\Models\Order;
use App\Models\OrderClearance;
use App\Models\PendingDeduction;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * F-169: Cook Wallet Dashboard
 *
 * Service layer for cook wallet operations.
 * BR-311: Total balance split into withdrawable and unwithdrawable.
 * BR-315: Recent transactions shows last 10 entries.
 * BR-316: Earnings summary: total earned, total withdrawn, pending.
 * BR-317: Earnings chart shows monthly totals for the past 6 months.
 * BR-321: Wallet data is tenant-scoped.
 */
class CookWalletService
{
    /**
     * BR-315: Number of recent transactions to display.
     */
    public const RECENT_TRANSACTION_LIMIT = 10;

    /**
     * BR-317: Number of months for earnings chart.
     */
    public const CHART_MONTHS = 6;

    /**
     * BR-325: Number of transactions per page in the history view.
     */
    public const TRANSACTION_HISTORY_PER_PAGE = 20;

    /**
     * Get or create the cook's wallet (lazy creation).
     *
     * Edge case: New cook with no wallet -- created with 0 balance on first visit.
     */
    public function getWallet(Tenant $tenant, User $cook): CookWallet
    {
        return CookWallet::getOrCreateForTenant($tenant, $cook);
    }

    /**
     * Recalculate and update wallet balances from transactions.
     *
     * BR-311: Total balance = withdrawable + unwithdrawable.
     * BR-312: Withdrawable = cleared funds (is_withdrawable=true OR withdrawable_at <= now).
     * BR-313: Unwithdrawable = funds still within hold period or blocked.
     */
    public function recalculateBalances(CookWallet $wallet): CookWallet
    {
        $tenantId = $wallet->tenant_id;
        $userId = $wallet->user_id;
        $now = now();

        // Calculate total credits (payment_credit, refund)
        $totalCredits = (float) WalletTransaction::query()
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->whereIn('type', [WalletTransaction::TYPE_PAYMENT_CREDIT, WalletTransaction::TYPE_REFUND])
            ->where('status', 'completed')
            ->sum('amount');

        // Calculate total debits (withdrawal, refund_deduction, commission, order_cancelled)
        $totalDebits = (float) WalletTransaction::query()
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->whereIn('type', [
                WalletTransaction::TYPE_WITHDRAWAL,
                WalletTransaction::TYPE_REFUND_DEDUCTION,
                WalletTransaction::TYPE_ORDER_CANCELLED,
            ])
            ->where('status', 'completed')
            ->sum('amount');

        $totalBalance = round($totalCredits - $totalDebits, 2);

        // Withdrawable: credits that have cleared (is_withdrawable=true OR withdrawable_at <= now)
        $withdrawableCredits = (float) WalletTransaction::query()
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->whereIn('type', [WalletTransaction::TYPE_PAYMENT_CREDIT, WalletTransaction::TYPE_REFUND])
            ->where('status', 'completed')
            ->where(function ($q) use ($now) {
                $q->where('is_withdrawable', true)
                    ->orWhere(function ($q2) use ($now) {
                        $q2->whereNotNull('withdrawable_at')
                            ->where('withdrawable_at', '<=', $now);
                    });
            })
            ->sum('amount');

        $withdrawableBalance = round(max(0, (float) $withdrawableCredits - $totalDebits), 2);
        $unwithdrawableBalance = round(max(0, $totalBalance - $withdrawableBalance), 2);

        $wallet->update([
            'total_balance' => max(0, $totalBalance),
            'withdrawable_balance' => $withdrawableBalance,
            'unwithdrawable_balance' => $unwithdrawableBalance,
        ]);

        return $wallet->fresh();
    }

    /**
     * Decrement the cook's unwithdrawable balance for a cancelled order.
     *
     * F-163 BR-250: Cook's unwithdrawable wallet amount for the cancelled order is decremented.
     * BR-253: A cook wallet transaction record is created (type: order_cancelled, debit).
     *
     * Creates a transaction record and recalculates balances atomically.
     * Does NOT throw on underflow — logs a warning and continues (edge case: BR from directive).
     *
     * @return array{wallet: CookWallet, transaction: WalletTransaction}
     */
    public function decrementUnwithdrawableForCancellation(
        CookWallet $wallet,
        Order $order,
        float $amount
    ): array {
        $wallet = CookWallet::query()
            ->where('id', $wallet->id)
            ->lockForUpdate()
            ->first();

        $currentUnwithdrawable = (float) $wallet->unwithdrawable_balance;

        // Edge case (directive): if unwithdrawable < amount, log warning but continue
        if ($currentUnwithdrawable < $amount) {
            \Illuminate\Support\Facades\Log::warning('F-163: Cook unwithdrawable_balance is less than order total during cancellation refund', [
                'cook_wallet_id' => $wallet->id,
                'order_id' => $order->id,
                'order_total' => $amount,
                'current_unwithdrawable' => $currentUnwithdrawable,
            ]);
        }

        // BR-253: Create cook wallet transaction (type: order_cancelled, debit from unwithdrawable)
        $transaction = WalletTransaction::create([
            'user_id' => $wallet->user_id,
            'tenant_id' => $wallet->tenant_id,
            'order_id' => $order->id,
            'payment_transaction_id' => null,
            'type' => WalletTransaction::TYPE_ORDER_CANCELLED,
            'amount' => $amount,
            'currency' => 'XAF',
            'balance_before' => $currentUnwithdrawable,
            'balance_after' => max(0, $currentUnwithdrawable - $amount),
            'is_withdrawable' => false,
            'withdrawable_at' => null,
            'status' => 'completed',
            'description' => __('Order :number cancelled — refund deducted from unwithdrawable', [
                'number' => $order->order_number,
            ]),
            'metadata' => [
                'source' => 'cancellation_refund',
                'order_number' => $order->order_number,
            ],
        ]);

        // Recalculate all balances to reflect the new debit transaction
        $wallet = $this->recalculateBalances($wallet);

        return [
            'wallet' => $wallet,
            'transaction' => $transaction,
        ];
    }

    /**
     * Get the last 10 wallet transactions for the cook, scoped to tenant.
     *
     * BR-315: Recent transactions show the last 10 entries.
     * Includes: order payments, commission deductions, withdrawals, auto-deductions.
     *
     * @return Collection<int, WalletTransaction>
     */
    public function getRecentTransactions(Tenant $tenant, User $cook): Collection
    {
        return WalletTransaction::query()
            ->where('user_id', $cook->id)
            ->where('tenant_id', $tenant->id)
            ->with(['order:id,order_number'])
            ->orderByDesc('created_at')
            ->limit(self::RECENT_TRANSACTION_LIMIT)
            ->get();
    }

    /**
     * Get the earnings summary data.
     *
     * BR-316: Total earned, total withdrawn, pending (unwithdrawable).
     *
     * @return array{total_earned: float, total_withdrawn: float, pending: float}
     */
    public function getEarningsSummary(Tenant $tenant, User $cook): array
    {
        $totalEarned = (float) WalletTransaction::query()
            ->where('user_id', $cook->id)
            ->where('tenant_id', $tenant->id)
            ->where('type', WalletTransaction::TYPE_PAYMENT_CREDIT)
            ->where('status', 'completed')
            ->sum('amount');

        $totalWithdrawn = (float) WalletTransaction::query()
            ->where('user_id', $cook->id)
            ->where('tenant_id', $tenant->id)
            ->where('type', WalletTransaction::TYPE_WITHDRAWAL)
            ->where('status', 'completed')
            ->sum('amount');

        $wallet = $this->getWallet($tenant, $cook);

        return [
            'total_earned' => round($totalEarned, 2),
            'total_withdrawn' => round($totalWithdrawn, 2),
            'pending' => (float) $wallet->unwithdrawable_balance,
        ];
    }

    /**
     * Get monthly earnings data for the past 6 months for the chart.
     *
     * BR-317: Earnings chart shows monthly totals for the past 6 months.
     *
     * @return array<int, array{month: string, label: string, amount: float}>
     */
    public function getMonthlyEarnings(Tenant $tenant, User $cook): array
    {
        $months = [];
        $now = Carbon::now('Africa/Douala');

        for ($i = self::CHART_MONTHS - 1; $i >= 0; $i--) {
            $date = $now->copy()->subMonths($i);
            $months[] = [
                'month' => $date->format('Y-m'),
                'label' => $date->format('M Y'),
                'short_label' => $date->format('M'),
                'amount' => 0.0,
            ];
        }

        // Query monthly earnings
        $sixMonthsAgo = $now->copy()->subMonths(self::CHART_MONTHS)->startOfMonth();

        $earnings = WalletTransaction::query()
            ->where('user_id', $cook->id)
            ->where('tenant_id', $tenant->id)
            ->where('type', WalletTransaction::TYPE_PAYMENT_CREDIT)
            ->where('status', 'completed')
            ->where('created_at', '>=', $sixMonthsAgo)
            ->selectRaw("to_char(created_at, 'YYYY-MM') as month, SUM(amount) as total")
            ->groupByRaw("to_char(created_at, 'YYYY-MM')")
            ->pluck('total', 'month');

        foreach ($months as &$month) {
            if ($earnings->has($month['month'])) {
                $month['amount'] = round((float) $earnings->get($month['month']), 2);
            }
        }

        return $months;
    }

    /**
     * Get the full wallet dashboard data.
     *
     * @return array{
     *     wallet: CookWallet,
     *     recentTransactions: Collection,
     *     earningsSummary: array{total_earned: float, total_withdrawn: float, pending: float},
     *     monthlyEarnings: array<int, array{month: string, label: string, amount: float}>,
     *     totalTransactionCount: int,
     *     isCook: bool,
     *     pendingDeductions: Collection,
     *     totalPendingDeduction: float
     * }
     */
    public function getDashboardData(Tenant $tenant, User $user): array
    {
        $cook = $tenant->cook;

        $wallet = $this->getWallet($tenant, $cook);
        $this->recalculateBalances($wallet);
        $wallet->refresh();

        $recentTransactions = $this->getRecentTransactions($tenant, $cook);
        $earningsSummary = $this->getEarningsSummary($tenant, $cook);
        $monthlyEarnings = $this->getMonthlyEarnings($tenant, $cook);

        $totalTransactionCount = WalletTransaction::query()
            ->where('user_id', $cook->id)
            ->where('tenant_id', $tenant->id)
            ->count();

        // BR-320: Managers can view but not withdraw
        $isCook = $user->id === $cook->id;

        // F-174: Pending deductions data
        $pendingDeductions = $this->getPendingDeductionsData($tenant, $cook);
        $totalPendingDeduction = $this->getTotalPendingDeduction($tenant, $cook);

        // F-186: Blocked payments data
        $blockedPayments = $this->getBlockedPaymentsData($tenant);
        $totalBlockedAmount = $this->getTotalBlockedAmount($tenant);

        return [
            'wallet' => $wallet,
            'recentTransactions' => $recentTransactions,
            'earningsSummary' => $earningsSummary,
            'monthlyEarnings' => $monthlyEarnings,
            'totalTransactionCount' => $totalTransactionCount,
            'isCook' => $isCook,
            'pendingDeductions' => $pendingDeductions,
            'totalPendingDeduction' => $totalPendingDeduction,
            'blockedPayments' => $blockedPayments,
            'totalBlockedAmount' => $totalBlockedAmount,
        ];
    }

    /**
     * F-174: Get pending deductions for the cook wallet dashboard.
     *
     * BR-372: Wallet shows total pending deduction amount.
     * BR-375: Transparent settlement history.
     *
     * @return Collection<int, PendingDeduction>
     */
    public function getPendingDeductionsData(Tenant $tenant, User $cook): Collection
    {
        return PendingDeduction::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $cook->id)
            ->whereNull('settled_at')
            ->where('remaining_amount', '>', 0)
            ->orderBy('created_at', 'asc')
            ->with(['order:id,order_number'])
            ->get();
    }

    /**
     * F-174: Get total pending deduction amount.
     *
     * BR-372: Wallet shows total pending deduction amount.
     */
    public function getTotalPendingDeduction(Tenant $tenant, User $cook): float
    {
        return (float) PendingDeduction::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $cook->id)
            ->whereNull('settled_at')
            ->where('remaining_amount', '>', 0)
            ->sum('remaining_amount');
    }

    /**
     * F-186: Get blocked/flagged payments for the cook wallet dashboard.
     *
     * BR-226: Payment block status visible on wallet view.
     *
     * @return Collection<int, OrderClearance>
     */
    public function getBlockedPaymentsData(Tenant $tenant): Collection
    {
        return OrderClearance::query()
            ->where('tenant_id', $tenant->id)
            ->withActiveComplaintBlock()
            ->with(['order:id,order_number', 'complaint:id,status,category,submitted_at'])
            ->orderBy('blocked_at', 'desc')
            ->get();
    }

    /**
     * F-186: Get the total blocked amount for the tenant.
     *
     * BR-225: Cook cannot withdraw blocked/flagged amounts.
     */
    public function getTotalBlockedAmount(Tenant $tenant): float
    {
        return (float) OrderClearance::query()
            ->where('tenant_id', $tenant->id)
            ->withActiveComplaintBlock()
            ->sum('amount');
    }

    /**
     * F-170: Get paginated, filtered transaction history for the cook.
     *
     * BR-324: Default sort by date descending (newest first).
     * BR-325: Paginated with 20 per page.
     * BR-327: Filter by type.
     * BR-330: All data is tenant-scoped.
     *
     * @param  array{type?: string, direction?: string}  $filters
     */
    public function getTransactionHistory(Tenant $tenant, User $cook, array $filters): LengthAwarePaginator
    {
        $type = $filters['type'] ?? '';
        $direction = $filters['direction'] ?? 'desc';

        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $query = WalletTransaction::query()
            ->where('user_id', $cook->id)
            ->where('tenant_id', $tenant->id)
            ->with(['order:id,order_number'])
            ->orderBy('created_at', $direction);

        // BR-327: Apply type filter if specified
        if ($type && in_array($type, WalletTransaction::TYPES, true)) {
            $query->where('type', $type);
        }

        return $query->paginate(self::TRANSACTION_HISTORY_PER_PAGE)
            ->withQueryString();
    }

    /**
     * F-170: Get summary counts for the transaction history page.
     *
     * Returns counts per transaction type for the summary cards.
     * BR-330: All data is tenant-scoped.
     *
     * @return array{total: int, order_payments: int, commissions: int, withdrawals: int, auto_deductions: int, clearances: int, became_withdrawable: int}
     */
    public function getTransactionSummaryCounts(Tenant $tenant, User $cook): array
    {
        $baseQuery = WalletTransaction::query()
            ->where('user_id', $cook->id)
            ->where('tenant_id', $tenant->id);

        return [
            'total' => (clone $baseQuery)->count(),
            'order_payments' => (clone $baseQuery)->where('type', WalletTransaction::TYPE_PAYMENT_CREDIT)->count(),
            'commissions' => (clone $baseQuery)->where('type', WalletTransaction::TYPE_COMMISSION)->count(),
            'withdrawals' => (clone $baseQuery)->where('type', WalletTransaction::TYPE_WITHDRAWAL)->count(),
            'auto_deductions' => (clone $baseQuery)->where('type', WalletTransaction::TYPE_REFUND_DEDUCTION)->count(),
            'clearances' => (clone $baseQuery)->where('type', WalletTransaction::TYPE_REFUND)->count(),
            'became_withdrawable' => (clone $baseQuery)->where('type', WalletTransaction::TYPE_BECAME_WITHDRAWABLE)->count(),
        ];
    }

    /**
     * F-170: Get type filter options for the transaction history page.
     *
     * BR-327: Filter by type allows: All, Order Payments, Commissions, Withdrawals, Auto-Deductions, Clearances.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function getTypeFilterOptions(): array
    {
        return [
            ['value' => WalletTransaction::TYPE_PAYMENT_CREDIT, 'label' => __('Order Payments')],
            ['value' => WalletTransaction::TYPE_COMMISSION, 'label' => __('Commissions')],
            ['value' => WalletTransaction::TYPE_WITHDRAWAL, 'label' => __('Withdrawals')],
            ['value' => WalletTransaction::TYPE_REFUND_DEDUCTION, 'label' => __('Auto-Deductions')],
            ['value' => WalletTransaction::TYPE_REFUND, 'label' => __('Clearances')],
            ['value' => WalletTransaction::TYPE_BECAME_WITHDRAWABLE, 'label' => __('Became Withdrawable')],
        ];
    }

    /**
     * F-170: Get the transaction type label for display.
     *
     * BR-326: Each transaction shows type.
     */
    public static function getTransactionTypeLabel(string $type): string
    {
        return match ($type) {
            WalletTransaction::TYPE_PAYMENT_CREDIT => __('Order Payment'),
            WalletTransaction::TYPE_COMMISSION => __('Commission'),
            WalletTransaction::TYPE_WITHDRAWAL => __('Withdrawal'),
            WalletTransaction::TYPE_REFUND_DEDUCTION => __('Auto-Deduction'),
            WalletTransaction::TYPE_REFUND => __('Clearance'),
            WalletTransaction::TYPE_WALLET_PAYMENT => __('Wallet Payment'),
            WalletTransaction::TYPE_BECAME_WITHDRAWABLE => __('Became Withdrawable'),
            default => __('Transaction'),
        };
    }

    /**
     * Format an amount in XAF.
     *
     * BR-318: All amounts are in XAF format.
     */
    public static function formatXAF(float $amount): string
    {
        return number_format($amount, 0, '.', ',').' XAF';
    }
}
