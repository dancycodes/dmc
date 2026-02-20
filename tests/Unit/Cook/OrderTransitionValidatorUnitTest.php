<?php

/**
 * F-159: Order Status Transition Validation — Unit Tests
 *
 * Tests the OrderTransitionValidator service for comprehensive
 * status transition validation including:
 *
 * BR-200: Valid forward transition chain
 * BR-201: No state skipping
 * BR-202: No backward transitions (except admin override)
 * BR-203: Admin override requires reason and is logged
 * BR-204: Cancellation only from pending_payment, paid, confirmed
 * BR-205: Refunded only from cancelled or via admin resolution
 * BR-206: Delivery orders follow delivery path
 * BR-207: Pickup orders follow pickup path
 * BR-208: Cross-path transitions rejected
 * BR-209: Terminal states cannot transition (except admin override)
 * BR-210: Validation errors include current/attempted/next valid status
 * BR-211: All validation is server-side
 */

use App\Models\Order;
use App\Models\OrderStatusTransition;
use App\Services\OrderStatusService;
use App\Services\OrderTransitionValidator;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();
    test()->validator = app(OrderTransitionValidator::class);
});

// =============================================================================
// BR-200: Valid Forward Transitions
// =============================================================================

test('allows paid to confirmed transition', function () {
    $order = Order::factory()->paid()->make();

    $result = test()->validator->validate($order, Order::STATUS_CONFIRMED);

    expect($result['valid'])->toBeTrue();
});

test('allows confirmed to preparing transition', function () {
    $order = Order::factory()->confirmed()->make();

    $result = test()->validator->validate($order, Order::STATUS_PREPARING);

    expect($result['valid'])->toBeTrue();
});

test('allows preparing to ready transition', function () {
    $order = Order::factory()->preparing()->make();

    $result = test()->validator->validate($order, Order::STATUS_READY);

    expect($result['valid'])->toBeTrue();
});

test('allows ready to out_for_delivery for delivery orders', function () {
    $order = Order::factory()->ready()->delivery()->make();

    $result = test()->validator->validate($order, Order::STATUS_OUT_FOR_DELIVERY);

    expect($result['valid'])->toBeTrue();
});

test('allows ready to ready_for_pickup for pickup orders', function () {
    $order = Order::factory()->ready()->pickup()->make();

    $result = test()->validator->validate($order, Order::STATUS_READY_FOR_PICKUP);

    expect($result['valid'])->toBeTrue();
});

test('allows out_for_delivery to delivered transition', function () {
    $order = Order::factory()->outForDelivery()->make();

    $result = test()->validator->validate($order, Order::STATUS_DELIVERED);

    expect($result['valid'])->toBeTrue();
});

test('allows ready_for_pickup to picked_up transition', function () {
    $order = Order::factory()->readyForPickup()->make();

    $result = test()->validator->validate($order, Order::STATUS_PICKED_UP);

    expect($result['valid'])->toBeTrue();
});

test('allows delivered to completed transition', function () {
    $order = Order::factory()->delivered()->make();

    $result = test()->validator->validate($order, Order::STATUS_COMPLETED);

    expect($result['valid'])->toBeTrue();
});

test('allows picked_up to completed transition', function () {
    $order = Order::factory()->pickedUp()->make();

    $result = test()->validator->validate($order, Order::STATUS_COMPLETED);

    expect($result['valid'])->toBeTrue();
});

// =============================================================================
// BR-201: No State Skipping
// =============================================================================

test('rejects paid to preparing (skipping confirmed)', function () {
    $order = Order::factory()->paid()->make();

    $result = test()->validator->validate($order, Order::STATUS_PREPARING);

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toContain('Confirmed')
        ->and($result['next_valid_status'])->toBe(Order::STATUS_CONFIRMED);
});

test('rejects paid to ready (skipping multiple states)', function () {
    $order = Order::factory()->paid()->make();

    $result = test()->validator->validate($order, Order::STATUS_READY);

    expect($result['valid'])->toBeFalse()
        ->and($result['next_valid_status'])->toBe(Order::STATUS_CONFIRMED);
});

test('rejects confirmed to ready (skipping preparing)', function () {
    $order = Order::factory()->confirmed()->make();

    $result = test()->validator->validate($order, Order::STATUS_READY);

    expect($result['valid'])->toBeFalse()
        ->and($result['next_valid_status'])->toBe(Order::STATUS_PREPARING);
});

test('rejects preparing to out_for_delivery (skipping ready)', function () {
    $order = Order::factory()->preparing()->delivery()->make();

    $result = test()->validator->validate($order, Order::STATUS_OUT_FOR_DELIVERY);

    expect($result['valid'])->toBeFalse()
        ->and($result['next_valid_status'])->toBe(Order::STATUS_READY);
});

// =============================================================================
// BR-202: No Backward Transitions (except admin override)
// =============================================================================

test('rejects backward transition from preparing to confirmed', function () {
    $order = Order::factory()->preparing()->make();

    $result = test()->validator->validate($order, Order::STATUS_CONFIRMED);

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toContain('backward');
});

test('rejects backward transition from ready to paid', function () {
    $order = Order::factory()->ready()->make();

    $result = test()->validator->validate($order, Order::STATUS_PAID);

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toContain('backward');
});

test('rejects backward transition from out_for_delivery to ready', function () {
    $order = Order::factory()->outForDelivery()->make();

    $result = test()->validator->validate($order, Order::STATUS_READY);

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toContain('backward');
});

// =============================================================================
// BR-203: Admin Override for Backward Transitions
// =============================================================================

test('allows admin override backward transition with reason', function () {
    $order = Order::factory()->preparing()->make();

    $result = test()->validator->validate($order, Order::STATUS_CONFIRMED, [
        'admin_override' => true,
        'override_reason' => 'Dispute resolution - reverting order status',
    ]);

    expect($result['valid'])->toBeTrue();
});

test('rejects admin override without reason', function () {
    $order = Order::factory()->preparing()->make();

    $result = test()->validator->validate($order, Order::STATUS_CONFIRMED, [
        'admin_override' => true,
        'override_reason' => null,
    ]);

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toContain('reason is required');
});

test('rejects admin override with empty reason', function () {
    $order = Order::factory()->preparing()->make();

    $result = test()->validator->validate($order, Order::STATUS_CONFIRMED, [
        'admin_override' => true,
        'override_reason' => '',
    ]);

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toContain('reason is required');
});

test('admin override from completed backward is allowed with reason', function () {
    $order = Order::factory()->completed()->make();

    $result = test()->validator->validate($order, Order::STATUS_PREPARING, [
        'admin_override' => true,
        'override_reason' => 'Rare dispute resolution scenario',
    ]);

    expect($result['valid'])->toBeTrue();
});

// =============================================================================
// BR-204: Cancellation Validation
// =============================================================================

test('allows cancellation from pending_payment', function () {
    $order = Order::factory()->pendingPayment()->make();

    $result = test()->validator->validate($order, Order::STATUS_CANCELLED);

    expect($result['valid'])->toBeTrue();
});

test('allows cancellation from paid', function () {
    $order = Order::factory()->paid()->make();

    $result = test()->validator->validate($order, Order::STATUS_CANCELLED);

    expect($result['valid'])->toBeTrue();
});

test('allows cancellation from confirmed', function () {
    $order = Order::factory()->confirmed()->make();

    $result = test()->validator->validate($order, Order::STATUS_CANCELLED);

    expect($result['valid'])->toBeTrue();
});

test('rejects cancellation from preparing', function () {
    $order = Order::factory()->preparing()->make();

    $result = test()->validator->validate($order, Order::STATUS_CANCELLED);

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toContain('cannot be cancelled');
});

test('rejects cancellation from ready', function () {
    $order = Order::factory()->ready()->make();

    $result = test()->validator->validate($order, Order::STATUS_CANCELLED);

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toContain('cannot be cancelled');
});

test('rejects cancellation from out_for_delivery', function () {
    $order = Order::factory()->outForDelivery()->make();

    $result = test()->validator->validate($order, Order::STATUS_CANCELLED);

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toContain('cannot be cancelled');
});

test('rejects cancellation from completed', function () {
    $order = Order::factory()->completed()->make();

    $result = test()->validator->validate($order, Order::STATUS_CANCELLED);

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toContain('cannot be cancelled');
});

test('admin can cancel from preparing', function () {
    $order = Order::factory()->preparing()->make();

    $result = test()->validator->validateCancellationTransition($order, true);

    expect($result['valid'])->toBeTrue();
});

test('admin cannot cancel terminal orders', function () {
    $order = Order::factory()->completed()->make();

    $result = test()->validator->validateCancellationTransition($order, true);

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toContain('terminal state');
});

// =============================================================================
// BR-205: Refunded Status Validation
// =============================================================================

test('allows refunded from cancelled', function () {
    $order = Order::factory()->cancelled()->make();

    $result = test()->validator->validate($order, Order::STATUS_REFUNDED);

    expect($result['valid'])->toBeTrue();
});

test('rejects refunded from paid without admin override', function () {
    $order = Order::factory()->paid()->make();

    $result = test()->validator->validate($order, Order::STATUS_REFUNDED);

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toContain('Cancelled status or via admin resolution');
});

test('rejects refunded from completed without admin override', function () {
    $order = Order::factory()->completed()->make();

    $result = test()->validator->validate($order, Order::STATUS_REFUNDED);

    expect($result['valid'])->toBeFalse();
});

test('rejects refunded from preparing without admin override', function () {
    $order = Order::factory()->preparing()->make();

    $result = test()->validator->validate($order, Order::STATUS_REFUNDED);

    expect($result['valid'])->toBeFalse();
});

test('allows refunded via admin override from any state', function () {
    $order = Order::factory()->completed()->make();

    $result = test()->validator->validate($order, Order::STATUS_REFUNDED, [
        'admin_override' => true,
    ]);

    expect($result['valid'])->toBeTrue();
});

// =============================================================================
// BR-206 & BR-207: Delivery/Pickup Path Enforcement
// =============================================================================

test('delivery order follows delivery path after ready', function () {
    $order = Order::factory()->ready()->delivery()->make();

    $result = test()->validator->validate($order, Order::STATUS_OUT_FOR_DELIVERY);

    expect($result['valid'])->toBeTrue();
});

test('pickup order follows pickup path after ready', function () {
    $order = Order::factory()->ready()->pickup()->make();

    $result = test()->validator->validate($order, Order::STATUS_READY_FOR_PICKUP);

    expect($result['valid'])->toBeTrue();
});

// =============================================================================
// BR-208: Cross-Path Transitions Rejected
// =============================================================================

test('delivery order cannot transition to ready_for_pickup', function () {
    $order = Order::factory()->ready()->delivery()->make();

    $result = test()->validator->validate($order, Order::STATUS_READY_FOR_PICKUP);

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toContain('Delivery orders cannot use pickup');
});

test('pickup order cannot transition to out_for_delivery', function () {
    $order = Order::factory()->ready()->pickup()->make();

    $result = test()->validator->validate($order, Order::STATUS_OUT_FOR_DELIVERY);

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toContain('Pickup orders cannot use delivery');
});

test('delivery order cannot transition to picked_up', function () {
    $order = Order::factory()->outForDelivery()->make();

    $result = test()->validator->validatePathConsistency($order, Order::STATUS_PICKED_UP);

    expect($result['valid'])->toBeFalse();
});

test('pickup order cannot transition to delivered', function () {
    $order = Order::factory()->readyForPickup()->make();

    $result = test()->validator->validatePathConsistency($order, Order::STATUS_DELIVERED);

    expect($result['valid'])->toBeFalse();
});

// =============================================================================
// BR-209: Terminal States Cannot Transition
// =============================================================================

test('completed order cannot transition to any status', function () {
    $order = Order::factory()->completed()->make();

    $result = test()->validator->validate($order, Order::STATUS_PREPARING);

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toContain('terminal state');
});

test('cancelled order cannot transition forward', function () {
    $order = Order::factory()->cancelled()->make();

    // Cancelled to Confirmed should be blocked as terminal
    $result = test()->validator->validate($order, Order::STATUS_CONFIRMED);

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toContain('terminal state');
});

test('refunded order cannot transition', function () {
    $order = Order::factory()->refunded()->make();

    $result = test()->validator->validate($order, Order::STATUS_PAID);

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toContain('terminal state');
});

test('admin override allows transition from terminal state with reason', function () {
    $order = Order::factory()->completed()->make();

    $result = test()->validator->validate($order, Order::STATUS_PREPARING, [
        'admin_override' => true,
        'override_reason' => 'Dispute requires re-preparation',
    ]);

    expect($result['valid'])->toBeTrue();
});

// =============================================================================
// BR-210: Validation Error Response Format
// =============================================================================

test('validation error includes current attempted and next valid status', function () {
    $order = Order::factory()->paid()->make();

    $result = test()->validator->validate($order, Order::STATUS_PREPARING);

    expect($result)->toHaveKeys(['valid', 'message', 'current_status', 'attempted_status', 'next_valid_status'])
        ->and($result['current_status'])->toBe(Order::STATUS_PAID)
        ->and($result['attempted_status'])->toBe(Order::STATUS_PREPARING)
        ->and($result['next_valid_status'])->toBe(Order::STATUS_CONFIRMED);
});

test('backward transition error includes status labels in message', function () {
    $order = Order::factory()->preparing()->make();

    $result = test()->validator->validate($order, Order::STATUS_CONFIRMED);

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toContain('Preparing')
        ->and($result['message'])->toContain('Confirmed');
});

// =============================================================================
// Edge Cases
// =============================================================================

test('no-op transition returns valid for same status', function () {
    $order = Order::factory()->paid()->make();

    $result = test()->validator->validate($order, Order::STATUS_PAID);

    expect($result['valid'])->toBeTrue();
});

test('invalid target status is rejected', function () {
    $order = Order::factory()->paid()->make();

    $result = test()->validator->validate($order, 'nonexistent_status');

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toContain('Invalid target status');
});

test('pending payment can only go to paid or cancelled', function () {
    $order = Order::factory()->pendingPayment()->make();

    // Pending payment has no forward transition via cook (paid is set by webhook)
    $result = test()->validator->validate($order, Order::STATUS_CONFIRMED);

    expect($result['valid'])->toBeFalse();

    // But cancellation is allowed
    $cancelResult = test()->validator->validate($order, Order::STATUS_CANCELLED);
    expect($cancelResult['valid'])->toBeTrue();
});

// =============================================================================
// getValidNextStatuses()
// =============================================================================

test('getValidNextStatuses returns forward and cancel for paid order', function () {
    $order = Order::factory()->paid()->make();

    $statuses = test()->validator->getValidNextStatuses($order);

    expect($statuses)->toContain(Order::STATUS_CONFIRMED)
        ->toContain(Order::STATUS_CANCELLED);
});

test('getValidNextStatuses returns only forward for preparing order', function () {
    $order = Order::factory()->preparing()->make();

    $statuses = test()->validator->getValidNextStatuses($order);

    expect($statuses)->toContain(Order::STATUS_READY)
        ->not->toContain(Order::STATUS_CANCELLED);
});

test('getValidNextStatuses returns nothing for completed order', function () {
    $order = Order::factory()->completed()->make();

    $statuses = test()->validator->getValidNextStatuses($order);

    expect($statuses)->toBeEmpty();
});

test('getValidNextStatuses includes refunded for admin on cancelled order', function () {
    $order = Order::factory()->cancelled()->make();

    $statuses = test()->validator->getValidNextStatuses($order, isAdmin: true);

    expect($statuses)->toContain(Order::STATUS_REFUNDED);
});

// =============================================================================
// canCancel()
// =============================================================================

test('canCancel returns true for cancellable statuses', function () {
    foreach (Order::CANCELLABLE_STATUSES as $status) {
        $order = Order::factory()->make(['status' => $status]);

        expect(test()->validator->canCancel($order))->toBeTrue(
            "Expected canCancel to be true for status: {$status}"
        );
    }
});

test('canCancel returns false for non-cancellable statuses', function () {
    $nonCancellable = [
        Order::STATUS_PREPARING,
        Order::STATUS_READY,
        Order::STATUS_OUT_FOR_DELIVERY,
        Order::STATUS_DELIVERED,
        Order::STATUS_COMPLETED,
    ];

    foreach ($nonCancellable as $status) {
        $order = Order::factory()->make(['status' => $status]);

        expect(test()->validator->canCancel($order))->toBeFalse(
            "Expected canCancel to be false for status: {$status}"
        );
    }
});

test('canCancel returns true for admin on non-terminal non-cancellable statuses', function () {
    $order = Order::factory()->preparing()->make();

    expect(test()->validator->canCancel($order, isAdmin: true))->toBeTrue();
});

test('canCancel returns false for already cancelled order', function () {
    $order = Order::factory()->cancelled()->make();

    expect(test()->validator->canCancel($order))->toBeFalse();
    expect(test()->validator->canCancel($order, isAdmin: true))->toBeFalse();
});

// =============================================================================
// isBackwardTransition()
// =============================================================================

test('detects backward transitions correctly for delivery orders', function () {
    $order = Order::factory()->ready()->delivery()->make();

    expect(test()->validator->isBackwardTransition($order, Order::STATUS_PREPARING))->toBeTrue()
        ->and(test()->validator->isBackwardTransition($order, Order::STATUS_OUT_FOR_DELIVERY))->toBeFalse();
});

test('detects backward transitions correctly for pickup orders', function () {
    $order = Order::factory()->ready()->pickup()->make();

    expect(test()->validator->isBackwardTransition($order, Order::STATUS_PREPARING))->toBeTrue()
        ->and(test()->validator->isBackwardTransition($order, Order::STATUS_READY_FOR_PICKUP))->toBeFalse();
});

// =============================================================================
// getFullChainForOrder()
// =============================================================================

test('returns delivery chain for delivery orders', function () {
    $order = Order::factory()->delivery()->make();

    $chain = test()->validator->getFullChainForOrder($order);

    expect($chain)->toContain(Order::STATUS_OUT_FOR_DELIVERY)
        ->toContain(Order::STATUS_DELIVERED)
        ->not->toContain(Order::STATUS_READY_FOR_PICKUP)
        ->not->toContain(Order::STATUS_PICKED_UP);
});

test('returns pickup chain for pickup orders', function () {
    $order = Order::factory()->pickup()->make();

    $chain = test()->validator->getFullChainForOrder($order);

    expect($chain)->toContain(Order::STATUS_READY_FOR_PICKUP)
        ->toContain(Order::STATUS_PICKED_UP)
        ->not->toContain(Order::STATUS_OUT_FOR_DELIVERY)
        ->not->toContain(Order::STATUS_DELIVERED);
});

// =============================================================================
// OrderStatusService Integration (delegates to validator)
// =============================================================================

test('OrderStatusService updateStatus records admin override in transition', function () {
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
        'override_reason' => 'Dispute resolution',
    ]);

    expect($result['success'])->toBeTrue();

    $transition = OrderStatusTransition::where('order_id', $order->id)->latest()->first();
    expect($transition->is_admin_override)->toBeTrue()
        ->and($transition->override_reason)->toBe('Dispute resolution');
});

test('OrderStatusService updateStatus logs admin override in activity log', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->cook_id = $cook->id;
    $tenant->save();
    $order = Order::factory()->completed()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $service = app(OrderStatusService::class);
    $service->updateStatus($order, Order::STATUS_PREPARING, $cook, [
        'admin_override' => true,
        'override_reason' => 'Customer dispute requires re-processing',
    ]);

    $activity = \Spatie\Activitylog\Models\Activity::where('subject_id', $order->id)
        ->where('subject_type', Order::class)
        ->where('description', 'like', 'Admin override%')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toContain('Admin override')
        ->and($activity->properties['admin_override'])->toBeTrue()
        ->and($activity->properties['override_reason'])->toBe('Customer dispute requires re-processing');
});

test('OrderStatusService validateTransition delegates to OrderTransitionValidator', function () {
    $order = Order::factory()->paid()->make();
    $service = app(OrderStatusService::class);

    // Valid transition
    $result = $service->validateTransition($order, Order::STATUS_CONFIRMED);
    expect($result['valid'])->toBeTrue();

    // Invalid skip
    $result = $service->validateTransition($order, Order::STATUS_PREPARING);
    expect($result['valid'])->toBeFalse()
        ->and($result['next_valid_status'])->toBe(Order::STATUS_CONFIRMED);
});

// =============================================================================
// Order Model Constants
// =============================================================================

test('STATUSES array includes refunded', function () {
    expect(Order::STATUSES)->toContain(Order::STATUS_REFUNDED);
});

test('CANCELLABLE_STATUSES matches BR-204', function () {
    expect(Order::CANCELLABLE_STATUSES)->toBe([
        Order::STATUS_PENDING_PAYMENT,
        Order::STATUS_PAID,
        Order::STATUS_CONFIRMED,
    ]);
});

test('TERMINAL_STATUSES includes completed cancelled and refunded', function () {
    expect(Order::TERMINAL_STATUSES)->toContain(Order::STATUS_COMPLETED)
        ->toContain(Order::STATUS_CANCELLED)
        ->toContain(Order::STATUS_REFUNDED);
});

// =============================================================================
// OrderStatusTransition Model
// =============================================================================

test('OrderStatusTransition has admin override fields', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->cook_id = $cook->id;
    $tenant->save();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $transition = OrderStatusTransition::create([
        'order_id' => $order->id,
        'triggered_by' => $cook->id,
        'previous_status' => Order::STATUS_COMPLETED,
        'new_status' => Order::STATUS_PREPARING,
        'is_admin_override' => true,
        'override_reason' => 'Test override reason',
    ]);

    expect($transition->is_admin_override)->toBeTrue()
        ->and($transition->override_reason)->toBe('Test override reason');
});

test('OrderStatusTransition defaults is_admin_override to false', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->cook_id = $cook->id;
    $tenant->save();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $transition = OrderStatusTransition::create([
        'order_id' => $order->id,
        'triggered_by' => $cook->id,
        'previous_status' => Order::STATUS_PAID,
        'new_status' => Order::STATUS_CONFIRMED,
    ]);

    // Re-fetch to get DB defaults applied
    $transition->refresh();

    expect($transition->is_admin_override)->toBeFalse()
        ->and($transition->override_reason)->toBeNull();
});
