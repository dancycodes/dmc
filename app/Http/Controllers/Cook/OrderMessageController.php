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
}
