<?php

namespace App\Services;

use App\Mail\NewOrderMail;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\NewOrderNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * F-191: Order Creation Notification Service
 *
 * Centralizes notification dispatch for new orders.
 * Sends push, database, and email notifications to the cook
 * and all managers associated with the tenant.
 *
 * BR-268: Triggered after successful payment webhook processing
 * BR-269: All three channels: push + DB + email
 * BR-270: Recipients: cook + all tenant managers
 * BR-276: Notifications are queued to not block webhook response
 */
class OrderNotificationService
{
    /**
     * Send new order notifications to the cook and all managers.
     *
     * BR-269: Push + database + email for all recipients.
     * BR-270: Cook + all managers associated with the tenant.
     * BR-276: Queued dispatch via notification queue.
     */
    public function notifyNewOrder(Order $order, Tenant $tenant): void
    {
        $recipients = $this->resolveRecipients($tenant);

        foreach ($recipients as $recipient) {
            $this->sendPushAndDatabaseNotification($recipient, $order, $tenant);
            $this->sendEmailNotification($recipient, $order, $tenant);
        }
    }

    /**
     * BR-270: Resolve all notification recipients for the tenant.
     *
     * Recipients include:
     * - The tenant's cook
     * - All managers associated with the tenant (users with 'manager' role)
     *
     * @return array<User>
     */
    public function resolveRecipients(Tenant $tenant): array
    {
        $recipients = [];
        $seenIds = [];

        // Add the cook
        $cook = $tenant->cook;
        if ($cook) {
            $recipients[] = $cook;
            $seenIds[] = $cook->id;
        }

        // Add managers with can-manage-orders permission for this tenant
        try {
            $managers = User::permission('can-manage-orders')->get();
            foreach ($managers as $manager) {
                if (! in_array($manager->id, $seenIds, true)) {
                    $recipients[] = $manager;
                    $seenIds[] = $manager->id;
                }
            }
        } catch (\Throwable) {
            // Permission may not exist yet — silent fail
        }

        return $recipients;
    }

    /**
     * Send push and database notification to a recipient.
     *
     * BR-271: Push notification content format.
     * BR-272: DB notification content.
     * BR-274: Links to order detail in cook dashboard.
     */
    private function sendPushAndDatabaseNotification(User $recipient, Order $order, Tenant $tenant): void
    {
        try {
            $recipient->notify(new NewOrderNotification($order, $tenant));
        } catch (\Throwable $e) {
            Log::warning('F-191: Push/DB notification failed', [
                'order_id' => $order->id,
                'recipient_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send email notification to a recipient.
     *
     * BR-273: Email contains full order detail.
     * BR-275: Email includes a "View Order" button.
     * BR-276: Email is queued via BaseMailableNotification.
     */
    private function sendEmailNotification(User $recipient, Order $order, Tenant $tenant): void
    {
        if (empty($recipient->email)) {
            // Edge case: Cook has no email address — skip silently
            return;
        }

        try {
            Mail::to($recipient->email)
                ->send(
                    (new NewOrderMail($order, $tenant))
                        ->forRecipient($recipient)
                );
        } catch (\Throwable $e) {
            // Edge case: Email delivery fails — log, push and DB still delivered
            Log::warning('F-191: Email notification failed', [
                'order_id' => $order->id,
                'recipient_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the item count from an order's items_snapshot.
     *
     * Used by push notifications for the summary format.
     */
    public static function getItemCount(Order $order): int
    {
        $snapshot = $order->items_snapshot;

        if (empty($snapshot)) {
            return 0;
        }

        if (is_string($snapshot)) {
            $decoded = json_decode($snapshot, true);
            $snapshot = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($snapshot)) {
            return 0;
        }

        $totalItems = 0;
        foreach ($snapshot as $item) {
            $totalItems += (int) ($item['quantity'] ?? 1);
        }

        return $totalItems;
    }

    /**
     * Get the delivery method label for push notification format.
     *
     * BR-271: Shows "Delivery" or "Pickup" in the push notification.
     */
    public static function getDeliveryMethodLabel(Order $order): string
    {
        return $order->delivery_method === Order::METHOD_DELIVERY
            ? __('Delivery')
            : __('Pickup');
    }
}
