<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MarkPayoutCompleteRequest;
use App\Models\PayoutTask;
use App\Services\PayoutService;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    /**
     * Display the payout task queue.
     *
     * F-065: Manual Payout Task Queue
     * BR-199: Only failed Flutterwave transfers appear in this queue
     * BR-205: Completed/resolved tasks in "Completed" tab
     * BR-206: Badge count on sidebar for pending tasks
     */
    public function index(Request $request, PayoutService $payoutService): mixed
    {
        $search = $request->input('search', '');
        $tab = $request->input('tab', 'active');

        // Build query based on active tab
        $query = PayoutTask::query()
            ->with(['cook', 'tenant', 'completedByUser']);

        if ($tab === 'completed') {
            $query->resolved()->orderByDesc('completed_at');
        } else {
            // Active tab: oldest first (priority queue)
            $query->pending()->orderBy('requested_at', 'asc');
        }

        // Apply search filter
        $query->search($search);

        $tasks = $query->paginate(20)->withQueryString();

        // BR-206: Pending count for sidebar badge
        $pendingCount = $payoutService->getPendingCount();
        $completedCount = PayoutTask::resolved()->count();

        $data = [
            'tasks' => $tasks,
            'search' => $search,
            'tab' => $tab,
            'pendingCount' => $pendingCount,
            'completedCount' => $completedCount,
        ];

        // Handle Gale navigate requests (search/tab/pagination triggers)
        if ($request->isGaleNavigate('payout-list')) {
            return gale()->fragment('admin.payouts.index', 'payout-list-content', $data);
        }

        return gale()->view('admin.payouts.index', $data, web: true);
    }

    /**
     * Display a single payout task detail.
     *
     * Scenario 4: Investigating failure details
     */
    public function show(Request $request, PayoutTask $task): mixed
    {
        $task->load(['cook', 'tenant', 'completedByUser']);

        // Get cook's withdrawal history (other payout tasks for same cook)
        $cookPayoutHistory = PayoutTask::query()
            ->where('cook_id', $task->cook_id)
            ->where('id', '!=', $task->id)
            ->orderByDesc('requested_at')
            ->limit(10)
            ->get();

        $data = [
            'task' => $task,
            'cookPayoutHistory' => $cookPayoutHistory,
        ];

        return gale()->view('admin.payouts.show', $data, web: true);
    }

    /**
     * Retry a failed Flutterwave transfer.
     *
     * Scenario 3: Retrying automatic transfer
     * BR-201: Retry initiates a new Flutterwave transfer with the same parameters
     * BR-202: Maximum 3 automatic retry attempts
     * BR-204: All queue actions are logged
     */
    public function retry(Request $request, PayoutTask $task, PayoutService $payoutService): mixed
    {
        // Cannot retry resolved tasks
        if ($task->isResolved()) {
            return $this->errorResponse($request, $task, __('This payout task has already been resolved.'));
        }

        // BR-202: Check retry limit
        if (! $task->canRetry()) {
            return $this->errorResponse($request, $task, __('Maximum retry attempts reached. Please use manual completion.'));
        }

        $result = $payoutService->retryTransfer($task, $request->user());

        if ($result['success']) {
            return $this->successResponse($request, $task, $result['message']);
        }

        return $this->errorResponse($request, $task, $result['message']);
    }

    /**
     * Mark a payout task as manually completed.
     *
     * Scenario 2: Marking as manually completed
     * BR-200: Requires a reference number as proof
     * BR-204: Action is logged
     * BR-205: Task moves to Completed tab
     */
    public function markComplete(Request $request, PayoutTask $task, PayoutService $payoutService): mixed
    {
        // Cannot complete already resolved tasks
        if ($task->isResolved()) {
            return $this->errorResponse($request, $task, __('This payout task has already been resolved.'));
        }

        // Dual Gale/HTTP validation
        if ($request->isGale()) {
            $validated = $request->validateState([
                'reference_number' => ['required', 'string', 'min:3', 'max:255'],
                'resolution_notes' => ['nullable', 'string', 'max:2000'],
            ]);
        } else {
            $validated = app(MarkPayoutCompleteRequest::class)->validated();
        }

        $payoutService->markAsManuallyCompleted($task, $validated, $request->user());

        return $this->successResponse($request, $task, __('Payout task marked as manually completed.'));
    }

    /**
     * Return a success response for Gale or HTTP requests.
     */
    private function successResponse(Request $request, PayoutTask $task, string $message): mixed
    {
        if ($request->isGale()) {
            return gale()
                ->redirect('/vault-entry/payouts')
                ->with('toast', [
                    'type' => 'success',
                    'message' => $message,
                ]);
        }

        return gale()->redirect('/vault-entry/payouts')
            ->with('toast', [
                'type' => 'success',
                'message' => $message,
            ]);
    }

    /**
     * Return an error response for Gale or HTTP requests.
     */
    private function errorResponse(Request $request, PayoutTask $task, string $message): mixed
    {
        if ($request->isGale()) {
            return gale()->state('error', $message);
        }

        return gale()->redirect('/vault-entry/payouts/'.$task->id)
            ->with('toast', [
                'type' => 'error',
                'message' => $message,
            ]);
    }
}
