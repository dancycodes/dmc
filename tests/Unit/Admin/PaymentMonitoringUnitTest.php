<?php

/**
 * F-059: Payment Monitoring View — Unit Tests
 *
 * Tests the PaymentTransaction model methods, scopes, and business logic.
 * Controller/view behavior is verified by Playwright in Phase 3.
 */

use App\Models\PaymentTransaction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Model Constants & Configuration
|--------------------------------------------------------------------------
*/

test('payment transaction has correct statuses constant', function () {
    expect(PaymentTransaction::STATUSES)
        ->toBe(['pending', 'successful', 'failed', 'refunded']);
});

test('payment transaction has correct payment methods constant', function () {
    expect(PaymentTransaction::PAYMENT_METHODS)
        ->toBe(['mtn_mobile_money', 'orange_money']);
});

test('payment transaction casts webhook_payload to array', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $txn = PaymentTransaction::factory()->create([
        'client_id' => $user->id,
        'cook_id' => $user->id,
        'tenant_id' => $tenant->id,
        'webhook_payload' => ['id' => 123, 'status' => 'successful'],
    ]);

    $txn->refresh();
    expect($txn->webhook_payload)->toBeArray()
        ->and($txn->webhook_payload['id'])->toBe(123);
});

test('payment transaction casts status_history to array', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $txn = PaymentTransaction::factory()->create([
        'client_id' => $user->id,
        'cook_id' => $user->id,
        'tenant_id' => $tenant->id,
        'status_history' => [
            ['status' => 'pending', 'timestamp' => '2026-01-01T00:00:00+00:00'],
        ],
    ]);

    $txn->refresh();
    expect($txn->status_history)->toBeArray()
        ->and($txn->status_history[0]['status'])->toBe('pending');
});

/*
|--------------------------------------------------------------------------
| Relationships
|--------------------------------------------------------------------------
*/

test('payment transaction belongs to client', function () {
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $txn = PaymentTransaction::factory()->create([
        'client_id' => $client->id,
        'cook_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($txn->client)->toBeInstanceOf(User::class)
        ->and($txn->client->id)->toBe($client->id);
});

test('payment transaction belongs to cook', function () {
    $client = User::factory()->create();
    $cook = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $txn = PaymentTransaction::factory()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($txn->cook)->toBeInstanceOf(User::class)
        ->and($txn->cook->id)->toBe($cook->id);
});

test('payment transaction belongs to tenant', function () {
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $txn = PaymentTransaction::factory()->create([
        'client_id' => $client->id,
        'cook_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($txn->tenant)->toBeInstanceOf(Tenant::class)
        ->and($txn->tenant->id)->toBe($tenant->id);
});

/*
|--------------------------------------------------------------------------
| Formatted Amount Methods
|--------------------------------------------------------------------------
*/

test('formattedAmount formats amount with comma separator and currency', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $txn = PaymentTransaction::factory()->create([
        'client_id' => $user->id,
        'cook_id' => $user->id,
        'tenant_id' => $tenant->id,
        'amount' => 15000,
        'currency' => 'XAF',
    ]);

    expect($txn->formattedAmount())->toBe('15,000 XAF');
});

test('formattedAmount handles zero amount', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $txn = PaymentTransaction::factory()->freeOrder()->create([
        'client_id' => $user->id,
        'cook_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($txn->formattedAmount())->toBe('0 XAF');
});

test('formattedRefundAmount formats refund amount correctly', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $txn = PaymentTransaction::factory()->refunded()->create([
        'client_id' => $user->id,
        'cook_id' => $user->id,
        'tenant_id' => $tenant->id,
        'amount' => 5000,
        'refund_amount' => 5000,
    ]);

    expect($txn->formattedRefundAmount())->toBe('5,000 XAF');
});

test('formattedRefundAmount returns zero when no refund amount', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $txn = PaymentTransaction::factory()->create([
        'client_id' => $user->id,
        'cook_id' => $user->id,
        'tenant_id' => $tenant->id,
        'refund_amount' => null,
    ]);

    expect($txn->formattedRefundAmount())->toBe('0 XAF');
});

/*
|--------------------------------------------------------------------------
| Payment Method Label
|--------------------------------------------------------------------------
*/

test('paymentMethodLabel returns MTN Mobile Money label', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $txn = PaymentTransaction::factory()->mtn()->create([
        'client_id' => $user->id,
        'cook_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($txn->paymentMethodLabel())->toBe('MTN Mobile Money');
});

test('paymentMethodLabel returns Orange Money label', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $txn = PaymentTransaction::factory()->orange()->create([
        'client_id' => $user->id,
        'cook_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($txn->paymentMethodLabel())->toBe('Orange Money');
});

/*
|--------------------------------------------------------------------------
| isPendingTooLong
|--------------------------------------------------------------------------
*/

test('isPendingTooLong returns true for pending payments older than 15 minutes', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $txn = PaymentTransaction::factory()->pendingTooLong()->create([
        'client_id' => $user->id,
        'cook_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($txn->isPendingTooLong())->toBeTrue();
});

test('isPendingTooLong returns false for recent pending payments', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $txn = PaymentTransaction::factory()->pending()->create([
        'client_id' => $user->id,
        'cook_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($txn->isPendingTooLong())->toBeFalse();
});

test('isPendingTooLong returns false for non-pending statuses', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $txn = PaymentTransaction::factory()->successful()->create([
        'client_id' => $user->id,
        'cook_id' => $user->id,
        'tenant_id' => $tenant->id,
        'created_at' => now()->subMinutes(30),
    ]);

    expect($txn->isPendingTooLong())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Status Scope
|--------------------------------------------------------------------------
*/

test('status scope filters by valid status', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    PaymentTransaction::factory()->successful()->create(['client_id' => $user->id, 'cook_id' => $user->id, 'tenant_id' => $tenant->id]);
    PaymentTransaction::factory()->failed()->create(['client_id' => $user->id, 'cook_id' => $user->id, 'tenant_id' => $tenant->id]);
    PaymentTransaction::factory()->pending()->create(['client_id' => $user->id, 'cook_id' => $user->id, 'tenant_id' => $tenant->id]);

    expect(PaymentTransaction::status('successful')->count())->toBe(1)
        ->and(PaymentTransaction::status('failed')->count())->toBe(1)
        ->and(PaymentTransaction::status('pending')->count())->toBe(1);
});

test('status scope returns all when empty string', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    PaymentTransaction::factory()->count(3)->create([
        'client_id' => $user->id,
        'cook_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    expect(PaymentTransaction::status('')->count())->toBe(3);
});

test('status scope returns all when null', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    PaymentTransaction::factory()->count(3)->create([
        'client_id' => $user->id,
        'cook_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    expect(PaymentTransaction::status(null)->count())->toBe(3);
});

test('status scope returns all for invalid status value', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    PaymentTransaction::factory()->count(3)->create([
        'client_id' => $user->id,
        'cook_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    expect(PaymentTransaction::status('invalid-status')->count())->toBe(3);
});

/*
|--------------------------------------------------------------------------
| Search Scope
|--------------------------------------------------------------------------
*/

test('search scope finds by flutterwave reference', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    PaymentTransaction::factory()->create([
        'client_id' => $user->id,
        'cook_id' => $user->id,
        'tenant_id' => $tenant->id,
        'flutterwave_reference' => 'FLW-12345678',
    ]);
    PaymentTransaction::factory()->create([
        'client_id' => $user->id,
        'cook_id' => $user->id,
        'tenant_id' => $tenant->id,
        'flutterwave_reference' => 'FLW-99999999',
    ]);

    expect(PaymentTransaction::search('FLW-12345678')->count())->toBe(1);
});

test('search scope finds by customer name', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    PaymentTransaction::factory()->create([
        'client_id' => $user->id,
        'cook_id' => $user->id,
        'tenant_id' => $tenant->id,
        'customer_name' => 'Ngono Marie',
    ]);
    PaymentTransaction::factory()->create([
        'client_id' => $user->id,
        'cook_id' => $user->id,
        'tenant_id' => $tenant->id,
        'customer_name' => 'Fotso Jean',
    ]);

    expect(PaymentTransaction::search('Ngono')->count())->toBe(1);
});

test('search scope finds by customer email', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    PaymentTransaction::factory()->create([
        'client_id' => $user->id,
        'cook_id' => $user->id,
        'tenant_id' => $tenant->id,
        'customer_email' => 'marie@example.com',
    ]);

    expect(PaymentTransaction::search('marie@example.com')->count())->toBe(1);
});

test('search scope finds by numeric order ID', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    PaymentTransaction::factory()->create([
        'client_id' => $user->id,
        'cook_id' => $user->id,
        'tenant_id' => $tenant->id,
        'order_id' => 1042,
    ]);

    expect(PaymentTransaction::search('1042')->count())->toBe(1);
});

test('search scope finds by ORD- pattern', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    PaymentTransaction::factory()->create([
        'client_id' => $user->id,
        'cook_id' => $user->id,
        'tenant_id' => $tenant->id,
        'order_id' => 1042,
    ]);

    expect(PaymentTransaction::search('ORD-1042')->count())->toBe(1);
});

test('search scope returns all when empty', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    PaymentTransaction::factory()->count(3)->create([
        'client_id' => $user->id,
        'cook_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    expect(PaymentTransaction::search('')->count())->toBe(3)
        ->and(PaymentTransaction::search(null)->count())->toBe(3);
});

/*
|--------------------------------------------------------------------------
| Factory States
|--------------------------------------------------------------------------
*/

test('factory creates valid payment transaction', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $txn = PaymentTransaction::factory()->create([
        'client_id' => $user->id,
        'cook_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($txn)->toBeInstanceOf(PaymentTransaction::class)
        ->and($txn->id)->not->toBeNull()
        ->and($txn->status)->toBeIn(PaymentTransaction::STATUSES)
        ->and($txn->payment_method)->toBeIn(PaymentTransaction::PAYMENT_METHODS);
});

test('factory successful state sets correct attributes', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $txn = PaymentTransaction::factory()->successful()->create([
        'client_id' => $user->id,
        'cook_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($txn->status)->toBe('successful')
        ->and($txn->response_code)->toBe('00');
});

test('factory failed state sets correct attributes', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $txn = PaymentTransaction::factory()->failed()->create([
        'client_id' => $user->id,
        'cook_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($txn->status)->toBe('failed')
        ->and($txn->response_code)->toBe('99');
});

test('factory refunded state includes refund details', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $txn = PaymentTransaction::factory()->refunded()->create([
        'client_id' => $user->id,
        'cook_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($txn->status)->toBe('refunded')
        ->and($txn->refund_reason)->not->toBeNull()
        ->and($txn->refund_amount)->not->toBeNull();
});

test('factory missingWebhook state nullifies webhook fields', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $txn = PaymentTransaction::factory()->missingWebhook()->create([
        'client_id' => $user->id,
        'cook_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($txn->webhook_payload)->toBeNull()
        ->and($txn->flutterwave_reference)->toBeNull()
        ->and($txn->payment_channel)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Activity Logging
|--------------------------------------------------------------------------
*/

test('payment transaction excludes webhook_payload from activity log', function () {
    $txn = new PaymentTransaction;

    expect($txn->getAdditionalExcludedAttributes())->toContain('webhook_payload');
});
