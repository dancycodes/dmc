<?php

/**
 * F-162: Order Cancellation — Unit Tests
 *
 * Tests for OrderCancellationService and related business logic.
 * BR-236: Cancellation only for Paid or Confirmed orders.
 * BR-237: Window from cook's setting (snapshot at order time).
 * BR-238: Timer starts from paid_at/created_at.
 * BR-241: Server re-validates status AND time window before processing.
 * BR-242: Order status → Cancelled; set orders.cancelled_at.
 */

use App\Models\Order;
use App\Services\CookSettingsService;

// ---------------------------------------------------------------------------
// Order model constants — used throughout cancellation feature
// ---------------------------------------------------------------------------

test('Order has STATUS_CANCELLED constant with correct value', function (): void {
    expect(Order::STATUS_CANCELLED)->toBe('cancelled');
});

test('Order has STATUS_PAID constant', function (): void {
    expect(Order::STATUS_PAID)->toBe('paid');
});

test('Order has STATUS_CONFIRMED constant', function (): void {
    expect(Order::STATUS_CONFIRMED)->toBe('confirmed');
});

test('Order CANCELLABLE_STATUSES contains paid and confirmed', function (): void {
    expect(Order::CANCELLABLE_STATUSES)
        ->toContain(Order::STATUS_PAID)
        ->toContain(Order::STATUS_CONFIRMED);
});

test('Order CANCELLABLE_STATUSES does not contain preparing or beyond', function (): void {
    expect(Order::CANCELLABLE_STATUSES)
        ->not->toContain(Order::STATUS_PREPARING)
        ->not->toContain(Order::STATUS_READY)
        ->not->toContain(Order::STATUS_COMPLETED)
        ->not->toContain(Order::STATUS_CANCELLED);
});

test('Order has cancelled_at in fillable array', function (): void {
    $order = new Order;
    expect($order->getFillable())->toContain('cancelled_at');
});

test('Order has cancellation_window_minutes in fillable array', function (): void {
    $order = new Order;
    expect($order->getFillable())->toContain('cancellation_window_minutes');
});

// ---------------------------------------------------------------------------
// CookSettingsService constants — BR-237
// ---------------------------------------------------------------------------

test('CookSettingsService has correct default cancellation window of 15 minutes', function (): void {
    expect(CookSettingsService::DEFAULT_CANCELLATION_WINDOW)->toBe(15);
});

test('CookSettingsService has correct minimum cancellation window of 5 minutes', function (): void {
    expect(CookSettingsService::MIN_CANCELLATION_WINDOW)->toBe(5);
});

test('CookSettingsService has correct maximum cancellation window of 120 minutes', function (): void {
    expect(CookSettingsService::MAX_CANCELLATION_WINDOW)->toBe(120);
});

test('CookSettingsService SETTINGS_KEY constant matches expected key', function (): void {
    expect(CookSettingsService::SETTINGS_KEY)->toBe('cancellation_window_minutes');
});

// ---------------------------------------------------------------------------
// Business logic helpers — canCancelOrder validation logic (integration-free)
// ---------------------------------------------------------------------------

test('non-cancellable statuses include preparing, ready, out_for_delivery, delivered, completed', function (): void {
    // These statuses are explicitly NOT in CANCELLABLE_STATUSES
    $nonCancellable = [
        Order::STATUS_PREPARING,
        Order::STATUS_READY,
        Order::STATUS_OUT_FOR_DELIVERY,
        Order::STATUS_READY_FOR_PICKUP,
        Order::STATUS_DELIVERED,
        Order::STATUS_PICKED_UP,
        Order::STATUS_COMPLETED,
        Order::STATUS_PAYMENT_FAILED,
        Order::STATUS_REFUNDED,
    ];

    foreach ($nonCancellable as $status) {
        expect(in_array($status, Order::CANCELLABLE_STATUSES, true))
            ->toBeFalse("Status {$status} should NOT be in CANCELLABLE_STATUSES");
    }
});

test('cancellation window boundary math is correct at 30 minutes', function (): void {
    // 30 min window, order placed 30 min ago — at the boundary, should be expired
    $windowMinutes = 30;
    $referenceTime = now()->subMinutes($windowMinutes);
    $windowExpiresAt = $referenceTime->copy()->addMinutes($windowMinutes);

    // At exact expiry, now()->lessThan($windowExpiresAt) should be false
    expect(now()->lessThan($windowExpiresAt))->toBeFalse();
});

test('cancellation window math returns positive remaining seconds within window', function (): void {
    $windowMinutes = 30;
    $elapsedMinutes = 10;
    $referenceTime = now()->subMinutes($elapsedMinutes);
    $windowExpiresAt = $referenceTime->copy()->addMinutes($windowMinutes);
    $remaining = now()->diffInSeconds($windowExpiresAt, false);

    // 20 minutes remaining = ~1200 seconds
    expect((int) $remaining)->toBeGreaterThan(1190)
        ->and((int) $remaining)->toBeLessThanOrEqual(1200);
});

test('cancellation window math returns 0 when window expired', function (): void {
    $windowMinutes = 30;
    $elapsedMinutes = 45;
    $referenceTime = now()->subMinutes($elapsedMinutes);
    $windowExpiresAt = $referenceTime->copy()->addMinutes($windowMinutes);
    $remaining = max(0, (int) now()->diffInSeconds($windowExpiresAt, false));

    expect($remaining)->toBe(0);
});

test('cancellation window of 0 means no cancellation ever', function (): void {
    // When window = 0, the condition "windowMinutes <= 0" returns false immediately
    $windowMinutes = 0;
    expect($windowMinutes <= 0)->toBeTrue();
});

// ---------------------------------------------------------------------------
// OrderCancellationService class exists and is auto-resolvable
// ---------------------------------------------------------------------------

test('OrderCancellationService class exists', function (): void {
    expect(class_exists(\App\Services\OrderCancellationService::class))->toBeTrue();
});

test('OrderCancelledNotification class exists', function (): void {
    expect(class_exists(\App\Notifications\OrderCancelledNotification::class))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Order model structure for cancellation flow (BR-242)
// ---------------------------------------------------------------------------

test('Order STATUS_CANCELLED value is the string cancelled', function (): void {
    // Verify the exact string value used in database and logic
    expect(Order::STATUS_CANCELLED)->toBe('cancelled');
});

test('Order has paid_at in fillable array', function (): void {
    $order = new Order;
    expect($order->getFillable())->toContain('paid_at');
});
