<?php

use App\Jobs\ProcessOrderRefund;
use App\Models\ClientWallet;
use App\Models\CookWallet;
use App\Models\Order;
use App\Models\WalletTransaction;
use App\Services\CookWalletService;
use App\Services\WalletRefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * F-163: Order Cancellation Refund Processing — Unit Tests
 *
 * Tests the ProcessOrderRefund job and related service methods.
 */
beforeEach(function () {
    $this->seedRolesAndPermissions();
});

// ─── ProcessOrderRefund Job ────────────────────────────────────────────────

test('job skips processing when order is already refunded (idempotency)', function () {
    ['tenant' => $tenant, 'cook' => $cook] = $this->createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = $this->createUserWithRole('client');

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_REFUNDED,
        'grand_total' => 5000,
    ]);

    $walletService = app(WalletRefundService::class);
    $cookWalletService = app(CookWalletService::class);

    $job = new ProcessOrderRefund($order->id, $client->id);
    $job->handle($walletService, $cookWalletService);

    // No client wallet transaction should be created
    expect(WalletTransaction::where('order_id', $order->id)
        ->where('type', WalletTransaction::TYPE_REFUND)
        ->count()
    )->toBe(0);
});

test('job skips processing when order is not in cancelled status', function () {
    ['tenant' => $tenant, 'cook' => $cook] = $this->createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = $this->createUserWithRole('client');

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_CONFIRMED,
        'grand_total' => 5000,
    ]);

    $walletService = app(WalletRefundService::class);
    $cookWalletService = app(CookWalletService::class);

    $job = new ProcessOrderRefund($order->id, $client->id);
    $job->handle($walletService, $cookWalletService);

    // Order status should not change
    $order->refresh();
    expect($order->status)->toBe(Order::STATUS_CONFIRMED);
});

test('job skips gracefully when order not found', function () {
    $client = $this->createUserWithRole('client');

    $walletService = app(WalletRefundService::class);
    $cookWalletService = app(CookWalletService::class);

    $job = new ProcessOrderRefund(999999, $client->id);

    // Should not throw
    $job->handle($walletService, $cookWalletService);

    expect(true)->toBeTrue();
});

test('job credits client wallet with full order amount', function () {
    ['tenant' => $tenant, 'cook' => $cook] = $this->createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = $this->createUserWithRole('client');

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

    $walletService = app(WalletRefundService::class);
    $cookWalletService = app(CookWalletService::class);

    $job = new ProcessOrderRefund($order->id, $client->id);
    $job->handle($walletService, $cookWalletService);

    // BR-248: Full order amount credited (subtotal + delivery fee)
    $clientWallet = ClientWallet::where('user_id', $client->id)->first();
    expect($clientWallet)->not->toBeNull();
    expect((float) $clientWallet->balance)->toBe(5000.0);
});

test('job creates client wallet refund transaction record', function () {
    ['tenant' => $tenant, 'cook' => $cook] = $this->createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = $this->createUserWithRole('client');

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_CANCELLED,
        'cancelled_at' => now(),
        'grand_total' => 5000,
    ]);

    $walletService = app(WalletRefundService::class);
    $cookWalletService = app(CookWalletService::class);

    $job = new ProcessOrderRefund($order->id, $client->id);
    $job->handle($walletService, $cookWalletService);

    // BR-252: Client wallet transaction (type: refund, credit)
    $transaction = WalletTransaction::where('order_id', $order->id)
        ->where('type', WalletTransaction::TYPE_REFUND)
        ->where('user_id', $client->id)
        ->first();

    expect($transaction)->not->toBeNull();
    expect((float) $transaction->amount)->toBe(5000.0);
    expect($transaction->status)->toBe('completed');
});

test('job transitions order status from cancelled to refunded', function () {
    ['tenant' => $tenant, 'cook' => $cook] = $this->createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = $this->createUserWithRole('client');

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_CANCELLED,
        'cancelled_at' => now(),
        'grand_total' => 5000,
    ]);

    $walletService = app(WalletRefundService::class);
    $cookWalletService = app(CookWalletService::class);

    $job = new ProcessOrderRefund($order->id, $client->id);
    $job->handle($walletService, $cookWalletService);

    $order->refresh();

    // BR-254: Order status transitions to Refunded
    expect($order->status)->toBe(Order::STATUS_REFUNDED);
    // BR-254: refunded_at timestamp is set
    expect($order->refunded_at)->not->toBeNull();
});

test('job decrements cook unwithdrawable balance', function () {
    ['tenant' => $tenant, 'cook' => $cook] = $this->createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    // Pre-seed the cook wallet with unwithdrawable balance
    CookWallet::create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
        'total_balance' => 5000,
        'withdrawable_balance' => 0,
        'unwithdrawable_balance' => 5000,
        'currency' => 'XAF',
    ]);

    $client = $this->createUserWithRole('client');

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_CANCELLED,
        'cancelled_at' => now(),
        'grand_total' => 5000,
    ]);

    $walletService = app(WalletRefundService::class);
    $cookWalletService = app(CookWalletService::class);

    $job = new ProcessOrderRefund($order->id, $client->id);
    $job->handle($walletService, $cookWalletService);

    $cookWallet = CookWallet::where('tenant_id', $tenant->id)->where('user_id', $cook->id)->first();

    // BR-250: Cook's unwithdrawable amount decremented
    expect((float) $cookWallet->unwithdrawable_balance)->toBe(0.0);
});

test('job creates cook order_cancelled transaction record', function () {
    ['tenant' => $tenant, 'cook' => $cook] = $this->createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    CookWallet::create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
        'total_balance' => 5000,
        'withdrawable_balance' => 0,
        'unwithdrawable_balance' => 5000,
        'currency' => 'XAF',
    ]);

    $client = $this->createUserWithRole('client');

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_CANCELLED,
        'cancelled_at' => now(),
        'grand_total' => 5000,
    ]);

    $walletService = app(WalletRefundService::class);
    $cookWalletService = app(CookWalletService::class);

    $job = new ProcessOrderRefund($order->id, $client->id);
    $job->handle($walletService, $cookWalletService);

    // BR-253: Cook wallet transaction (type: order_cancelled, debit from unwithdrawable)
    $cookTx = WalletTransaction::where('order_id', $order->id)
        ->where('type', WalletTransaction::TYPE_ORDER_CANCELLED)
        ->where('user_id', $cook->id)
        ->first();

    expect($cookTx)->not->toBeNull();
    expect((float) $cookTx->amount)->toBe(5000.0);
    expect($cookTx->status)->toBe('completed');
});

test('job handles free order (0 XAF) and still moves to Refunded', function () {
    ['tenant' => $tenant, 'cook' => $cook] = $this->createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = $this->createUserWithRole('client');

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_CANCELLED,
        'cancelled_at' => now(),
        'subtotal' => 0,
        'delivery_fee' => 0,
        'grand_total' => 0,
    ]);

    $walletService = app(WalletRefundService::class);
    $cookWalletService = app(CookWalletService::class);

    $job = new ProcessOrderRefund($order->id, $client->id);
    $job->handle($walletService, $cookWalletService);

    $order->refresh();

    // BR-254: Even free orders move to Refunded
    expect($order->status)->toBe(Order::STATUS_REFUNDED);
    expect($order->refunded_at)->not->toBeNull();
});

// ─── CookWalletService::decrementUnwithdrawableForCancellation ─────────────

test('decrementUnwithdrawableForCancellation creates transaction and recalculates balances', function () {
    ['tenant' => $tenant, 'cook' => $cook] = $this->createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $wallet = CookWallet::create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
        'total_balance' => 8000,
        'withdrawable_balance' => 3000,
        'unwithdrawable_balance' => 5000,
        'currency' => 'XAF',
    ]);

    $client = $this->createUserWithRole('client');
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_CANCELLED,
        'grand_total' => 5000,
    ]);

    $cookWalletService = app(CookWalletService::class);
    $result = $cookWalletService->decrementUnwithdrawableForCancellation($wallet, $order, 5000);

    expect($result['wallet'])->toBeInstanceOf(CookWallet::class);
    expect($result['transaction'])->toBeInstanceOf(WalletTransaction::class);
    expect($result['transaction']->type)->toBe(WalletTransaction::TYPE_ORDER_CANCELLED);
    expect((float) $result['transaction']->amount)->toBe(5000.0);
});

test('decrementUnwithdrawableForCancellation handles underflow gracefully', function () {
    ['tenant' => $tenant, 'cook' => $cook] = $this->createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    // Wallet with less than the order total in unwithdrawable
    $wallet = CookWallet::create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
        'total_balance' => 1000,
        'withdrawable_balance' => 0,
        'unwithdrawable_balance' => 1000,
        'currency' => 'XAF',
    ]);

    $client = $this->createUserWithRole('client');
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_CANCELLED,
        'grand_total' => 5000,
    ]);

    $cookWalletService = app(CookWalletService::class);

    // Should NOT throw — just log a warning
    $result = $cookWalletService->decrementUnwithdrawableForCancellation($wallet, $order, 5000);

    // Transaction is still created
    expect($result['transaction']->type)->toBe(WalletTransaction::TYPE_ORDER_CANCELLED);
    // Wallet balance capped at 0 (not negative)
    expect((float) $result['wallet']->unwithdrawable_balance)->toBeGreaterThanOrEqual(0);
});

// ─── WalletTransaction TYPE constant ──────────────────────────────────────

test('WalletTransaction TYPE_ORDER_CANCELLED is defined and is a debit type', function () {
    expect(WalletTransaction::TYPE_ORDER_CANCELLED)->toBe('order_cancelled');
    expect(in_array(WalletTransaction::TYPE_ORDER_CANCELLED, WalletTransaction::TYPES, true))->toBeTrue();

    $tx = new WalletTransaction(['type' => WalletTransaction::TYPE_ORDER_CANCELLED]);
    expect($tx->isDebit())->toBeTrue();
    expect($tx->isCredit())->toBeFalse();
});

// ─── Order model ───────────────────────────────────────────────────────────

test('Order model has refunded_at in fillable and casts as datetime', function () {
    $order = new Order;
    expect(in_array('refunded_at', $order->getFillable(), true))->toBeTrue();

    $order = Order::factory()->make(['refunded_at' => now()]);
    expect($order->refunded_at)->toBeInstanceOf(\Carbon\Carbon::class);
});
