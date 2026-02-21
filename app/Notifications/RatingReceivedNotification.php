<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Rating;

/**
 * F-176: Rating Received Push Notification (N-019)
 *
 * Sent to the cook when a client rates their order.
 *
 * BR-396: Cook is notified of new ratings (push + DB).
 */
class RatingReceivedNotification extends BasePushNotification
{
    public function __construct(
        private Rating $rating,
        private Order $order,
    ) {}

    /**
     * Get the push notification title.
     */
    public function getTitle(object $notifiable): string
    {
        return __('New Rating Received!');
    }

    /**
     * Get the push notification body text.
     */
    public function getBody(object $notifiable): string
    {
        $stars = str_repeat('★', $this->rating->stars) . str_repeat('☆', 5 - $this->rating->stars);

        return __('Order :number rated :stars', [
            'number' => $this->order->order_number,
            'stars' => $stars,
        ]);
    }

    /**
     * Get the URL the notification links to.
     */
    public function getActionUrl(object $notifiable): string
    {
        $tenant = $this->order->tenant;
        if ($tenant) {
            return $tenant->getUrl() . '/dashboard/orders/' . $this->order->id;
        }

        return '/dashboard/orders/' . $this->order->id;
    }

    /**
     * Get additional data payload for the notification.
     *
     * @return array<string, mixed>
     */
    public function getData(object $notifiable): array
    {
        return [
            'rating_id' => $this->rating->id,
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'stars' => $this->rating->stars,
            'tenant_id' => $this->order->tenant_id,
            'type' => 'rating_received',
        ];
    }

    /**
     * Get the notification tag for grouping.
     */
    public function getTag(object $notifiable): ?string
    {
        return 'rating-' . $this->order->id;
    }
}
