<?php

/**
 * F-138: Meal Component Selection & Cart Add — Unit Tests
 *
 * Tests the CartService business logic including:
 * - Adding components to cart
 * - Quantity limits (BR-243)
 * - Requirement rules enforcement (BR-244)
 * - Running totals (BR-245)
 * - Session-based cart (BR-246)
 * - Guest cart (BR-247)
 * - Duplicate handling (BR-248)
 * - Grouping by meal (BR-249)
 * - XAF pricing (BR-250)
 */

use App\Models\ComponentRequirementRule;
use App\Models\Meal;
use App\Models\MealComponent;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();
    test()->seedSellingUnits();
    $this->cartService = new CartService;
    $this->tenantData = test()->createTenantWithCook();
    $this->tenant = $this->tenantData['tenant'];
});

// --- Cart Basics ---

test('empty cart returns correct structure', function () {
    $cart = $this->cartService->getCart($this->tenant->id);

    expect($cart)->toHaveKeys(['items', 'meals', 'summary'])
        ->and($cart['items'])->toBeEmpty()
        ->and($cart['meals'])->toBeEmpty()
        ->and($cart['summary']['count'])->toBe(0)
        ->and($cart['summary']['total'])->toBe(0);
});

test('cart summary returns count and total', function () {
    $summary = $this->cartService->getCartSummary($this->tenant->id);

    expect($summary)->toHaveKeys(['count', 'total'])
        ->and($summary['count'])->toBe(0)
        ->and($summary['total'])->toBe(0);
});

// --- Adding to Cart (BR-246, BR-247, BR-248, BR-250) ---

test('can add component to cart', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);

    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 2000,
        'is_available' => true,
        'min_quantity' => 1,
        'max_quantity' => 10,
        'available_quantity' => null,
    ]);

    $result = $this->cartService->addToCart($this->tenant->id, $meal->id, $component->id, 2);

    expect($result['success'])->toBeTrue()
        ->and($result['error'])->toBeNull()
        ->and($result['cart']['summary']['count'])->toBe(2)
        ->and($result['cart']['summary']['total'])->toBe(4000);
});

test('adding same component updates quantity instead of duplicating', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);

    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 1500,
        'is_available' => true,
        'min_quantity' => 1,
        'max_quantity' => 10,
        'available_quantity' => null,
    ]);

    $this->cartService->addToCart($this->tenant->id, $meal->id, $component->id, 2);
    $result = $this->cartService->addToCart($this->tenant->id, $meal->id, $component->id, 3);

    expect($result['success'])->toBeTrue()
        ->and($result['cart']['summary']['count'])->toBe(3)
        ->and($result['cart']['summary']['total'])->toBe(4500)
        ->and(count($result['cart']['items']))->toBe(1);
});

test('can add components from multiple meals', function () {
    $meal1 = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);

    $meal2 = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);

    $comp1 = MealComponent::factory()->create([
        'meal_id' => $meal1->id,
        'price' => 2000,
        'is_available' => true,
        'min_quantity' => 1,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    $comp2 = MealComponent::factory()->create([
        'meal_id' => $meal2->id,
        'price' => 500,
        'is_available' => true,
        'min_quantity' => 1,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    $this->cartService->addToCart($this->tenant->id, $meal1->id, $comp1->id, 2);
    $result = $this->cartService->addToCart($this->tenant->id, $meal2->id, $comp2->id, 1);

    expect($result['cart']['summary']['count'])->toBe(3)
        ->and($result['cart']['summary']['total'])->toBe(4500);
});

// --- Grouping by Meal (BR-249) ---

test('cart items are grouped by meal', function () {
    $meal1 = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);

    $meal2 = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);

    $comp1 = MealComponent::factory()->create([
        'meal_id' => $meal1->id,
        'price' => 2000,
        'is_available' => true,
        'min_quantity' => 1,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    $comp2 = MealComponent::factory()->create([
        'meal_id' => $meal1->id,
        'price' => 500,
        'is_available' => true,
        'min_quantity' => 1,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    $comp3 = MealComponent::factory()->create([
        'meal_id' => $meal2->id,
        'price' => 1000,
        'is_available' => true,
        'min_quantity' => 1,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    $this->cartService->addToCart($this->tenant->id, $meal1->id, $comp1->id, 2);
    $this->cartService->addToCart($this->tenant->id, $meal1->id, $comp2->id, 1);
    $this->cartService->addToCart($this->tenant->id, $meal2->id, $comp3->id, 1);

    $cart = $this->cartService->getCart($this->tenant->id);

    expect($cart['meals'])->toHaveCount(2)
        ->and($cart['meals'][0]['items'])->toHaveCount(2)
        ->and($cart['meals'][0]['subtotal'])->toBe(4500)
        ->and($cart['meals'][1]['items'])->toHaveCount(1)
        ->and($cart['meals'][1]['subtotal'])->toBe(1000);
});

// --- Quantity Limits (BR-243) ---

test('quantity is capped at stock limit', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);

    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 1000,
        'is_available' => true,
        'min_quantity' => 1,
        'max_quantity' => null,
        'available_quantity' => 3,
    ]);

    $result = $this->cartService->addToCart($this->tenant->id, $meal->id, $component->id, 5);

    expect($result['success'])->toBeTrue()
        ->and($result['cart']['summary']['count'])->toBe(3);
});

test('quantity is capped at cook-defined max', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);

    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 1000,
        'is_available' => true,
        'min_quantity' => 1,
        'max_quantity' => 4,
        'available_quantity' => null,
    ]);

    $result = $this->cartService->addToCart($this->tenant->id, $meal->id, $component->id, 10);

    expect($result['success'])->toBeTrue()
        ->and($result['cart']['summary']['count'])->toBe(4);
});

test('quantity uses lesser of stock and max', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);

    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 1000,
        'is_available' => true,
        'min_quantity' => 1,
        'max_quantity' => 10,
        'available_quantity' => 3,
    ]);

    $result = $this->cartService->addToCart($this->tenant->id, $meal->id, $component->id, 8);

    expect($result['success'])->toBeTrue()
        ->and($result['cart']['summary']['count'])->toBe(3);
});

test('quantity minimum is 1', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);

    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 1000,
        'is_available' => true,
        'min_quantity' => 1,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    $result = $this->cartService->addToCart($this->tenant->id, $meal->id, $component->id, 0);

    expect($result['success'])->toBeTrue()
        ->and($result['cart']['summary']['count'])->toBe(1);
});

// --- Sold-Out Validation (BR-162) ---

test('cannot add sold out component', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);

    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 1000,
        'is_available' => true,
        'min_quantity' => 1,
        'max_quantity' => null,
        'available_quantity' => 0,
    ]);

    $result = $this->cartService->addToCart($this->tenant->id, $meal->id, $component->id, 1);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->not->toBeNull();
});

test('cannot add unavailable component', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);

    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 1000,
        'is_available' => false,
        'min_quantity' => 1,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    $result = $this->cartService->addToCart($this->tenant->id, $meal->id, $component->id, 1);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->not->toBeNull();
});

// --- Meal Validation ---

test('cannot add component from unavailable meal', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => false,
    ]);

    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 1000,
        'is_available' => true,
    ]);

    $result = $this->cartService->addToCart($this->tenant->id, $meal->id, $component->id, 1);

    expect($result['success'])->toBeFalse();
});

test('cannot add component from draft meal', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_DRAFT,
        'is_available' => true,
    ]);

    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 1000,
        'is_available' => true,
    ]);

    $result = $this->cartService->addToCart($this->tenant->id, $meal->id, $component->id, 1);

    expect($result['success'])->toBeFalse();
});

test('cannot add component from another tenant', function () {
    $otherTenantData = test()->createTenantWithCook();
    $otherTenant = $otherTenantData['tenant'];

    $meal = Meal::factory()->create([
        'tenant_id' => $otherTenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);

    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 1000,
        'is_available' => true,
    ]);

    $result = $this->cartService->addToCart($this->tenant->id, $meal->id, $component->id, 1);

    expect($result['success'])->toBeFalse();
});

// --- Remove from Cart ---

test('can remove component from cart', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);

    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 2000,
        'is_available' => true,
        'min_quantity' => 1,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    $this->cartService->addToCart($this->tenant->id, $meal->id, $component->id, 2);
    $result = $this->cartService->removeFromCart($this->tenant->id, $component->id);

    expect($result['success'])->toBeTrue()
        ->and($result['cart']['summary']['count'])->toBe(0)
        ->and($result['cart']['summary']['total'])->toBe(0);
});

// --- Update Quantity ---

test('can update quantity for existing cart item', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);

    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 1000,
        'is_available' => true,
        'min_quantity' => 1,
        'max_quantity' => 10,
        'available_quantity' => null,
    ]);

    $this->cartService->addToCart($this->tenant->id, $meal->id, $component->id, 2);
    $result = $this->cartService->updateQuantity($this->tenant->id, $component->id, 5);

    expect($result['success'])->toBeTrue()
        ->and($result['cart']['summary']['count'])->toBe(5)
        ->and($result['cart']['summary']['total'])->toBe(5000);
});

test('cannot update quantity for item not in cart', function () {
    $result = $this->cartService->updateQuantity($this->tenant->id, 999, 5);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->not->toBeNull();
});

// --- Clear Cart ---

test('can clear entire cart', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);

    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 2000,
        'is_available' => true,
        'min_quantity' => 1,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    $this->cartService->addToCart($this->tenant->id, $meal->id, $component->id, 3);
    $result = $this->cartService->clearCart($this->tenant->id);

    expect($result['success'])->toBeTrue()
        ->and($result['cart']['summary']['count'])->toBe(0)
        ->and($result['cart']['summary']['total'])->toBe(0);
});

// --- Cart Components for Meal ---

test('getCartComponentsForMeal returns correct data', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);

    $comp1 = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 1000,
        'is_available' => true,
        'min_quantity' => 1,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    $comp2 = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 500,
        'is_available' => true,
        'min_quantity' => 1,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    $this->cartService->addToCart($this->tenant->id, $meal->id, $comp1->id, 2);
    $this->cartService->addToCart($this->tenant->id, $meal->id, $comp2->id, 1);

    $result = $this->cartService->getCartComponentsForMeal($this->tenant->id, $meal->id);

    expect($result)->toHaveCount(2)
        ->and($result[$comp1->id])->toBe(2)
        ->and($result[$comp2->id])->toBe(1);
});

// --- Requirement Rules (BR-244) ---

test('requires_any_of rule blocks add when no required component in cart', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);

    $mainDish = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 2000,
        'is_available' => true,
        'min_quantity' => 1,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    $side = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 500,
        'is_available' => true,
        'min_quantity' => 1,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    // Side requires main dish
    $rule = ComponentRequirementRule::create([
        'meal_component_id' => $side->id,
        'rule_type' => ComponentRequirementRule::RULE_TYPE_REQUIRES_ANY_OF,
    ]);
    $rule->targetComponents()->attach($mainDish->id);

    $result = $this->cartService->addToCart($this->tenant->id, $meal->id, $side->id, 1);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain($side->name_en);
});

test('requires_any_of rule allows add when required component is in cart', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);

    $mainDish = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 2000,
        'is_available' => true,
        'min_quantity' => 1,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    $side = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 500,
        'is_available' => true,
        'min_quantity' => 1,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    $rule = ComponentRequirementRule::create([
        'meal_component_id' => $side->id,
        'rule_type' => ComponentRequirementRule::RULE_TYPE_REQUIRES_ANY_OF,
    ]);
    $rule->targetComponents()->attach($mainDish->id);

    // Add main dish first
    $this->cartService->addToCart($this->tenant->id, $meal->id, $mainDish->id, 1);

    $result = $this->cartService->addToCart($this->tenant->id, $meal->id, $side->id, 1);

    expect($result['success'])->toBeTrue();
});

test('incompatible_with rule blocks add when conflicting component in cart', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);

    $comp1 = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 1000,
        'is_available' => true,
        'min_quantity' => 1,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    $comp2 = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 1000,
        'is_available' => true,
        'min_quantity' => 1,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    // comp2 is incompatible with comp1
    $rule = ComponentRequirementRule::create([
        'meal_component_id' => $comp2->id,
        'rule_type' => ComponentRequirementRule::RULE_TYPE_INCOMPATIBLE_WITH,
    ]);
    $rule->targetComponents()->attach($comp1->id);

    // Add comp1 first
    $this->cartService->addToCart($this->tenant->id, $meal->id, $comp1->id, 1);

    $result = $this->cartService->addToCart($this->tenant->id, $meal->id, $comp2->id, 1);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('incompatible');
});

// --- Price Formatting ---

test('formatPrice formats correctly', function () {
    expect(CartService::formatPrice(2000))->toBe('2,000 XAF')
        ->and(CartService::formatPrice(0))->toBe('0 XAF')
        ->and(CartService::formatPrice(100000))->toBe('100,000 XAF');
});

// --- Cart Max Items ---

test('cart enforces maximum item limit', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);

    // Fill cart to max
    $components = MealComponent::factory()->count(CartService::MAX_CART_ITEMS)->create([
        'meal_id' => $meal->id,
        'price' => 100,
        'is_available' => true,
        'min_quantity' => 1,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    foreach ($components as $comp) {
        $this->cartService->addToCart($this->tenant->id, $meal->id, $comp->id, 1);
    }

    // Add one more
    $extraComp = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 100,
        'is_available' => true,
        'min_quantity' => 1,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    $result = $this->cartService->addToCart($this->tenant->id, $meal->id, $extraComp->id, 1);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('Maximum');
});

// --- Free Component ---

test('free component has zero price in cart', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);

    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 0,
        'is_available' => true,
        'min_quantity' => 1,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    $result = $this->cartService->addToCart($this->tenant->id, $meal->id, $component->id, 2);

    expect($result['success'])->toBeTrue()
        ->and($result['cart']['summary']['total'])->toBe(0);
});
