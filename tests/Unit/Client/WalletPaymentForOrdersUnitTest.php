<?php

/**
 * F-168: Client Wallet Payment for Orders — Unit Tests
 *
 * Tests wallet payment logic: partial wallet deduction, reversal on failure,
 * full wallet payment, checkout service wallet option building, and validation.
 */

use App\Models\ClientWallet;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\PaymentService;
use App\Services\PlatformSettingService;
use App\Services\WalletPaymentService;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->platformSettingService = Mockery::mock(PlatformSettingService::class);
    $this->paymentService = Mockery::mock(PaymentService::class);
    $this->cartService = Mockery::mock(CartService::class);
    $this->checkoutService = Mockery::mock(CheckoutService::class);
    $this->webhookService = Mockery::mock(WebhookService::class);

    $this->walletPaymentService = new WalletPaymentService(
        $this->platformSettingService,
        $this->paymentService,
        $this->cartService,
        $this->checkoutService,
        $this->webhookService,
    );

    $this->cook = User::factory()->create();
    $this->tenant = Tenant::factory()->create(['cook_id' => $this->cook->id]);
    $this->client = User::factory()->create();
});

// --- Partial Wallet Deduction Tests ---

test('deductWalletForPartialPayment deducts wallet balance and returns remainder', function () {
    // Arrange: client has 3000 XAF, order is 5000 XAF
    $wallet = ClientWallet::create([
        'user_id' => $this->client->id,
        'balance' => 3000,
        'currency' => 'XAF',
    ]);

    $order = Order::factory()->create([
        'client_id' => $this->client->id,
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'grand_total' => 5000,
        'status' => Order::STATUS_PENDING_PAYMENT,
    ]);

    $this->platformSettingService->shouldReceive('isWalletEnabled')->andReturn(true);

    // Act
    $result = $this->walletPaymentService->deductWalletForPartialPayment($order, $this->client, $this->tenant);

    // Assert
    expect($result['success'])->toBeTrue();
    expect($result['wallet_amount'])->toBe(3000.0);
    expect($result['remainder'])->toBe(2000.0);
    expect($result['error'])->toBeNull();

    // Wallet balance should be 0
    $wallet->refresh();
    expect((float) $wallet->balance)->toBe(0.0);

    // Order wallet_amount updated
    $order->refresh();
    expect((float) $order->wallet_amount)->toBe(3000.0);

    // Wallet transaction created
    $transaction = WalletTransaction::where('order_id', $order->id)
        ->where('type', WalletTransaction::TYPE_WALLET_PAYMENT)
        ->first();
    expect($transaction)->not->toBeNull();
    expect((float) $transaction->amount)->toBe(3000.0);
    expect((float) $transaction->balance_before)->toBe(3000.0);
    expect((float) $transaction->balance_after)->toBe(0.0);
});

test('deductWalletForPartialPayment fails when wallet is disabled', function () {
    ClientWallet::create([
        'user_id' => $this->client->id,
        'balance' => 3000,
        'currency' => 'XAF',
    ]);

    $order = Order::factory()->create([
        'client_id' => $this->client->id,
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'grand_total' => 5000,
        'status' => Order::STATUS_PENDING_PAYMENT,
    ]);

    $this->platformSettingService->shouldReceive('isWalletEnabled')->andReturn(false);

    $result = $this->walletPaymentService->deductWalletForPartialPayment($order, $this->client, $this->tenant);

    expect($result['success'])->toBeFalse();
    expect($result['wallet_amount'])->toBe(0);
    expect($result['remainder'])->toBe(5000.0);
});

test('deductWalletForPartialPayment fails when wallet has zero balance', function () {
    ClientWallet::create([
        'user_id' => $this->client->id,
        'balance' => 0,
        'currency' => 'XAF',
    ]);

    $order = Order::factory()->create([
        'client_id' => $this->client->id,
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'grand_total' => 5000,
        'status' => Order::STATUS_PENDING_PAYMENT,
    ]);

    $this->platformSettingService->shouldReceive('isWalletEnabled')->andReturn(true);

    $result = $this->walletPaymentService->deductWalletForPartialPayment($order, $this->client, $this->tenant);

    expect($result['success'])->toBeFalse();
});

test('deductWalletForPartialPayment balance never goes negative', function () {
    // Balance is 1 XAF, order is 5000 XAF
    $wallet = ClientWallet::create([
        'user_id' => $this->client->id,
        'balance' => 1,
        'currency' => 'XAF',
    ]);

    $order = Order::factory()->create([
        'client_id' => $this->client->id,
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'grand_total' => 5000,
        'status' => Order::STATUS_PENDING_PAYMENT,
    ]);

    $this->platformSettingService->shouldReceive('isWalletEnabled')->andReturn(true);

    $result = $this->walletPaymentService->deductWalletForPartialPayment($order, $this->client, $this->tenant);

    expect($result['success'])->toBeTrue();
    expect($result['wallet_amount'])->toBe(1.0);
    expect($result['remainder'])->toBe(4999.0);

    $wallet->refresh();
    expect((float) $wallet->balance)->toBeGreaterThanOrEqual(0);
});

// --- Wallet Reversal Tests ---

test('reverseWalletDeduction restores wallet balance', function () {
    $wallet = ClientWallet::create([
        'user_id' => $this->client->id,
        'balance' => 0,
        'currency' => 'XAF',
    ]);

    $order = Order::factory()->create([
        'client_id' => $this->client->id,
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'grand_total' => 5000,
        'wallet_amount' => 3000,
        'status' => Order::STATUS_PAYMENT_FAILED,
    ]);

    $reversed = $this->walletPaymentService->reverseWalletDeduction($order, $this->client);

    expect($reversed)->toBeTrue();

    $wallet->refresh();
    expect((float) $wallet->balance)->toBe(3000.0);

    $order->refresh();
    expect((float) $order->wallet_amount)->toBe(0.0);

    // Refund transaction created
    $refund = WalletTransaction::where('order_id', $order->id)
        ->where('type', WalletTransaction::TYPE_REFUND)
        ->first();
    expect($refund)->not->toBeNull();
    expect((float) $refund->amount)->toBe(3000.0);
});

test('reverseWalletDeduction does nothing when wallet_amount is zero', function () {
    $order = Order::factory()->create([
        'client_id' => $this->client->id,
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'grand_total' => 5000,
        'wallet_amount' => 0,
        'status' => Order::STATUS_PAYMENT_FAILED,
    ]);

    $reversed = $this->walletPaymentService->reverseWalletDeduction($order, $this->client);

    expect($reversed)->toBeTrue();

    // No refund transaction should be created
    $refundCount = WalletTransaction::where('order_id', $order->id)
        ->where('type', WalletTransaction::TYPE_REFUND)
        ->count();
    expect($refundCount)->toBe(0);
});

// --- Order Model Tests ---

test('Order model has wallet_amount in fillable and casts', function () {
    $order = Order::factory()->create([
        'client_id' => $this->client->id,
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'grand_total' => 5000,
        'wallet_amount' => 2500.50,
    ]);

    $order->refresh();
    expect($order->wallet_amount)->not->toBeNull();
    // Check the cast produces a numeric/decimal value
    expect((float) $order->wallet_amount)->toBe(2500.50);
});

test('Order getPaymentProviderLabel returns partial wallet labels', function () {
    $order = new Order;

    $order->payment_provider = 'wallet_mtn_momo';
    expect($order->getPaymentProviderLabel())->toBe(__('Wallet + MTN MoMo'));

    $order->payment_provider = 'wallet_orange_money';
    expect($order->getPaymentProviderLabel())->toBe(__('Wallet + Orange Money'));

    $order->payment_provider = 'wallet';
    expect($order->getPaymentProviderLabel())->toBe(__('Wallet Balance'));
});

// --- CheckoutService Wallet Option Tests ---

test('getWalletOption returns partial_available when balance is less than total', function () {
    $checkoutService = app(CheckoutService::class);

    // Create wallet with balance < order total
    ClientWallet::create([
        'user_id' => $this->client->id,
        'balance' => 3000,
        'currency' => 'XAF',
    ]);

    // Enable wallet
    $mock = Mockery::mock(PlatformSettingService::class);
    $mock->shouldReceive('isWalletEnabled')->andReturn(true);
    app()->instance(PlatformSettingService::class, $mock);

    $options = $checkoutService->getPaymentOptions($this->tenant->id, $this->client->id, 5000);
    $wallet = $options['wallet'];

    expect($wallet['visible'])->toBeTrue();
    expect($wallet['enabled'])->toBeTrue();
    expect($wallet['balance'])->toBe(3000);
    expect($wallet['sufficient'])->toBeFalse();
    expect($wallet['partial_available'])->toBeTrue();
    expect($wallet['remainder'])->toBe(2000);
});

test('getWalletOption hides wallet when balance is zero', function () {
    $checkoutService = app(CheckoutService::class);

    ClientWallet::create([
        'user_id' => $this->client->id,
        'balance' => 0,
        'currency' => 'XAF',
    ]);

    $mock = Mockery::mock(PlatformSettingService::class);
    $mock->shouldReceive('isWalletEnabled')->andReturn(true);
    app()->instance(PlatformSettingService::class, $mock);

    $options = $checkoutService->getPaymentOptions($this->tenant->id, $this->client->id, 5000);
    $wallet = $options['wallet'];

    expect($wallet['visible'])->toBeFalse();
    expect($wallet['partial_available'])->toBeFalse();
});

test('getWalletOption shows sufficient when balance covers full total', function () {
    $checkoutService = app(CheckoutService::class);

    ClientWallet::create([
        'user_id' => $this->client->id,
        'balance' => 10000,
        'currency' => 'XAF',
    ]);

    $mock = Mockery::mock(PlatformSettingService::class);
    $mock->shouldReceive('isWalletEnabled')->andReturn(true);
    app()->instance(PlatformSettingService::class, $mock);

    $options = $checkoutService->getPaymentOptions($this->tenant->id, $this->client->id, 5000);
    $wallet = $options['wallet'];

    expect($wallet['visible'])->toBeTrue();
    expect($wallet['sufficient'])->toBeTrue();
    expect($wallet['partial_available'])->toBeFalse();
    expect($wallet['remainder'])->toBe(0);
});

test('getWalletOption hides when admin disables wallet', function () {
    $checkoutService = app(CheckoutService::class);

    ClientWallet::create([
        'user_id' => $this->client->id,
        'balance' => 5000,
        'currency' => 'XAF',
    ]);

    $mock = Mockery::mock(PlatformSettingService::class);
    $mock->shouldReceive('isWalletEnabled')->andReturn(false);
    app()->instance(PlatformSettingService::class, $mock);

    $options = $checkoutService->getPaymentOptions($this->tenant->id, $this->client->id, 5000);
    $wallet = $options['wallet'];

    expect($wallet['visible'])->toBeFalse();
    expect($wallet['enabled'])->toBeFalse();
});

// --- Validation Tests ---

test('validatePaymentSelection accepts partial wallet with mobile money', function () {
    $checkoutService = app(CheckoutService::class);

    ClientWallet::create([
        'user_id' => $this->client->id,
        'balance' => 3000,
        'currency' => 'XAF',
    ]);

    $mock = Mockery::mock(PlatformSettingService::class);
    $mock->shouldReceive('isWalletEnabled')->andReturn(true);
    app()->instance(PlatformSettingService::class, $mock);

    $result = $checkoutService->validatePaymentSelection(
        'mtn_momo',
        '+237677123456',
        $this->client->id,
        5000,
        true // use_wallet
    );

    expect($result['valid'])->toBeTrue();
});

test('validatePaymentSelection rejects partial wallet with no balance', function () {
    $checkoutService = app(CheckoutService::class);

    ClientWallet::create([
        'user_id' => $this->client->id,
        'balance' => 0,
        'currency' => 'XAF',
    ]);

    $mock = Mockery::mock(PlatformSettingService::class);
    $mock->shouldReceive('isWalletEnabled')->andReturn(true);
    app()->instance(PlatformSettingService::class, $mock);

    $result = $checkoutService->validatePaymentSelection(
        'mtn_momo',
        '+237677123456',
        $this->client->id,
        5000,
        true // use_wallet
    );

    expect($result['valid'])->toBeFalse();
});
