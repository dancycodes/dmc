<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ClientTransactionListRequest;
use App\Services\ClientTransactionService;

class TransactionController extends Controller
{
    /**
     * Display the client's transaction history.
     *
     * F-164: Client Transaction History
     * BR-260: Shows all client transactions across all tenants.
     * BR-261: Transaction types: payment (debit), refund (credit), wallet_payment (debit).
     * BR-262: Default sort by date descending (newest first).
     * BR-263: Paginated with 20 per page.
     * BR-264: Each transaction shows: date, description, amount, type, status, reference.
     * BR-267: Filter by type: All, Payments, Refunds, Wallet Payments.
     * BR-268: Clicking a transaction navigates to the transaction detail view (F-165).
     * BR-269: Authentication required.
     * BR-270: All user-facing text uses __() localization.
     */
    public function index(ClientTransactionListRequest $request, ClientTransactionService $transactionService): mixed
    {
        $user = $request->user();
        $filters = $request->validated();
        $typeFilter = $filters['type'] ?? '';

        $transactions = $transactionService->getTransactions($user, $filters);
        $summaryCounts = $transactionService->getSummaryCounts($user);
        $typeOptions = ClientTransactionService::getTypeFilterOptions();

        $viewData = [
            'transactions' => $transactions,
            'summaryCounts' => $summaryCounts,
            'typeFilter' => $typeFilter,
            'typeOptions' => $typeOptions,
            'direction' => $filters['direction'] ?? 'desc',
        ];

        // Fragment-based partial update for Gale navigate
        if ($request->isGaleNavigate('transactions')) {
            return gale()
                ->fragment('client.transactions.index', 'transactions-content', $viewData);
        }

        return gale()->view('client.transactions.index', $viewData, web: true);
    }

    /**
     * Display the detail of a single transaction.
     *
     * F-165: Transaction Detail View
     * BR-271: Client can only view their own transaction details.
     * BR-272: All transaction types display: amount, type, date/time, status.
     * BR-273: Payment transactions additionally show: payment method, Flutterwave reference.
     * BR-274: Refund transactions additionally show: original order reference, refund reason.
     * BR-275: Wallet payment transactions show: wallet as the payment method.
     * BR-276: Order reference is a clickable link to the order detail page (F-161).
     * BR-277: Failed transactions show the failure reason.
     * BR-278: All amounts displayed in XAF format.
     * BR-279: All user-facing text uses __() localization.
     */
    public function show(string $sourceType, int $sourceId, ClientTransactionService $transactionService): mixed
    {
        $user = request()->user();

        // Validate source_type
        if (! in_array($sourceType, ['payment_transaction', 'wallet_transaction'], true)) {
            abort(404);
        }

        // BR-271: Ownership check is inside the service method
        $transaction = $transactionService->getTransactionDetail($user, $sourceType, $sourceId);

        if (! $transaction) {
            abort(403);
        }

        return gale()->view('client.transactions.show', [
            'transaction' => $transaction,
        ], web: true);
    }
}
