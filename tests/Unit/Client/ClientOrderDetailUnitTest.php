<?php

use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ClientOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| F-161: Client Order Detail & Status Tracking — Unit Tests
|--------------------------------------------------------------------------
|
| Tests for ClientOrderService::getOrderDetail() and the client order
| detail route at /my-orders/{order}.
|
| BR-222 through BR-235.
|
*/

// ===== Service Layer Tests =====

test('getOrderDetail returns all required data fields', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create(['whatsapp' => '+237612345678']);
    $order = Order::factory()->paid()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    $data = $service->getOrderDetail($order);

    expect($data)->toHaveKeys([
        'order',
        'items',
        'statusTimeline',
        'paymentTransaction',
        'canCancel',
        'cancellationSecondsRemaining',
        'canReport',
        'canRate',
        'cookWhatsapp',
        'cookName',
        'tenantUrl',
        'tenantActive',
    ]);
});

test('getOrderDetail parses order items from snapshot', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->paid()->withMultipleItems()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    $data = $service->getOrderDetail($order);

    expect($data['items'])->toHaveCount(3);
    expect($data['items'][0])->toHaveKeys(['meal_name', 'component_name', 'quantity', 'unit_price', 'subtotal']);
    expect($data['items'][0]['meal_name'])->toBe('Ndole');
});

test('getOrderDetail returns cook WhatsApp number when set', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create(['whatsapp' => '+237612345678']);
    $order = Order::factory()->paid()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    $data = $service->getOrderDetail($order);

    expect($data['cookWhatsapp'])->toBe('+237612345678');
});

test('getOrderDetail returns null WhatsApp when cook has no number', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create(['whatsapp' => null]);
    $order = Order::factory()->paid()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    $data = $service->getOrderDetail($order);

    expect($data['cookWhatsapp'])->toBeNull();
});

test('getOrderDetail shows tenant inactive status', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create(['is_active' => false]);
    $order = Order::factory()->paid()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    $data = $service->getOrderDetail($order);

    expect($data['tenantActive'])->toBeFalse();
});

// ===== Cancellation Window Tests (BR-225, BR-226) =====

test('canCancelOrder returns true for paid order within cancellation window', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    PlatformSetting::updateOrCreate(
        ['key' => 'default_cancellation_window'],
        ['value' => '30', 'type' => 'integer', 'group' => 'orders']
    );

    $order = Order::factory()->paid()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'paid_at' => now()->subMinutes(10),
    ]);

    expect($service->canCancelOrder($order))->toBeTrue();
});

test('canCancelOrder returns true for confirmed order within cancellation window', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    PlatformSetting::updateOrCreate(
        ['key' => 'default_cancellation_window'],
        ['value' => '30', 'type' => 'integer', 'group' => 'orders']
    );

    $order = Order::factory()->confirmed()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'paid_at' => now()->subMinutes(10),
    ]);

    expect($service->canCancelOrder($order))->toBeTrue();
});

test('canCancelOrder returns false when cancellation window expired', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    PlatformSetting::updateOrCreate(
        ['key' => 'default_cancellation_window'],
        ['value' => '30', 'type' => 'integer', 'group' => 'orders']
    );

    $order = Order::factory()->paid()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'paid_at' => now()->subMinutes(45),
    ]);

    expect($service->canCancelOrder($order))->toBeFalse();
});

test('canCancelOrder returns false for preparing status', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $order = Order::factory()->preparing()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($service->canCancelOrder($order))->toBeFalse();
});

test('canCancelOrder returns false for completed order', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $order = Order::factory()->completed()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($service->canCancelOrder($order))->toBeFalse();
});

test('canCancelOrder respects tenant-level cancellation window override', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create([
        'settings' => ['cancellation_window_minutes' => 60],
    ]);

    // Order paid 45 minutes ago -- outside 30-min default but within 60-min override
    $order = Order::factory()->paid()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'paid_at' => now()->subMinutes(45),
    ]);

    expect($service->canCancelOrder($order))->toBeTrue();
});

test('getCancellationSecondsRemaining returns correct value', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    PlatformSetting::updateOrCreate(
        ['key' => 'default_cancellation_window'],
        ['value' => '30', 'type' => 'integer', 'group' => 'orders']
    );

    // Order paid 10 minutes ago with 30-min window = 20 min remaining = ~1200 seconds
    $order = Order::factory()->paid()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'paid_at' => now()->subMinutes(10),
    ]);

    $remaining = $service->getCancellationSecondsRemaining($order);

    // Should be approximately 1200 seconds (20 minutes), with a small tolerance
    expect($remaining)->toBeGreaterThan(1190)->toBeLessThanOrEqual(1200);
});

// ===== Report / Rate Tests (BR-227, BR-228) =====

test('canReport is true for delivered orders', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $order = Order::factory()->delivered()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    $data = $service->getOrderDetail($order);

    expect($data['canReport'])->toBeTrue();
});

test('canReport is true for picked up orders', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $order = Order::factory()->pickedUp()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    $data = $service->getOrderDetail($order);

    expect($data['canReport'])->toBeTrue();
});

test('canReport is true for completed orders', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $order = Order::factory()->completed()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    $data = $service->getOrderDetail($order);

    expect($data['canReport'])->toBeTrue();
});

test('canReport is false for preparing orders', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $order = Order::factory()->preparing()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    $data = $service->getOrderDetail($order);

    expect($data['canReport'])->toBeFalse();
});

test('canRate is true for completed unrated orders', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $order = Order::factory()->completed()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    $data = $service->getOrderDetail($order);

    expect($data['canRate'])->toBeTrue();
});

test('canRate is false for non-completed orders', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $order = Order::factory()->preparing()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    $data = $service->getOrderDetail($order);

    expect($data['canRate'])->toBeFalse();
});

// ===== Status Refresh Tests (BR-223) =====

test('getOrderStatusRefresh returns correct structure', function () {
    $service = new ClientOrderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->preparing()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    $data = $service->getOrderStatusRefresh($order);

    expect($data)->toHaveKeys([
        'status',
        'statusLabel',
        'statusTimeline',
        'canCancel',
        'cancellationSecondsRemaining',
        'canReport',
        'canRate',
    ]);
    expect($data['status'])->toBe(Order::STATUS_PREPARING);
    expect($data['canCancel'])->toBeFalse();
});

// Note: HTTP/controller tests are NOT included here per the feature agent protocol.
// For UI features with blade files, Playwright handles controller verification in Phase 3.
// This unit test file covers service-layer logic only.
