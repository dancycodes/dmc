<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CookOrderService;
use App\Services\OrderStatusService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Display the cook's order list page.
     *
     * F-155: Cook Order List View
     * BR-155: Orders are tenant-scoped.
     * BR-156: Paginated with default 20 per page.
     * BR-157: Default sort by date descending (newest first).
     * BR-162: Only users with can-manage-orders permission.
     */
    public function index(Request $request, CookOrderService $orderService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-162: Permission check
        if (! $user->can('can-manage-orders')) {
            abort(403);
        }

        $filters = [
            'search' => $request->input('search', ''),
            'status' => $request->input('status', ''),
            'date_from' => $request->input('date_from', ''),
            'date_to' => $request->input('date_to', ''),
            'sort' => $request->input('sort', 'created_at'),
            'direction' => $request->input('direction', 'desc'),
        ];

        $orders = $orderService->getOrderList($tenant, $filters);
        $summary = $orderService->getOrderSummary($tenant);
        $statusOptions = CookOrderService::getStatusFilterOptions();

        $data = [
            'orders' => $orders,
            'summary' => $summary,
            'statusOptions' => $statusOptions,
            'search' => $filters['search'],
            'status' => $filters['status'],
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
            'sort' => $filters['sort'],
            'direction' => $filters['direction'],
        ];

        // Handle Gale navigate requests (search/filter/sort triggers)
        if ($request->isGaleNavigate('order-list')) {
            return gale()->fragment('cook.orders.index', 'order-list-content', $data);
        }

        return gale()->view('cook.orders.index', $data, web: true);
    }

    /**
     * Display the cook's order detail page.
     *
     * F-156: Cook Order Detail View
     * BR-166: Order detail is tenant-scoped.
     * BR-175: Only users with manage-orders permission.
     * BR-167-BR-177: All order sections displayed.
     */
    public function show(Request $request, int $orderId, CookOrderService $orderService, OrderStatusService $statusService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-175: Permission check
        if (! $user->can('can-manage-orders')) {
            abort(403);
        }

        // BR-166: Tenant-scoped order lookup
        $order = Order::query()
            ->forTenant($tenant->id)
            ->findOrFail($orderId);

        $detail = $orderService->getOrderDetail($order);

        // F-157: Use transition table timeline when transitions exist, else fallback to activity log
        $transitionTimeline = $statusService->getTransitionTimeline($order);
        $statusTimeline = count($transitionTimeline) > 1
            ? $transitionTimeline
            : $detail['statusTimeline'];

        $nextStatus = $detail['nextStatus'];
        $nextStatusLabel = $nextStatus ? OrderStatusService::getActionLabel($nextStatus) : null;
        $requiresConfirmation = $nextStatus ? $statusService->requiresConfirmation($nextStatus) : false;
        $confirmationMessage = $nextStatus ? OrderStatusService::getConfirmationMessage($nextStatus) : '';

        $data = [
            'order' => $detail['order'],
            'items' => $detail['items'],
            'statusTimeline' => $statusTimeline,
            'nextStatus' => $nextStatus,
            'nextStatusLabel' => $nextStatusLabel,
            'requiresConfirmation' => $requiresConfirmation,
            'confirmationMessage' => $confirmationMessage,
            'paymentTransaction' => $detail['paymentTransaction'],
        ];

        // Handle Gale navigate request for timeline fragment refresh
        if ($request->isGaleNavigate('order-detail')) {
            return gale()->fragment('cook.orders.show', 'order-detail-content', $data);
        }

        return gale()->view('cook.orders.show', $data, web: true);
    }

    /**
     * Update the status of a single order.
     *
     * F-157: Single Order Status Update
     * BR-178: Only the next valid status is allowed.
     * BR-182: Validated server-side.
     * BR-183: Triggers notification to client.
     * BR-184: Logged via Spatie Activitylog.
     * BR-187: Only users with manage-orders permission.
     */
    public function updateStatus(Request $request, int $orderId, OrderStatusService $statusService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-187: Permission check
        if (! $user->can('can-manage-orders')) {
            abort(403);
        }

        // Tenant-scoped order lookup
        $order = Order::query()
            ->forTenant($tenant->id)
            ->findOrFail($orderId);

        // Get the target status from Gale state or request input
        $targetStatus = $request->isGale()
            ? $request->state('nextStatus')
            : $request->input('next_status');

        if (empty($targetStatus)) {
            $targetStatus = $order->getNextStatus();
        }

        if (! $targetStatus) {
            if ($request->isGale()) {
                return gale()->redirect('/')->back()->with('error', __('No valid status transition available.'));
            }

            return redirect()->back()->with('error', __('No valid status transition available.'));
        }

        $result = $statusService->updateStatus($order, $targetStatus, $user);

        if ($result['success']) {
            if ($request->isGale()) {
                return gale()->redirect('/dashboard/orders/'.$orderId)
                    ->with('success', $result['message']);
            }

            return redirect()->route('cook.orders.show', $orderId)
                ->with('success', $result['message']);
        }

        if ($request->isGale()) {
            return gale()->redirect('/')->back()->with('error', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }
}
