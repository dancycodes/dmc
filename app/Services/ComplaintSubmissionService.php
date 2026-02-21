<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * F-183: Client Complaint Submission Service
 *
 * Handles the business logic for client complaint submission,
 * including validation, photo storage, payment block triggering,
 * and notification dispatch.
 */
class ComplaintSubmissionService
{
    /**
     * F-183 BR-185: Client-facing complaint categories.
     */
    public const CLIENT_CATEGORIES = [
        'food_quality',
        'delivery_issue',
        'missing_item',
        'wrong_order',
        'other',
    ];

    /**
     * F-183 BR-183: Statuses that allow complaint submission.
     */
    public const COMPLAINABLE_STATUSES = [
        Order::STATUS_DELIVERED,
        Order::STATUS_PICKED_UP,
        Order::STATUS_COMPLETED,
    ];

    /**
     * F-183 BR-186: Description constraints.
     */
    public const MIN_DESCRIPTION_LENGTH = 10;

    public const MAX_DESCRIPTION_LENGTH = 1000;

    /**
     * F-183 BR-188: Photo upload constraints.
     */
    public const MAX_PHOTO_SIZE_KB = 5120; // 5MB

    public const ACCEPTED_PHOTO_MIMES = ['jpeg', 'jpg', 'png', 'webp'];

    /**
     * Check if the client can submit a complaint on this order.
     *
     * BR-183: Only delivered or completed orders.
     * BR-184: One complaint per order.
     *
     * @return array{can_complain: bool, reason: string|null}
     */
    public function canSubmitComplaint(Order $order, User $user): array
    {
        // Verify ownership
        if ($order->client_id !== $user->id) {
            return ['can_complain' => false, 'reason' => 'not_owner'];
        }

        // BR-183: Only delivered/completed orders
        if (! in_array($order->status, self::COMPLAINABLE_STATUSES, true)) {
            return ['can_complain' => false, 'reason' => 'invalid_status'];
        }

        // BR-184: One complaint per order
        if ($this->hasExistingComplaint($order)) {
            return ['can_complain' => false, 'reason' => 'already_complained'];
        }

        return ['can_complain' => true, 'reason' => null];
    }

    /**
     * BR-184: Check if a complaint already exists for this order.
     */
    public function hasExistingComplaint(Order $order): bool
    {
        return Complaint::query()
            ->where('order_id', $order->id)
            ->exists();
    }

    /**
     * Get the existing complaint for an order (if any).
     */
    public function getExistingComplaint(Order $order): ?Complaint
    {
        return Complaint::query()
            ->where('order_id', $order->id)
            ->first();
    }

    /**
     * Submit a complaint on an order.
     *
     * BR-189: Initial status is "open".
     * BR-190: Triggers payment block if cook payment is unwithdrawable.
     * BR-191: Cook and managers receive notifications.
     * BR-192: 24-hour auto-escalation clock starts.
     * BR-194: Activity logged via Spatie.
     *
     * @param  array{category: string, description: string}  $data
     */
    public function submitComplaint(
        Order $order,
        User $client,
        array $data,
        ?UploadedFile $photo = null
    ): Complaint {
        return DB::transaction(function () use ($order, $client, $data, $photo) {
            // Store photo if provided
            $photoPath = null;
            if ($photo) {
                $photoPath = $this->storePhoto($photo, $order);
            }

            // Create complaint record
            $complaint = Complaint::create([
                'order_id' => $order->id,
                'client_id' => $client->id,
                'cook_id' => $order->cook_id,
                'tenant_id' => $order->tenant_id,
                'category' => $data['category'],
                'description' => $data['description'],
                'photo_path' => $photoPath,
                'status' => 'open',
                'is_escalated' => false,
                'submitted_at' => now(),
            ]);

            // BR-194: Activity logging
            activity('complaints')
                ->performedOn($complaint)
                ->causedBy($client)
                ->withProperties([
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'category' => $data['category'],
                    'has_photo' => $photo !== null,
                ])
                ->log('complaint_submitted');

            // BR-190: Trigger payment block if cook payment is unwithdrawable (F-186)
            $this->triggerPaymentBlockIfApplicable($order);

            // BR-191: Notify cook and managers
            $this->notifyCookAndManagers($complaint, $order);

            return $complaint;
        });
    }

    /**
     * Store the complaint photo in tenant-scoped storage.
     *
     * BR-187: Maximum one image per complaint.
     * BR-188: JPEG, PNG, WebP; max 5MB.
     */
    private function storePhoto(UploadedFile $photo, Order $order): string
    {
        $directory = 'complaints/tenant-'.$order->tenant_id;

        return $photo->store($directory, 'public');
    }

    /**
     * BR-190: Trigger payment block if the cook's payment is still unwithdrawable.
     *
     * Uses OrderClearanceService::pauseTimer() from F-171.
     */
    private function triggerPaymentBlockIfApplicable(Order $order): void
    {
        try {
            $clearanceService = app(OrderClearanceService::class);
            $clearanceService->pauseTimer($order);
        } catch (\Throwable $e) {
            // F-186 not yet fully implemented or clearance doesn't exist
            // Log but don't fail the complaint submission
            \Illuminate\Support\Facades\Log::warning('F-183: Payment block trigger failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * BR-191: Notify cook and managers of the tenant.
     *
     * Sends push + database notifications.
     * Full notification implementation deferred to F-193.
     */
    private function notifyCookAndManagers(Complaint $complaint, Order $order): void
    {
        try {
            // Get cook user
            $cook = $order->cook;

            if ($cook) {
                // Forward-compatible: F-193 will implement the full notification
                // For now, create database notification directly
                $cook->notify(new \App\Notifications\ComplaintSubmittedNotification($complaint, $order));
            }

            // Notify managers with complaint management permission
            // Forward-compatible: F-184 will define exact manager resolution
            // For now, notify users with can-manage-complaints permission
            try {
                $managers = User::permission('can-manage-complaints')->get();
                foreach ($managers as $manager) {
                    if ($manager->id !== $cook?->id) {
                        $manager->notify(new \App\Notifications\ComplaintSubmittedNotification($complaint, $order));
                    }
                }
            } catch (\Throwable) {
                // Permission may not exist yet - silent fail
            }
        } catch (\Throwable $e) {
            // Don't fail complaint submission if notifications fail
            \Illuminate\Support\Facades\Log::warning('F-183: Notification dispatch failed', [
                'complaint_id' => $complaint->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
