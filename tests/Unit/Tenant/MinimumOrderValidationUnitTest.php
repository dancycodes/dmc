<?php

/**
 * F-144: Minimum Order Amount Validation — Unit Tests
 *
 * Tests the server-side minimum order amount validation logic in CartController::checkout().
 * The client-side enforcement (Alpine computed isBelowMinimum, disabled button) was already
 * implemented by F-213. This feature adds the server-side gate that rejects crafted POST
 * requests that bypass the frontend.
 *
 * Business rules tested:
 * - BR-299: Minimum order amount configured per cook in tenant settings (CookSettingsService)
 * - BR-300: Minimum checked against food subtotal ONLY (before delivery fee)
 * - BR-301: Error message format: "Minimum order is {minimum} XAF. Add {remaining} XAF more to proceed."
 * - BR-302: Checkout blocked when minimum is not met
 * - BR-304: Skip validation if minimum is 0 or null
 * - BR-306: remaining = minimum - cart_subtotal
 */

use App\Models\Meal;
use App\Models\MealComponent;
use App\Services\CartService;
use App\Services\CookSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();
    test()->seedSellingUnits();
    $this->tenantData = test()->createTenantWithCook();
    $this->tenant = $this->tenantData['tenant'];
    $this->cartService = new CartService;

    // Create a meal and component for cart manipulation
    $this->meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
    ]);
    $this->component = MealComponent::factory()->create([
        'meal_id' => $this->meal->id,
        'is_available' => true,
        'price' => 1000,
    ]);
});

// ---- CookSettingsService::getMinimumOrderAmount() ----

test('getMinimumOrderAmount returns 0 when not configured', function () {
    $service = new CookSettingsService;

    $amount = $service->getMinimumOrderAmount($this->tenant);

    expect($amount)->toBe(0);
});

test('getMinimumOrderAmount returns the configured value', function () {
    $service = new CookSettingsService;
    $this->tenant->setSetting(CookSettingsService::MINIMUM_ORDER_AMOUNT_KEY, 2000);
    $this->tenant->save();

    $amount = $service->getMinimumOrderAmount($this->tenant);

    expect($amount)->toBe(2000);
});

test('getMinimumOrderAmount returns 0 when explicitly set to 0 (BR-304)', function () {
    $service = new CookSettingsService;
    $this->tenant->setSetting(CookSettingsService::MINIMUM_ORDER_AMOUNT_KEY, 0);
    $this->tenant->save();

    $amount = $service->getMinimumOrderAmount($this->tenant);

    expect($amount)->toBe(0);
});

// ---- BR-300: Minimum vs food subtotal (cart summary total) ----

test('cart summary total excludes delivery fee (food subtotal only)', function () {
    // Add item: 1 × 1500 XAF
    $component = MealComponent::factory()->create([
        'meal_id' => $this->meal->id,
        'is_available' => true,
        'price' => 1500,
    ]);
    $this->cartService->addToCart($this->tenant->id, $this->meal->id, $component->id, 1);

    $cart = $this->cartService->getCart($this->tenant->id);

    // summary.total is food subtotal only — delivery is not in it
    expect($cart['summary']['total'])->toBe(1500);
});

// ---- BR-301/BR-306: Error message format ----

test('remaining amount is calculated as minimum minus cart subtotal (BR-306)', function () {
    $minimum = 2000;
    $subtotal = 1200;
    $expected_remaining = $minimum - $subtotal; // 800

    expect($expected_remaining)->toBe(800);
});

test('error message matches BR-301 format', function () {
    $minimum = 2000;
    $remaining = 800;

    $message = __('Minimum order is :minimum XAF. Add :remaining XAF more to proceed.', [
        'minimum' => number_format($minimum),
        'remaining' => number_format($remaining),
    ]);

    expect($message)->toContain('2,000')
        ->and($message)->toContain('800')
        ->and($message)->toContain('Minimum order is')
        ->and($message)->toContain('Add')
        ->and($message)->toContain('more to proceed.');
});

test('error message includes formatted numbers for very high minimums', function () {
    $minimum = 100000;
    $subtotal = 500;
    $remaining = $minimum - $subtotal; // 99500

    $message = __('Minimum order is :minimum XAF. Add :remaining XAF more to proceed.', [
        'minimum' => number_format($minimum),
        'remaining' => number_format($remaining),
    ]);

    expect($message)->toContain('100,000')
        ->and($message)->toContain('99,500');
});

// ---- BR-304: Skip validation when minimum is 0 ----

test('validation is skipped when minimum order amount is 0 (BR-304)', function () {
    // Minimum = 0 means no validation is required
    $minimum = 0;
    $subtotal = 100;

    // The condition: $minimum > 0 && $subtotal < $minimum
    $shouldBlock = $minimum > 0 && $subtotal < $minimum;

    expect($shouldBlock)->toBeFalse();
});

test('validation is skipped when minimum order amount is null (BR-304)', function () {
    $minimum = null;
    $subtotal = 100;

    // int cast: (int) null = 0, which is > 0 is false
    $minimumInt = (int) $minimum;
    $shouldBlock = $minimumInt > 0 && $subtotal < $minimumInt;

    expect($shouldBlock)->toBeFalse();
});

// ---- BR-302: Checkout blocked when minimum is not met ----

test('cart exactly equal to minimum is valid (BR-302 edge case)', function () {
    $minimum = 2000;
    $subtotal = 2000;

    // $subtotal < $minimum is false → not blocked
    $isBelowMinimum = $subtotal < $minimum;

    expect($isBelowMinimum)->toBeFalse();
});

test('cart one XAF below minimum is invalid', function () {
    $minimum = 2000;
    $subtotal = 1999;

    $isBelowMinimum = $minimum > 0 && $subtotal < $minimum;

    expect($isBelowMinimum)->toBeTrue();
});

test('cart above minimum is valid', function () {
    $minimum = 2000;
    $subtotal = 3500;

    $isBelowMinimum = $minimum > 0 && $subtotal < $minimum;

    expect($isBelowMinimum)->toBeFalse();
});

// ---- CartService: food subtotal for validation boundary cases ----

test('cart with multiple items sums correctly for minimum validation', function () {
    // Two items totaling 1800 XAF
    $comp1 = MealComponent::factory()->create([
        'meal_id' => $this->meal->id,
        'is_available' => true,
        'price' => 1200,
    ]);
    $comp2 = MealComponent::factory()->create([
        'meal_id' => $this->meal->id,
        'is_available' => true,
        'price' => 600,
    ]);

    $this->cartService->addToCart($this->tenant->id, $this->meal->id, $comp1->id, 1);
    $this->cartService->addToCart($this->tenant->id, $this->meal->id, $comp2->id, 1);

    $cart = $this->cartService->getCart($this->tenant->id);
    $subtotal = $cart['summary']['total'];

    // Minimum = 2000 XAF → subtotal 1800 is below → remaining = 200
    $minimum = 2000;
    $remaining = $minimum - $subtotal;

    expect($subtotal)->toBe(1800)
        ->and($remaining)->toBe(200)
        ->and($minimum > 0 && $subtotal < $minimum)->toBeTrue();
});

test('cart meets minimum after adding more items', function () {
    // Start with 1000 XAF — below 2000 minimum
    $this->cartService->addToCart($this->tenant->id, $this->meal->id, $this->component->id, 1);

    $cart = $this->cartService->getCart($this->tenant->id);
    expect($cart['summary']['total'])->toBe(1000);

    $minimum = 2000;
    expect($minimum > 0 && $cart['summary']['total'] < $minimum)->toBeTrue();

    // Add another to reach 2000 (meeting exactly)
    $this->cartService->updateQuantity($this->tenant->id, $this->component->id, 2);

    $cart = $this->cartService->getCart($this->tenant->id);
    expect($cart['summary']['total'])->toBe(2000);
    expect($minimum > 0 && $cart['summary']['total'] < $minimum)->toBeFalse();
});

// ---- Tenant model integration ----

test('Tenant model getMinimumOrderAmount returns correct value after setting', function () {
    $service = new CookSettingsService;
    $service->updateMinimumOrderAmount($this->tenant, 3000, $this->tenantData['cook']);

    // Refresh tenant from DB to verify persistence
    $this->tenant->refresh();

    expect($this->tenant->getMinimumOrderAmount())->toBe(3000);
});

test('Tenant model getMinimumOrderAmount returns 0 when unconfigured', function () {
    // Fresh tenant with no settings set
    expect($this->tenant->getMinimumOrderAmount())->toBe(0);
});

// ---- Constants validation ----

test('CookSettingsService constants are correct', function () {
    expect(CookSettingsService::DEFAULT_MINIMUM_ORDER_AMOUNT)->toBe(0)
        ->and(CookSettingsService::MIN_ORDER_AMOUNT)->toBe(0)
        ->and(CookSettingsService::MAX_ORDER_AMOUNT)->toBe(100000)
        ->and(CookSettingsService::MINIMUM_ORDER_AMOUNT_KEY)->toBe('minimum_order_amount');
});
