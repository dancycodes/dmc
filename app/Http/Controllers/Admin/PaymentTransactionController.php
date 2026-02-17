<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;

class PaymentTransactionController extends Controller
{
    /**
     * Display the payment monitoring list.
     *
     * F-059: Payment Monitoring View
     * BR-155: Search covers order ID, client name, client email, Flutterwave reference
     * BR-156: Default sort: date descending (most recent first)
     * BR-157: Pagination: 20 items per page
     */
    public function index(Request $request): mixed
    {
        $search = $request->input('search', '');
        $status = $request->input('status', '');
        $sortBy = $request->input('sort', 'created_at');
        $sortDir = $request->input('direction', 'desc');

        // Validate sort column
        $allowedSorts = ['amount', 'payment_method', 'status', 'created_at', 'order_id'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'desc';

        // BR-157: 20 items per page, eager load relationships
        $transactions = PaymentTransaction::query()
            ->with(['client', 'cook', 'tenant'])
            ->search($search)
            ->status($status)
            ->orderBy($sortBy, $sortDir)
            ->paginate(20)
            ->withQueryString();

        // Summary counts for dashboard cards
        $totalCount = PaymentTransaction::count();
        $successfulAmount = PaymentTransaction::where('status', 'successful')->sum('amount');
        $failedCount = PaymentTransaction::where('status', 'failed')->count();
        $pendingCount = PaymentTransaction::where('status', 'pending')->count();

        $data = [
            'transactions' => $transactions,
            'search' => $search,
            'status' => $status,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'totalCount' => $totalCount,
            'successfulAmount' => $successfulAmount,
            'failedCount' => $failedCount,
            'pendingCount' => $pendingCount,
        ];

        // Handle Gale navigate requests (search/filter/sort triggers)
        if ($request->isGaleNavigate('payment-list')) {
            return gale()->fragment('admin.payments.index', 'payment-list-content', $data);
        }

        return gale()->view('admin.payments.index', $data, web: true);
    }

    /**
     * Display a single payment transaction detail.
     *
     * F-059: Scenario 4 — Transaction detail view
     * BR-154: Transaction detail shows raw Flutterwave response data for debugging
     */
    public function show(Request $request, PaymentTransaction $transaction): mixed
    {
        $transaction->load(['client', 'cook', 'tenant']);

        $data = [
            'transaction' => $transaction,
        ];

        return gale()->view('admin.payments.show', $data, web: true);
    }
}
