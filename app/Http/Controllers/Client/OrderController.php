<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ClientOrderListRequest;
use App\Models\Order;
use App\Services\ClientOrderService;
use App\Services\OrderCancellationService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Display the client's order list.
     *
     * F-160: Client Order List
     * BR-212: Shows all orders for the authenticated client across all tenants.
     * BR-213: Active orders pinned to the top.
     * BR-214: Past orders below active orders.
     * BR-219: Accessible from any domain (main or tenant).
     * BR-220: Authentication required.
     */
    public function index(ClientOrderListRequest $request, ClientOrderService $orderService): mixed
    {
        $user = $request->user();
        $filters = $request->validated();
        $statusFilter = $filters['status'] ?? '';

        // Scenario 3: When a status filter is active, disable pinning
        $isFiltered = ! empty($statusFilter);

        if ($isFiltered) {
            $activeOrders = collect();
            $pastOrders = $orderService->getFilteredOrders($user, $filters);
        } else {
            $activeOrders = $orderService->getActiveOrders($user);
            $pastOrders = $orderService->getPastOrders($user, $filters);
        }

        $activeCount = $orderService->getActiveOrderCount($user);
        $statusOptions = ClientOrderService::getStatusFilterOptions();

        $viewData = [
            'activeOrders' => $activeOrders,
            'pastOrders' => $pastOrders,
            'activeCount' => $activeCount,
            'statusFilter' => $statusFilter,
            'statusOptions' => $statusOptions,
            'isFiltered' => $isFiltered,
            'sort' => $filters['sort'] ?? 'created_at',
            'direction' => $filters['direction'] ?? 'desc',
        ];

        // Fragment-based partial update for Gale navigate
        if ($request->isGaleNavigate('orders')) {
            return gale()
                ->fragment('client.orders.index', 'orders-content', $viewData);
        }

        return gale()->view('client.orders.index', $viewData, web: true);
    }

    /**
     * Display the client's order detail with status tracking.
     *
     * F-161: Client Order Detail & Status Tracking
     * BR-222: Client can only view their own orders.
     * BR-223: Status updates pushed to client view in real-time via Gale SSE.
     * BR-224: Visual timeline shows all status transitions with timestamps.
     * BR-225: Cancel button visible when order is Paid/Confirmed AND within cancellation window.
     * BR-226: Countdown timer shows remaining cancellation time.
     * BR-227: Report a Problem link for delivered/picked up/completed statuses.
     * BR-228: Rating prompt for completed, unrated orders.
     * BR-229: Cook's WhatsApp number displayed for urgent contact.
     * BR-230: Payment details show method, amount, reference, status.
     * BR-231: Items show meal name, components, quantities, prices.
     * BR-232: Delivery orders show town, quarter, landmark, delivery fee.
     * BR-233: Pickup orders show pickup location name and address.
     * BR-234: All amounts in XAF format.
     * BR-235: All text uses __() localization.
     */
    public function show(Request $request, Order $order, ClientOrderService $orderService): mixed
    {
        // BR-222: Client can only view their own orders
        if ($order->client_id !== $request->user()->id) {
            abort(403, __('You are not authorized to view this order.'));
        }

        $detailData = $orderService->getOrderDetail($order);

        // Fragment-based partial update for status polling
        if ($request->isGaleNavigate('order-status')) {
            return gale()
                ->fragment('client.orders.show', 'order-detail-content', $detailData);
        }

        return gale()->view('client.orders.show', $detailData, web: true);
    }

    /**
     * Cancel the client's order.
     *
     * F-162: Order Cancellation
     * BR-236: Cancellation only for Paid or Confirmed orders.
     * BR-241: Server re-validates status AND time window before processing.
     * BR-242: Order status → Cancelled; set orders.cancelled_at.
     * BR-246: Client can only cancel their own orders.
     * BR-247: All user-facing text via __().
     */
    public function cancel(Request $request, Order $order, ClientOrderService $orderService, OrderCancellationService $cancellationService): mixed
    {
        // BR-246: Client can only cancel their own orders
        if ($order->client_id !== $request->user()->id) {
            abort(403, __('You are not authorized to cancel this order.'));
        }

        $result = $cancellationService->cancelOrder($order, $request->user());

        if (! $result['success']) {
            return gale()
                ->messages(['cancel' => $result['message']])
                ->dispatch('toast', ['type' => 'error', 'message' => $result['message']]);
        }

        // Reload the order to get fresh status for the redirect
        $order->refresh();

        return gale()
            ->redirect(url('/my-orders/'.$order->id))
            ->with('success', $result['message']);
    }

    /**
     * Refresh the order status data for real-time polling.
     *
     * F-161 BR-223: Status updates are pushed to the client's view in real-time.
     * Uses x-interval polling with Gale component state updates.
     */
    public function refreshStatus(Request $request, Order $order, ClientOrderService $orderService): mixed
    {
        // BR-222: Client can only view their own orders
        if ($order->client_id !== $request->user()->id) {
            abort(403);
        }

        $statusData = $orderService->getOrderStatusRefresh($order);

        return gale()
            ->componentState('order-tracker', [
                'currentStatus' => $statusData['status'],
                'currentStatusLabel' => $statusData['statusLabel'],
                'canCancel' => $statusData['canCancel'],
                'cancelSecondsRemaining' => $statusData['cancellationSecondsRemaining'],
                'canReport' => $statusData['canReport'],
                'hasComplaint' => $statusData['hasComplaint'],
                'canRate' => $statusData['canRate'],
                'rated' => $statusData['rated'],
                'submittedStars' => $statusData['submittedStars'],
                'submittedReview' => $statusData['submittedReview'],
            ])
            ->fragment('client.orders.show', 'status-timeline-section', [
                'statusTimeline' => $statusData['statusTimeline'],
                'order' => $order,
            ]);
    }
}
