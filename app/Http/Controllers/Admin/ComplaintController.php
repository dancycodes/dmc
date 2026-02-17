<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ComplaintListRequest;
use App\Http\Requests\Admin\ResolveComplaintRequest;
use App\Models\Complaint;
use App\Models\PaymentTransaction;
use App\Services\ComplaintResolutionService;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    /**
     * Display the complaint escalation queue.
     *
     * F-060: Complaint Escalation Queue
     * BR-160: Default sort: oldest unresolved complaints first (priority queue)
     * BR-163: Only shows complaints that have reached admin level
     * BR-164: Resolved and dismissed complaints remain visible but sorted below unresolved
     */
    public function index(ComplaintListRequest $request): mixed
    {
        $search = $request->input('search', '');
        $category = $request->input('category', '');
        $status = $request->input('status', '');
        $sortBy = $request->input('sort', '');
        $sortDir = $request->input('direction', 'asc');

        // Validate sort column
        $allowedSorts = ['id', 'submitted_at', 'escalated_at', 'category', 'status'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = '';
        }
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'asc';

        // BR-163: Only escalated complaints
        $query = Complaint::query()
            ->with(['client', 'cook', 'tenant'])
            ->escalated()
            ->search($search)
            ->ofCategory($category)
            ->ofStatus($status);

        // BR-160: Default sort is priority (oldest unresolved first)
        // If user chooses a specific sort, use that instead
        if ($sortBy !== '') {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->prioritySort();
        }

        $complaints = $query->paginate(20)->withQueryString();

        // Summary counts for dashboard cards
        $totalEscalated = Complaint::escalated()->count();
        $pendingCount = Complaint::escalated()->where('status', 'pending_resolution')->count();
        $resolvedThisWeek = Complaint::escalated()
            ->where('status', 'resolved')
            ->where('resolved_at', '>=', now()->startOfWeek())
            ->count();

        $data = [
            'complaints' => $complaints,
            'search' => $search,
            'category' => $category,
            'status' => $status,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'totalEscalated' => $totalEscalated,
            'pendingCount' => $pendingCount,
            'resolvedThisWeek' => $resolvedThisWeek,
        ];

        // Handle Gale navigate requests (search/filter/sort triggers)
        if ($request->isGaleNavigate('complaint-list')) {
            return gale()->fragment('admin.complaints.index', 'complaint-list-content', $data);
        }

        return gale()->view('admin.complaints.index', $data, web: true);
    }

    /**
     * Display a single complaint for resolution.
     *
     * F-061: Admin Complaint Resolution
     * Shows full complaint details, order/payment info, cook history, and resolution form.
     */
    public function show(Complaint $complaint, ComplaintResolutionService $resolutionService): mixed
    {
        $complaint->load(['client', 'cook', 'tenant', 'resolvedByUser']);

        // Get payment transaction for this order
        $paymentTransaction = null;
        $orderAmount = null;
        if ($complaint->order_id) {
            $paymentTransaction = PaymentTransaction::query()
                ->where('order_id', $complaint->order_id)
                ->where('status', 'successful')
                ->first();
            $orderAmount = $paymentTransaction ? (float) $paymentTransaction->amount : null;
        }

        // Cook complaint history
        $cook = $complaint->cook;
        $cookComplaintCount = $cook ? $resolutionService->getCookComplaintCount($cook) : 0;
        $cookWarningCount = $cook ? $resolutionService->getCookWarningCount($cook) : 0;
        $previousSuspensions = $cook ? $resolutionService->getCookPreviousSuspensions($cook) : [];

        // Check if order was already refunded
        $orderAlreadyRefunded = $resolutionService->isOrderAlreadyRefunded($complaint);

        $data = [
            'complaint' => $complaint,
            'paymentTransaction' => $paymentTransaction,
            'orderAmount' => $orderAmount,
            'cookComplaintCount' => $cookComplaintCount,
            'cookWarningCount' => $cookWarningCount,
            'previousSuspensions' => $previousSuspensions,
            'orderAlreadyRefunded' => $orderAlreadyRefunded,
        ];

        return gale()->view('admin.complaints.show', $data, web: true);
    }

    /**
     * Resolve a complaint with the selected action.
     *
     * F-061: Admin Complaint Resolution
     * BR-165 through BR-174: Resolution business rules
     */
    public function resolve(Request $request, Complaint $complaint, ComplaintResolutionService $resolutionService): mixed
    {
        // BR-174: Cannot re-resolve
        if ($complaint->isResolved()) {
            if ($request->isGale()) {
                return gale()->state('error', __('This complaint has already been resolved.'));
            }

            return gale()->redirect('/vault-entry/complaints/'.$complaint->id)
                ->back()
                ->with('toast', [
                    'type' => 'error',
                    'message' => __('This complaint has already been resolved.'),
                ]);
        }

        // Dual Gale/HTTP validation
        if ($request->isGale()) {
            $validated = $request->validateState([
                'resolution_type' => ['required', 'string', \Illuminate\Validation\Rule::in(Complaint::RESOLUTION_TYPES)],
                'resolution_notes' => ['required', 'string', 'min:10', 'max:2000'],
                'refund_amount' => ['required_if:resolution_type,partial_refund', 'nullable', 'numeric', 'min:1'],
                'suspension_days' => ['required_if:resolution_type,suspend', 'nullable', 'integer', 'min:1', 'max:365'],
            ]);
        } else {
            $validated = app(ResolveComplaintRequest::class)->validated();
        }

        // Additional validation: refund_amount <= order total (BR-167)
        if ($validated['resolution_type'] === 'partial_refund' && isset($validated['refund_amount'])) {
            $complaint->load('tenant');
            $orderAmount = $this->getOrderAmount($complaint);
            if ($orderAmount !== null && (float) $validated['refund_amount'] > $orderAmount) {
                if ($request->isGale()) {
                    return gale()->messages([
                        'refund_amount' => __('Refund cannot exceed order amount of :amount XAF.', ['amount' => number_format($orderAmount)]),
                    ]);
                }

                return gale()->redirect('/vault-entry/complaints/'.$complaint->id)
                    ->back()
                    ->withErrors(['refund_amount' => __('Refund cannot exceed order amount.')])
                    ->withInput();
            }
        }

        $resolutionService->resolve($complaint, $validated, $request->user());

        if ($request->isGale()) {
            return gale()
                ->state('resolved', true)
                ->state('error', '')
                ->redirect('/vault-entry/complaints/'.$complaint->id)
                ->with('toast', [
                    'type' => 'success',
                    'message' => __('Complaint resolved successfully.'),
                ]);
        }

        return gale()->redirect('/vault-entry/complaints/'.$complaint->id)
            ->back()
            ->with('toast', [
                'type' => 'success',
                'message' => __('Complaint resolved successfully.'),
            ]);
    }

    /**
     * Get the order amount from associated payment transactions.
     */
    private function getOrderAmount(Complaint $complaint): ?float
    {
        if (! $complaint->order_id) {
            return null;
        }

        $transaction = PaymentTransaction::query()
            ->where('order_id', $complaint->order_id)
            ->where('status', 'successful')
            ->first();

        return $transaction ? (float) $transaction->amount : null;
    }
}
