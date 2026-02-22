<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\OrderMessage;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * F-190: New Message Push + Database Notification (N-016)
 *
 * Sent to the recipient(s) when a message is sent in an order thread.
 *
 * BR-259: Push + DB channels only (no email).
 * BR-260: Includes sender name, message preview (first 100 chars), order ID.
 * BR-261: Links directly to the order message thread.
 * BR-262: If recipient is viewing the thread, push is suppressed (DB always recorded).
 * BR-263: DB notification always recorded.
 * BR-266: Notifications are marked as read when user opens the thread.
 * BR-267: All text uses __() localization.
 */
class NewMessageNotification extends BasePushNotification
{
    /**
     * The type identifier for database queries.
     */
    public const TYPE = 'new_message';

    /**
     * @param  Order  $order  The order the message belongs to.
     * @param  OrderMessage  $message  The message that was sent.
     * @param  bool  $suppressPush  BR-262: True if the recipient is currently viewing the thread.
     */
    public function __construct(
        private Order $order,
        private OrderMessage $message,
        private bool $suppressPush = false,
    ) {}

    /**
     * Get the notification channels.
     *
     * BR-259: Push + DB channels only.
     * BR-262: Push is suppressed if recipient is viewing the thread.
     * BR-263: DB notification is always recorded.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($this->suppressPush) {
            // DB-only when recipient is actively viewing the thread
            return ['database'];
        }

        return [WebPushChannel::class, 'database'];
    }

    /**
     * Get the push notification title.
     *
     * BR-260: Notification includes sender name.
     */
    public function getTitle(object $notifiable): string
    {
        return __('New Message');
    }

    /**
     * Get the push notification body text.
     *
     * BR-260: Sender name + message preview (first 100 characters).
     */
    public function getBody(object $notifiable): string
    {
        $preview = $this->buildPreview();
        $senderName = $this->message->sender_name;

        return __(':name: :preview', [
            'name' => $senderName,
            'preview' => $preview,
        ]);
    }

    /**
     * Get the URL the notification links to.
     *
     * BR-261: Links directly to the order message thread.
     * The URL resolves to the correct context depending on who the recipient is.
     */
    public function getActionUrl(object $notifiable): string
    {
        $isClient = $this->order->client_id === $notifiable->id;

        if ($isClient) {
            return url('/my-orders/'.$this->order->id.'/messages');
        }

        // Cook or manager — link to dashboard thread
        $tenantUrl = $this->order->tenant?->getUrl() ?? '';

        return $tenantUrl.'/dashboard/orders/'.$this->order->id.'/messages';
    }

    /**
     * Get additional data payload for the notification.
     *
     * BR-260: Includes sender name, message preview, and order ID.
     *
     * @return array<string, mixed>
     */
    public function getData(object $notifiable): array
    {
        return [
            'type' => self::TYPE,
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'message_id' => $this->message->id,
            'sender_name' => $this->message->sender_name,
            'sender_role' => $this->message->sender_role,
            'preview' => $this->buildPreview(),
            'tenant_id' => $this->order->tenant_id,
        ];
    }

    /**
     * Get the notification tag for grouping (one active tag per order thread).
     */
    public function getTag(object $notifiable): ?string
    {
        return 'message-order-'.$this->order->id;
    }

    /**
     * Build a truncated message preview (first 100 characters).
     *
     * BR-260: Message preview limited to first 100 characters.
     */
    private function buildPreview(): string
    {
        $body = $this->message->body;

        if (mb_strlen($body) <= 100) {
            return $body;
        }

        return mb_substr($body, 0, 97).'...';
    }
}
