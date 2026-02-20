<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Tenant;

/**
 * F-154: New Order Push Notification (N-001)
 *
 * Sent to the cook after a client's payment is confirmed.
 *
 * BR-401: A push notification is sent to the cook about the new order.
 * BR-407: Uses push + database channels.
 */
class NewOrderNotification extends BasePushNotification
{
    public function __construct(
        private Order $order,
        private Tenant $tenant,
    ) {}

    /**
     * Get the push notification title.
     */
    public function getTitle(object $notifiable): string
    {
        return __('New Order Received!');
    }

    /**
     * Get the push notification body text.
     *
     * BR-401: "New order #DM-2024-0045 received! 6,500 XAF."
     */
    public function getBody(object $notifiable): string
    {
        return __('New order :number received! :amount.', [
            'number' => $this->order->order_number,
            'amount' => $this->order->formattedGrandTotal(),
        ]);
    }

    /**
     * Get the URL the notification links to.
     *
     * Links to the cook dashboard order detail (F-156 forward-compatible).
     */
    public function getActionUrl(object $notifiable): string
    {
        $tenantUrl = $this->tenant->getUrl();

        return $tenantUrl.'/dashboard/orders/'.$this->order->id;
    }

    /**
     * Get additional data payload for the notification.
     *
     * @return array<string, mixed>
     */
    public function getData(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'amount' => $this->order->grand_total,
            'tenant_id' => $this->tenant->id,
            'type' => 'new_order',
        ];
    }

    /**
     * Get the notification tag for grouping.
     */
    public function getTag(object $notifiable): ?string
    {
        return 'order-'.$this->order->id;
    }
}
