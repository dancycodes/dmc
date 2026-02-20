<?php

/**
 * F-156: Cook Order Detail View — Unit Tests
 *
 * Tests the CookOrderService::getOrderDetail() method,
 * Order model helper methods (getNextStatus, isTerminal, etc.),
 * and the controller show() method authorization.
 */

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CookOrderService;

uses(Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();
});

// --- Order Model: getNextStatus() ---

test('getNextStatus returns confirmed for paid orders', function () {
    $order = Order::factory()->paid()->make();
    expect($order->getNextStatus())->toBe(Order::STATUS_CONFIRMED);
});

test('getNextStatus returns preparing for confirmed orders', function () {
    $order = Order::factory()->confirmed()->make();
    expect($order->getNextStatus())->toBe(Order::STATUS_PREPARING);
});

test('getNextStatus returns ready for preparing orders', function () {
    $order = Order::factory()->preparing()->make();
    expect($order->getNextStatus())->toBe(Order::STATUS_READY);
});

test('getNextStatus returns out_for_delivery for ready delivery orders', function () {
    $order = Order::factory()->ready()->delivery()->make();
    expect($order->getNextStatus())->toBe(Order::STATUS_OUT_FOR_DELIVERY);
});

test('getNextStatus returns ready_for_pickup for ready pickup orders', function () {
    $order = Order::factory()->ready()->pickup()->make();
    expect($order->getNextStatus())->toBe(Order::STATUS_READY_FOR_PICKUP);
});

test('getNextStatus returns completed for delivered orders', function () {
    $order = Order::factory()->make(['status' => Order::STATUS_DELIVERED]);
    expect($order->getNextStatus())->toBe(Order::STATUS_COMPLETED);
});

test('getNextStatus returns completed for picked_up orders', function () {
    $order = Order::factory()->make(['status' => Order::STATUS_PICKED_UP]);
    expect($order->getNextStatus())->toBe(Order::STATUS_COMPLETED);
});

test('getNextStatus returns null for completed orders (terminal)', function () {
    $order = Order::factory()->completed()->make();
    expect($order->getNextStatus())->toBeNull();
});

test('getNextStatus returns null for cancelled orders (terminal)', function () {
    $order = Order::factory()->cancelled()->make();
    expect($order->getNextStatus())->toBeNull();
});

test('getNextStatus returns null for pending_payment orders', function () {
    $order = Order::factory()->pendingPayment()->make();
    expect($order->getNextStatus())->toBeNull();
});

// --- Order Model: isTerminal() ---

test('isTerminal returns true for completed status', function () {
    $order = Order::factory()->completed()->make();
    expect($order->isTerminal())->toBeTrue();
});

test('isTerminal returns true for cancelled status', function () {
    $order = Order::factory()->cancelled()->make();
    expect($order->isTerminal())->toBeTrue();
});

test('isTerminal returns false for preparing status', function () {
    $order = Order::factory()->preparing()->make();
    expect($order->isTerminal())->toBeFalse();
});

// --- Order Model: getStatusLabel() ---

test('getStatusLabel returns correct labels', function () {
    expect(Order::getStatusLabel(Order::STATUS_PAID))->toBe(__('Paid'));
    expect(Order::getStatusLabel(Order::STATUS_CONFIRMED))->toBe(__('Confirmed'));
    expect(Order::getStatusLabel(Order::STATUS_PREPARING))->toBe(__('Preparing'));
    expect(Order::getStatusLabel(Order::STATUS_READY))->toBe(__('Ready'));
    expect(Order::getStatusLabel(Order::STATUS_OUT_FOR_DELIVERY))->toBe(__('Out for Delivery'));
    expect(Order::getStatusLabel(Order::STATUS_COMPLETED))->toBe(__('Completed'));
    expect(Order::getStatusLabel(Order::STATUS_CANCELLED))->toBe(__('Cancelled'));
});

// --- Order Model: getPaymentProviderLabel() ---

test('getPaymentProviderLabel returns correct labels', function () {
    $order = Order::factory()->mtn()->make();
    expect($order->getPaymentProviderLabel())->toBe('MTN MoMo');

    $order = Order::factory()->orange()->make();
    expect($order->getPaymentProviderLabel())->toBe('Orange Money');

    $order = Order::factory()->make(['payment_provider' => 'wallet']);
    expect($order->getPaymentProviderLabel())->toBe(__('Wallet Balance'));
});

// --- CookOrderService: parseOrderItems() ---

test('parseOrderItems returns structured items from snapshot', function () {
    $order = Order::factory()->withMultipleItems()->make();
    $service = new CookOrderService;

    $items = $service->parseOrderItems($order);

    expect($items)->toHaveCount(3);
    expect($items[0])->toHaveKeys(['meal_name', 'component_name', 'quantity', 'unit_price', 'subtotal']);
    expect($items[0]['meal_name'])->toBe('Ndole');
    expect($items[0]['quantity'])->toBe(2);
    expect($items[0]['unit_price'])->toBe(1500);
    expect($items[0]['subtotal'])->toBe(3000);
});

test('parseOrderItems returns empty array for null snapshot', function () {
    $order = Order::factory()->make(['items_snapshot' => null]);
    $service = new CookOrderService;

    expect($service->parseOrderItems($order))->toBeEmpty();
});

test('parseOrderItems handles double-encoded JSON', function () {
    $snapshot = json_encode([
        ['meal_name' => 'Ndole', 'component_name' => 'Standard', 'quantity' => 1, 'unit_price' => 1500, 'subtotal' => 1500],
    ]);
    $order = Order::factory()->make(['items_snapshot' => $snapshot]);
    $service = new CookOrderService;

    $items = $service->parseOrderItems($order);

    expect($items)->toHaveCount(1);
    expect($items[0]['meal_name'])->toBe('Ndole');
});

test('parseOrderItems handles legacy key format', function () {
    $order = Order::factory()->make([
        'items_snapshot' => [
            ['meal' => 'Jollof', 'component' => 'Large', 'quantity' => 1, 'price' => 2000],
        ],
    ]);
    $service = new CookOrderService;

    $items = $service->parseOrderItems($order);

    expect($items)->toHaveCount(1);
    expect($items[0]['meal_name'])->toBe('Jollof');
    expect($items[0]['unit_price'])->toBe(2000);
});

// --- CookOrderService: getStatusTimeline() ---

test('getStatusTimeline includes order creation entry', function () {
    $order = Order::factory()->paid()->create();
    $service = new CookOrderService;

    $timeline = $service->getStatusTimeline($order);

    expect($timeline)->toBeArray();
    expect(count($timeline))->toBeGreaterThanOrEqual(1);
    expect($timeline[0]['status'])->toBe(Order::STATUS_PENDING_PAYMENT);
});

test('getStatusTimeline includes paid_at entry when set', function () {
    $order = Order::factory()->paid()->create();
    $service = new CookOrderService;

    $timeline = $service->getStatusTimeline($order);

    $paidEntry = collect($timeline)->firstWhere('status', Order::STATUS_PAID);
    expect($paidEntry)->not->toBeNull();
    expect($paidEntry['label'])->toBe(__('Paid'));
});

test('getStatusTimeline includes confirmed_at entry', function () {
    $order = Order::factory()->confirmed()->create();
    $service = new CookOrderService;

    $timeline = $service->getStatusTimeline($order);

    $confirmedEntry = collect($timeline)->firstWhere('status', Order::STATUS_CONFIRMED);
    expect($confirmedEntry)->not->toBeNull();
});

test('getStatusTimeline includes cancelled_at entry', function () {
    $order = Order::factory()->cancelled()->create();
    $service = new CookOrderService;

    $timeline = $service->getStatusTimeline($order);

    $cancelledEntry = collect($timeline)->firstWhere('status', Order::STATUS_CANCELLED);
    expect($cancelledEntry)->not->toBeNull();
});

// --- CookOrderService: getOrderDetail() ---

test('getOrderDetail returns all expected keys', function () {
    $order = Order::factory()->paid()->create();
    $service = new CookOrderService;

    $detail = $service->getOrderDetail($order);

    expect($detail)->toHaveKeys([
        'order', 'items', 'statusTimeline', 'nextStatus', 'nextStatusLabel', 'paymentTransaction',
    ]);
});

test('getOrderDetail returns correct nextStatus for paid order', function () {
    $order = Order::factory()->paid()->create();
    $service = new CookOrderService;

    $detail = $service->getOrderDetail($order);

    expect($detail['nextStatus'])->toBe(Order::STATUS_CONFIRMED);
    expect($detail['nextStatusLabel'])->toBe(__('Confirmed'));
});

test('getOrderDetail returns null nextStatus for completed order', function () {
    $order = Order::factory()->completed()->create();
    $service = new CookOrderService;

    $detail = $service->getOrderDetail($order);

    expect($detail['nextStatus'])->toBeNull();
    expect($detail['nextStatusLabel'])->toBeNull();
});

test('getOrderDetail loads client relationship', function () {
    $client = User::factory()->create(['name' => 'Test Client']);
    $order = Order::factory()->paid()->create(['client_id' => $client->id]);
    $service = new CookOrderService;

    $detail = $service->getOrderDetail($order);

    expect($detail['order']->client)->not->toBeNull();
    expect($detail['order']->client->name)->toBe('Test Client');
});

// --- CookOrderService: getLatestPaymentTransaction() ---

test('getLatestPaymentTransaction returns latest transaction', function () {
    $order = Order::factory()->paid()->create();
    $tx = PaymentTransaction::factory()->create([
        'order_id' => $order->id,
        'tenant_id' => $order->tenant_id,
        'status' => 'successful',
    ]);
    $service = new CookOrderService;

    $result = $service->getLatestPaymentTransaction($order);

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($tx->id);
});

test('getLatestPaymentTransaction returns null when no transactions', function () {
    $order = Order::factory()->paid()->create();
    $service = new CookOrderService;

    $result = $service->getLatestPaymentTransaction($order);

    expect($result)->toBeNull();
});

// --- Controller: Authorization ---

test('show returns 403 for users without manage-orders permission', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $user->assignRole('client');
    $order = Order::factory()->paid()->create(['tenant_id' => $tenant->id]);

    $host = parse_url(config('app.url'), PHP_URL_HOST);
    $url = "https://{$tenant->slug}.{$host}/dashboard/orders/{$order->id}";

    // Client role doesn't have cook dashboard access, so EnsureCookAccess middleware returns 403
    $response = $this->actingAs($user)->get($url);
    expect($response->status())->toBeIn([403, 404]);
});

test('show returns 404 for order from different tenant', function () {
    $cook = test()->createUserWithRole('cook');
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $otherTenant = Tenant::factory()->create();
    $order = Order::factory()->paid()->create(['tenant_id' => $otherTenant->id]);

    $host = parse_url(config('app.url'), PHP_URL_HOST);
    $url = "https://{$tenant->slug}.{$host}/dashboard/orders/{$order->id}";

    $this->actingAs($cook)
        ->get($url)
        ->assertStatus(404);
});

test('show returns 200 for cook with manage-orders permission', function () {
    $cook = test()->createUserWithRole('cook');
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->paid()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $host = parse_url(config('app.url'), PHP_URL_HOST);
    $url = "https://{$tenant->slug}.{$host}/dashboard/orders/{$order->id}";

    $this->actingAs($cook)
        ->get($url)
        ->assertOk();
});

// --- Order Model: notes field ---

test('order notes field is fillable', function () {
    $order = Order::factory()->create(['notes' => 'No pepper please']);
    expect($order->notes)->toBe('No pepper please');
});

test('order notes field defaults to null', function () {
    $order = Order::factory()->create();
    expect($order->notes)->toBeNull();
});

// --- Order Factory states ---

test('withNotes factory state sets notes', function () {
    $order = Order::factory()->withNotes('Extra plantain please')->create();
    expect($order->notes)->toBe('Extra plantain please');
});
