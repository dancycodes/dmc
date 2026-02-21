<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\CookTransactionListRequest;
use App\Services\CookWalletService;
use Illuminate\Http\Request;

/**
 * F-169: Cook Wallet Dashboard
 * F-170: Cook Wallet Transaction History
 *
 * Displays the cook's wallet page showing balance, transactions,
 * earnings summary, and earnings chart.
 *
 * BR-311: Total balance split into withdrawable and unwithdrawable.
 * BR-314: Withdraw button active only when withdrawable > 0.
 * BR-315: Recent transactions shows last 10 entries.
 * BR-316: Earnings summary: total earned, total withdrawn, pending.
 * BR-317: Earnings chart shows monthly totals for the past 6 months.
 * BR-319: Only cook or users with manage-finances permission can access.
 * BR-320: Managers can view but not withdraw.
 * BR-321: Wallet data is tenant-scoped.
 * BR-322: All user-facing text uses __() localization.
 */
class WalletController extends Controller
{
    /**
     * Display the cook's wallet dashboard.
     */
    public function index(Request $request, CookWalletService $walletService): mixed
    {
        $tenant = tenant();
        $user = $request->user();

        // BR-319: Only cook or users with can-manage-cook-wallet permission
        if (! $user->can('can-manage-cook-wallet')) {
            abort(403);
        }

        $dashboardData = $walletService->getDashboardData($tenant, $user);

        return gale()->view('cook.wallet.index', $dashboardData, web: true);
    }

    /**
     * Display the cook's full wallet transaction history.
     *
     * F-170: Cook Wallet Transaction History
     * BR-323: Transaction types: order_payment_received, became_withdrawable, commission_deducted, withdrawal, auto_deduction.
     * BR-324: Default sort is by date descending (newest first).
     * BR-325: Paginated with 20 per page.
     * BR-326: Each transaction shows: date, description, amount, type, order reference.
     * BR-327: Filter by type allows: All, Order Payments, Commissions, Withdrawals, Auto-Deductions, Clearances.
     * BR-328: All amounts are in XAF format.
     * BR-329: Credit transactions (incoming) in green; debit transactions (outgoing) in red.
     * BR-330: Transaction data is tenant-scoped.
     * BR-331: Only users with manage-finances permission can access.
     * BR-332: All user-facing text must use __() localization.
     */
    public function transactions(CookTransactionListRequest $request, CookWalletService $walletService): mixed
    {
        $tenant = tenant();
        $cook = $tenant->cook;
        $filters = $request->validated();
        $typeFilter = $filters['type'] ?? '';

        $transactions = $walletService->getTransactionHistory($tenant, $cook, $filters);
        $summaryCounts = $walletService->getTransactionSummaryCounts($tenant, $cook);
        $typeOptions = CookWalletService::getTypeFilterOptions();

        $viewData = [
            'transactions' => $transactions,
            'summaryCounts' => $summaryCounts,
            'typeFilter' => $typeFilter,
            'typeOptions' => $typeOptions,
            'direction' => $filters['direction'] ?? 'desc',
        ];

        // Fragment-based partial update for Gale navigate
        if ($request->isGaleNavigate('cook-transactions')) {
            return gale()
                ->fragment('cook.wallet.transactions', 'transactions-content', $viewData);
        }

        return gale()->view('cook.wallet.transactions', $viewData, web: true);
    }
}
