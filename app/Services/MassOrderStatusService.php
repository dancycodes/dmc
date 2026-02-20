<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * F-158: Service layer for mass order status updates.
 *
 * Handles bulk status transitions by delegating to OrderStatusService
 * for individual order validation and update.
 *
 * BR-189: Only orders at the same current status can be bulk-updated together.
 * BR-191: Each order is validated individually against F-159 transition rules.
 * BR-192: Failed orders do not prevent successful orders from being updated.
 * BR-193: Results are reported per-order: success count and individual failure reasons.
 * BR-194: Each successful status change triggers a client notification.
 * BR-195: Each status change is logged individually via Spatie Activitylog.
 * BR-198: Mass completion triggers commission deduction and withdrawable timer for each order.
 */
class MassOrderStatusService
{
    public function __construct(
        private OrderStatusService $statusService,
    ) {}

    /**
     * Execute a mass status update on a collection of orders.
     *
     * BR-192: Failed orders do not prevent successful orders from being updated.
     * BR-193: Results reported per-order.
     *
     * @param  array<int>  $orderIds
     * @return array{success_count: int, fail_count: int, total: int, target_status: string, target_status_label: string, failures: array<array{order_id: int, order_number: string, reason: string}>}
     */
    public function massUpdateStatus(array $orderIds, string $targetStatus, User $user, Tenant $tenant): array
    {
        $orders = Order::query()
            ->forTenant($tenant->id)
            ->whereIn('id', $orderIds)
            ->get();

        $successCount = 0;
        $failures = [];

        foreach ($orders as $order) {
            $result = $this->statusService->updateStatus($order, $targetStatus, $user);

            if ($result['success']) {
                $successCount++;
            } else {
                $failures[] = [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'reason' => $result['message'],
                ];
            }
        }

        // Track orders that were not found (IDs provided but not in tenant)
        $foundIds = $orders->pluck('id')->map(fn ($id) => (int) $id)->toArray();
        $missingIds = array_diff($orderIds, $foundIds);

        foreach ($missingIds as $missingId) {
            $failures[] = [
                'order_id' => $missingId,
                'order_number' => __('Unknown'),
                'reason' => __('Order not found.'),
            ];
        }

        return [
            'success_count' => $successCount,
            'fail_count' => count($failures),
            'total' => count($orderIds),
            'target_status' => $targetStatus,
            'target_status_label' => Order::getStatusLabel($targetStatus),
            'failures' => $failures,
        ];
    }

    /**
     * Validate that all selected orders share the same status.
     *
     * BR-189: Only orders at the same current status can be bulk-updated together.
     *
     * @param  array<int>  $orderIds
     * @return array{valid: bool, message: string, common_status: ?string, next_status: ?string}
     */
    public function validateSameStatus(array $orderIds, Tenant $tenant): array
    {
        $orders = Order::query()
            ->forTenant($tenant->id)
            ->whereIn('id', $orderIds)
            ->get();

        if ($orders->isEmpty()) {
            return [
                'valid' => false,
                'message' => __('No valid orders found.'),
                'common_status' => null,
                'next_status' => null,
            ];
        }

        $statuses = $orders->pluck('status')->unique();

        if ($statuses->count() > 1) {
            return [
                'valid' => false,
                'message' => __('Selected orders must be at the same status.'),
                'common_status' => null,
                'next_status' => null,
            ];
        }

        $commonStatus = $statuses->first();

        // For the "ready" status, check if all orders share the same delivery method
        // since the next status depends on delivery_method
        if ($commonStatus === Order::STATUS_READY) {
            $deliveryMethods = $orders->pluck('delivery_method')->unique();
            if ($deliveryMethods->count() > 1) {
                return [
                    'valid' => false,
                    'message' => __('Selected orders have mixed delivery methods. Please update delivery and pickup orders separately.'),
                    'common_status' => $commonStatus,
                    'next_status' => null,
                ];
            }
        }

        // Get the next status from the first order (all should be the same)
        $nextStatus = $orders->first()->getNextStatus();

        if (! $nextStatus) {
            return [
                'valid' => false,
                'message' => __('No valid transition available for the selected orders.'),
                'common_status' => $commonStatus,
                'next_status' => null,
            ];
        }

        return [
            'valid' => true,
            'message' => '',
            'common_status' => $commonStatus,
            'next_status' => $nextStatus,
        ];
    }

    /**
     * Get the bulk action label for a given target status.
     *
     * @return string The action label for the bulk operation.
     */
    public static function getBulkActionLabel(string $targetStatus, int $count): string
    {
        $statusLabel = Order::getStatusLabel($targetStatus);

        return __('Move :count orders to :status', [
            'count' => $count,
            'status' => $statusLabel,
        ]);
    }
}
