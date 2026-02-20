<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ClientOrderListRequest;
use App\Services\ClientOrderService;

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
}
