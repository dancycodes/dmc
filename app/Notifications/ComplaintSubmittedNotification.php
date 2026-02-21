<?php

namespace App\Notifications;

use App\Models\Complaint;
use App\Models\Order;

/**
 * F-183 BR-191 / N-009: Notification sent to cook and managers when a complaint is submitted.
 *
 * Channels: Push (WebPush) + Database.
 */
class ComplaintSubmittedNotification extends BasePushNotification
{
    public function __construct(
        private Complaint $complaint,
        private Order $order
    ) {}

    /**
     * Get the push notification title.
     */
    public function getTitle(object $notifiable): string
    {
        return __('New Complaint on Order');
    }

    /**
     * Get the push notification body text.
     */
    public function getBody(object $notifiable): string
    {
        $category = $this->complaint->categoryLabel();

        return __('A complaint has been filed on order :order - :category', [
            'order' => $this->order->order_number,
            'category' => $category,
        ]);
    }

    /**
     * Get the URL the notification should link to when clicked.
     *
     * Links to the cook dashboard order detail page.
     */
    public function getActionUrl(object $notifiable): string
    {
        return '/dashboard/orders/'.$this->order->id;
    }

    /**
     * Get additional data payload.
     *
     * @return array<string, mixed>
     */
    public function getData(object $notifiable): array
    {
        return [
            'complaint_id' => $this->complaint->id,
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'category' => $this->complaint->category,
            'type' => 'complaint_submitted',
        ];
    }

    /**
     * Get the notification tag for grouping.
     */
    public function getTag(object $notifiable): ?string
    {
        return 'complaint-'.$this->complaint->id;
    }
}
