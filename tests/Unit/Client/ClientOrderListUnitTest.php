<?php

use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ClientOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| F-160: Client Order List — Unit Tests
|--------------------------------------------------------------------------
|
| Tests for ClientOrderService and the client order list route.
| BR-212 through BR-221.
|
*/

// ===== ClientOrderService Unit Tests =====

test('getActiveOrders returns only active status orders for client', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();

    // Active orders
    Order::factory()->paid()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);
    Order::factory()->preparing()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);
    Order::factory()->outForDelivery()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    // Past orders
    Order::factory()->completed()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);
    Order::factory()->cancelled()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    $activeOrders = $service->getActiveOrders($client);

    expect($activeOrders)->toHaveCount(3);
    $activeOrders->each(function ($order) {
        expect($order->status)->toBeIn(ClientOrderService::ACTIVE_STATUSES);
    });
});

test('getActiveOrders returns orders sorted by date descending', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();

    Order::factory()->paid()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'created_at' => now()->subDays(2),
    ]);
    Order::factory()->confirmed()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'created_at' => now()->subDay(),
    ]);
    Order::factory()->preparing()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'created_at' => now(),
    ]);

    $activeOrders = $service->getActiveOrders($client);

    expect($activeOrders->first()->status)->toBe(Order::STATUS_PREPARING);
});

test('getActiveOrders returns orders across all tenants', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    $tenant3 = Tenant::factory()->create();

    Order::factory()->paid()->create(['client_id' => $client->id, 'tenant_id' => $tenant1->id]);
    Order::factory()->confirmed()->create(['client_id' => $client->id, 'tenant_id' => $tenant2->id]);
    Order::factory()->preparing()->create(['client_id' => $client->id, 'tenant_id' => $tenant3->id]);

    $activeOrders = $service->getActiveOrders($client);

    expect($activeOrders)->toHaveCount(3);
    $tenantIds = $activeOrders->pluck('tenant_id')->unique()->values()->toArray();
    expect($tenantIds)->toHaveCount(3);
});

test('getActiveOrders does not include other clients orders', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $otherClient = User::factory()->create();
    $tenant = Tenant::factory()->create();

    Order::factory()->paid()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);
    Order::factory()->paid()->create(['client_id' => $otherClient->id, 'tenant_id' => $tenant->id]);

    $activeOrders = $service->getActiveOrders($client);

    expect($activeOrders)->toHaveCount(1);
    expect($activeOrders->first()->client_id)->toBe($client->id);
});

test('getPastOrders returns only past status orders paginated', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();

    Order::factory()->completed()->count(5)->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);
    Order::factory()->cancelled()->count(3)->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);
    Order::factory()->paid()->count(2)->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    $pastOrders = $service->getPastOrders($client, []);

    expect($pastOrders->total())->toBe(8);
    foreach ($pastOrders as $order) {
        expect($order->status)->toBeIn(ClientOrderService::PAST_STATUSES);
    }
});

test('getPastOrders filters by status within past statuses', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();

    Order::factory()->completed()->count(3)->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);
    Order::factory()->cancelled()->count(2)->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    $pastOrders = $service->getPastOrders($client, ['status' => Order::STATUS_COMPLETED]);

    expect($pastOrders->total())->toBe(3);
    foreach ($pastOrders as $order) {
        expect($order->status)->toBe(Order::STATUS_COMPLETED);
    }
});

test('getPastOrders paginates at 15 per page', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();

    Order::factory()->completed()->count(20)->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    $pastOrders = $service->getPastOrders($client, []);

    expect($pastOrders->perPage())->toBe(15);
    expect($pastOrders->total())->toBe(20);
    expect($pastOrders->count())->toBe(15);
});

test('getFilteredOrders returns all statuses when filtered', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();

    Order::factory()->paid()->count(3)->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);
    Order::factory()->completed()->count(2)->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    $filteredOrders = $service->getFilteredOrders($client, ['status' => Order::STATUS_PAID]);

    expect($filteredOrders->total())->toBe(3);
    foreach ($filteredOrders as $order) {
        expect($order->status)->toBe(Order::STATUS_PAID);
    }
});

test('getActiveOrderCount returns correct count', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();

    Order::factory()->paid()->count(2)->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);
    Order::factory()->completed()->count(3)->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    $count = $service->getActiveOrderCount($client);

    expect($count)->toBe(2);
});

test('getStatusFilterOptions returns all status options', function () {
    $options = ClientOrderService::getStatusFilterOptions();

    expect($options)->toBeArray();
    expect($options[0]['value'])->toBe('');
    expect(count($options))->toBeGreaterThan(10);
});

test('formatXAF formats currency correctly', function () {
    expect(ClientOrderService::formatXAF(5000))->toBe('5,000 XAF');
    expect(ClientOrderService::formatXAF(0))->toBe('0 XAF');
    expect(ClientOrderService::formatXAF(1500000))->toBe('1,500,000 XAF');
});

test('getTenantUrl returns hash for null tenant', function () {
    expect(ClientOrderService::getTenantUrl(null))->toBe('#');
});

test('getTenantUrl returns tenant url for valid tenant', function () {
    $tenant = Tenant::factory()->create(['slug' => 'test-cook']);
    $url = ClientOrderService::getTenantUrl($tenant);
    expect($url)->toContain('test-cook');
});

// ===== Route / Controller Tests =====

test('unauthenticated user is redirected to login', function () {
    $this->seedRolesAndPermissions();

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $response = $this->get("http://{$mainDomain}/my-orders");

    $response->assertRedirect();
});

test('authenticated user can access client order list', function () {
    $user = $this->actingAsRole('client');
    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $response = $this->get("http://{$mainDomain}/my-orders");

    $response->assertSuccessful();
});

test('order list shows active orders pinned at top', function () {
    $user = $this->actingAsRole('client');
    $tenant = Tenant::factory()->create();
    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    Order::factory()->preparing()->create(['client_id' => $user->id, 'tenant_id' => $tenant->id]);
    Order::factory()->completed()->create(['client_id' => $user->id, 'tenant_id' => $tenant->id]);

    $response = $this->get("http://{$mainDomain}/my-orders");

    $response->assertSuccessful();
    $response->assertSee('Active Orders');
    $response->assertSee('Past Orders');
});

test('order list shows empty state when no orders', function () {
    $user = $this->actingAsRole('client');
    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $response = $this->get("http://{$mainDomain}/my-orders");

    $response->assertSuccessful();
    $response->assertSee("You haven't placed any orders yet.");
});

test('order list filters by status', function () {
    $user = $this->actingAsRole('client');
    $tenant = Tenant::factory()->create();
    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    Order::factory()->completed()->create(['client_id' => $user->id, 'tenant_id' => $tenant->id]);
    Order::factory()->cancelled()->create(['client_id' => $user->id, 'tenant_id' => $tenant->id]);

    $response = $this->get("http://{$mainDomain}/my-orders?status=completed");

    $response->assertSuccessful();
    // When filtered, active pinning is disabled
    $response->assertDontSee('Active Orders');
});

test('order list shows orders across multiple tenants', function () {
    $user = $this->actingAsRole('client');
    $tenant1 = Tenant::factory()->create(['name_en' => 'Chef Latifa']);
    $tenant2 = Tenant::factory()->create(['name_en' => 'Chef Powel']);
    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    Order::factory()->completed()->create(['client_id' => $user->id, 'tenant_id' => $tenant1->id]);
    Order::factory()->completed()->create(['client_id' => $user->id, 'tenant_id' => $tenant2->id]);

    $response = $this->get("http://{$mainDomain}/my-orders");

    $response->assertSuccessful();
    $response->assertSee('Chef Latifa');
    $response->assertSee('Chef Powel');
});

test('order list shows order number and total', function () {
    $user = $this->actingAsRole('client');
    $tenant = Tenant::factory()->create();
    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'order_number' => 'DMC-260220-0042',
        'grand_total' => 5000,
    ]);

    $response = $this->get("http://{$mainDomain}/my-orders");

    $response->assertSuccessful();
    $response->assertSee('DMC-260220-0042');
    $response->assertSee('5,000 XAF');
});

test('ACTIVE_STATUSES and PAST_STATUSES cover all non-payment-failed statuses', function () {
    $allCovered = array_merge(ClientOrderService::ACTIVE_STATUSES, ClientOrderService::PAST_STATUSES);
    $allStatuses = array_diff(Order::STATUSES, [Order::STATUS_PAYMENT_FAILED]);

    foreach ($allStatuses as $status) {
        expect(in_array($status, $allCovered, true))->toBeTrue(
            "Status '{$status}' is not in ACTIVE_STATUSES or PAST_STATUSES"
        );
    }
});

test('no active section shown when all orders are completed', function () {
    $user = $this->actingAsRole('client');
    $tenant = Tenant::factory()->create();
    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    Order::factory()->completed()->count(3)->create(['client_id' => $user->id, 'tenant_id' => $tenant->id]);

    $response = $this->get("http://{$mainDomain}/my-orders");

    $response->assertSuccessful();
    $response->assertDontSee('Active Orders');
});

test('client order list route is named correctly', function () {
    $this->seedRolesAndPermissions();

    $url = route('client.orders.index');
    expect($url)->toContain('/my-orders');
});

test('active orders include all non-terminal statuses', function () {
    $expectedActive = [
        Order::STATUS_PENDING_PAYMENT,
        Order::STATUS_PAID,
        Order::STATUS_CONFIRMED,
        Order::STATUS_PREPARING,
        Order::STATUS_READY,
        Order::STATUS_OUT_FOR_DELIVERY,
        Order::STATUS_READY_FOR_PICKUP,
        Order::STATUS_DELIVERED,
        Order::STATUS_PICKED_UP,
    ];

    expect(ClientOrderService::ACTIVE_STATUSES)->toBe($expectedActive);
});

test('getPastOrders supports ascending sort direction', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();

    Order::factory()->completed()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'created_at' => now()->subDays(5),
    ]);
    Order::factory()->completed()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'created_at' => now(),
    ]);

    $pastOrders = $service->getPastOrders($client, ['direction' => 'asc']);

    expect($pastOrders->first()->created_at->lt($pastOrders->last()->created_at))->toBeTrue();
});

test('getActiveOrders eager loads tenant relationship', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create(['name_en' => 'Test Cook']);

    Order::factory()->paid()->create(['client_id' => $client->id, 'tenant_id' => $tenant->id]);

    $activeOrders = $service->getActiveOrders($client);

    expect($activeOrders->first()->relationLoaded('tenant'))->toBeTrue();
    expect($activeOrders->first()->tenant->name_en)->toBe('Test Cook');
});
