<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
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
}
