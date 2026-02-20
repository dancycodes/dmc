<?php

/**
 * F-158: Mass Order Status Update — Unit Tests
 *
 * Tests for MassOrderStatusService business logic.
 * BR-189: Only orders at the same status can be bulk-updated.
 * BR-191: Each order validated individually.
 * BR-192: Failed orders do not prevent successful orders from updating.
 * BR-193: Results reported per-order.
 * BR-195: Each status change logged individually.
 * BR-197: Only users with manage-orders permission.
 * BR-198: Mass completion triggers per-order handling.
 */

use App\Models\Order;
use App\Models\OrderStatusTransition;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MassOrderStatusService;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();
});

// =============================================
// MassOrderStatusService - validateSameStatus()
// =============================================

test('validateSameStatus returns valid when all orders share same status', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = User::factory()->create();

    $orders = Order::factory()->count(3)->paid()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    $service = new MassOrderStatusService(new OrderStatusService);
    $result = $service->validateSameStatus($orders->pluck('id')->toArray(), $tenant);

    expect($result['valid'])->toBeTrue();
    expect($result['common_status'])->toBe(Order::STATUS_PAID);
    expect($result['next_status'])->toBe(Order::STATUS_CONFIRMED);
});

test('validateSameStatus returns invalid when orders have mixed statuses (BR-189)', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = User::factory()->create();

    $paidOrder = Order::factory()->paid()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);
    $preparingOrder = Order::factory()->preparing()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    $service = new MassOrderStatusService(new OrderStatusService);
    $result = $service->validateSameStatus(
        [$paidOrder->id, $preparingOrder->id],
        $tenant
    );

    expect($result['valid'])->toBeFalse();
    expect($result['message'])->toContain('same status');
});

test('validateSameStatus returns invalid when no orders found', function () {
    ['tenant' => $tenant] = createTenantWithCook();

    $service = new MassOrderStatusService(new OrderStatusService);
    $result = $service->validateSameStatus([99999], $tenant);

    expect($result['valid'])->toBeFalse();
    expect($result['message'])->toContain('No valid orders found');
});

test('validateSameStatus returns invalid for terminal status orders', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = User::factory()->create();

    $orders = Order::factory()->count(2)->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_COMPLETED,
        'completed_at' => now(),
    ]);

    $service = new MassOrderStatusService(new OrderStatusService);
    $result = $service->validateSameStatus($orders->pluck('id')->toArray(), $tenant);

    expect($result['valid'])->toBeFalse();
    expect($result['next_status'])->toBeNull();
});

test('validateSameStatus detects mixed delivery methods for ready status', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = User::factory()->create();

    $deliveryOrder = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_READY,
        'delivery_method' => Order::METHOD_DELIVERY,
    ]);
    $pickupOrder = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_READY,
        'delivery_method' => Order::METHOD_PICKUP,
    ]);

    $service = new MassOrderStatusService(new OrderStatusService);
    $result = $service->validateSameStatus(
        [$deliveryOrder->id, $pickupOrder->id],
        $tenant
    );

    expect($result['valid'])->toBeFalse();
    expect($result['message'])->toContain('mixed delivery methods');
});

test('validateSameStatus works for ready status with same delivery method', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = User::factory()->create();

    $orders = Order::factory()->count(2)->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_READY,
        'delivery_method' => Order::METHOD_DELIVERY,
    ]);

    $service = new MassOrderStatusService(new OrderStatusService);
    $result = $service->validateSameStatus($orders->pluck('id')->toArray(), $tenant);

    expect($result['valid'])->toBeTrue();
    expect($result['next_status'])->toBe(Order::STATUS_OUT_FOR_DELIVERY);
});

// =============================================
// MassOrderStatusService - massUpdateStatus()
// =============================================

test('massUpdateStatus updates all valid orders successfully', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $user = $cook;
    $client = User::factory()->create();

    $orders = Order::factory()->count(3)->paid()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    $service = new MassOrderStatusService(new OrderStatusService);
    $result = $service->massUpdateStatus(
        $orders->pluck('id')->toArray(),
        Order::STATUS_CONFIRMED,
        $user,
        $tenant
    );

    expect($result['success_count'])->toBe(3);
    expect($result['fail_count'])->toBe(0);
    expect($result['total'])->toBe(3);
    expect($result['target_status'])->toBe(Order::STATUS_CONFIRMED);
    expect($result['failures'])->toBeEmpty();

    // Verify all orders were actually updated in the database
    foreach ($orders as $order) {
        expect($order->fresh()->status)->toBe(Order::STATUS_CONFIRMED);
    }
});

test('massUpdateStatus handles partial failure (BR-192)', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $user = $cook;
    $client = User::factory()->create();

    // Two paid orders (valid for confirmed transition)
    $paidOrders = Order::factory()->count(2)->paid()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    // One cancelled order (invalid for confirmed transition)
    $cancelledOrder = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_CANCELLED,
        'cancelled_at' => now(),
    ]);

    $allIds = $paidOrders->pluck('id')->merge([$cancelledOrder->id])->toArray();

    $service = new MassOrderStatusService(new OrderStatusService);
    $result = $service->massUpdateStatus(
        $allIds,
        Order::STATUS_CONFIRMED,
        $user,
        $tenant
    );

    expect($result['success_count'])->toBe(2);
    expect($result['fail_count'])->toBe(1);
    expect($result['failures'])->toHaveCount(1);
    expect($result['failures'][0]['order_id'])->toBe($cancelledOrder->id);
});

test('massUpdateStatus tracks missing order IDs', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $user = $cook;
    $client = User::factory()->create();

    $order = Order::factory()->paid()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    $service = new MassOrderStatusService(new OrderStatusService);
    $result = $service->massUpdateStatus(
        [$order->id, 99999],
        Order::STATUS_CONFIRMED,
        $user,
        $tenant
    );

    expect($result['success_count'])->toBe(1);
    expect($result['fail_count'])->toBe(1);
    expect($result['failures'][0]['order_id'])->toBe(99999);
});

test('massUpdateStatus creates transition records per order (BR-195)', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $user = $cook;
    $client = User::factory()->create();

    $orders = Order::factory()->count(3)->paid()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    $service = new MassOrderStatusService(new OrderStatusService);
    $service->massUpdateStatus(
        $orders->pluck('id')->toArray(),
        Order::STATUS_CONFIRMED,
        $user,
        $tenant
    );

    // Each order should have a transition record
    foreach ($orders as $order) {
        $transition = OrderStatusTransition::where('order_id', $order->id)->first();
        expect($transition)->not->toBeNull();
        expect($transition->previous_status)->toBe(Order::STATUS_PAID);
        expect($transition->new_status)->toBe(Order::STATUS_CONFIRMED);
        expect($transition->triggered_by)->toBe($user->id);
    }
});

test('massUpdateStatus creates activity log entries per order (BR-195)', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $user = $cook;
    $client = User::factory()->create();

    $orders = Order::factory()->count(2)->paid()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    $service = new MassOrderStatusService(new OrderStatusService);
    $service->massUpdateStatus(
        $orders->pluck('id')->toArray(),
        Order::STATUS_CONFIRMED,
        $user,
        $tenant
    );

    // Each order should have an activity log entry
    foreach ($orders as $order) {
        $activity = \Spatie\Activitylog\Models\Activity::query()
            ->where('subject_type', Order::class)
            ->where('subject_id', $order->id)
            ->where('causer_id', $user->id)
            ->where('event', 'updated')
            ->first();
        expect($activity)->not->toBeNull();
    }
});

test('massUpdateStatus isolates failures from successes (BR-192)', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $user = $cook;
    $client = User::factory()->create();

    // One paid order (valid)
    $paidOrder = Order::factory()->paid()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    // One order that is already confirmed (should report as no-op/success since it handles duplicate)
    $confirmedOrder = Order::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    $service = new MassOrderStatusService(new OrderStatusService);
    $result = $service->massUpdateStatus(
        [$paidOrder->id, $confirmedOrder->id],
        Order::STATUS_CONFIRMED,
        $user,
        $tenant
    );

    // Paid order should succeed, confirmed order should succeed (already in status)
    expect($result['success_count'])->toBe(2);
    expect($paidOrder->fresh()->status)->toBe(Order::STATUS_CONFIRMED);
});

test('massUpdateStatus all orders fail returns zero success count', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $user = $cook;
    $client = User::factory()->create();

    // Create completed orders - cannot transition further
    $orders = Order::factory()->count(2)->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_COMPLETED,
        'completed_at' => now(),
    ]);

    $service = new MassOrderStatusService(new OrderStatusService);
    $result = $service->massUpdateStatus(
        $orders->pluck('id')->toArray(),
        Order::STATUS_CONFIRMED,
        $user,
        $tenant
    );

    expect($result['success_count'])->toBe(0);
    expect($result['fail_count'])->toBe(2);
    expect($result['failures'])->toHaveCount(2);
});

test('massUpdateStatus respects tenant isolation', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $user = $cook;
    $client = User::factory()->create();

    // Order belongs to a different tenant
    $otherTenant = Tenant::factory()->create();
    $otherOrder = Order::factory()->paid()->create([
        'tenant_id' => $otherTenant->id,
        'client_id' => $client->id,
    ]);

    $service = new MassOrderStatusService(new OrderStatusService);
    $result = $service->massUpdateStatus(
        [$otherOrder->id],
        Order::STATUS_CONFIRMED,
        $user,
        $tenant
    );

    // Order should not be found (belongs to different tenant)
    expect($result['success_count'])->toBe(0);
    expect($result['fail_count'])->toBe(1);
    expect($otherOrder->fresh()->status)->toBe(Order::STATUS_PAID);
});

// =============================================
// MassOrderStatusService - getBulkActionLabel()
// =============================================

test('getBulkActionLabel returns formatted label with count and status', function () {
    $label = MassOrderStatusService::getBulkActionLabel(Order::STATUS_CONFIRMED, 5);

    expect($label)->toContain('5');
    expect($label)->toContain('Confirmed');
});

// =============================================
// Form Request Validation
// =============================================

test('MassOrderStatusUpdateRequest authorizes users with manage-orders permission', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();

    $request = new \App\Http\Requests\Cook\MassOrderStatusUpdateRequest;
    $request->setUserResolver(fn () => $cook);

    expect($request->authorize())->toBeTrue();
});

test('MassOrderStatusUpdateRequest rejects users without manage-orders permission', function () {
    $user = test()->createUserWithRole('client');

    $request = new \App\Http\Requests\Cook\MassOrderStatusUpdateRequest;
    $request->setUserResolver(fn () => $user);

    expect($request->authorize())->toBeFalse();
});

test('MassOrderStatusUpdateRequest validates required fields', function () {
    $request = new \App\Http\Requests\Cook\MassOrderStatusUpdateRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('order_ids');
    expect($rules)->toHaveKey('target_status');
    expect($rules['order_ids'])->toContain('required');
    expect($rules['target_status'])->toContain('required');
});

// =============================================
// Edge Cases
// =============================================

test('massUpdateStatus handles concurrent modification gracefully', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $user = $cook;
    $client = User::factory()->create();

    $order = Order::factory()->paid()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    // Simulate concurrent modification: update order before mass update
    $order->update(['status' => Order::STATUS_CONFIRMED]);

    $service = new MassOrderStatusService(new OrderStatusService);
    $result = $service->massUpdateStatus(
        [$order->id],
        Order::STATUS_CONFIRMED,
        $user,
        $tenant
    );

    // Should handle as already-in-status (success, not error)
    expect($result['success_count'])->toBe(1);
});

test('massUpdateStatus with empty order IDs returns failures for all', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $user = $cook;

    $service = new MassOrderStatusService(new OrderStatusService);
    $result = $service->massUpdateStatus(
        [],
        Order::STATUS_CONFIRMED,
        $user,
        $tenant
    );

    expect($result['success_count'])->toBe(0);
    expect($result['fail_count'])->toBe(0);
    expect($result['total'])->toBe(0);
});

test('massUpdateStatus returns correct target status label', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $user = $cook;
    $client = User::factory()->create();

    $order = Order::factory()->paid()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    $service = new MassOrderStatusService(new OrderStatusService);
    $result = $service->massUpdateStatus(
        [$order->id],
        Order::STATUS_CONFIRMED,
        $user,
        $tenant
    );

    expect($result['target_status_label'])->toBe('Confirmed');
});

test('massUpdateStatus handles preparing to ready transition', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $user = $cook;
    $client = User::factory()->create();

    $orders = Order::factory()->count(2)->preparing()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    $service = new MassOrderStatusService(new OrderStatusService);
    $result = $service->massUpdateStatus(
        $orders->pluck('id')->toArray(),
        Order::STATUS_READY,
        $user,
        $tenant
    );

    expect($result['success_count'])->toBe(2);
    expect($result['fail_count'])->toBe(0);

    foreach ($orders as $order) {
        expect($order->fresh()->status)->toBe(Order::STATUS_READY);
    }
});
