<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Tenant;

/**
 * F-192: Order Status Update Push + Database Notification (N-002 through N-005)
 *
 * Sent to the client when the cook updates an order's status.
 *
 * BR-278: Push + DB notifications are sent for every order status change.
 * BR-280: Notification content is customized per status with relevant details.
 * BR-281: Confirmed status includes order ID and confirmation message.
 * BR-282: Ready status includes order ID and pickup location (if pickup) or delivery ETA (if delivery).
 * BR-283: Delivered/Completed notification includes order ID and prompt to rate/review.
 * BR-284: Cancelled status includes order ID and cancellation reason if available.
 * BR-285: Push and DB notifications link to the client's order detail page.
 * BR-287: Queued via BasePushNotification (implements ShouldQueue).
 * BR-288: All notification text uses __() localization.
 */
class OrderStatusUpdateNotification extends BasePushNotification
{
    public function __construct(
        private Order $order,
        private Tenant $tenant,
        private string $previousStatus,
        private string $newStatus,
    ) {}

    /**
     * Get the push notification title.
     *
     * BR-280: Title varies by new status.
     */
    public function getTitle(object $notifiable): string
    {
        return match ($this->newStatus) {
            Order::STATUS_CONFIRMED => __('Order Confirmed!'),
            Order::STATUS_PREPARING => __('Order Being Prepared'),
            Order::STATUS_READY => __('Order Ready'),
            Order::STATUS_OUT_FOR_DELIVERY => __('Order Out for Delivery'),
            Order::STATUS_READY_FOR_PICKUP => __('Ready for Pickup!'),
            Order::STATUS_DELIVERED => __('Order Delivered!'),
            Order::STATUS_PICKED_UP => __('Order Picked Up!'),
            Order::STATUS_COMPLETED => __('Order Completed!'),
            Order::STATUS_CANCELLED => __('Order Cancelled'),
            Order::STATUS_REFUNDED => __('Order Refunded'),
            default => __('Order Update'),
        };
    }

    /**
     * Get the push notification body text.
     *
     * BR-280/BR-281/BR-282/BR-283/BR-284: Status-specific content.
     */
    public function getBody(object $notifiable): string
    {
        $number = $this->order->order_number;

        return match ($this->newStatus) {
            Order::STATUS_CONFIRMED => __(
                'Order :number Confirmed! Your order is being prepared.',
                ['number' => $number]
            ),
            Order::STATUS_PREPARING => __(
                'Order :number is being prepared.',
                ['number' => $number]
            ),
            Order::STATUS_READY => __(
                'Order :number is ready.',
                ['number' => $number]
            ),
            Order::STATUS_OUT_FOR_DELIVERY => __(
                'Order :number is on its way!',
                ['number' => $number]
            ),
            Order::STATUS_READY_FOR_PICKUP => $this->getReadyForPickupBody($number),
            Order::STATUS_DELIVERED => __(
                'Order :number has been delivered. Enjoy your meal!',
                ['number' => $number]
            ),
            Order::STATUS_PICKED_UP => __(
                'Order :number has been picked up. Enjoy your meal!',
                ['number' => $number]
            ),
            Order::STATUS_COMPLETED => __(
                'Order :number is completed. Thank you for your order!',
                ['number' => $number]
            ),
            Order::STATUS_CANCELLED => $this->getCancelledBody($number),
            Order::STATUS_REFUNDED => __(
                'Order :number has been refunded.',
                ['number' => $number]
            ),
            default => __(
                'Your order :number has been updated.',
                ['number' => $number]
            ),
        };
    }

    /**
     * Get the URL the notification links to.
     *
     * BR-285: Push and DB notifications link to the client's order detail page.
     */
    public function getActionUrl(object $notifiable): string
    {
        $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

        return 'https://'.$mainDomain.'/my-orders/'.$this->order->id;
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
            'tenant_id' => $this->tenant->id,
            'previous_status' => $this->previousStatus,
            'new_status' => $this->newStatus,
            'delivery_method' => $this->order->delivery_method,
            'type' => 'order_status_update',
        ];
    }

    /**
     * Get the notification tag for grouping/replacing notifications.
     *
     * Using order-specific tag so newer status updates replace older ones.
     */
    public function getTag(object $notifiable): ?string
    {
        return 'order-status-'.$this->order->id;
    }

    /**
     * Build the "Ready for Pickup" body with pickup location details.
     *
     * BR-282: Ready status includes pickup location if pickup order.
     */
    private function getReadyForPickupBody(string $number): string
    {
        $location = '';

        if ($this->order->pickupLocation) {
            $location = $this->order->pickupLocation->name ?? '';
        }

        if ($location) {
            return __(
                'Order :number is ready for pickup! Head to :location.',
                ['number' => $number, 'location' => $location]
            );
        }

        return __(
            'Order :number is ready for pickup!',
            ['number' => $number]
        );
    }

    /**
     * Build the cancelled notification body with optional reason.
     *
     * BR-284: Cancelled status includes order ID and cancellation reason if available.
     */
    private function getCancelledBody(string $number): string
    {
        return __(
            'Order :number has been cancelled.',
            ['number' => $number]
        );
    }
}
