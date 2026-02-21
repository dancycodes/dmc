<?php

namespace App\Services;

use App\Mail\OrderStatusUpdateMail;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\OrderStatusUpdateNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * F-192: Order Status Update Notification Service
 *
 * Centralizes notification dispatch when an order's status changes.
 * Sends push + DB notifications to the client for every status change.
 * Sends email notifications only for key status transitions.
 *
 * BR-278: Push + DB notifications sent for every order status change.
 * BR-279: Email only for key statuses: Confirmed, Ready for Pickup,
 *         Out for Delivery, Delivered, Completed.
 * BR-285: Notifications link to the client's order detail page.
 * BR-287: Notifications are queued to not block the status update response.
 */
class OrderStatusNotificationService
{
    /**
     * Dispatch all notifications for an order status change to the client.
     *
     * BR-278: Push + DB for every status change.
     * BR-279: Email only for key statuses.
     * BR-287: All notifications are queued.
     */
    public function notifyStatusUpdate(
        Order $order,
        Tenant $tenant,
        string $previousStatus,
        string $newStatus,
    ): void {
        $client = $this->resolveClient($order);

        if (! $client) {
            Log::warning('F-192: Cannot send status notification — no client found', [
                'order_id' => $order->id,
                'new_status' => $newStatus,
            ]);

            return;
        }

        $this->sendPushAndDatabaseNotification($client, $order, $tenant, $previousStatus, $newStatus);

        if (OrderStatusUpdateMail::shouldSendEmailForStatus($newStatus)) {
            $this->sendEmailNotification($client, $order, $tenant, $newStatus);
        }
    }

    /**
     * Resolve the client user for the order.
     *
     * Loads the client relationship if not already loaded.
     */
    public function resolveClient(Order $order): ?User
    {
        if (! $order->relationLoaded('client')) {
            $order->load('client');
        }

        return $order->client;
    }

    /**
     * Send push and database notification to the client.
     *
     * BR-278: Every status change triggers push + DB notification.
     * BR-280: Content customized per status.
     * BR-285: Links to client's order detail page.
     */
    private function sendPushAndDatabaseNotification(
        User $client,
        Order $order,
        Tenant $tenant,
        string $previousStatus,
        string $newStatus,
    ): void {
        try {
            // Load pickup location for body text (BR-282: Ready for Pickup includes location)
            if (! $order->relationLoaded('pickupLocation')) {
                $order->load('pickupLocation');
            }

            $client->notify(
                new OrderStatusUpdateNotification($order, $tenant, $previousStatus, $newStatus)
            );
        } catch (\Throwable $e) {
            Log::warning('F-192: Push/DB notification failed', [
                'order_id' => $order->id,
                'client_id' => $client->id,
                'new_status' => $newStatus,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send email notification to the client for key status transitions.
     *
     * BR-279: Only for key statuses (Confirmed, Ready for Pickup, Out for Delivery, Delivered, Completed).
     * BR-283: Delivered/Completed email includes rate/review prompt.
     * BR-286: Email includes a "View Order" button.
     */
    private function sendEmailNotification(
        User $client,
        Order $order,
        Tenant $tenant,
        string $newStatus,
    ): void {
        if (empty($client->email)) {
            return;
        }

        try {
            Mail::to($client->email)
                ->send(
                    (new OrderStatusUpdateMail($order, $tenant, $newStatus))
                        ->forRecipient($client)
                );
        } catch (\Throwable $e) {
            // Email failure must not prevent push/DB delivery
            Log::warning('F-192: Email notification failed', [
                'order_id' => $order->id,
                'client_id' => $client->id,
                'new_status' => $newStatus,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
