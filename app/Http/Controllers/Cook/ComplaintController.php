<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\StoreComplaintResponseRequest;
use App\Models\Complaint;
use App\Models\ComplaintResponse;
use App\Services\ComplaintResponseService;
use App\Services\ComplaintTrackingService;
use Illuminate\Http\Request;

/**
 * F-184: Cook/Manager Complaint Response Controller.
 *
 * Handles complaint viewing and response within the cook dashboard.
 * BR-195: Only cook or manager with manage-complaints permission.
 */
class ComplaintController extends Controller
{
    /**
     * Display the complaint list for the current tenant.
     *
     * UI/UX: Complaint list with order ID, client name, category badge, status badge, date.
     */
    public function index(Request $request, ComplaintResponseService $service): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-195: Permission check
        if (! $service->canRespond(
            Complaint::make(['tenant_id' => $tenant->id]),
            $user
        )) {
            abort(403);
        }

        $filters = [
            'search' => $request->input('search', ''),
            'status' => $request->input('status', ''),
        ];

        $complaints = $service->getComplaintsForTenant($tenant->id, $filters);
        $summary = $service->getComplaintSummary($tenant->id);

        $data = [
            'complaints' => $complaints,
            'summary' => $summary,
            'search' => $filters['search'],
            'currentStatus' => $filters['status'],
            'statusOptions' => Complaint::COOK_STATUSES,
        ];

        // Fragment-based partial update for search/filter
        if ($request->isGaleNavigate('complaints')) {
            return gale()
                ->fragment('cook.complaints.index', 'complaints-list', $data);
        }

        return gale()->view('cook.complaints.index', $data, web: true);
    }

    /**
     * F-184/F-187: Display complaint detail with status timeline and response form.
     *
     * UI/UX: Split layout — complaint info + timeline (left/top) and response form (right/bottom).
     * BR-232: Status timeline shows all four states.
     * BR-233: All messages visible to cook/manager.
     */
    public function show(
        Request $request,
        Complaint $complaint,
        ComplaintResponseService $service,
        ComplaintTrackingService $trackingService
    ): mixed {
        $user = $request->user();
        $tenant = tenant();

        // Tenant scope check
        if ($complaint->tenant_id !== $tenant->id) {
            abort(404);
        }

        // BR-195: Permission check
        if (! $service->canRespond($complaint, $user)) {
            abort(403);
        }

        $complaint = $trackingService->getComplaintTrackingData($complaint);
        $orderItems = $service->parseOrderItems($complaint->order?->items_snapshot);
        $orderTotal = (int) ($complaint->order?->grand_total ?? 0);

        // F-187: Build timeline and message thread
        $timeline = $trackingService->buildTimeline($complaint);
        $messages = $trackingService->buildMessageThread($complaint);
        $resolution = $trackingService->getResolutionData($complaint, 'cook');

        return gale()->view('cook.complaints.show', [
            'complaint' => $complaint,
            'orderItems' => $orderItems,
            'orderTotal' => $orderTotal,
            'resolutionTypes' => ComplaintResponse::RESOLUTION_TYPES,
            'timeline' => $timeline,
            'messages' => $messages,
            'resolution' => $resolution,
        ], web: true);
    }

    /**
     * Submit a response to a complaint.
     *
     * BR-196: Required text, min 10 chars, max 2000 chars.
     * BR-197: Resolution options: apology_only, partial_refund_offer, full_refund_offer.
     * BR-198: Partial refund amount must be > 0 and <= order total.
     * BR-199: Full refund auto-set to order total.
     * BR-200: Status changes from "open" to "in_review" on first response.
     */
    public function respond(Request $request, Complaint $complaint, ComplaintResponseService $service): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // Tenant scope check
        if ($complaint->tenant_id !== $tenant->id) {
            abort(404);
        }

        // BR-195: Permission check
        if (! $service->canRespond($complaint, $user)) {
            abort(403);
        }

        $complaint->load('order:id,grand_total');
        $orderTotal = (int) ($complaint->order?->grand_total ?? 0);

        // Validation rules
        $rules = [
            'message' => [
                'required',
                'string',
                'min:'.ComplaintResponse::MIN_MESSAGE_LENGTH,
                'max:'.ComplaintResponse::MAX_MESSAGE_LENGTH,
            ],
            'resolution_type' => [
                'required',
                'string',
                'in:'.implode(',', ComplaintResponse::RESOLUTION_TYPES),
            ],
            'refund_amount' => [
                'nullable',
                'integer',
                'min:1',
                'max:'.$orderTotal,
            ],
        ];

        $messages = [
            'message.required' => __('Please write a response message.'),
            'message.min' => __('Response must be at least :min characters.'),
            'message.max' => __('Response cannot exceed :max characters.'),
            'resolution_type.required' => __('Please select a resolution type.'),
            'resolution_type.in' => __('Please select a valid resolution type.'),
            'refund_amount.min' => __('Refund amount must be at least 1 XAF.'),
            'refund_amount.max' => __('Refund amount cannot exceed the order total of :max XAF.'),
        ];

        // Dual Gale/HTTP validation
        if ($request->isGale()) {
            $validated = $request->validateState($rules, $messages);
        } else {
            $formRequest = app(StoreComplaintResponseRequest::class);
            $validated = $formRequest->validated();
        }

        // BR-198: Require refund_amount for partial refund
        if ($validated['resolution_type'] === ComplaintResponse::RESOLUTION_PARTIAL_REFUND) {
            if (empty($validated['refund_amount']) || (int) $validated['refund_amount'] < 1) {
                if ($request->isGale()) {
                    return gale()->messages([
                        'refund_amount' => __('Please enter a refund amount for partial refund.'),
                    ]);
                }
                abort(422, __('Please enter a refund amount for partial refund.'));
            }
        }

        // BR-199: Full refund sets amount to order total
        $refundAmount = null;
        if ($validated['resolution_type'] === ComplaintResponse::RESOLUTION_PARTIAL_REFUND) {
            $refundAmount = (int) $validated['refund_amount'];
        } elseif ($validated['resolution_type'] === ComplaintResponse::RESOLUTION_FULL_REFUND) {
            $refundAmount = $orderTotal;
        }

        $response = $service->submitResponse($complaint, $user, [
            'message' => $validated['message'],
            'resolution_type' => $validated['resolution_type'],
            'refund_amount' => $refundAmount,
        ]);

        return gale()->redirect('/dashboard/complaints/'.$complaint->id)
            ->with('toast', json_encode([
                'type' => 'success',
                'message' => __('Your response has been submitted successfully.'),
            ]));
    }
}
