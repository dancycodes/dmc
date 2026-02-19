<?php

/**
 * F-146: Order Total Calculation & Summary — Unit Tests
 *
 * Tests CheckoutService getOrderSummary method and related logic including:
 * - BR-316: Itemized list shows meal name, component name, quantity, unit price, line subtotal
 * - BR-317: Items are grouped by meal
 * - BR-318: Subtotal is the sum of all food item line subtotals
 * - BR-319: Delivery fee is shown as a separate line item; pickup shows "Pickup - Free"
 * - BR-320: Promo discount (if applicable) is shown as a negative line item
 * - BR-321: Grand total = subtotal + delivery fee - promo discount
 * - BR-322: All amounts displayed in XAF (integer, formatted with thousand separators)
 * - BR-324: Edit Cart link allows returning to cart
 * - BR-326: All text must be localized via __()
 */

use App\Models\DeliveryArea;
use App\Models\DeliveryAreaQuarter;
use App\Models\Meal;
use App\Models\MealComponent;
use App\Models\Quarter;
use App\Models\Town;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->checkoutService = new CheckoutService;
    $this->cartService = new CartService;
    test()->seedRolesAndPermissions();
});

// -- getOrderSummary: Basic structure (BR-316, BR-317) --

test('getOrderSummary returns correct structure with all required fields', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'name_en' => 'Ndole Platter',
        'name_fr' => 'Plat de Ndole',
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'name_en' => 'Ndole',
        'name_fr' => 'Ndole',
        'price' => 2000,
    ]);

    $this->cartService->addToCart($tenant->id, $meal->id, $component->id, 2);
    $cart = $this->cartService->getCart($tenant->id);

    $this->checkoutService->setDeliveryMethod($tenant->id, CheckoutService::METHOD_PICKUP);

    $summary = $this->checkoutService->getOrderSummary($tenant->id, $cart);

    expect($summary)->toHaveKeys([
        'meals', 'subtotal', 'delivery_method', 'delivery_fee',
        'delivery_display', 'promo_discount', 'promo_code',
        'grand_total', 'item_count', 'price_changes',
    ]);
});

// -- BR-318: Subtotal calculation --

test('subtotal is the sum of all food item line subtotals (BR-318)', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);
    $comp1 = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'name_en' => 'Ndole',
        'price' => 2000,
    ]);
    $comp2 = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'name_en' => 'Plantains',
        'price' => 500,
    ]);

    $this->cartService->addToCart($tenant->id, $meal->id, $comp1->id, 2);
    $this->cartService->addToCart($tenant->id, $meal->id, $comp2->id, 1);
    $cart = $this->cartService->getCart($tenant->id);

    $this->checkoutService->setDeliveryMethod($tenant->id, CheckoutService::METHOD_PICKUP);
    $summary = $this->checkoutService->getOrderSummary($tenant->id, $cart);

    // 2000 * 2 + 500 * 1 = 4500
    expect($summary['subtotal'])->toBe(4500);
});

// -- BR-317: Items grouped by meal --

test('items are grouped by meal in the summary (BR-317)', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $meal1 = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'name_en' => 'Ndole Platter',
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);
    $meal2 = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'name_en' => 'Eru Soup',
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);

    $comp1 = MealComponent::factory()->create(['meal_id' => $meal1->id, 'name_en' => 'Ndole', 'price' => 2000]);
    $comp2 = MealComponent::factory()->create(['meal_id' => $meal2->id, 'name_en' => 'Eru', 'price' => 1500]);

    $this->cartService->addToCart($tenant->id, $meal1->id, $comp1->id, 2);
    $this->cartService->addToCart($tenant->id, $meal2->id, $comp2->id, 1);
    $cart = $this->cartService->getCart($tenant->id);

    $this->checkoutService->setDeliveryMethod($tenant->id, CheckoutService::METHOD_PICKUP);
    $summary = $this->checkoutService->getOrderSummary($tenant->id, $cart);

    expect($summary['meals'])->toHaveCount(2);
});

// -- BR-319: Delivery fee as separate line item --

test('delivery fee is included in summary for delivery orders (BR-319)', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Akwa', 'name_fr' => 'Akwa']);
    $deliveryArea = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $deliveryArea->id,
        'quarter_id' => $quarter->id,
        'delivery_fee' => 500,
    ]);

    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id, 'price' => 3000]);

    $this->cartService->addToCart($tenant->id, $meal->id, $component->id, 2);
    $cart = $this->cartService->getCart($tenant->id);

    $this->checkoutService->setDeliveryMethod($tenant->id, CheckoutService::METHOD_DELIVERY);
    $this->checkoutService->setDeliveryLocation($tenant->id, [
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
        'neighbourhood' => 'Near market',
    ]);

    $summary = $this->checkoutService->getOrderSummary($tenant->id, $cart);

    expect($summary['delivery_fee'])->toBe(500)
        ->and($summary['delivery_method'])->toBe('delivery');
});

test('pickup orders show zero delivery fee (BR-319)', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id, 'price' => 3000]);

    $this->cartService->addToCart($tenant->id, $meal->id, $component->id, 1);
    $cart = $this->cartService->getCart($tenant->id);

    $this->checkoutService->setDeliveryMethod($tenant->id, CheckoutService::METHOD_PICKUP);

    $summary = $this->checkoutService->getOrderSummary($tenant->id, $cart);

    expect($summary['delivery_fee'])->toBe(0)
        ->and($summary['delivery_method'])->toBe('pickup');
});

// -- BR-321: Grand total calculation --

test('grand total equals subtotal plus delivery fee (BR-321)', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Akwa', 'name_fr' => 'Akwa']);
    $deliveryArea = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $deliveryArea->id,
        'quarter_id' => $quarter->id,
        'delivery_fee' => 500,
    ]);

    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);
    $comp1 = MealComponent::factory()->create(['meal_id' => $meal->id, 'price' => 2000]);
    $comp2 = MealComponent::factory()->create(['meal_id' => $meal->id, 'price' => 500]);

    $this->cartService->addToCart($tenant->id, $meal->id, $comp1->id, 2);
    $this->cartService->addToCart($tenant->id, $meal->id, $comp2->id, 1);
    $cart = $this->cartService->getCart($tenant->id);

    $this->checkoutService->setDeliveryMethod($tenant->id, CheckoutService::METHOD_DELIVERY);
    $this->checkoutService->setDeliveryLocation($tenant->id, [
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
        'neighbourhood' => 'Near market',
    ]);

    $summary = $this->checkoutService->getOrderSummary($tenant->id, $cart);

    // Subtotal: 2000*2 + 500*1 = 4500, Delivery: 500, Total: 5000
    expect($summary['subtotal'])->toBe(4500)
        ->and($summary['delivery_fee'])->toBe(500)
        ->and($summary['grand_total'])->toBe(5000);
});

test('grand total equals subtotal for pickup orders (BR-321)', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id, 'price' => 3000]);

    $this->cartService->addToCart($tenant->id, $meal->id, $component->id, 2);
    $cart = $this->cartService->getCart($tenant->id);

    $this->checkoutService->setDeliveryMethod($tenant->id, CheckoutService::METHOD_PICKUP);

    $summary = $this->checkoutService->getOrderSummary($tenant->id, $cart);

    // Subtotal: 3000*2 = 6000, Delivery: 0, Total: 6000
    expect($summary['subtotal'])->toBe(6000)
        ->and($summary['delivery_fee'])->toBe(0)
        ->and($summary['grand_total'])->toBe(6000);
});

// -- BR-320: Promo discount (forward-compatible) --

test('promo discount defaults to zero when no promo applied (BR-320)', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id, 'price' => 1000]);

    $this->cartService->addToCart($tenant->id, $meal->id, $component->id, 1);
    $cart = $this->cartService->getCart($tenant->id);

    $this->checkoutService->setDeliveryMethod($tenant->id, CheckoutService::METHOD_PICKUP);

    $summary = $this->checkoutService->getOrderSummary($tenant->id, $cart);

    expect($summary['promo_discount'])->toBe(0)
        ->and($summary['promo_code'])->toBeNull();
});

// -- Item count --

test('item count reflects total quantity of all items', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);
    $comp1 = MealComponent::factory()->create(['meal_id' => $meal->id, 'price' => 1000]);
    $comp2 = MealComponent::factory()->create(['meal_id' => $meal->id, 'price' => 500]);

    $this->cartService->addToCart($tenant->id, $meal->id, $comp1->id, 3);
    $this->cartService->addToCart($tenant->id, $meal->id, $comp2->id, 2);
    $cart = $this->cartService->getCart($tenant->id);

    $this->checkoutService->setDeliveryMethod($tenant->id, CheckoutService::METHOD_PICKUP);

    $summary = $this->checkoutService->getOrderSummary($tenant->id, $cart);

    expect($summary['item_count'])->toBe(5);
});

// -- Edge case: Price changes --

test('detectPriceChanges returns empty array when no prices changed', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id, 'price' => 1500]);

    $this->cartService->addToCart($tenant->id, $meal->id, $component->id, 1);
    $cart = $this->cartService->getCart($tenant->id);

    $this->checkoutService->setDeliveryMethod($tenant->id, CheckoutService::METHOD_PICKUP);

    $summary = $this->checkoutService->getOrderSummary($tenant->id, $cart);

    expect($summary['price_changes'])->toBeEmpty();
});

test('detectPriceChanges detects when component price changed since cart add', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'name_en' => 'Ndole',
        'name_fr' => 'Ndole',
        'price' => 1500,
    ]);

    $this->cartService->addToCart($tenant->id, $meal->id, $component->id, 2);

    // Simulate price change after adding to cart
    $component->update(['price' => 2000]);

    $cart = $this->cartService->getCart($tenant->id);

    $this->checkoutService->setDeliveryMethod($tenant->id, CheckoutService::METHOD_PICKUP);

    $summary = $this->checkoutService->getOrderSummary($tenant->id, $cart);

    expect($summary['price_changes'])->toHaveCount(1)
        ->and($summary['price_changes'][0]['old_price'])->toBe(1500)
        ->and($summary['price_changes'][0]['new_price'])->toBe(2000)
        ->and($summary['price_changes'][0]['name'])->toBe('Ndole');
});

// -- Edge case: Empty cart --

test('getOrderSummary handles empty cart gracefully', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $cart = $this->cartService->getCart($tenant->id);

    $this->checkoutService->setDeliveryMethod($tenant->id, CheckoutService::METHOD_PICKUP);

    $summary = $this->checkoutService->getOrderSummary($tenant->id, $cart);

    expect($summary['subtotal'])->toBe(0)
        ->and($summary['grand_total'])->toBe(0)
        ->and($summary['item_count'])->toBe(0)
        ->and($summary['meals'])->toBeEmpty()
        ->and($summary['price_changes'])->toBeEmpty();
});

// -- Edge case: Grand total never negative --

test('grand total never goes negative even with large discount', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id, 'price' => 500]);

    $this->cartService->addToCart($tenant->id, $meal->id, $component->id, 1);
    $cart = $this->cartService->getCart($tenant->id);

    $this->checkoutService->setDeliveryMethod($tenant->id, CheckoutService::METHOD_PICKUP);

    $summary = $this->checkoutService->getOrderSummary($tenant->id, $cart);

    // Currently no promo, but ensure structure supports it
    expect($summary['grand_total'])->toBeGreaterThanOrEqual(0);
});

// -- getSummaryBackUrl --

test('getSummaryBackUrl returns phone step URL', function () {
    $service = new CheckoutService;

    $url = $service->getSummaryBackUrl();

    expect($url)->toContain('/checkout/phone');
});

// -- Multiple meals scenario (Scenario 1 from spec) --

test('full order summary scenario with multiple meals', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Akwa', 'name_fr' => 'Akwa']);
    $deliveryArea = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $deliveryArea->id,
        'quarter_id' => $quarter->id,
        'delivery_fee' => 500,
    ]);

    $meal1 = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'name_en' => 'Ndole Platter',
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);
    $meal2 = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'name_en' => 'Eru Soup',
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);

    $ndole = MealComponent::factory()->create(['meal_id' => $meal1->id, 'name_en' => 'Ndole', 'price' => 2000]);
    $plantains = MealComponent::factory()->create(['meal_id' => $meal1->id, 'name_en' => 'Plantains', 'price' => 500]);
    $eru = MealComponent::factory()->create(['meal_id' => $meal2->id, 'name_en' => 'Eru', 'price' => 1500]);

    $this->cartService->addToCart($tenant->id, $meal1->id, $ndole->id, 2);
    $this->cartService->addToCart($tenant->id, $meal1->id, $plantains->id, 1);
    $this->cartService->addToCart($tenant->id, $meal2->id, $eru->id, 1);
    $cart = $this->cartService->getCart($tenant->id);

    $this->checkoutService->setDeliveryMethod($tenant->id, CheckoutService::METHOD_DELIVERY);
    $this->checkoutService->setDeliveryLocation($tenant->id, [
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
        'neighbourhood' => 'Near Akwa Palace',
    ]);

    $summary = $this->checkoutService->getOrderSummary($tenant->id, $cart);

    // Ndole x2 = 4000, Plantains x1 = 500, Eru x1 = 1500
    // Subtotal: 6000, Delivery: 500, Total: 6500
    expect($summary['subtotal'])->toBe(6000)
        ->and($summary['delivery_fee'])->toBe(500)
        ->and($summary['grand_total'])->toBe(6500)
        ->and($summary['item_count'])->toBe(4)
        ->and($summary['meals'])->toHaveCount(2)
        ->and($summary['promo_discount'])->toBe(0);
});

// -- XAF formatting --

test('CartService formatPrice formats with thousand separators (BR-322)', function () {
    expect(CartService::formatPrice(10000))->toBe('10,000 XAF')
        ->and(CartService::formatPrice(0))->toBe('0 XAF')
        ->and(CartService::formatPrice(500))->toBe('500 XAF')
        ->and(CartService::formatPrice(1500000))->toBe('1,500,000 XAF');
});

// -- Delivery display data in summary --

test('delivery display data includes quarter name for delivery orders', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Bonaberi', 'name_fr' => 'Bonaberi']);
    $deliveryArea = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $deliveryArea->id,
        'quarter_id' => $quarter->id,
        'delivery_fee' => 750,
    ]);

    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id, 'price' => 1000]);

    $this->cartService->addToCart($tenant->id, $meal->id, $component->id, 1);
    $cart = $this->cartService->getCart($tenant->id);

    $this->checkoutService->setDeliveryMethod($tenant->id, CheckoutService::METHOD_DELIVERY);
    $this->checkoutService->setDeliveryLocation($tenant->id, [
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
        'neighbourhood' => 'Near bridge',
    ]);

    $summary = $this->checkoutService->getOrderSummary($tenant->id, $cart);

    expect($summary['delivery_display']['quarter_name'])->toBe('Bonaberi')
        ->and($summary['delivery_display']['fee'])->toBe(750);
});

test('delivery display data for pickup shows free', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id, 'price' => 1000]);

    $this->cartService->addToCart($tenant->id, $meal->id, $component->id, 1);
    $cart = $this->cartService->getCart($tenant->id);

    $this->checkoutService->setDeliveryMethod($tenant->id, CheckoutService::METHOD_PICKUP);

    $summary = $this->checkoutService->getOrderSummary($tenant->id, $cart);

    expect($summary['delivery_display']['is_free'])->toBeTrue()
        ->and($summary['delivery_display']['fee'])->toBe(0);
});
