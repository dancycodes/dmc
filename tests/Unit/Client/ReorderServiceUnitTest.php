<?php

use App\Models\Meal;
use App\Models\MealComponent;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ReorderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| F-199: Reorder from Past Order — Unit Tests
|--------------------------------------------------------------------------
|
| Tests for ReorderService business logic.
|
| Business Rules covered:
| BR-356: Only Completed, Delivered, Picked Up qualify for reorder.
| BR-357: Items + quantities copied into a new cart.
| BR-358: Current prices are used (not original prices).
| BR-359: Price changes are tracked and returned.
| BR-360: Unavailable components are excluded with a warning.
| BR-361: Deleted meals are excluded with an explanation.
| BR-362: All items unavailable → error, no cart.
| BR-363: Inactive tenant → error.
| BR-365: Existing cart for a different tenant → cart_conflict = true.
|
*/

// ===== BR-356: Eligibility Check =====

test('isEligibleForReorder returns true for completed orders', function () {
    $service = new ReorderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->completed()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($service->isEligibleForReorder($order))->toBeTrue();
});

test('isEligibleForReorder returns true for delivered orders', function () {
    $service = new ReorderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->delivered()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($service->isEligibleForReorder($order))->toBeTrue();
});

test('isEligibleForReorder returns false for paid orders', function () {
    $service = new ReorderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->paid()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($service->isEligibleForReorder($order))->toBeFalse();
});

test('isEligibleForReorder returns false for cancelled orders', function () {
    $service = new ReorderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->cancelled()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($service->isEligibleForReorder($order))->toBeFalse();
});

// ===== BR-363: Inactive Tenant =====

test('prepareReorder returns error when tenant is inactive', function () {
    $service = new ReorderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create(['is_active' => false]);
    $order = Order::factory()->completed()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'items_snapshot' => json_encode([
            ['meal_id' => 1, 'component_id' => 1, 'quantity' => 1, 'unit_price' => 1000, 'meal_name' => 'Ndole', 'component_name' => ''],
        ]),
    ]);

    $result = $service->prepareReorder($order, null);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toBe(__('This cook is no longer available on DancyMeals.'));
});

// ===== BR-361: Deleted Meals / Empty Snapshot =====

test('prepareReorder returns error when snapshot is empty', function () {
    $service = new ReorderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create(['is_active' => true]);
    $order = Order::factory()->completed()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'items_snapshot' => json_encode([]),
    ]);

    $result = $service->prepareReorder($order, null);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->not()->toBeNull();
});

// ===== BR-358 + BR-359: Current Prices, Price Changes =====

test('prepareReorder uses current component price and tracks price change', function () {
    $service = new ReorderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create(['is_active' => true]);

    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 3000, // Current price
        'is_available' => true,
    ]);

    $order = Order::factory()->completed()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'items_snapshot' => json_encode([
            [
                'meal_id' => $meal->id,
                'component_id' => $component->id,
                'quantity' => 2,
                'unit_price' => 2500, // Old price
                'meal_name' => 'Ndole',
                'component_name' => 'Large portion',
            ],
        ]),
    ]);

    $result = $service->prepareReorder($order, null);

    expect($result['success'])->toBeTrue()
        ->and($result['price_changes'])->toHaveCount(1)
        ->and($result['price_changes'][0]['old_price'])->toBe(2500)
        ->and($result['price_changes'][0]['new_price'])->toBe(3000)
        // BR-358: Cart uses current price
        ->and($result['_cart_items'][(string) $component->id]['unit_price'])->toBe(3000);
});

test('prepareReorder does not record price change when price is same', function () {
    $service = new ReorderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create(['is_active' => true]);

    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 2500,
        'is_available' => true,
    ]);

    $order = Order::factory()->completed()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'items_snapshot' => json_encode([
            [
                'meal_id' => $meal->id,
                'component_id' => $component->id,
                'quantity' => 1,
                'unit_price' => 2500, // Same price
                'meal_name' => 'Ndole',
                'component_name' => '',
            ],
        ]),
    ]);

    $result = $service->prepareReorder($order, null);

    expect($result['success'])->toBeTrue()
        ->and($result['price_changes'])->toBeEmpty();
});

// ===== BR-360: Unavailable Components =====

test('prepareReorder excludes unavailable component with warning', function () {
    $service = new ReorderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create(['is_active' => true]);

    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);

    $availableComponent = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 1500,
        'is_available' => true,
    ]);

    $unavailableComponent = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 2000,
        'is_available' => false,
    ]);

    $order = Order::factory()->completed()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'items_snapshot' => json_encode([
            [
                'meal_id' => $meal->id,
                'component_id' => $availableComponent->id,
                'quantity' => 1,
                'unit_price' => 1500,
                'meal_name' => 'Jollof',
                'component_name' => 'Regular',
            ],
            [
                'meal_id' => $meal->id,
                'component_id' => $unavailableComponent->id,
                'quantity' => 1,
                'unit_price' => 2000,
                'meal_name' => 'Jollof',
                'component_name' => 'Extra',
            ],
        ]),
    ]);

    $result = $service->prepareReorder($order, null);

    expect($result['success'])->toBeTrue()
        ->and($result['warnings'])->toHaveCount(1)
        ->and($result['items_added'])->toBe(1)
        ->and(isset($result['_cart_items'][(string) $availableComponent->id]))->toBeTrue()
        ->and(isset($result['_cart_items'][(string) $unavailableComponent->id]))->toBeFalse();
});

// ===== BR-362: All Items Unavailable =====

test('prepareReorder fails when all components are unavailable', function () {
    $service = new ReorderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create(['is_active' => true]);

    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => false, // Meal unavailable
    ]);

    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 2000,
        'is_available' => true,
    ]);

    $order = Order::factory()->completed()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'items_snapshot' => json_encode([
            [
                'meal_id' => $meal->id,
                'component_id' => $component->id,
                'quantity' => 2,
                'unit_price' => 2000,
                'meal_name' => 'Ndole',
                'component_name' => '',
            ],
        ]),
    ]);

    $result = $service->prepareReorder($order, null);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->not()->toBeNull()
        ->and($result['items_added'])->toBe(0);
});

// ===== BR-365: Cart Conflict =====

test('prepareReorder returns cart_conflict when existing cart is for different tenant', function () {
    $service = new ReorderService;
    $client = User::factory()->create();

    $targetTenant = Tenant::factory()->create(['is_active' => true]);
    $otherTenant = Tenant::factory()->create(['is_active' => true, 'name_en' => 'Other Cook']);

    $meal = Meal::factory()->create([
        'tenant_id' => $targetTenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 2000,
        'is_available' => true,
    ]);

    $order = Order::factory()->completed()->create([
        'client_id' => $client->id,
        'tenant_id' => $targetTenant->id,
        'items_snapshot' => json_encode([
            [
                'meal_id' => $meal->id,
                'component_id' => $component->id,
                'quantity' => 1,
                'unit_price' => 2000,
                'meal_name' => 'Test Meal',
                'component_name' => '',
            ],
        ]),
    ]);

    // Simulate an existing cart for a different tenant
    $result = $service->prepareReorder($order, $otherTenant->id);

    expect($result['success'])->toBeFalse()
        ->and($result['cart_conflict'])->toBeTrue()
        ->and($result['conflict_tenant_name'])->toBe('Other Cook');
});

// ===== Cart Session Write =====

test('writeCartToSession stores items in session under correct key', function () {
    $service = new ReorderService;
    $tenantId = 42;
    $cartItems = [
        '5' => [
            'component_id' => 5,
            'meal_id' => 1,
            'meal_name' => 'Ndole',
            'name' => 'Large portion',
            'unit_price' => 3000,
            'unit' => 'serving',
            'quantity' => 2,
        ],
    ];

    $service->writeCartToSession($tenantId, $cartItems);

    expect(Session::get('dmc-cart-42'))->toBe($cartItems);
});

test('getActiveCartTenantId returns null when no cart exists', function () {
    $service = new ReorderService;
    Session::forget('dmc-cart-1');
    Session::forget('dmc-cart-2');

    expect($service->getActiveCartTenantId())->toBeNull();
});

test('getActiveCartTenantId returns tenant id when cart exists', function () {
    $service = new ReorderService;
    Session::put('dmc-cart-99', ['some-item' => ['component_id' => 1]]);

    expect($service->getActiveCartTenantId())->toBe(99);
});

// ===== Successful Reorder =====

test('prepareReorder succeeds and returns redirect url with items', function () {
    $service = new ReorderService;
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create(['is_active' => true]);

    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 2000,
        'is_available' => true,
    ]);

    $order = Order::factory()->completed()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'items_snapshot' => json_encode([
            [
                'meal_id' => $meal->id,
                'component_id' => $component->id,
                'quantity' => 2,
                'unit_price' => 2000,
                'meal_name' => 'Ndole',
                'component_name' => 'Regular',
            ],
        ]),
    ]);

    $result = $service->prepareReorder($order, null);

    expect($result['success'])->toBeTrue()
        ->and($result['error'])->toBeNull()
        ->and($result['items_added'])->toBe(1)
        ->and($result['cart_conflict'])->toBeFalse()
        ->and($result['redirect_url'])->toContain('/cart')
        ->and($result['_tenant_id'])->toBe($tenant->id)
        ->and($result['_cart_items'][(string) $component->id]['quantity'])->toBe(2);
});
