<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderMessageService;
use Illuminate\Http\Request;

class OrderMessageController extends Controller
{
    /**
     * Display the message thread for an order (client view).
     *
     * F-188: Order Message Thread View
     * BR-239: Each order has exactly one message thread.
     * BR-240: Messages displayed in chronological order.
     * BR-241: Initial load shows most recent 20 messages.
     * BR-244: Thread accessible only by order's client, tenant's cook, and authorized managers.
     * BR-246: All user-facing text uses __() localization.
     */
    public function show(Request $request, Order $order, OrderMessageService $messageService): mixed
    {
        $user = $request->user();

        // BR-244: Only the order's client can view this via client route
        if ($order->client_id !== $user->id) {
            abort(403, __('You are not authorized to view this message thread.'));
        }

        $threadData = $messageService->getThreadData($order, $user);

        $cookName = $order->tenant?->name ?? __('Cook');
        $tenantUrl = $order->tenant?->getUrl() ?? '';

        $viewData = array_merge($threadData, [
            'order' => $order,
            'cookName' => $cookName,
            'tenantUrl' => $tenantUrl,
        ]);

        // Fragment-based partial update for older messages lazy loading
        if ($request->isGaleNavigate('messages')) {
            return gale()
                ->fragment('client.orders.messages', 'messages-thread', $viewData);
        }

        return gale()->view('client.orders.messages', $viewData, web: true);
    }

    /**
     * Load older messages (lazy loading on scroll to top).
     *
     * F-188 BR-242: Older messages load in batches of 20.
     */
    public function loadOlder(Request $request, Order $order, OrderMessageService $messageService): mixed
    {
        $user = $request->user();

        if ($order->client_id !== $user->id) {
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
}
