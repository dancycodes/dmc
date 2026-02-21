<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Tenant;
use App\Services\OrderNotificationService;

/**
 * F-191: New Order Push + Database Notification (N-001)
 *
 * Sent to the cook and managers after a client's payment is confirmed.
 *
 * BR-271: Push notification content: "New Order #[ID] -- [item count] items, [total] XAF ([Delivery/Pickup])"
 * BR-272: DB notification content: order ID, client name, items summary, total, delivery/pickup
 * BR-274: Push and DB notifications link to the order detail in the cook dashboard
 * BR-276: Queued via BasePushNotification (implements ShouldQueue)
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
     * BR-271: "New Order #[ID] -- [item count] items, [total] XAF ([Delivery/Pickup])"
     */
    public function getBody(object $notifiable): string
    {
        $itemCount = OrderNotificationService::getItemCount($this->order);
        $deliveryLabel = OrderNotificationService::getDeliveryMethodLabel($this->order);

        return __('New Order :number - :count items, :amount (:method)', [
            'number' => $this->order->order_number,
            'count' => $itemCount,
            'amount' => $this->order->formattedGrandTotal(),
            'method' => $deliveryLabel,
        ]);
    }

    /**
     * Get the URL the notification links to.
     *
     * BR-274: Links to the cook dashboard order detail.
     */
    public function getActionUrl(object $notifiable): string
    {
        $tenantUrl = $this->tenant->getUrl();

        return $tenantUrl.'/dashboard/orders/'.$this->order->id;
    }

    /**
     * Get additional data payload for the notification.
     *
     * BR-272: DB notification data includes order ID, client name,
     * items summary, total, delivery/pickup.
     *
     * @return array<string, mixed>
     */
    public function getData(object $notifiable): array
    {
        $itemCount = OrderNotificationService::getItemCount($this->order);

        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'amount' => $this->order->grand_total,
            'tenant_id' => $this->tenant->id,
            'client_name' => $this->order->client?->name ?? __('Customer'),
            'item_count' => $itemCount,
            'items_summary' => $this->order->items_summary,
            'delivery_method' => $this->order->delivery_method,
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
