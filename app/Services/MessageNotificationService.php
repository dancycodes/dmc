<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * F-190: Message Notification Service
 *
 * Orchestrates push and database notification dispatch when a message is sent
 * in an order thread. Handles recipient resolution, push suppression for
 * active viewers, and notification read marking.
 *
 * BR-259: Push + DB channels only (no email).
 * BR-262: Push suppressed when recipient is viewing the thread (via cache flag).
 * BR-263: DB notification always recorded.
 * BR-264: Client sends → notify cook + managers; Cook/manager sends → notify client.
 * BR-265: Cook/manager sends → client only.
 */
class MessageNotificationService
{
    /**
     * Cache key prefix for thread-viewing status tracking.
     *
     * BR-262: Used to detect if a recipient is currently viewing the thread
     * so push notification can be suppressed.
     */
    public const VIEWING_CACHE_PREFIX = 'message-thread-viewing';

    /**
     * How long (seconds) the "currently viewing" flag remains active.
     *
     * The flag is refreshed on every show() call. If the user hasn't
     * loaded the thread for longer than this TTL, push resumes.
     */
    public const VIEWING_TTL_SECONDS = 60;

    /**
     * Dispatch notifications after a message is sent.
     *
     * BR-264: Client sends → cook + managers.
     * BR-265: Cook/manager sends → client only.
     * BR-262/BR-263: Push suppressed for viewers; DB always recorded.
     */
    public function notifyNewMessage(Order $order, OrderMessage $message): void
    {
        $senderRole = $message->sender_role;

        if ($senderRole === OrderMessage::ROLE_CLIENT) {
            $recipients = $this->resolveCookAndManagerRecipients($order);
        } else {
            // Cook or manager sent → notify client only
            $recipients = $this->resolveClientRecipient($order);
        }

        foreach ($recipients as $recipient) {
            // BR-263 edge case: do not notify the sender themselves
            if ($recipient->id === $message->sender_id) {
                continue;
            }

            $this->sendNotification($recipient, $order, $message);
        }
    }

    /**
     * Mark all unread message notifications for this order as read.
     *
     * BR-266: Notifications are marked as read when the user opens the message thread.
     */
    public function markThreadNotificationsRead(Order $order, User $user): void
    {
        try {
            DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $user->id)
                ->whereNull('read_at')
                ->whereRaw("data::jsonb->>'type' = ?", [NewMessageNotification::TYPE])
                ->whereRaw("(data::jsonb->>'order_id')::int = ?", [$order->id])
                ->update(['read_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('F-190: Failed to mark thread notifications as read', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Record that a user is currently viewing the message thread.
     *
     * BR-262: Sets a short-lived cache flag so the notification service
     * can suppress push when the user has the thread open.
     */
    public function markUserViewingThread(Order $order, User $user): void
    {
        $key = $this->buildViewingCacheKey($user->id, $order->id);
        Cache::put($key, true, self::VIEWING_TTL_SECONDS);
    }

    /**
     * Check if a user is currently viewing the message thread.
     *
     * BR-262: Returns true if the user has the thread open (within the TTL).
     */
    public function isUserViewingThread(int $userId, int $orderId): bool
    {
        $key = $this->buildViewingCacheKey($userId, $orderId);

        return (bool) Cache::get($key, false);
    }

    /**
     * BR-264: Resolve the cook and all managers with can-manage-orders permission
     * for the tenant associated with the given order.
     *
     * @return array<User>
     */
    public function resolveCookAndManagerRecipients(Order $order): array
    {
        $recipients = [];
        $seenIds = [];

        // Add the cook
        $cook = $order->cook;
        if ($cook) {
            $recipients[] = $cook;
            $seenIds[] = $cook->id;
        }

        // Add tenant managers with can-manage-orders, scoped to this tenant
        try {
            $tenant = $order->tenant;
            if ($tenant) {
                $managers = User::query()
                    ->join('tenant_managers', 'users.id', '=', 'tenant_managers.user_id')
                    ->where('tenant_managers.tenant_id', '=', $tenant->id)
                    ->whereNotIn('users.id', $seenIds)
                    ->select('users.*')
                    ->get();

                foreach ($managers as $manager) {
                    if ($manager->hasDirectPermission('can-manage-orders')) {
                        $recipients[] = $manager;
                        $seenIds[] = $manager->id;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('F-190: Failed to resolve manager recipients', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $recipients;
    }

    /**
     * BR-265: Resolve the client as the sole recipient.
     *
     * @return array<User>
     */
    public function resolveClientRecipient(Order $order): array
    {
        $client = $order->client;

        return $client ? [$client] : [];
    }

    /**
     * Send the notification to a single recipient.
     *
     * BR-262: Checks the viewing cache to decide whether to suppress push.
     * BR-263: DB notification is always dispatched regardless.
     */
    private function sendNotification(User $recipient, Order $order, OrderMessage $message): void
    {
        try {
            $suppressPush = $this->isUserViewingThread($recipient->id, $order->id);

            $recipient->notify(new NewMessageNotification($order, $message, $suppressPush));
        } catch (\Throwable $e) {
            Log::warning('F-190: Notification dispatch failed', [
                'order_id' => $order->id,
                'message_id' => $message->id,
                'recipient_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build the cache key for a user's thread-viewing status.
     */
    private function buildViewingCacheKey(int $userId, int $orderId): string
    {
        return self::VIEWING_CACHE_PREFIX.':'.$userId.':'.$orderId;
    }
}
