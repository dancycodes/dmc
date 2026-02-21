<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreComplaintRequest;
use App\Models\Complaint;
use App\Models\Order;
use App\Services\ComplaintSubmissionService;
use Illuminate\Http\Request;

/**
 * F-183: Client Complaint Submission Controller.
 *
 * Handles complaint form display and submission on client order detail.
 * Uses Gale SSE for reactive form submission without page reload.
 */
class ComplaintController extends Controller
{
    /**
     * Show the complaint form for an order.
     *
     * BR-183: Only delivered or completed orders can receive a complaint.
     * BR-184: One complaint per order.
     */
    public function create(
        Request $request,
        Order $order,
        ComplaintSubmissionService $service
    ): mixed {
        // Ownership check
        if ($order->client_id !== $request->user()->id) {
            abort(403, __('You are not authorized to file a complaint on this order.'));
        }

        $check = $service->canSubmitComplaint($order, $request->user());

        if (! $check['can_complain']) {
            if ($check['reason'] === 'already_complained') {
                $complaint = $service->getExistingComplaint($order);

                return gale()->redirect('/my-orders/'.$order->id.'/complaint/'.$complaint->id)
                    ->with('toast', json_encode([
                        'type' => 'info',
                        'message' => __('You have already filed a complaint on this order.'),
                    ]));
            }

            abort(403, __('You cannot file a complaint on this order.'));
        }

        $order->load(['tenant:id,name_en,name_fr,slug']);

        $categoryLabels = Complaint::getClientCategoryLabels();

        return gale()->view('client.complaints.create', [
            'order' => $order,
            'categoryLabels' => $categoryLabels,
            'cookName' => $order->tenant?->name ?? __('Unknown Cook'),
        ], web: true);
    }

    /**
     * Submit the complaint.
     *
     * BR-189: Initial status is "open".
     * BR-190: Triggers payment block if applicable.
     * BR-191: Cook and managers notified.
     * BR-192: 24h auto-escalation clock starts.
     * BR-194: Activity logged.
     */
    public function store(
        Request $request,
        Order $order,
        ComplaintSubmissionService $service
    ): mixed {
        // Ownership check
        if ($order->client_id !== $request->user()->id) {
            abort(403, __('You are not authorized to file a complaint on this order.'));
        }

        $check = $service->canSubmitComplaint($order, $request->user());

        if (! $check['can_complain']) {
            if ($request->isGale()) {
                return gale()
                    ->state('error', __('You cannot file a complaint on this order.'));
            }
            abort(403, __('You cannot file a complaint on this order.'));
        }

        // Dual Gale/HTTP validation
        if ($request->isGale()) {
            $validated = $request->validateState([
                'category' => [
                    'required',
                    'string',
                    'in:'.implode(',', ComplaintSubmissionService::CLIENT_CATEGORIES),
                ],
                'description' => [
                    'required',
                    'string',
                    'min:'.ComplaintSubmissionService::MIN_DESCRIPTION_LENGTH,
                    'max:'.ComplaintSubmissionService::MAX_DESCRIPTION_LENGTH,
                ],
            ], [
                'category.required' => __('Please select a complaint category.'),
                'category.in' => __('Please select a valid complaint category.'),
                'description.required' => __('Please describe the issue you experienced.'),
                'description.min' => __('Description must be at least :min characters.'),
                'description.max' => __('Description cannot exceed :max characters.'),
            ]);

            // Handle photo separately (comes via FormData, not Alpine state)
            $photo = $request->file('photo');
            if ($photo) {
                $request->validate([
                    'photo' => [
                        'image',
                        'mimes:'.implode(',', ComplaintSubmissionService::ACCEPTED_PHOTO_MIMES),
                        'max:'.ComplaintSubmissionService::MAX_PHOTO_SIZE_KB,
                    ],
                ], [
                    'photo.image' => __('The uploaded file must be an image.'),
                    'photo.mimes' => __('Accepted image formats: JPEG, PNG, WebP.'),
                    'photo.max' => __('Image size must not exceed 5MB.'),
                ]);
            }
        } else {
            $formRequest = app(StoreComplaintRequest::class);
            $validated = $formRequest->validated();
            $photo = $request->file('photo');
        }

        $complaint = $service->submitComplaint(
            $order,
            $request->user(),
            [
                'category' => $validated['category'],
                'description' => $validated['description'],
            ],
            $photo
        );

        return gale()->redirect('/my-orders/'.$order->id)
            ->with('toast', json_encode([
                'type' => 'success',
                'message' => __('Your complaint has been submitted. We will review it shortly.'),
            ]));
    }

    /**
     * Show the complaint status (F-187 stub).
     *
     * Scenario 2: Client views existing complaint status.
     */
    public function show(
        Request $request,
        Order $order,
        Complaint $complaint,
        ComplaintSubmissionService $service
    ): mixed {
        // Ownership check
        if ($order->client_id !== $request->user()->id) {
            abort(403, __('You are not authorized to view this complaint.'));
        }

        // Verify complaint belongs to order
        if ($complaint->order_id !== $order->id) {
            abort(404);
        }

        return gale()->view('client.complaints.show', [
            'order' => $order,
            'complaint' => $complaint,
            'cookName' => $order->tenant?->name ?? __('Unknown Cook'),
        ], web: true);
    }
}
