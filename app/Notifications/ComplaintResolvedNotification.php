<?php

namespace App\Notifications;

use App\Models\Complaint;

/**
 * F-193 BR-292 / N-012: Notification sent to the client when a complaint is resolved.
 *
 * Channels: Push (WebPush) + Database.
 * Email is handled separately by ComplaintResolvedMail (BR-293).
 */
class ComplaintResolvedNotification extends BasePushNotification
{
    public function __construct(
        private Complaint $complaint
    ) {}

    /**
     * Get the push notification title.
     */
    public function getTitle(object $notifiable): string
    {
        return __('Complaint Resolved');
    }

    /**
     * Get the push notification body text.
     */
    public function getBody(object $notifiable): string
    {
        $orderNumber = $this->complaint->order?->order_number ?? '';

        return match ($this->complaint->resolution_type) {
            'dismiss' => __('Your complaint on order :order has been reviewed', [
                'order' => $orderNumber,
            ]),
            'partial_refund' => __('Your complaint on order :order was resolved with a partial refund', [
                'order' => $orderNumber,
            ]),
            'full_refund' => __('Your complaint on order :order was resolved with a full refund', [
                'order' => $orderNumber,
            ]),
            'warning' => __('Your complaint on order :order has been resolved', [
                'order' => $orderNumber,
            ]),
            'suspend' => __('Your complaint on order :order has been resolved', [
                'order' => $orderNumber,
            ]),
            default => __('Your complaint on order :order has been resolved', [
                'order' => $orderNumber,
            ]),
        };
    }

    /**
     * Get the URL the notification should link to when clicked.
     *
     * BR-295: Links to the complaint detail/tracking page (F-187).
     */
    public function getActionUrl(object $notifiable): string
    {
        return '/my-orders/'.$this->complaint->order_id.'/complaint/'.$this->complaint->id;
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
            'order_id' => $this->complaint->order_id,
            'order_number' => $this->complaint->order?->order_number,
            'resolution_type' => $this->complaint->resolution_type,
            'refund_amount' => $this->complaint->refund_amount,
            'type' => 'complaint_resolved',
        ];
    }

    /**
     * Get the notification tag for grouping.
     */
    public function getTag(object $notifiable): ?string
    {
        return 'complaint-resolved-'.$this->complaint->id;
    }
}
