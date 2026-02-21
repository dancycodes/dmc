<?php

namespace App\Notifications;

use App\Models\Complaint;

/**
 * F-185 BR-213: Notification sent to the cook when a complaint is auto-escalated.
 *
 * Channels: Push (WebPush) + Database.
 * UI/UX: "A complaint on order #[ID] was escalated because no response was provided within 24 hours"
 */
class ComplaintEscalatedCookNotification extends BasePushNotification
{
    public function __construct(
        private Complaint $complaint
    ) {}

    /**
     * Get the push notification title.
     */
    public function getTitle(object $notifiable): string
    {
        return __('Complaint Auto-Escalated');
    }

    /**
     * Get the push notification body text.
     */
    public function getBody(object $notifiable): string
    {
        $orderNumber = $this->complaint->order?->order_number ?? __('N/A');

        return __('A complaint on order :order was escalated because no response was provided within 24 hours', [
            'order' => $orderNumber,
        ]);
    }

    /**
     * Get the URL the notification should link to when clicked.
     *
     * Links to the cook dashboard complaint detail page.
     */
    public function getActionUrl(object $notifiable): string
    {
        return '/dashboard/complaints/'.$this->complaint->id;
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
            'category' => $this->complaint->category,
            'escalation_reason' => Complaint::ESCALATION_AUTO_24H,
            'type' => 'complaint_escalated_cook',
        ];
    }

    /**
     * Get the notification tag for grouping.
     */
    public function getTag(object $notifiable): ?string
    {
        return 'complaint-escalated-cook-'.$this->complaint->id;
    }
}
