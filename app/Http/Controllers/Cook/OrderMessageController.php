<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderMessageService;
use Illuminate\Http\Request;

class OrderMessageController extends Controller
{
    /**
     * Display the message thread for an order (cook/manager dashboard view).
     *
     * F-188: Order Message Thread View
     * BR-239: Each order has exactly one message thread.
     * BR-244: Thread accessible only by order's client, tenant's cook, and authorized managers.
     * BR-246: All user-facing text uses __() localization.
     * Scenario 5: Managers with manage-orders permission see all messages.
     */
    public function show(Request $request, Order $order, OrderMessageService $messageService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-244: Only cook/manager for this tenant can view via dashboard route
        if (! $user->can('can-manage-orders')) {
            abort(403, __('You do not have permission to view order messages.'));
        }

        if ($tenant && $order->tenant_id !== $tenant->id) {
            abort(403, __('You are not authorized to view this message thread.'));
        }

        $threadData = $messageService->getThreadData($order, $user);

        $clientName = $order->client?->name ?? __('Unknown Client');

        $viewData = array_merge($threadData, [
            'order' => $order,
            'clientName' => $clientName,
        ]);

        // Fragment-based partial update for older messages lazy loading
        if ($request->isGaleNavigate('messages')) {
            return gale()
                ->fragment('cook.orders.messages', 'messages-thread', $viewData);
        }

        return gale()->view('cook.orders.messages', $viewData, web: true);
    }

    /**
     * Load older messages (lazy loading on scroll to top).
     *
     * F-188 BR-242: Older messages load in batches of 20.
     */
    public function loadOlder(Request $request, Order $order, OrderMessageService $messageService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        if (! $user->can('can-manage-orders')) {
            abort(403);
        }

        if ($tenant && $order->tenant_id !== $tenant->id) {
            abort(403);
        }

        $beforeId = (int) $request->state('oldestMessageId', 0);

        if ($beforeId <= 0) {
            return gale()->state('hasOlderMessages', false);
        }

        $result = $messageService->getOlderMessages($order, $beforeId);

        $formattedMessages = $result['messages']->map(
            fn ($msg) => $messageService->formatMessage($msg, $user)
        )->values();

        return gale()
            ->state('hasOlderMessages', $result['hasOlderMessages'])
            ->state('oldestMessageId', $result['oldestMessageId'])
            ->state('olderMessages', $formattedMessages->toArray());
    }

    /**
     * Send a message in the order thread (cook/manager sends to client).
     *
     * F-189 BR-249: Max 500 characters.
     * F-189 BR-251: Messages appear instantly for the sender via Gale.
     * F-189 BR-253: Available while order is active or within 7 days of completion.
     * F-189 BR-254: Disabled for Cancelled/Refunded orders.
     * F-189 BR-255: Whitespace-only messages cannot be sent.
     * F-189 BR-257: Rate-limited via 'messaging' limiter (10/min per user).
     */
    public function send(Request $request, Order $order, OrderMessageService $messageService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        if (! $user->can('can-manage-orders')) {
            abort(403, __('You do not have permission to send order messages.'));
        }

        if ($tenant && $order->tenant_id !== $tenant->id) {
            abort(403, __('You are not authorized to send messages for this order.'));
        }

        // BR-253, BR-254: Check availability window
        if (! $messageService->canSendMessage($order)) {
            return gale()->messages([
                'body' => __('Messaging is no longer available for this order.'),
            ]);
        }

        $validated = $request->validateState([
            'body' => ['required', 'string', 'max:500'],
        ]);

        // BR-255: Reject whitespace-only messages
        $body = trim($validated['body']);

        if ($body === '') {
            return gale()->messages([
                'body' => __('Please type a message before sending.'),
            ]);
        }

        $result = $messageService->sendMessage($order, $user, $body);

        return gale()
            ->state('newMessage', $result['formatted'])
            ->state('body', '');
    }
}
