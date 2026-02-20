<?php

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\ClientTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| F-165: Transaction Detail View — Unit Tests
|--------------------------------------------------------------------------
|
| Tests for ClientTransactionService::getTransactionDetail() and the
| transaction detail route at /my-transactions/{sourceType}/{sourceId}.
| BR-271 through BR-279.
|
*/

// ===== Service: getTransactionDetail — Payment Transactions =====

test('getTransactionDetail returns payment transaction detail for owner', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->paid()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    $pt = PaymentTransaction::factory()->successful()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'amount' => 5000,
        'payment_method' => 'mtn_mobile_money',
        'flutterwave_reference' => 'FLW-12345678',
    ]);

    $result = $service->getTransactionDetail($client, 'payment_transaction', $pt->id);

    expect($result)->not->toBeNull()
        ->and($result['source_type'])->toBe('payment_transaction')
        ->and($result['source_id'])->toBe($pt->id)
        ->and($result['amount'])->toBe(5000.0)
        ->and($result['type'])->toBe('payment')
        ->and($result['debit_credit'])->toBe('debit')
        ->and($result['status'])->toBe('completed')
        ->and($result['payment_method'])->toBe('MTN Mobile Money')
        ->and($result['flutterwave_reference'])->toBe('FLW-12345678')
        ->and($result['order_number'])->toBe($order->order_number)
        ->and($result['order_exists'])->toBeTrue();
});

test('getTransactionDetail returns refund payment transaction with reason', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->refunded()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    $pt = PaymentTransaction::factory()->refunded()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'amount' => 3000,
        'refund_amount' => 3000,
        'refund_reason' => 'Order cancelled by client',
    ]);

    $result = $service->getTransactionDetail($client, 'payment_transaction', $pt->id);

    expect($result)->not->toBeNull()
        ->and($result['type'])->toBe('refund')
        ->and($result['debit_credit'])->toBe('credit')
        ->and($result['amount'])->toBe(3000.0)
        ->and($result['refund_reason'])->toBe('Order cancelled by client')
        ->and($result['status'])->toBe('refunded');
});

test('getTransactionDetail returns failed payment with failure reason', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    $pt = PaymentTransaction::factory()->failed()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'amount' => 4000,
        'response_message' => 'Payment timed out',
    ]);

    $result = $service->getTransactionDetail($client, 'payment_transaction', $pt->id);

    expect($result)->not->toBeNull()
        ->and($result['status'])->toBe('failed')
        ->and($result['failure_reason'])->toBe('Payment timed out');
});

test('getTransactionDetail returns null for other users payment transaction (BR-271)', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $otherClient = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->paid()->create(['client_id' => $otherClient->id, 'tenant_id' => $tenant->id]);

    $pt = PaymentTransaction::factory()->successful()->create([
        'client_id' => $otherClient->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $result = $service->getTransactionDetail($client, 'payment_transaction', $pt->id);

    expect($result)->toBeNull();
});

// ===== Service: getTransactionDetail — Wallet Transactions =====

test('getTransactionDetail returns wallet refund transaction detail', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->refunded()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    $wt = WalletTransaction::factory()->refund()->create([
        'user_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'amount' => 2000,
        'description' => 'Refund for cancelled order',
        'balance_before' => 1000,
        'balance_after' => 3000,
    ]);

    $result = $service->getTransactionDetail($client, 'wallet_transaction', $wt->id);

    expect($result)->not->toBeNull()
        ->and($result['source_type'])->toBe('wallet_transaction')
        ->and($result['type'])->toBe('refund')
        ->and($result['debit_credit'])->toBe('credit')
        ->and($result['amount'])->toBe(2000.0)
        ->and($result['payment_method'])->toContain('Wallet')
        ->and($result['refund_reason'])->toBe('Refund for cancelled order')
        ->and($result['order_exists'])->toBeTrue()
        ->and($result['balance_before'])->toBe(1000.0)
        ->and($result['balance_after'])->toBe(3000.0);
});

test('getTransactionDetail returns wallet payment transaction detail (BR-275)', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->paid()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    $wt = WalletTransaction::factory()->create([
        'user_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'type' => 'wallet_payment',
        'amount' => 3000,
        'balance_before' => 5000,
        'balance_after' => 2000,
    ]);

    $result = $service->getTransactionDetail($client, 'wallet_transaction', $wt->id);

    expect($result)->not->toBeNull()
        ->and($result['type'])->toBe('wallet_payment')
        ->and($result['debit_credit'])->toBe('debit')
        ->and($result['payment_method'])->toContain('Wallet');
});

test('getTransactionDetail returns null for other users wallet transaction (BR-271)', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $otherClient = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->refunded()->create(['client_id' => $otherClient->id, 'tenant_id' => $tenant->id]);

    $wt = WalletTransaction::factory()->refund()->create([
        'user_id' => $otherClient->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $result = $service->getTransactionDetail($client, 'wallet_transaction', $wt->id);

    expect($result)->toBeNull();
});

test('getTransactionDetail returns null for invalid source type', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();

    $result = $service->getTransactionDetail($client, 'invalid_type', 1);

    expect($result)->toBeNull();
});

test('getTransactionDetail returns null for non-existent transaction', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();

    $result = $service->getTransactionDetail($client, 'payment_transaction', 999999);

    expect($result)->toBeNull();
});

// ===== Service: Edge Cases =====

test('payment transaction without flutterwave reference shows null reference', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->paid()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    $pt = PaymentTransaction::factory()->successful()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'flutterwave_reference' => null,
        'flutterwave_tx_ref' => null,
    ]);

    $result = $service->getTransactionDetail($client, 'payment_transaction', $pt->id);

    expect($result['flutterwave_reference'])->toBeNull()
        ->and($result['flutterwave_tx_ref'])->toBeNull()
        ->and($result['reference'])->toBeNull();
});

test('refund without explicit reason shows default reason', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->refunded()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    $pt = PaymentTransaction::factory()->refunded()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'refund_reason' => null,
    ]);

    $result = $service->getTransactionDetail($client, 'payment_transaction', $pt->id);

    expect($result['refund_reason'])->toBe('Order cancelled by client');
});

test('wallet refund without description shows complaint reason', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->refunded()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    $wt = WalletTransaction::factory()->refund()->create([
        'user_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'description' => null,
        'metadata' => null,
    ]);

    $result = $service->getTransactionDetail($client, 'wallet_transaction', $wt->id);

    expect($result['refund_reason'])->toContain('Complaint resolved');
});

test('pending transaction shows is_pending flag', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    $pt = PaymentTransaction::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'status' => 'pending',
    ]);

    $result = $service->getTransactionDetail($client, 'payment_transaction', $pt->id);

    expect($result['is_pending'])->toBeTrue()
        ->and($result['status'])->toBe('pending');
});

test('failed payment without response message shows default failure reason', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    $pt = PaymentTransaction::factory()->failed()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'response_message' => null,
    ]);

    $result = $service->getTransactionDetail($client, 'payment_transaction', $pt->id);

    expect($result['failure_reason'])->toBe('Payment timed out');
});

// ===== Route Tests =====

test('show route returns 200 for authenticated client viewing own payment transaction', function () {
    $this->seedRolesAndPermissions();
    $client = User::factory()->create();
    $client->assignRole('client');
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->paid()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    $pt = PaymentTransaction::factory()->successful()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $response = $this->actingAs($client)->get("https://{$mainDomain}/my-transactions/payment_transaction/{$pt->id}");

    $response->assertOk();
    $response->assertViewIs('client.transactions.show');
    $response->assertViewHas('transaction');
});

test('show route returns 200 for authenticated client viewing own wallet transaction', function () {
    $this->seedRolesAndPermissions();
    $client = User::factory()->create();
    $client->assignRole('client');
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->refunded()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    $wt = WalletTransaction::factory()->refund()->create([
        'user_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $response = $this->actingAs($client)->get("https://{$mainDomain}/my-transactions/wallet_transaction/{$wt->id}");

    $response->assertOk();
    $response->assertViewIs('client.transactions.show');
});

test('show route returns 403 for client accessing another users transaction (BR-271)', function () {
    $this->seedRolesAndPermissions();
    $client = User::factory()->create();
    $client->assignRole('client');
    $otherClient = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->paid()->create(['client_id' => $otherClient->id, 'tenant_id' => $tenant->id]);

    $pt = PaymentTransaction::factory()->successful()->create([
        'client_id' => $otherClient->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $response = $this->actingAs($client)->get("https://{$mainDomain}/my-transactions/payment_transaction/{$pt->id}");

    $response->assertForbidden();
});

test('show route returns 404 for invalid source type', function () {
    $this->seedRolesAndPermissions();
    $client = User::factory()->create();
    $client->assignRole('client');

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $response = $this->actingAs($client)->get("https://{$mainDomain}/my-transactions/invalid_type/1");

    $response->assertNotFound();
});

test('show route requires authentication', function () {
    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $response = $this->get("https://{$mainDomain}/my-transactions/payment_transaction/1");

    $response->assertRedirect();
});

test('formatXAF formats amount correctly (BR-278)', function () {
    expect(ClientTransactionService::formatXAF(5000))->toBe('5,000 XAF')
        ->and(ClientTransactionService::formatXAF(15000.50))->toBe('15,001 XAF')
        ->and(ClientTransactionService::formatXAF(0))->toBe('0 XAF')
        ->and(ClientTransactionService::formatXAF(1000000))->toBe('1,000,000 XAF');
});

// ===== View Data Verification =====

test('transaction detail contains all required fields (BR-272)', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->paid()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    $pt = PaymentTransaction::factory()->successful()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'amount' => 7500,
    ]);

    $result = $service->getTransactionDetail($client, 'payment_transaction', $pt->id);

    // BR-272: All transaction types must display these fields
    expect($result)->toHaveKeys([
        'amount',
        'type',
        'date',
        'status',
        'description',
        'payment_method',
        'order_number',
        'order_id',
        'order_exists',
        'debit_credit',
    ]);
});

test('payment transaction detail contains flutterwave fields (BR-273)', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->paid()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    $pt = PaymentTransaction::factory()->successful()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'flutterwave_reference' => 'FLW-TEST-REF',
    ]);

    $result = $service->getTransactionDetail($client, 'payment_transaction', $pt->id);

    expect($result)->toHaveKeys(['flutterwave_reference', 'flutterwave_tx_ref', 'payment_method']);
});
