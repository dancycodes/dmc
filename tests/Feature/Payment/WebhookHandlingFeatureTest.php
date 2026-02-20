<?php

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WalletTransaction;

beforeEach(function () {
    test()->seedRolesAndPermissions();
    config(['flutterwave.webhook_secret' => 'test-webhook-secret']);
});

/*
|--------------------------------------------------------------------------
| Webhook Endpoint Authentication Tests
|--------------------------------------------------------------------------
*/

test('webhook with valid signature returns 200', function () {
    $tenant = Tenant::factory()->create(['settings' => ['commission_rate' => 10.0]]);
    $cook = User::factory()->create();
    $client = User::factory()->create();

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'client_id' => $client->id,
        'status' => Order::STATUS_PENDING_PAYMENT,
        'grand_total' => 5000,
    ]);

    PaymentTransaction::factory()->pending()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'amount' => 5000,
        'flutterwave_tx_ref' => 'DMC-TX-FEAT-001',
    ]);

    $response = $this->postJson('/webhooks/flutterwave', [
        'event' => 'charge.completed',
        'data' => [
            'tx_ref' => 'DMC-TX-FEAT-001',
            'flw_ref' => 'FLW-REF-F001',
            'status' => 'successful',
            'amount' => 5000,
        ],
    ], [
        'verif-hash' => 'test-webhook-secret',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['status' => 'ok']);
});

test('webhook with invalid signature returns 401', function () {
    $response = $this->postJson('/webhooks/flutterwave', [
        'event' => 'charge.completed',
        'data' => [
            'tx_ref' => 'DMC-TX-FEAT-002',
            'status' => 'successful',
        ],
    ], [
        'verif-hash' => 'wrong-secret',
    ]);

    $response->assertStatus(401);
    $response->assertJson(['status' => 'error']);
});

test('webhook without signature returns 401', function () {
    $response = $this->postJson('/webhooks/flutterwave', [
        'event' => 'charge.completed',
        'data' => ['tx_ref' => 'DMC-TX-FEAT-003'],
    ]);

    $response->assertStatus(401);
});

/*
|--------------------------------------------------------------------------
| Successful Payment Flow Tests
|--------------------------------------------------------------------------
*/

test('successful webhook updates order and creates wallet records', function () {
    $tenant = Tenant::factory()->create(['settings' => ['commission_rate' => 10.0]]);
    $cook = User::factory()->create();
    $client = User::factory()->create();

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'client_id' => $client->id,
        'status' => Order::STATUS_PENDING_PAYMENT,
        'grand_total' => 10000,
    ]);

    PaymentTransaction::factory()->pending()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'amount' => 10000,
        'flutterwave_tx_ref' => 'DMC-TX-FEAT-004',
    ]);

    $response = $this->postJson('/webhooks/flutterwave', [
        'event' => 'charge.completed',
        'data' => [
            'tx_ref' => 'DMC-TX-FEAT-004',
            'flw_ref' => 'FLW-REF-F004',
            'status' => 'successful',
            'amount' => 10000,
        ],
    ], [
        'verif-hash' => 'test-webhook-secret',
    ]);

    $response->assertStatus(200);

    // Order is paid
    expect($order->fresh()->status)->toBe(Order::STATUS_PAID);
    expect($order->fresh()->paid_at)->not->toBeNull();

    // Cook wallet credited (10,000 - 10% = 9,000)
    $walletCredit = WalletTransaction::query()
        ->where('user_id', $cook->id)
        ->where('type', WalletTransaction::TYPE_PAYMENT_CREDIT)
        ->first();
    expect($walletCredit)->not->toBeNull();
    expect((float) $walletCredit->amount)->toBe(9000.0);

    // Commission recorded (10% of 10,000 = 1,000)
    $commission = WalletTransaction::query()
        ->where('user_id', $cook->id)
        ->where('type', WalletTransaction::TYPE_COMMISSION)
        ->first();
    expect($commission)->not->toBeNull();
    expect((float) $commission->amount)->toBe(1000.0);
});

/*
|--------------------------------------------------------------------------
| Failed Payment Flow Tests
|--------------------------------------------------------------------------
*/

test('failed webhook updates order to payment_failed', function () {
    $tenant = Tenant::factory()->create();
    $cook = User::factory()->create();
    $client = User::factory()->create();

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'client_id' => $client->id,
        'status' => Order::STATUS_PENDING_PAYMENT,
    ]);

    PaymentTransaction::factory()->pending()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'flutterwave_tx_ref' => 'DMC-TX-FEAT-005',
    ]);

    $response = $this->postJson('/webhooks/flutterwave', [
        'event' => 'charge.completed',
        'data' => [
            'tx_ref' => 'DMC-TX-FEAT-005',
            'flw_ref' => 'FLW-REF-F005',
            'status' => 'failed',
        ],
    ], [
        'verif-hash' => 'test-webhook-secret',
    ]);

    $response->assertStatus(200);
    expect($order->fresh()->status)->toBe(Order::STATUS_PAYMENT_FAILED);

    // No wallet transactions for failed payments
    expect(WalletTransaction::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Idempotency Tests
|--------------------------------------------------------------------------
*/

test('duplicate webhook returns 200 without creating duplicates', function () {
    $tenant = Tenant::factory()->create(['settings' => ['commission_rate' => 10.0]]);
    $cook = User::factory()->create();
    $client = User::factory()->create();

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'client_id' => $client->id,
        'status' => Order::STATUS_PENDING_PAYMENT,
        'grand_total' => 5000,
    ]);

    PaymentTransaction::factory()->pending()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'amount' => 5000,
        'flutterwave_tx_ref' => 'DMC-TX-FEAT-006',
    ]);

    $webhookData = [
        'event' => 'charge.completed',
        'data' => [
            'tx_ref' => 'DMC-TX-FEAT-006',
            'flw_ref' => 'FLW-REF-F006',
            'status' => 'successful',
            'amount' => 5000,
        ],
    ];
    $headers = ['verif-hash' => 'test-webhook-secret'];

    // First call
    $this->postJson('/webhooks/flutterwave', $webhookData, $headers)->assertStatus(200);

    // Second call (duplicate)
    $this->postJson('/webhooks/flutterwave', $webhookData, $headers)->assertStatus(200);

    // Only 1 wallet credit created
    expect(WalletTransaction::where('type', WalletTransaction::TYPE_PAYMENT_CREDIT)->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Edge Cases
|--------------------------------------------------------------------------
*/

test('webhook without csrf token is not rejected', function () {
    // Webhook route should be excluded from CSRF verification
    $response = $this->post('/webhooks/flutterwave', [
        'event' => 'charge.completed',
        'data' => ['tx_ref' => 'test'],
    ], [
        'verif-hash' => 'test-webhook-secret',
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ]);

    // Should not get 419 (CSRF token mismatch)
    expect($response->status())->not->toBe(419);
});

test('orphan transaction reference returns 200 to prevent retries', function () {
    $response = $this->postJson('/webhooks/flutterwave', [
        'event' => 'charge.completed',
        'data' => [
            'tx_ref' => 'DMC-TX-NONEXISTENT',
            'flw_ref' => 'FLW-REF-ORPHAN',
            'status' => 'successful',
            'amount' => 5000,
        ],
    ], [
        'verif-hash' => 'test-webhook-secret',
    ]);

    // BR-375: Return 200 to prevent Flutterwave retries
    $response->assertStatus(200);
});

test('activity log records webhook processing', function () {
    $tenant = Tenant::factory()->create(['settings' => ['commission_rate' => 10.0]]);
    $cook = User::factory()->create();
    $client = User::factory()->create();

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'client_id' => $client->id,
        'status' => Order::STATUS_PENDING_PAYMENT,
        'grand_total' => 5000,
    ]);

    PaymentTransaction::factory()->pending()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'amount' => 5000,
        'flutterwave_tx_ref' => 'DMC-TX-FEAT-008',
    ]);

    $this->postJson('/webhooks/flutterwave', [
        'event' => 'charge.completed',
        'data' => [
            'tx_ref' => 'DMC-TX-FEAT-008',
            'flw_ref' => 'FLW-REF-F008',
            'status' => 'successful',
            'amount' => 5000,
        ],
    ], [
        'verif-hash' => 'test-webhook-secret',
    ]);

    // BR-374: Activity log entry for webhook processing
    $activityLog = \Spatie\Activitylog\Models\Activity::query()
        ->where('log_name', 'webhooks')
        ->latest()
        ->first();

    expect($activityLog)->not->toBeNull();
    expect($activityLog->description)->toContain('successful');
});
