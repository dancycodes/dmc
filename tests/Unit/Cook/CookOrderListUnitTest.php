<?php

/**
 * F-155: Cook Order List View — Unit Tests
 *
 * Tests for CookOrderService and Order model methods.
 */

use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CookOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();
});

// =============================================
// CookOrderService Tests
// =============================================

test('getOrderList returns paginated orders for tenant', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = User::factory()->create();
    Order::factory()->count(3)->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    // Orders for different tenant should not appear
    $otherTenant = Tenant::factory()->create();
    Order::factory()->count(2)->create([
        'tenant_id' => $otherTenant->id,
        'client_id' => $client->id,
    ]);

    $service = new CookOrderService;
    $result = $service->getOrderList($tenant, []);

    expect($result->total())->toBe(3);
});

test('getOrderList filters by status', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = User::factory()->create();

    Order::factory()->paid()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'cook_id' => $cook->id]);
    Order::factory()->confirmed()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'cook_id' => $cook->id]);
    Order::factory()->preparing()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'cook_id' => $cook->id]);

    $service = new CookOrderService;
    $result = $service->getOrderList($tenant, ['status' => Order::STATUS_PAID]);

    expect($result->total())->toBe(1);
    expect($result->items()[0]->status)->toBe(Order::STATUS_PAID);
});

test('getOrderList searches by order number case-insensitively', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = User::factory()->create();

    Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'order_number' => 'DMC-260220-0001',
    ]);
    Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'order_number' => 'DMC-260220-0002',
    ]);

    $service = new CookOrderService;
    $result = $service->getOrderList($tenant, ['search' => '0001']);

    expect($result->total())->toBe(1);
    expect($result->items()[0]->order_number)->toBe('DMC-260220-0001');
});

test('getOrderList searches by client name case-insensitively', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $amara = User::factory()->create(['name' => 'Amara Nchinda']);
    $belle = User::factory()->create(['name' => 'Belle Foncha']);

    Order::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $amara->id, 'cook_id' => $cook->id]);
    Order::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $belle->id, 'cook_id' => $cook->id]);

    $service = new CookOrderService;
    $result = $service->getOrderList($tenant, ['search' => 'amara']);

    expect($result->total())->toBe(1);
});

test('getOrderList filters by date range', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = User::factory()->create();

    Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'created_at' => now()->subDays(10),
    ]);
    Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'created_at' => now()->subDays(3),
    ]);
    Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'created_at' => now(),
    ]);

    $service = new CookOrderService;
    $result = $service->getOrderList($tenant, [
        'date_from' => now()->subDays(5)->toDateString(),
        'date_to' => now()->toDateString(),
    ]);

    expect($result->total())->toBe(2);
});

test('getOrderList combines search and status filter', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = User::factory()->create(['name' => 'Amara']);

    Order::factory()->paid()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'cook_id' => $cook->id]);
    Order::factory()->confirmed()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'cook_id' => $cook->id]);

    $service = new CookOrderService;
    $result = $service->getOrderList($tenant, [
        'search' => 'Amara',
        'status' => Order::STATUS_PAID,
    ]);

    expect($result->total())->toBe(1);
    expect($result->items()[0]->status)->toBe(Order::STATUS_PAID);
});

test('getOrderList sorts by created_at desc by default', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = User::factory()->create();

    $old = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'created_at' => now()->subDays(5),
    ]);
    $new = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'created_at' => now(),
    ]);

    $service = new CookOrderService;
    $result = $service->getOrderList($tenant, []);

    expect($result->items()[0]->id)->toBe($new->id);
    expect($result->items()[1]->id)->toBe($old->id);
});

test('getOrderList sorts by grand_total ascending', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = User::factory()->create();

    $cheap = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'grand_total' => 1000,
    ]);
    $expensive = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'grand_total' => 50000,
    ]);

    $service = new CookOrderService;
    $result = $service->getOrderList($tenant, [
        'sort' => 'grand_total',
        'direction' => 'asc',
    ]);

    expect($result->items()[0]->id)->toBe($cheap->id);
    expect($result->items()[1]->id)->toBe($expensive->id);
});

test('getOrderList rejects invalid sort columns', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = User::factory()->create();
    Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    $service = new CookOrderService;
    // Should not throw, falls back to created_at
    $result = $service->getOrderList($tenant, ['sort' => 'malicious_column']);

    expect($result->total())->toBe(1);
});

test('getOrderSummary returns correct counts', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = User::factory()->create();

    Order::factory()->paid()->count(2)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'cook_id' => $cook->id]);
    Order::factory()->confirmed()->count(3)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'cook_id' => $cook->id]);
    Order::factory()->preparing()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'cook_id' => $cook->id]);
    Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'cook_id' => $cook->id]);
    Order::factory()->cancelled()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'cook_id' => $cook->id]);

    $service = new CookOrderService;
    $summary = $service->getOrderSummary($tenant);

    expect($summary['total'])->toBe(8);
    expect($summary['paid'])->toBe(2);
    expect($summary['confirmed'])->toBe(3);
    expect($summary['preparing'])->toBe(1);
    expect($summary['completed'])->toBe(1);
    expect($summary['cancelled'])->toBe(1);
});

test('getOrderSummary is tenant-scoped', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $otherTenant = Tenant::factory()->create();
    $client = User::factory()->create();

    Order::factory()->paid()->count(5)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'cook_id' => $cook->id]);
    Order::factory()->paid()->count(10)->create(['tenant_id' => $otherTenant->id, 'client_id' => $client->id]);

    $service = new CookOrderService;
    $summary = $service->getOrderSummary($tenant);

    expect($summary['total'])->toBe(5);
    expect($summary['paid'])->toBe(5);
});

// =============================================
// Items Summary Tests
// =============================================

test('getItemsSummary formats meal names with quantities', function () {
    $order = Order::factory()->withMultipleItems()->make();

    $summary = CookOrderService::getItemsSummary($order);

    expect($summary)->toContain('Ndole x2');
    expect($summary)->toContain('Eru x1');
    expect($summary)->toContain('Jollof Rice x3');
});

test('getItemsSummary handles empty snapshot', function () {
    $order = Order::factory()->make(['items_snapshot' => null]);

    $summary = CookOrderService::getItemsSummary($order);

    expect($summary)->toBe(__('No items'));
});

test('getItemsSummary truncates long summaries', function () {
    $order = Order::factory()->make([
        'items_snapshot' => [
            ['meal_name' => 'Ndole with Plantains', 'quantity' => 2],
            ['meal_name' => 'Eru with Fufu Corn', 'quantity' => 1],
            ['meal_name' => 'Jollof Rice Special', 'quantity' => 3],
            ['meal_name' => 'Grilled Fish Platter', 'quantity' => 1],
            ['meal_name' => 'Pepper Soup Extra', 'quantity' => 2],
        ],
    ]);

    $summary = CookOrderService::getItemsSummary($order, 40);

    expect(mb_strlen($summary))->toBeLessThanOrEqual(43); // 40 + '...'
    expect($summary)->toEndWith('...');
});

test('getItemsSummary handles double-encoded JSON strings', function () {
    $items = [['meal_name' => 'Ndole', 'quantity' => 2]];
    $order = Order::factory()->make();
    // Simulate double-encoded JSON string
    $order->setAttribute('items_snapshot', json_encode($items));

    $summary = CookOrderService::getItemsSummary($order);

    expect($summary)->toContain('Ndole x2');
});

test('getItemsSummary handles legacy format keys', function () {
    $order = Order::factory()->make([
        'items_snapshot' => [
            ['meal' => 'Ndole', 'quantity' => 1],
        ],
    ]);

    $summary = CookOrderService::getItemsSummary($order);

    expect($summary)->toContain('Ndole x1');
});

// =============================================
// Format & Static Method Tests
// =============================================

test('formatXAF formats amounts correctly', function () {
    expect(CookOrderService::formatXAF(0))->toBe('0 XAF');
    expect(CookOrderService::formatXAF(1500))->toBe('1,500 XAF');
    expect(CookOrderService::formatXAF(100000))->toBe('100,000 XAF');
});

test('getStatusFilterOptions returns all order statuses', function () {
    $options = CookOrderService::getStatusFilterOptions();

    // First option is "All Statuses" with empty value
    expect($options[0]['value'])->toBe('');
    expect(count($options))->toBeGreaterThan(5);
});

// =============================================
// Order Model Tests
// =============================================

test('Order search scope matches order number', function () {
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();

    Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'order_number' => 'DMC-260220-0042',
    ]);
    Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'order_number' => 'DMC-260220-0099',
    ]);

    $results = Order::search('0042')->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->order_number)->toBe('DMC-260220-0042');
});

test('Order search scope matches client name', function () {
    $amara = User::factory()->create(['name' => 'Amara Nchinda']);
    $belle = User::factory()->create(['name' => 'Belle']);
    $tenant = Tenant::factory()->create();

    Order::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $amara->id]);
    Order::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $belle->id]);

    $results = Order::search('amara')->get();

    expect($results)->toHaveCount(1);
});

test('Order items_summary accessor returns formatted string', function () {
    $order = Order::factory()->withMultipleItems()->make();

    expect($order->items_summary)->toContain('Ndole x2');
    expect($order->items_summary)->toContain('Eru x1');
});

test('Order forTenant scope filters by tenant_id', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $client = User::factory()->create();

    Order::factory()->count(3)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
    Order::factory()->count(5)->create(['tenant_id' => $otherTenant->id, 'client_id' => $client->id]);

    expect(Order::forTenant($tenant->id)->count())->toBe(3);
});

// =============================================
// Factory States Tests
// =============================================

test('Order factory confirmed state sets correct fields', function () {
    $order = Order::factory()->confirmed()->make();

    expect($order->status)->toBe(Order::STATUS_CONFIRMED);
    expect($order->confirmed_at)->not->toBeNull();
    expect($order->paid_at)->not->toBeNull();
});

test('Order factory preparing state sets correct fields', function () {
    $order = Order::factory()->preparing()->make();

    expect($order->status)->toBe(Order::STATUS_PREPARING);
});

test('Order factory completed state sets correct fields', function () {
    $order = Order::factory()->completed()->make();

    expect($order->status)->toBe(Order::STATUS_COMPLETED);
    expect($order->completed_at)->not->toBeNull();
});

test('Order factory withMultipleItems state has 3 items', function () {
    $order = Order::factory()->withMultipleItems()->make();

    expect($order->items_snapshot)->toHaveCount(3);
});
