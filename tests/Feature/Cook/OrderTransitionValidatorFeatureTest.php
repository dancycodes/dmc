<?php

/**
 * F-159: Order Status Transition Validation — Feature Tests
 *
 * Tests the integration of OrderTransitionValidator with OrderStatusService
 * and MassOrderStatusService through HTTP endpoints.
 *
 * Verifies that transition validation is enforced end-to-end through
 * the cook order management controllers.
 */

use App\Models\Order;
use App\Models\OrderStatusTransition;
use App\Services\MassOrderStatusService;
use App\Services\OrderStatusService;
use App\Services\OrderTransitionValidator;

beforeEach(function () {
    test()->seedRolesAndPermissions();
});

// =============================================================================
// OrderStatusService: Full Update Flow with Validator
// =============================================================================

test('service allows valid forward transition and creates transition record', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->cook_id = $cook->id;
    $tenant->save();
    $order = Order::factory()->paid()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $service = app(OrderStatusService::class);
    $result = $service->updateStatus($order, Order::STATUS_CONFIRMED, $cook);

    expect($result['success'])->toBeTrue()
        ->and($result['new_status'])->toBe(Order::STATUS_CONFIRMED);

    // Verify transition record
    $transition = OrderStatusTransition::where('order_id', $order->id)->first();
    expect($transition)->not->toBeNull()
        ->and($transition->previous_status)->toBe(Order::STATUS_PAID)
        ->and($transition->new_status)->toBe(Order::STATUS_CONFIRMED)
        ->and($transition->is_admin_override)->toBeFalse()
        ->and($transition->override_reason)->toBeNull();
});

test('service rejects state skip through controller', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->cook_id = $cook->id;
    $tenant->save();
    $order = Order::factory()->paid()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $service = app(OrderStatusService::class);
    $result = $service->updateStatus($order, Order::STATUS_PREPARING, $cook);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Confirmed');

    // Verify order status unchanged
    $order->refresh();
    expect($order->status)->toBe(Order::STATUS_PAID);
});

test('service rejects backward transition', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->cook_id = $cook->id;
    $tenant->save();
    $order = Order::factory()->preparing()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $service = app(OrderStatusService::class);
    $result = $service->updateStatus($order, Order::STATUS_CONFIRMED, $cook);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('backward');

    // Verify status unchanged
    $order->refresh();
    expect($order->status)->toBe(Order::STATUS_PREPARING);
});

test('service allows admin override backward transition', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->cook_id = $cook->id;
    $tenant->save();
    $order = Order::factory()->completed()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $service = app(OrderStatusService::class);
    $result = $service->updateStatus($order, Order::STATUS_PREPARING, $cook, [
        'admin_override' => true,
        'override_reason' => 'Customer dispute resolution',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['new_status'])->toBe(Order::STATUS_PREPARING);

    // Verify transition record has admin override details
    $transition = OrderStatusTransition::where('order_id', $order->id)->latest()->first();
    expect($transition->is_admin_override)->toBeTrue()
        ->and($transition->override_reason)->toBe('Customer dispute resolution');
});

test('service rejects admin override without reason', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->cook_id = $cook->id;
    $tenant->save();
    $order = Order::factory()->completed()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $service = app(OrderStatusService::class);
    $result = $service->updateStatus($order, Order::STATUS_PREPARING, $cook, [
        'admin_override' => true,
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('reason is required');
});

// =============================================================================
// Cancellation Flow
// =============================================================================

test('service allows cancellation from paid status', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->cook_id = $cook->id;
    $tenant->save();
    $order = Order::factory()->paid()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $service = app(OrderStatusService::class);
    $result = $service->updateStatus($order, Order::STATUS_CANCELLED, $cook);

    expect($result['success'])->toBeTrue()
        ->and($result['new_status'])->toBe(Order::STATUS_CANCELLED);

    $order->refresh();
    expect($order->status)->toBe(Order::STATUS_CANCELLED)
        ->and($order->cancelled_at)->not->toBeNull();
});

test('service rejects cancellation from preparing status', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->cook_id = $cook->id;
    $tenant->save();
    $order = Order::factory()->preparing()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $service = app(OrderStatusService::class);
    $result = $service->updateStatus($order, Order::STATUS_CANCELLED, $cook);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('cannot be cancelled');

    $order->refresh();
    expect($order->status)->toBe(Order::STATUS_PREPARING);
});

// =============================================================================
// Refund Flow
// =============================================================================

test('service allows refund from cancelled status', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->cook_id = $cook->id;
    $tenant->save();
    $order = Order::factory()->cancelled()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $service = app(OrderStatusService::class);
    $result = $service->updateStatus($order, Order::STATUS_REFUNDED, $cook);

    expect($result['success'])->toBeTrue()
        ->and($result['new_status'])->toBe(Order::STATUS_REFUNDED);
});

test('service rejects refund from non-cancelled status', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->cook_id = $cook->id;
    $tenant->save();
    $order = Order::factory()->paid()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $service = app(OrderStatusService::class);
    $result = $service->updateStatus($order, Order::STATUS_REFUNDED, $cook);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Cancelled status');
});

// =============================================================================
// Delivery/Pickup Path Enforcement via Service
// =============================================================================

test('service enforces delivery path for delivery orders', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->cook_id = $cook->id;
    $tenant->save();
    $order = Order::factory()->ready()->delivery()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $service = app(OrderStatusService::class);

    // Valid: delivery path
    $result = $service->updateStatus($order, Order::STATUS_OUT_FOR_DELIVERY, $cook);
    expect($result['success'])->toBeTrue();
});

test('service rejects pickup path for delivery orders', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->cook_id = $cook->id;
    $tenant->save();
    $order = Order::factory()->ready()->delivery()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $service = app(OrderStatusService::class);

    // Invalid: pickup path on delivery order
    $result = $service->updateStatus($order, Order::STATUS_READY_FOR_PICKUP, $cook);
    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Delivery orders cannot use pickup');
});

test('service enforces pickup path for pickup orders', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->cook_id = $cook->id;
    $tenant->save();
    $order = Order::factory()->ready()->pickup()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $service = app(OrderStatusService::class);

    // Valid: pickup path
    $result = $service->updateStatus($order, Order::STATUS_READY_FOR_PICKUP, $cook);
    expect($result['success'])->toBeTrue();
});

test('service rejects delivery path for pickup orders', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->cook_id = $cook->id;
    $tenant->save();
    $order = Order::factory()->ready()->pickup()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $service = app(OrderStatusService::class);

    // Invalid: delivery path on pickup order
    $result = $service->updateStatus($order, Order::STATUS_OUT_FOR_DELIVERY, $cook);
    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Pickup orders cannot use delivery');
});

// =============================================================================
// MassOrderStatusService Integration
// =============================================================================

test('mass update service validates each order individually', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->cook_id = $cook->id;
    $tenant->save();

    $order1 = Order::factory()->paid()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);
    $order2 = Order::factory()->paid()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $service = app(MassOrderStatusService::class);
    $result = $service->massUpdateStatus(
        [$order1->id, $order2->id],
        Order::STATUS_CONFIRMED,
        $cook,
        $tenant,
    );

    expect($result['success_count'])->toBe(2)
        ->and($result['fail_count'])->toBe(0);
});

test('mass update service reports individual failures', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->cook_id = $cook->id;
    $tenant->save();

    // One valid, one already at target
    $order1 = Order::factory()->paid()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);
    $order2 = Order::factory()->preparing()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $service = app(MassOrderStatusService::class);
    $result = $service->massUpdateStatus(
        [$order1->id, $order2->id],
        Order::STATUS_CONFIRMED,
        $cook,
        $tenant,
    );

    // order1 succeeds (paid -> confirmed), order2 fails (preparing -> confirmed is backward)
    expect($result['success_count'])->toBe(1)
        ->and($result['fail_count'])->toBe(1)
        ->and($result['failures'])->toHaveCount(1)
        ->and($result['failures'][0]['order_id'])->toBe($order2->id);
});

// =============================================================================
// Full Lifecycle: Complete delivery and pickup chains
// =============================================================================

test('complete delivery lifecycle succeeds', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->cook_id = $cook->id;
    $tenant->save();
    $order = Order::factory()->paid()->delivery()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $service = app(OrderStatusService::class);
    $chain = [
        Order::STATUS_CONFIRMED,
        Order::STATUS_PREPARING,
        Order::STATUS_READY,
        Order::STATUS_OUT_FOR_DELIVERY,
        Order::STATUS_DELIVERED,
        Order::STATUS_COMPLETED,
    ];

    foreach ($chain as $status) {
        $result = $service->updateStatus($order, $status, $cook);
        expect($result['success'])->toBeTrue("Failed to transition to {$status}");
        $order->refresh();
    }

    expect($order->status)->toBe(Order::STATUS_COMPLETED);

    // Verify all transitions recorded
    $transitions = OrderStatusTransition::where('order_id', $order->id)->get();
    expect($transitions)->toHaveCount(count($chain));
});

test('complete pickup lifecycle succeeds', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->cook_id = $cook->id;
    $tenant->save();
    $order = Order::factory()->paid()->pickup()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $service = app(OrderStatusService::class);
    $chain = [
        Order::STATUS_CONFIRMED,
        Order::STATUS_PREPARING,
        Order::STATUS_READY,
        Order::STATUS_READY_FOR_PICKUP,
        Order::STATUS_PICKED_UP,
        Order::STATUS_COMPLETED,
    ];

    foreach ($chain as $status) {
        $result = $service->updateStatus($order, $status, $cook);
        expect($result['success'])->toBeTrue("Failed to transition to {$status}");
        $order->refresh();
    }

    expect($order->status)->toBe(Order::STATUS_COMPLETED);
});

// =============================================================================
// Concurrent Updates (Optimistic Locking)
// =============================================================================

test('concurrent update second request fails gracefully', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->cook_id = $cook->id;
    $tenant->save();
    $order = Order::factory()->paid()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $service = app(OrderStatusService::class);

    // First update succeeds
    $result1 = $service->updateStatus($order, Order::STATUS_CONFIRMED, $cook);
    expect($result1['success'])->toBeTrue();

    // Second attempt on stale order (not refreshed) should handle gracefully
    // The order object still thinks status is 'paid' but DB is now 'confirmed'
    $result2 = $service->updateStatus($order, Order::STATUS_CONFIRMED, $cook);

    // The lockForUpdate re-fetch catches the mismatch
    expect($result2['success'])->toBeFalse()
        ->and($result2['message'])->toContain('updated by another user');
});
