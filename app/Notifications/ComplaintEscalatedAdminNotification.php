<?php

namespace App\Notifications;

use App\Models\Complaint;

/**
 * F-185 BR-211 / N-011: Notification sent to admin users when a complaint is auto-escalated.
 *
 * Channels: Push (WebPush) + Database.
 * Admin notification includes a direct link to the complaint in the admin panel.
 */
class ComplaintEscalatedAdminNotification extends BasePushNotification
{
    public function __construct(
        private Complaint $complaint
    ) {}

    /**
     * Get the push notification title.
     */
    public function getTitle(object $notifiable): string
    {
        return __('Complaint Escalated');
    }

    /**
     * Get the push notification body text.
     */
    public function getBody(object $notifiable): string
    {
        $orderNumber = $this->complaint->order?->order_number ?? __('N/A');

        return __('Complaint on order :order escalated - unresolved 24h', [
            'order' => $orderNumber,
        ]);
    }

    /**
     * Get the URL the notification should link to when clicked.
     *
     * Links to the admin complaint detail page.
     */
    public function getActionUrl(object $notifiable): string
    {
        return '/vault-entry/complaints/'.$this->complaint->id;
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
            'tenant_id' => $this->complaint->tenant_id,
            'escalation_reason' => Complaint::ESCALATION_AUTO_24H,
            'type' => 'complaint_escalated',
        ];
    }

    /**
     * Get the notification tag for grouping.
     */
    public function getTag(object $notifiable): ?string
    {
        return 'complaint-escalated-'.$this->complaint->id;
    }
}
