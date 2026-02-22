<?php

namespace App\Notifications;

use App\Models\Announcement;

/**
 * F-195: System Announcement Push + Database Notification (N-020)
 *
 * Sent to users targeted by an admin announcement.
 * Delivers via push (WebPush) and database channels.
 * Email is handled separately by SystemAnnouncementMail.
 *
 * BR-315: All three channels used: push + DB + email
 * BR-316: Dispatched via queue (ShouldQueue via BasePushNotification)
 * BR-319: Subject: "DancyMeals Announcement"
 */
class SystemAnnouncementNotification extends BasePushNotification
{
    public function __construct(
        private Announcement $announcement,
    ) {}

    /**
     * Get the push notification title.
     *
     * BR-319: Title is "DancyMeals Announcement"
     */
    public function getTitle(object $notifiable): string
    {
        return __('DancyMeals Announcement');
    }

    /**
     * Get the push notification body text.
     *
     * First 160 chars of announcement content.
     */
    public function getBody(object $notifiable): string
    {
        return $this->announcement->getContentPreview(160);
    }

    /**
     * Get the URL to link to when the notification is clicked.
     *
     * Links to the user's notifications page.
     */
    public function getActionUrl(object $notifiable): string
    {
        return '/profile/notifications';
    }

    /**
     * Get additional data payload for the notification.
     *
     * @return array<string, mixed>
     */
    public function getData(object $notifiable): array
    {
        return [
            'announcement_id' => $this->announcement->id,
            'type' => 'system_announcement',
            'target_type' => $this->announcement->target_type,
        ];
    }

    /**
     * Get the notification tag for grouping.
     */
    public function getTag(object $notifiable): ?string
    {
        return 'announcement-'.$this->announcement->id;
    }
}
