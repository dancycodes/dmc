<?php

namespace App\Notifications;

use App\Models\Complaint;
use App\Models\ComplaintResponse;

/**
 * F-184 BR-202 / N-010: Notification sent to client when cook responds to a complaint.
 *
 * Channels: Push (WebPush) + Database.
 */
class ComplaintResponseNotification extends BasePushNotification
{
    public function __construct(
        private Complaint $complaint,
        private ComplaintResponse $response
    ) {}

    /**
     * Get the push notification title.
     */
    public function getTitle(object $notifiable): string
    {
        return __('Response to Your Complaint');
    }

    /**
     * Get the push notification body text.
     */
    public function getBody(object $notifiable): string
    {
        $orderNumber = $this->complaint->order?->order_number ?? '';

        return __('The cook has responded to your complaint on order :order', [
            'order' => $orderNumber,
        ]);
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
            'response_id' => $this->response->id,
            'order_id' => $this->complaint->order_id,
            'order_number' => $this->complaint->order?->order_number,
            'resolution_type' => $this->response->resolution_type,
            'type' => 'complaint_response',
        ];
    }

    /**
     * Get the notification tag for grouping.
     */
    public function getTag(object $notifiable): ?string
    {
        return 'complaint-response-'.$this->complaint->id;
    }
}
