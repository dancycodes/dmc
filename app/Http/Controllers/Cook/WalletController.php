<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Services\CookWalletService;
use Illuminate\Http\Request;

/**
 * F-169: Cook Wallet Dashboard
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
}
