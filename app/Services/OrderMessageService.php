<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\User;

class OrderMessageService
{
    /**
     * Get paginated messages for an order thread (most recent first, reversed for display).
     *
     * F-188 BR-241: Initial load shows the most recent 20 messages.
     * F-188 BR-242: Older messages load in batches of 20.
     * F-188 BR-240: Messages displayed in chronological order (oldest first).
     *
     * @return array{messages: \Illuminate\Support\Collection, hasOlderMessages: bool, oldestMessageId: int|null, totalCount: int}
     */
    public function getThreadMessages(Order $order, ?int $beforeId = null): array
    {
        $query = $order->messages()
            ->with('sender')
            ->orderByDesc('id');

        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }

        $messages = $query->limit(OrderMessage::PER_PAGE + 1)->get();

        $hasOlderMessages = $messages->count() > OrderMessage::PER_PAGE;
        if ($hasOlderMessages) {
            $messages = $messages->take(OrderMessage::PER_PAGE);
        }

        // Reverse to chronological order (oldest first) for display
        $messages = $messages->reverse()->values();

        $oldestMessageId = $messages->first()?->id;

        return [
            'messages' => $messages,
            'hasOlderMessages' => $hasOlderMessages,
            'oldestMessageId' => $oldestMessageId,
            'totalCount' => $order->messages()->count(),
        ];
    }

    /**
     * Load older messages (for lazy loading on scroll to top).
     *
     * F-188 BR-242: Older messages load in batches of 20 without losing scroll position.
     *
     * @return array{messages: \Illuminate\Support\Collection, hasOlderMessages: bool, oldestMessageId: int|null}
     */
    public function getOlderMessages(Order $order, int $beforeId): array
    {
        return $this->getThreadMessages($order, $beforeId);
    }

    /**
     * Determine the sender role for the given user on this order.
     *
     * F-188 BR-244: Thread accessible only by order's client, tenant's cook, and authorized managers.
     */
    public function getSenderRole(Order $order, User $user): string
    {
        if ($order->client_id === $user->id) {
            return OrderMessage::ROLE_CLIENT;
        }

        // Check if user is a manager for this tenant
        if ($user->hasDirectPermission('can-manage-orders')) {
            // Cooks have all permissions via Gate::before; distinguish them
            if ($order->cook_id === $user->id) {
                return OrderMessage::ROLE_COOK;
            }

            return OrderMessage::ROLE_MANAGER;
        }

        return OrderMessage::ROLE_COOK;
    }

    /**
     * Format a message for display with relative timestamp.
     *
     * F-188 BR-247: Timestamps displayed in relative format.
     *
     * @return array{id: int, sender_name: string, role_label: string, body: string, is_mine: bool, created_at_formatted: string, created_at_full: string, created_at_iso: string}
     */
    public function formatMessage(OrderMessage $message, User $viewingUser): array
    {
        return [
            'id' => $message->id,
            'sender_name' => $message->sender_name,
            'role_label' => $message->role_label,
            'sender_role' => $message->sender_role,
            'body' => $message->body,
            'is_mine' => $message->sender_id === $viewingUser->id,
            'created_at_formatted' => $message->created_at->diffForHumans(),
            'created_at_full' => $message->created_at->format('M d, Y H:i'),
            'created_at_iso' => $message->created_at->toISOString(),
        ];
    }

    /**
     * Check if the message thread is read-only for this order.
     *
     * F-188 BR-245: Thread is read-only after order is Completed + 7 days or Cancelled.
     */
    public function isThreadReadOnly(Order $order): bool
    {
        if ($order->status === Order::STATUS_CANCELLED) {
            return true;
        }

        if ($order->status === Order::STATUS_REFUNDED) {
            return true;
        }

        if ($order->status === Order::STATUS_COMPLETED && $order->completed_at !== null) {
            return $order->completed_at->diffInDays(now()) >= 7;
        }

        return false;
    }

    /**
     * Send a new message in the order thread.
     *
     * F-189 BR-248: Messages are text-only.
     * F-189 BR-249: Max 500 characters.
     * F-189 BR-255: Whitespace-only messages stripped via trim.
     * F-189 BR-256: HTML entities escaped via e() in Blade — body stored as plain text.
     *
     * @return array{message: \App\Models\OrderMessage, formatted: array<string, mixed>}
     */
    public function sendMessage(Order $order, User $sender, string $body): array
    {
        $senderRole = $this->getSenderRole($order, $sender);

        $message = $order->messages()->create([
            'sender_id' => $sender->id,
            'sender_role' => $senderRole,
            'body' => $body,
        ]);

        $message->load('sender');

        return [
            'message' => $message,
            'formatted' => $this->formatMessage($message, $sender),
        ];
    }

    /**
     * Check if the order is available for messaging (not read-only).
     *
     * F-189 BR-253: Available while active and up to 7 days after Completed.
     * F-189 BR-254: Disabled for Cancelled or Refunded orders.
     */
    public function canSendMessage(Order $order): bool
    {
        return ! $this->isThreadReadOnly($order);
    }

    /**
     * Check if the user is authorized to view the thread for this order.
     *
     * F-188 BR-244: Thread accessible only by order's client, tenant's cook, and authorized managers.
     */
    public function canViewThread(Order $order, User $user): bool
    {
        // Client: can view their own order
        if ($order->client_id === $user->id) {
            return true;
        }

        // Cook: order must belong to their tenant
        if ($order->cook_id === $user->id) {
            return true;
        }

        // Manager: must have can-manage-orders and be in this tenant
        $tenant = tenant();
        if ($tenant && $order->tenant_id === $tenant->id && $user->hasDirectPermission('can-manage-orders')) {
            return true;
        }

        return false;
    }

    /**
     * Get complete thread data for blade view.
     *
     * @return array<string, mixed>
     */
    public function getThreadData(Order $order, User $viewingUser, ?int $beforeId = null): array
    {
        $result = $this->getThreadMessages($order, $beforeId);

        $formattedMessages = $result['messages']->map(
            fn (OrderMessage $msg) => $this->formatMessage($msg, $viewingUser)
        )->values();

        return [
            'messages' => $formattedMessages,
            'hasOlderMessages' => $result['hasOlderMessages'],
            'oldestMessageId' => $result['oldestMessageId'],
            'isReadOnly' => $this->isThreadReadOnly($order),
            'totalCount' => $result['totalCount'],
        ];
    }
}
