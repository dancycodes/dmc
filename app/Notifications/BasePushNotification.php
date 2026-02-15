<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Base push notification class for DancyMeals.
 *
 * All push notifications extend this class. It provides a consistent
 * structure with title, body, icon, action URL, and data payload.
 * Subclasses override the abstract methods to customize content.
 *
 * Channels: WebPush (push) + database. Email is handled by separate
 * notification classes (F-015).
 */
abstract class BasePushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Get the notification channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class, 'database'];
    }

    /**
     * Get the push notification title.
     */
    abstract public function getTitle(object $notifiable): string;

    /**
     * Get the push notification body text.
     */
    abstract public function getBody(object $notifiable): string;

    /**
     * Get the URL the notification should link to when clicked.
     */
    abstract public function getActionUrl(object $notifiable): string;

    /**
     * Get the notification icon URL.
     * Defaults to the app icon. Override to customize.
     */
    public function getIcon(object $notifiable): string
    {
        return '/icons/icon-192x192.png';
    }

    /**
     * Get additional data payload for the notification.
     * Override in subclasses to add context-specific data.
     *
     * @return array<string, mixed>
     */
    public function getData(object $notifiable): array
    {
        return [];
    }

    /**
     * Get the notification tag for grouping/replacing notifications.
     * Override in subclasses for notification deduplication.
     */
    public function getTag(object $notifiable): ?string
    {
        return null;
    }

    /**
     * Build the WebPush representation of the notification.
     */
    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $message = (new WebPushMessage)
            ->title($this->getTitle($notifiable))
            ->body($this->getBody($notifiable))
            ->icon($this->getIcon($notifiable))
            ->data([
                'url' => $this->getActionUrl($notifiable),
                'notification_id' => $this->id,
                ...$this->getData($notifiable),
            ]);

        $tag = $this->getTag($notifiable);
        if ($tag !== null) {
            $message->tag($tag);
        }

        return $message;
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->getTitle($notifiable),
            'body' => $this->getBody($notifiable),
            'icon' => $this->getIcon($notifiable),
            'action_url' => $this->getActionUrl($notifiable),
            'data' => $this->getData($notifiable),
        ];
    }
}
