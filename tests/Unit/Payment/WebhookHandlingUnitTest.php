<?php

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();
    $this->webhookService = app(WebhookService::class);
});

/*
|--------------------------------------------------------------------------
| Signature Verification Tests
|--------------------------------------------------------------------------
*/

test('valid signature passes verification', function () {
    config(['flutterwave.webhook_secret' => 'test-secret-hash']);

    expect($this->webhookService->verifySignature('test-secret-hash'))->toBeTrue();
});

test('invalid signature fails verification', function () {
    config(['flutterwave.webhook_secret' => 'test-secret-hash']);

    expect($this->webhookService->verifySignature('wrong-hash'))->toBeFalse();
});

test('empty signature fails verification', function () {
    config(['flutterwave.webhook_secret' => 'test-secret-hash']);

    expect($this->webhookService->verifySignature(null))->toBeFalse();
    expect($this->webhookService->verifySignature(''))->toBeFalse();
});

test('empty webhook secret fails verification', function () {
    config(['flutterwave.webhook_secret' => '']);

    expect($this->webhookService->verifySignature('any-hash'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Successful Payment Processing Tests
|--------------------------------------------------------------------------
*/

test('successful payment updates order to paid status', function () {
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

    $transaction = PaymentTransaction::factory()->pending()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'amount' => 5000,
        'flutterwave_tx_ref' => 'DMC-TX-TEST-001',
    ]);

    $payload = [
        'event' => 'charge.completed',
        'data' => [
            'tx_ref' => 'DMC-TX-TEST-001',
            'flw_ref' => 'FLW-REF-12345',
            'status' => 'successful',
            'amount' => 5000,
            'currency' => 'XAF',
            'payment_type' => 'mobilemoneycm',
        ],
    ];

    $result = $this->webhookService->processWebhook($payload);

    expect($result['success'])->toBeTrue();
    expect($result['already_processed'])->toBeFalse();
    expect($order->fresh()->status)->toBe(Order::STATUS_PAID);
    expect($order->fresh()->paid_at)->not->toBeNull();
});

test('successful payment credits cook wallet with correct amount', function () {
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

    $transaction = PaymentTransaction::factory()->pending()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'amount' => 10000,
        'flutterwave_tx_ref' => 'DMC-TX-TEST-002',
    ]);

    $payload = [
        'event' => 'charge.completed',
        'data' => [
            'tx_ref' => 'DMC-TX-TEST-002',
            'flw_ref' => 'FLW-REF-22222',
            'status' => 'successful',
            'amount' => 10000,
        ],
    ];

    $this->webhookService->processWebhook($payload);

    // BR-366: Cook receives order amount minus commission
    // BR-368: 10% commission on 10,000 XAF = 1,000 XAF commission
    // Cook share = 10,000 - 1,000 = 9,000 XAF
    $walletCredit = WalletTransaction::query()
        ->where('user_id', $cook->id)
        ->where('type', WalletTransaction::TYPE_PAYMENT_CREDIT)
        ->first();

    expect($walletCredit)->not->toBeNull();
    expect((float) $walletCredit->amount)->toBe(9000.0);
    expect($walletCredit->order_id)->toBe($order->id);
});

test('successful payment records commission separately', function () {
    $tenant = Tenant::factory()->create(['settings' => ['commission_rate' => 15.0]]);
    $cook = User::factory()->create();
    $client = User::factory()->create();

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'client_id' => $client->id,
        'status' => Order::STATUS_PENDING_PAYMENT,
        'grand_total' => 20000,
    ]);

    $transaction = PaymentTransaction::factory()->pending()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'amount' => 20000,
        'flutterwave_tx_ref' => 'DMC-TX-TEST-003',
    ]);

    $payload = [
        'event' => 'charge.completed',
        'data' => [
            'tx_ref' => 'DMC-TX-TEST-003',
            'flw_ref' => 'FLW-REF-33333',
            'status' => 'successful',
            'amount' => 20000,
        ],
    ];

    $this->webhookService->processWebhook($payload);

    // BR-369: Commission recorded as separate transaction
    // 15% of 20,000 = 3,000 XAF
    $commission = WalletTransaction::query()
        ->where('user_id', $cook->id)
        ->where('type', WalletTransaction::TYPE_COMMISSION)
        ->first();

    expect($commission)->not->toBeNull();
    expect((float) $commission->amount)->toBe(3000.0);
    expect($commission->order_id)->toBe($order->id);
});

test('wallet credit is marked as unwithdrawable initially', function () {
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

    $transaction = PaymentTransaction::factory()->pending()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'amount' => 5000,
        'flutterwave_tx_ref' => 'DMC-TX-TEST-004',
    ]);

    $payload = [
        'event' => 'charge.completed',
        'data' => [
            'tx_ref' => 'DMC-TX-TEST-004',
            'flw_ref' => 'FLW-REF-44444',
            'status' => 'successful',
            'amount' => 5000,
        ],
    ];

    $this->webhookService->processWebhook($payload);

    // BR-367: Credit is initially unwithdrawable
    $walletCredit = WalletTransaction::query()
        ->where('user_id', $cook->id)
        ->where('type', WalletTransaction::TYPE_PAYMENT_CREDIT)
        ->first();

    expect($walletCredit->is_withdrawable)->toBeFalse();
    expect($walletCredit->withdrawable_at)->not->toBeNull();
    expect($walletCredit->withdrawable_at->isFuture())->toBeTrue();
});

test('zero commission rate gives cook full amount with no commission record', function () {
    $tenant = Tenant::factory()->create(['settings' => ['commission_rate' => 0.0]]);
    $cook = User::factory()->create();
    $client = User::factory()->create();

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'client_id' => $client->id,
        'status' => Order::STATUS_PENDING_PAYMENT,
        'grand_total' => 8000,
    ]);

    $transaction = PaymentTransaction::factory()->pending()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'amount' => 8000,
        'flutterwave_tx_ref' => 'DMC-TX-TEST-005',
    ]);

    $payload = [
        'event' => 'charge.completed',
        'data' => [
            'tx_ref' => 'DMC-TX-TEST-005',
            'flw_ref' => 'FLW-REF-55555',
            'status' => 'successful',
            'amount' => 8000,
        ],
    ];

    $this->webhookService->processWebhook($payload);

    // Cook receives full amount
    $walletCredit = WalletTransaction::query()
        ->where('user_id', $cook->id)
        ->where('type', WalletTransaction::TYPE_PAYMENT_CREDIT)
        ->first();

    expect((float) $walletCredit->amount)->toBe(8000.0);

    // No commission record
    $commissionCount = WalletTransaction::query()
        ->where('user_id', $cook->id)
        ->where('type', WalletTransaction::TYPE_COMMISSION)
        ->count();

    expect($commissionCount)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Failed Payment Tests
|--------------------------------------------------------------------------
*/

test('failed payment updates order to payment_failed status', function () {
    $tenant = Tenant::factory()->create();
    $cook = User::factory()->create();
    $client = User::factory()->create();

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'client_id' => $client->id,
        'status' => Order::STATUS_PENDING_PAYMENT,
    ]);

    $transaction = PaymentTransaction::factory()->pending()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'flutterwave_tx_ref' => 'DMC-TX-TEST-006',
    ]);

    $payload = [
        'event' => 'charge.completed',
        'data' => [
            'tx_ref' => 'DMC-TX-TEST-006',
            'flw_ref' => 'FLW-REF-66666',
            'status' => 'failed',
            'amount' => 5000,
        ],
    ];

    $result = $this->webhookService->processWebhook($payload);

    expect($result['success'])->toBeTrue();
    expect($order->fresh()->status)->toBe(Order::STATUS_PAYMENT_FAILED);
});

test('failed payment does not credit cook wallet', function () {
    $tenant = Tenant::factory()->create();
    $cook = User::factory()->create();
    $client = User::factory()->create();

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'client_id' => $client->id,
        'status' => Order::STATUS_PENDING_PAYMENT,
    ]);

    $transaction = PaymentTransaction::factory()->pending()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'flutterwave_tx_ref' => 'DMC-TX-TEST-007',
    ]);

    $payload = [
        'event' => 'charge.completed',
        'data' => [
            'tx_ref' => 'DMC-TX-TEST-007',
            'flw_ref' => 'FLW-REF-77777',
            'status' => 'failed',
        ],
    ];

    $this->webhookService->processWebhook($payload);

    $walletCount = WalletTransaction::query()
        ->where('user_id', $cook->id)
        ->count();

    expect($walletCount)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Idempotency Tests
|--------------------------------------------------------------------------
*/

test('duplicate webhook is handled idempotently', function () {
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

    $transaction = PaymentTransaction::factory()->pending()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'amount' => 5000,
        'flutterwave_tx_ref' => 'DMC-TX-TEST-008',
    ]);

    $payload = [
        'event' => 'charge.completed',
        'data' => [
            'tx_ref' => 'DMC-TX-TEST-008',
            'flw_ref' => 'FLW-REF-88888',
            'status' => 'successful',
            'amount' => 5000,
        ],
    ];

    // Process first time
    $result1 = $this->webhookService->processWebhook($payload);
    expect($result1['success'])->toBeTrue();
    expect($result1['already_processed'])->toBeFalse();

    // Process second time (duplicate)
    $result2 = $this->webhookService->processWebhook($payload);
    expect($result2['success'])->toBeTrue();
    expect($result2['already_processed'])->toBeTrue();

    // Only 1 wallet credit should exist
    $walletCreditCount = WalletTransaction::query()
        ->where('user_id', $cook->id)
        ->where('type', WalletTransaction::TYPE_PAYMENT_CREDIT)
        ->count();

    expect($walletCreditCount)->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Edge Case Tests
|--------------------------------------------------------------------------
*/

test('orphan transaction reference returns 200 success', function () {
    $payload = [
        'event' => 'charge.completed',
        'data' => [
            'tx_ref' => 'DMC-TX-NONEXISTENT',
            'flw_ref' => 'FLW-REF-99999',
            'status' => 'successful',
            'amount' => 5000,
        ],
    ];

    $result = $this->webhookService->processWebhook($payload);

    // Returns success to prevent retries, but logs as orphan
    expect($result['success'])->toBeTrue();
    expect($result['message'])->toBe('Transaction reference not found');
});

test('malformed payload is handled gracefully', function () {
    $result = $this->webhookService->processWebhook([]);
    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('Malformed payload');

    $result2 = $this->webhookService->processWebhook(['event' => 'charge.completed']);
    expect($result2['success'])->toBeFalse();
    expect($result2['message'])->toBe('Malformed payload');
});

test('unhandled event types return success', function () {
    $payload = [
        'event' => 'transfer.completed',
        'data' => ['some' => 'data'],
    ];

    $result = $this->webhookService->processWebhook($payload);

    expect($result['success'])->toBeTrue();
    expect($result['message'])->toBe('Event type not handled');
});

test('wallet balance calculation is accurate', function () {
    $cook = User::factory()->create();
    $tenant = Tenant::factory()->create();

    // Create some wallet transactions
    WalletTransaction::factory()->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
        'amount' => 5000,
        'status' => 'completed',
    ]);

    WalletTransaction::factory()->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
        'amount' => 3000,
        'status' => 'completed',
    ]);

    WalletTransaction::factory()->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_WITHDRAWAL,
        'amount' => 2000,
        'status' => 'completed',
    ]);

    $balance = $this->webhookService->getCookWalletBalance($cook->id);

    expect($balance)->toBe(6000.0); // 5000 + 3000 - 2000
});

test('transaction record is updated with webhook data on success', function () {
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

    $transaction = PaymentTransaction::factory()->pending()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'amount' => 5000,
        'flutterwave_tx_ref' => 'DMC-TX-TEST-010',
    ]);

    $payload = [
        'event' => 'charge.completed',
        'data' => [
            'tx_ref' => 'DMC-TX-TEST-010',
            'flw_ref' => 'FLW-REF-AAAA',
            'status' => 'successful',
            'amount' => 5000,
            'app_fee' => 70.0,
            'amount_settled' => 4930.0,
            'payment_type' => 'mobilemoneycm',
            'processor_response' => '00',
        ],
    ];

    $this->webhookService->processWebhook($payload);

    $updated = $transaction->fresh();
    expect($updated->status)->toBe('successful');
    expect($updated->flutterwave_reference)->toBe('FLW-REF-AAAA');
    expect((float) $updated->flutterwave_fee)->toBe(70.0);
    expect((float) $updated->settlement_amount)->toBe(4930.0);
    expect($updated->webhook_payload)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| WalletTransaction Model Tests
|--------------------------------------------------------------------------
*/

test('wallet transaction model has correct relationships', function () {
    $cook = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $wt = WalletTransaction::factory()->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    expect($wt->user->id)->toBe($cook->id);
    expect($wt->tenant->id)->toBe($tenant->id);
    expect($wt->order->id)->toBe($order->id);
});

test('wallet transaction identifies credits and debits correctly', function () {
    $credit = new WalletTransaction(['type' => WalletTransaction::TYPE_PAYMENT_CREDIT]);
    expect($credit->isCredit())->toBeTrue();
    expect($credit->isDebit())->toBeFalse();

    $commission = new WalletTransaction(['type' => WalletTransaction::TYPE_COMMISSION]);
    expect($commission->isDebit())->toBeTrue();
    expect($commission->isCredit())->toBeFalse();
});

test('wallet transaction formats amount correctly', function () {
    $wt = new WalletTransaction(['amount' => 15000, 'currency' => 'XAF']);
    expect($wt->formattedAmount())->toBe('15,000 XAF');
});
