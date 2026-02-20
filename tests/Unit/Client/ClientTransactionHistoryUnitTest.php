<?php

use App\Http\Requests\Client\ClientTransactionListRequest;
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
| F-164: Client Transaction History — Unit Tests
|--------------------------------------------------------------------------
|
| Tests for ClientTransactionService and the transaction list route.
| BR-260 through BR-270.
|
*/

// ===== ClientTransactionService Unit Tests =====

test('getTransactions returns payment transactions for client', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->paid()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    PaymentTransaction::factory()->successful()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'amount' => 5000,
    ]);

    $result = $service->getTransactions($client, []);

    expect($result->total())->toBe(1);
    expect($result->items()[0]['type'])->toBe('payment');
    expect($result->items()[0]['debit_credit'])->toBe('debit');
});

test('getTransactions returns refund transactions for client', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->refunded()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    PaymentTransaction::factory()->refunded()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'amount' => 3000,
        'refund_amount' => 3000,
    ]);

    $result = $service->getTransactions($client, []);

    expect($result->total())->toBe(1);
    expect($result->items()[0]['type'])->toBe('refund');
    expect($result->items()[0]['debit_credit'])->toBe('credit');
});

test('getTransactions returns wallet refund transactions for client', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->refunded()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    WalletTransaction::factory()->refund()->create([
        'user_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'amount' => 2000,
    ]);

    $result = $service->getTransactions($client, []);

    expect($result->total())->toBe(1);
    expect($result->items()[0]['type'])->toBe('refund');
    expect($result->items()[0]['debit_credit'])->toBe('credit');
});

test('BR-260: getTransactions shows transactions across all tenants', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    $order1 = Order::factory()->paid()->create(['client_id' => $client->id, 'tenant_id' => $tenant1->id]);
    $order2 = Order::factory()->paid()->create(['client_id' => $client->id, 'tenant_id' => $tenant2->id]);

    PaymentTransaction::factory()->successful()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant1->id,
        'order_id' => $order1->id,
    ]);
    PaymentTransaction::factory()->successful()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant2->id,
        'order_id' => $order2->id,
    ]);

    $result = $service->getTransactions($client, []);

    expect($result->total())->toBe(2);
});

test('BR-262: getTransactions default sort is newest first', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order1 = Order::factory()->paid()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);
    $order2 = Order::factory()->paid()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    PaymentTransaction::factory()->successful()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order1->id,
        'amount' => 1000,
        'created_at' => now()->subDays(2),
    ]);
    PaymentTransaction::factory()->successful()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order2->id,
        'amount' => 2000,
        'created_at' => now()->subDay(),
    ]);

    $result = $service->getTransactions($client, []);

    expect((float) $result->items()[0]['amount'])->toBe(2000.0);
    expect((float) $result->items()[1]['amount'])->toBe(1000.0);
});

test('BR-262: getTransactions supports ascending sort', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order1 = Order::factory()->paid()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);
    $order2 = Order::factory()->paid()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    PaymentTransaction::factory()->successful()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order1->id,
        'amount' => 1000,
        'created_at' => now()->subDays(2),
    ]);
    PaymentTransaction::factory()->successful()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order2->id,
        'amount' => 2000,
        'created_at' => now()->subDay(),
    ]);

    $result = $service->getTransactions($client, ['direction' => 'asc']);

    expect((float) $result->items()[0]['amount'])->toBe(1000.0);
});

test('BR-267: filter by payment type returns only payments', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->paid()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);
    $orderRefunded = Order::factory()->refunded()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    PaymentTransaction::factory()->successful()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);
    PaymentTransaction::factory()->refunded()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $orderRefunded->id,
    ]);

    $result = $service->getTransactions($client, ['type' => 'payment']);

    expect($result->total())->toBe(1);
    expect($result->items()[0]['type'])->toBe('payment');
});

test('BR-267: filter by refund type returns only refunds', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->paid()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);
    $orderRefunded = Order::factory()->refunded()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    PaymentTransaction::factory()->successful()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);
    PaymentTransaction::factory()->refunded()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $orderRefunded->id,
    ]);

    $result = $service->getTransactions($client, ['type' => 'refund']);

    expect($result->total())->toBe(1);
    expect($result->items()[0]['type'])->toBe('refund');
});

test('BR-264: each transaction has required fields', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->paid()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    PaymentTransaction::factory()->successful()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $result = $service->getTransactions($client, []);
    $txn = $result->items()[0];

    expect($txn)->toHaveKeys([
        'id', 'date', 'description', 'amount', 'type',
        'debit_credit', 'status', 'reference', 'payment_method',
        'order_number', 'order_id', 'tenant_name',
    ]);
});

test('BR-265: payment transactions have debit indicator', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->paid()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    PaymentTransaction::factory()->successful()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $result = $service->getTransactions($client, []);

    expect($result->items()[0]['debit_credit'])->toBe('debit');
});

test('BR-266: refund transactions have credit indicator', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->refunded()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    PaymentTransaction::factory()->refunded()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $result = $service->getTransactions($client, []);

    expect($result->items()[0]['debit_credit'])->toBe('credit');
});

test('getTransactions does not return other clients transactions', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $otherClient = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->paid()->create(['client_id' => $otherClient->id, 'tenant_id' => $tenant->id]);

    PaymentTransaction::factory()->successful()->create([
        'client_id' => $otherClient->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $result = $service->getTransactions($client, []);

    expect($result->total())->toBe(0);
});

test('getSummaryCounts returns correct category totals', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order1 = Order::factory()->paid()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);
    $order2 = Order::factory()->refunded()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    PaymentTransaction::factory()->successful()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order1->id,
    ]);
    PaymentTransaction::factory()->refunded()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order2->id,
    ]);
    WalletTransaction::factory()->refund()->create([
        'user_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order2->id,
    ]);

    $counts = $service->getSummaryCounts($client);

    expect($counts['total'])->toBe(3);
    expect($counts['payments'])->toBe(1);
    expect($counts['refunds'])->toBe(2);
    expect($counts['wallet_payments'])->toBe(0);
});

test('getTypeFilterOptions returns expected options', function () {
    $options = ClientTransactionService::getTypeFilterOptions();

    expect($options)->toHaveCount(4);
    expect(collect($options)->pluck('value')->toArray())->toBe(['', 'payment', 'refund', 'wallet_payment']);
});

test('formatXAF formats amounts correctly', function () {
    expect(ClientTransactionService::formatXAF(5000))->toBe('5,000 XAF');
    expect(ClientTransactionService::formatXAF(0))->toBe('0 XAF');
    expect(ClientTransactionService::formatXAF(15000))->toBe('15,000 XAF');
    expect(ClientTransactionService::formatXAF(1500000))->toBe('1,500,000 XAF');
});

test('failed payment transactions show with failed status', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'status' => 'payment_failed',
    ]);

    PaymentTransaction::factory()->failed()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $result = $service->getTransactions($client, []);

    expect($result->items()[0]['status'])->toBe('failed');
});

test('pending payment transactions show with pending status', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'status' => 'pending_payment',
    ]);

    PaymentTransaction::factory()->pending()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $result = $service->getTransactions($client, []);

    expect($result->items()[0]['status'])->toBe('pending');
});

// ===== Route / Controller Tests =====

test('BR-269: unauthenticated user cannot access transaction history', function () {
    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);
    $response = $this->get("https://{$mainDomain}/my-transactions");
    $response->assertRedirect();
});

test('BR-269: authenticated user can access transaction history', function () {
    $this->seedRolesAndPermissions();
    $client = $this->createUserWithRole('client');
    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $response = $this->actingAs($client)->get("https://{$mainDomain}/my-transactions");
    $response->assertOk();
});

test('transaction history page shows empty state with no transactions', function () {
    $this->seedRolesAndPermissions();
    $client = $this->createUserWithRole('client');
    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $response = $this->actingAs($client)->get("https://{$mainDomain}/my-transactions");

    $response->assertOk();
    $response->assertSee(__('No transactions yet.'));
});

test('transaction history page shows transactions', function () {
    $this->seedRolesAndPermissions();
    $client = $this->createUserWithRole('client');
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->paid()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    PaymentTransaction::factory()->successful()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'amount' => 5000,
    ]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);
    $response = $this->actingAs($client)->get("https://{$mainDomain}/my-transactions");

    $response->assertOk();
    $response->assertSee($order->order_number);
});

test('BR-267: type filter parameter is validated', function () {
    $this->seedRolesAndPermissions();
    $client = $this->createUserWithRole('client');
    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    // Valid type filter
    $response = $this->actingAs($client)->get("https://{$mainDomain}/my-transactions?type=payment");
    $response->assertOk();

    // Invalid type filter
    $response = $this->actingAs($client)->get("https://{$mainDomain}/my-transactions?type=invalid");
    $response->assertStatus(302); // Validation redirect
});

test('transaction description includes order number', function () {
    $service = new ClientTransactionService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->paid()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    PaymentTransaction::factory()->successful()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $result = $service->getTransactions($client, []);
    $txn = $result->items()[0];

    expect($txn['description'])->toContain($order->order_number);
    expect($txn['order_number'])->toBe($order->order_number);
});

test('BR-263: pagination defaults to 20 per page', function () {
    expect(ClientTransactionService::DEFAULT_PER_PAGE)->toBe(20);
});

test('FILTER_TYPES contains expected types', function () {
    expect(ClientTransactionService::FILTER_TYPES)->toBe(['payment', 'refund', 'wallet_payment']);
});

// ===== Form Request Tests =====

test('ClientTransactionListRequest authorizes authenticated users', function () {
    $this->seedRolesAndPermissions();
    $client = $this->createUserWithRole('client');

    $request = ClientTransactionListRequest::create('/my-transactions', 'GET', []);
    $request->setUserResolver(fn () => $client);

    expect($request->authorize())->toBeTrue();
});

test('ClientTransactionListRequest rejects unauthenticated users', function () {
    $request = ClientTransactionListRequest::create('/my-transactions', 'GET', []);
    $request->setUserResolver(fn () => null);

    expect($request->authorize())->toBeFalse();
});

test('ClientTransactionListRequest validates type filter options', function () {
    $request = new ClientTransactionListRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('type');
    expect($rules)->toHaveKey('direction');
    expect($rules)->toHaveKey('page');
});
