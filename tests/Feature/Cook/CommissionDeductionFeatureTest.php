<?php

use App\Models\Order;
use App\Models\WalletTransaction;
use App\Services\OrderStatusService;

/**
 * F-175: Commission Deduction on Completion — Feature Tests
 *
 * Tests the integration of commission deduction with the order status
 * update flow (OrderStatusService -> CommissionDeductionService).
 */
beforeEach(function () {
    $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);

    $result = createTenantWithCook();
    $this->tenant = $result['tenant'];
    $this->cook = $result['cook'];
});

test('completing an order via OrderStatusService triggers commission deduction', function () {
    // Create an order in "delivered" status ready to be completed
    $order = Order::factory()->delivered()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'subtotal' => 5000,
        'delivery_fee' => 500,
        'grand_total' => 5500,
    ]);

    $statusService = app(OrderStatusService::class);
    $result = $statusService->updateStatus($order, Order::STATUS_COMPLETED, $this->cook);

    expect($result['success'])->toBeTrue()
        ->and($result['new_status'])->toBe(Order::STATUS_COMPLETED);

    // Verify commission was deducted
    $commissionTx = WalletTransaction::query()
        ->where('order_id', $order->id)
        ->where('type', WalletTransaction::TYPE_COMMISSION)
        ->first();

    expect($commissionTx)->not->toBeNull()
        ->and((float) $commissionTx->amount)->toBe(500.0);

    // Verify payment credit was created
    $creditTx = WalletTransaction::query()
        ->where('order_id', $order->id)
        ->where('type', WalletTransaction::TYPE_PAYMENT_CREDIT)
        ->first();

    expect($creditTx)->not->toBeNull()
        ->and((float) $creditTx->amount)->toBe(5000.0);

    // Verify order has commission fields
    $order->refresh();
    expect((float) $order->commission_amount)->toBe(500.0)
        ->and((float) $order->commission_rate)->toBe(10.0);
});

test('completing a pickup order via OrderStatusService triggers commission deduction', function () {
    $order = Order::factory()->pickedUp()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'subtotal' => 8000,
        'delivery_fee' => 0,
        'grand_total' => 8000,
    ]);

    $statusService = app(OrderStatusService::class);
    $result = $statusService->updateStatus($order, Order::STATUS_COMPLETED, $this->cook);

    expect($result['success'])->toBeTrue();

    $commissionTx = WalletTransaction::query()
        ->where('order_id', $order->id)
        ->where('type', WalletTransaction::TYPE_COMMISSION)
        ->first();

    expect((float) $commissionTx->amount)->toBe(800.0);

    $creditTx = WalletTransaction::query()
        ->where('order_id', $order->id)
        ->where('type', WalletTransaction::TYPE_PAYMENT_CREDIT)
        ->first();

    // Cook credit: (8000 - 800) + 0 = 7200
    expect((float) $creditTx->amount)->toBe(7200.0);
});

test('completing order with custom commission rate uses correct rate', function () {
    $this->tenant->setSetting('commission_rate', 15.0);
    $this->tenant->save();

    $order = Order::factory()->delivered()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'subtotal' => 10000,
        'delivery_fee' => 1000,
        'grand_total' => 11000,
    ]);

    $statusService = app(OrderStatusService::class);
    $statusService->updateStatus($order, Order::STATUS_COMPLETED, $this->cook);

    $order->refresh();
    expect((float) $order->commission_rate)->toBe(15.0)
        ->and((float) $order->commission_amount)->toBe(1500.0);
});

test('order clearance is created when order completes', function () {
    $order = Order::factory()->delivered()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'subtotal' => 5000,
        'delivery_fee' => 500,
        'grand_total' => 5500,
    ]);

    $statusService = app(OrderStatusService::class);
    $statusService->updateStatus($order, Order::STATUS_COMPLETED, $this->cook);

    $clearance = \App\Models\OrderClearance::query()
        ->where('order_id', $order->id)
        ->first();

    expect($clearance)->not->toBeNull()
        ->and($clearance->is_cleared)->toBeFalse()
        ->and((float) $clearance->amount)->toBe(5000.0);
});

test('non-completion status update does not trigger commission', function () {
    $order = Order::factory()->paid()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'subtotal' => 5000,
        'delivery_fee' => 0,
        'grand_total' => 5000,
    ]);

    $statusService = app(OrderStatusService::class);
    $statusService->updateStatus($order, Order::STATUS_CONFIRMED, $this->cook);

    $commissionCount = WalletTransaction::query()
        ->where('order_id', $order->id)
        ->where('type', WalletTransaction::TYPE_COMMISSION)
        ->count();

    expect($commissionCount)->toBe(0);
});

test('activity log entry created for commission deduction on completion', function () {
    $order = Order::factory()->delivered()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'subtotal' => 6000,
        'delivery_fee' => 0,
        'grand_total' => 6000,
    ]);

    $statusService = app(OrderStatusService::class);
    $statusService->updateStatus($order, Order::STATUS_COMPLETED, $this->cook);

    $logEntry = \Spatie\Activitylog\Models\Activity::query()
        ->where('log_name', 'commissions')
        ->where('description', 'commission_deducted')
        ->latest()
        ->first();

    expect($logEntry)->not->toBeNull()
        ->and((float) $logEntry->properties['commission_amount'])->toBe(600.0)
        ->and($logEntry->properties['order_number'])->toBe($order->order_number);
});
