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
}
