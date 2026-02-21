<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Tenant;

/**
 * F-162 N-017: Order Cancelled Notification
 *
 * Sent to the cook (and managers with can-manage-orders) when a client cancels their order.
 * Channels: Push + Database (no email for cancellation).
 *
 * BR-244: Cook + manager(s) notified (push + DB: N-017).
 */
class OrderCancelledNotification extends BasePushNotification
{
    public function __construct(
        public readonly Order $order,
        public readonly Tenant $tenant,
    ) {}

    /**
     * Get the push notification title.
     */
    public function getTitle(object $notifiable): string
    {
        return __('Order Cancelled');
    }

    /**
     * Get the push notification body.
     *
     * N-017: "Order cancelled by customer"
     */
    public function getBody(object $notifiable): string
    {
        return __('Order #:number has been cancelled by the customer.', [
            'number' => $this->order->order_number,
        ]);
    }

    /**
     * Get the URL the notification links to (cook order detail).
     */
    public function getActionUrl(object $notifiable): string
    {
        return $this->tenant->getUrl('/dashboard/orders/'.$this->order->id);
    }

    /**
     * Get additional data for the notification payload.
     *
     * @return array<string, mixed>
     */
    public function getData(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'grand_total' => $this->order->grand_total,
        ];
    }

    /**
     * Get the notification tag for deduplication.
     */
    public function getTag(object $notifiable): ?string
    {
        return 'order-cancelled-'.$this->order->id;
    }
}
