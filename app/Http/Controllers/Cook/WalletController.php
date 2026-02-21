<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\CookTransactionListRequest;
use App\Http\Requests\Cook\StoreWithdrawalRequest;
use App\Services\CookWalletService;
use App\Services\WithdrawalRequestService;
use Illuminate\Http\Request;

/**
 * F-169: Cook Wallet Dashboard
 * F-170: Cook Wallet Transaction History
 * F-172: Cook Withdrawal Request
 *
 * Displays the cook's wallet page showing balance, transactions,
 * earnings summary, and earnings chart. Handles withdrawal requests.
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
 * BR-353: Only the cook can initiate withdrawals (not managers).
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

    /**
     * Display the withdrawal request form.
     *
     * F-172: Cook Withdrawal Request
     * BR-353: Only the cook can initiate withdrawals (not managers).
     */
    public function showWithdraw(Request $request, WithdrawalRequestService $withdrawalService): mixed
    {
        $tenant = tenant();
        $user = $request->user();

        // BR-353: Only the cook (not managers) can withdraw
        if ($user->id !== $tenant->cook_id) {
            abort(403);
        }

        $formData = $withdrawalService->getWithdrawFormData($tenant, $user);

        return gale()->view('cook.wallet.withdraw', $formData, web: true);
    }

    /**
     * Process the withdrawal request submission.
     *
     * F-172: Cook Withdrawal Request
     * BR-348: Cook must confirm mobile money number.
     * BR-350: Confirmation dialog shown before final submission.
     * BR-352: Withdrawable balance decremented immediately.
     */
    public function submitWithdraw(Request $request, WithdrawalRequestService $withdrawalService): mixed
    {
        $tenant = tenant();
        $user = $request->user();

        // BR-353: Only the cook (not managers) can withdraw
        if ($user->id !== $tenant->cook_id) {
            abort(403);
        }

        // Dual Gale/HTTP validation
        if ($request->isGale()) {
            $validated = $request->validateState([
                'amount' => ['required', 'integer', 'min:1'],
                'mobile_money_number' => ['required', 'string', 'regex:'.StoreWithdrawalRequest::CAMEROON_PHONE_REGEX],
                'mobile_money_provider' => ['required', 'string', 'in:mtn_momo,orange_money'],
            ]);
        } else {
            $formRequest = app(StoreWithdrawalRequest::class);
            $validated = $formRequest->validated();
        }

        // Normalize the phone number
        $validated['mobile_money_number'] = $withdrawalService->normalizePhone($validated['mobile_money_number']);

        $result = $withdrawalService->submitWithdrawal($tenant, $user, $validated);

        if (! $result['success']) {
            if ($request->isGale()) {
                return gale()->messages(['amount' => $result['message']]);
            }

            return back()->withErrors(['amount' => $result['message']])->withInput();
        }

        // Success
        if ($request->isGale()) {
            return gale()
                ->redirect('/dashboard/wallet')
                ->with('toast', $result['message']);
        }

        return redirect()->route('cook.wallet.index')
            ->with('toast', $result['message']);
    }
}
