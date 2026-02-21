<?php

use App\Jobs\ProcessOrderRefund;
use App\Mail\RefundCreditedMail;
use App\Models\ClientWallet;
use App\Models\CookWallet;
use App\Models\Order;
use App\Models\OrderStatusTransition;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/**
 * F-163: Order Cancellation Refund Processing — Feature Tests
 *
 * Tests the full integration of the refund flow, including job dispatching,
 * wallet updates, status transitions, and notification delivery.
 */
beforeEach(function () {
    $this->seedRolesAndPermissions();
});

// ─── Job dispatch from OrderCancellationService ───────────────────────────

test('OrderCancellationService dispatches ProcessOrderRefund job on cancellation', function () {
    Queue::fake();

    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = createUser('client');

    // Give tenant a cancellation window setting
    $settings = $tenant->settings ?? [];
    $settings['cancellation_window_minutes'] = 60;
    $tenant->update(['settings' => $settings]);

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_PAID,
        'paid_at' => now(),
        'cancellation_window_minutes' => 60,
        'grand_total' => 5000,
    ]);

    $service = app(\App\Services\OrderCancellationService::class);
    $result = $service->cancelOrder($order, $client);

    expect($result['success'])->toBeTrue();

    // ProcessOrderRefund job should be queued
    Queue::assertPushed(ProcessOrderRefund::class, function ($job) use ($order, $client) {
        return $job->orderId === $order->id && $job->clientId === $client->id;
    });
});

// ─── Full refund flow integration ─────────────────────────────────────────

test('full refund flow credits client wallet and sets order to Refunded', function () {
    Mail::fake();
    Notification::fake();

    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = createUser('client');

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_CANCELLED,
        'cancelled_at' => now(),
        'subtotal' => 4500,
        'delivery_fee' => 500,
        'grand_total' => 5000,
    ]);

    $walletService = app(\App\Services\WalletRefundService::class);
    $cookWalletService = app(\App\Services\CookWalletService::class);

    $job = new ProcessOrderRefund($order->id, $client->id);
    $job->handle($walletService, $cookWalletService);

    // Client wallet credited
    $wallet = ClientWallet::where('user_id', $client->id)->first();
    expect((float) $wallet->balance)->toBe(5000.0);

    // Order status updated
    $order->refresh();
    expect($order->status)->toBe(Order::STATUS_REFUNDED);
    expect($order->refunded_at)->not->toBeNull();
});

test('refund creates status transition record from cancelled to refunded', function () {
    Mail::fake();
    Notification::fake();

    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = createUser('client');

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_CANCELLED,
        'cancelled_at' => now(),
        'grand_total' => 5000,
    ]);

    $walletService = app(\App\Services\WalletRefundService::class);
    $cookWalletService = app(\App\Services\CookWalletService::class);

    $job = new ProcessOrderRefund($order->id, $client->id);
    $job->handle($walletService, $cookWalletService);

    $transition = OrderStatusTransition::where('order_id', $order->id)
        ->where('previous_status', Order::STATUS_CANCELLED)
        ->where('new_status', Order::STATUS_REFUNDED)
        ->first();

    expect($transition)->not->toBeNull();
    expect($transition->is_admin_override)->toBeFalse();
});

test('refund sends email notification to client', function () {
    Mail::fake();
    Notification::fake();

    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = createUser('client');

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_CANCELLED,
        'cancelled_at' => now(),
        'grand_total' => 5000,
    ]);

    $walletService = app(\App\Services\WalletRefundService::class);
    $cookWalletService = app(\App\Services\CookWalletService::class);

    $job = new ProcessOrderRefund($order->id, $client->id);
    $job->handle($walletService, $cookWalletService);

    // BR-255: Client receives email notification (N-008)
    Mail::assertQueued(RefundCreditedMail::class);
});

test('refund updates cook wallet unwithdrawable balance', function () {
    Mail::fake();
    Notification::fake();

    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    // Pre-create cook wallet with funds
    CookWallet::create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
        'total_balance' => 5000,
        'withdrawable_balance' => 0,
        'unwithdrawable_balance' => 5000,
        'currency' => 'XAF',
    ]);

    $client = createUser('client');

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_CANCELLED,
        'cancelled_at' => now(),
        'grand_total' => 5000,
    ]);

    $walletService = app(\App\Services\WalletRefundService::class);
    $cookWalletService = app(\App\Services\CookWalletService::class);

    $job = new ProcessOrderRefund($order->id, $client->id);
    $job->handle($walletService, $cookWalletService);

    $cookWallet = CookWallet::where('tenant_id', $tenant->id)->where('user_id', $cook->id)->first();

    // BR-250: Cook unwithdrawable_amount decremented
    expect((float) $cookWallet->unwithdrawable_balance)->toBe(0.0);

    // BR-253: Cook wallet transaction created
    $cookTx = WalletTransaction::where('order_id', $order->id)
        ->where('type', WalletTransaction::TYPE_ORDER_CANCELLED)
        ->first();
    expect($cookTx)->not->toBeNull();
});

test('duplicate job call does not create duplicate refund (idempotency)', function () {
    Mail::fake();
    Notification::fake();

    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = createUser('client');

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_CANCELLED,
        'cancelled_at' => now(),
        'grand_total' => 5000,
    ]);

    $walletService = app(\App\Services\WalletRefundService::class);
    $cookWalletService = app(\App\Services\CookWalletService::class);

    $job = new ProcessOrderRefund($order->id, $client->id);
    $job->handle($walletService, $cookWalletService);

    // Second call — order is now Refunded
    $job->handle($walletService, $cookWalletService);

    // Only one client refund transaction should exist
    $refundCount = WalletTransaction::where('order_id', $order->id)
        ->where('type', WalletTransaction::TYPE_REFUND)
        ->where('user_id', $client->id)
        ->count();

    expect($refundCount)->toBe(1);

    // Client wallet balance should only be 5000, not 10000
    $wallet = ClientWallet::where('user_id', $client->id)->first();
    expect((float) $wallet->balance)->toBe(5000.0);
});
