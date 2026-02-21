<?php

namespace App\Notifications;

use App\Models\Complaint;

/**
 * F-185 BR-212: Notification sent to the client when their complaint is auto-escalated.
 *
 * Channels: Push (WebPush) + Database.
 * UI/UX: "Your complaint has been escalated to our support team for review"
 */
class ComplaintEscalatedClientNotification extends BasePushNotification
{
    public function __construct(
        private Complaint $complaint
    ) {}

    /**
     * Get the push notification title.
     */
    public function getTitle(object $notifiable): string
    {
        return __('Complaint Escalated for Review');
    }

    /**
     * Get the push notification body text.
     */
    public function getBody(object $notifiable): string
    {
        return __('Your complaint has been escalated to our support team for review');
    }

    /**
     * Get the URL the notification should link to when clicked.
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
            'type' => 'complaint_escalated_client',
        ];
    }

    /**
     * Get the notification tag for grouping.
     */
    public function getTag(object $notifiable): ?string
    {
        return 'complaint-escalated-client-'.$this->complaint->id;
    }
}
