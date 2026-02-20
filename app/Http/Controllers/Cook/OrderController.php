<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CookOrderService;
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
    public function show(Request $request, int $orderId, CookOrderService $orderService): mixed
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

        $data = [
            'order' => $detail['order'],
            'items' => $detail['items'],
            'statusTimeline' => $detail['statusTimeline'],
            'nextStatus' => $detail['nextStatus'],
            'nextStatusLabel' => $detail['nextStatusLabel'],
            'paymentTransaction' => $detail['paymentTransaction'],
        ];

        return gale()->view('cook.orders.show', $data, web: true);
    }
}
