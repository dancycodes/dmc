<?php

use App\Models\Meal;
use App\Models\MealComponent;
use App\Services\MealComponentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| F-124: Meal Component Quantity Settings — Unit Tests
|--------------------------------------------------------------------------
|
| Tests for MealComponentService quantity methods:
| - updateQuantitySettings()
| - decrementAvailableQuantity()
| - incrementAvailableQuantity()
| - isLowStock()
| - getStockStatus()
|
| Also covers model methods: isLowStock(), isOutOfStock()
|
| BR-334 to BR-347 coverage.
|
| HTTP endpoint behaviour is verified via Playwright in Phase 3.
|
*/

beforeEach(function () {
    $this->seedRolesAndPermissions();
    $this->seedSellingUnits();
    $result = createTenantWithCook();
    $this->tenant = $result['tenant'];
    $this->cook = $result['cook'];
    $this->tenant->update(['cook_id' => $this->cook->id]);
    $this->service = new MealComponentService;
});

// ── Service: updateQuantitySettings() ──────────────────────────────────

test('updateQuantitySettings updates min, max, and available quantities', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'min_quantity' => 0,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    $result = $this->service->updateQuantitySettings($component, [
        'min_quantity' => 2,
        'max_quantity' => 10,
        'available_quantity' => 50,
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['component']->min_quantity)->toBe(2)
        ->and($result['component']->max_quantity)->toBe(10)
        ->and($result['component']->available_quantity)->toBe(50);
});

test('updateQuantitySettings persists to database', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'min_quantity' => 0,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    $this->service->updateQuantitySettings($component, [
        'min_quantity' => 3,
        'max_quantity' => 8,
        'available_quantity' => 25,
    ]);

    $this->assertDatabaseHas('meal_components', [
        'id' => $component->id,
        'min_quantity' => 3,
        'max_quantity' => 8,
        'available_quantity' => 25,
    ]);
});

test('updateQuantitySettings returns old values for activity logging', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'min_quantity' => 1,
        'max_quantity' => 5,
        'available_quantity' => 20,
        'is_available' => true,
    ]);

    $result = $this->service->updateQuantitySettings($component, [
        'min_quantity' => 2,
        'max_quantity' => 10,
        'available_quantity' => 30,
    ]);

    expect($result['old_values'])->toBe([
        'min_quantity' => 1,
        'max_quantity' => 5,
        'available_quantity' => 20,
        'is_available' => true,
    ]);
});

// ── BR-334: Minimum quantity default is 0 ──────────────────────────────

test('BR-334: min quantity defaults to 0 when null', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'min_quantity' => 5,
    ]);

    $result = $this->service->updateQuantitySettings($component, [
        'min_quantity' => null,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    expect($result['component']->min_quantity)->toBe(0);
});

test('BR-334: min quantity defaults to 0 when empty string', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'min_quantity' => 5,
    ]);

    $result = $this->service->updateQuantitySettings($component, [
        'min_quantity' => '',
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    expect($result['component']->min_quantity)->toBe(0);
});

// ── BR-335: Maximum quantity default is null (unlimited) ───────────────

test('BR-335: max quantity is null when empty string', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'max_quantity' => 5,
    ]);

    $result = $this->service->updateQuantitySettings($component, [
        'min_quantity' => 0,
        'max_quantity' => '',
        'available_quantity' => null,
    ]);

    expect($result['component']->max_quantity)->toBeNull();
});

test('BR-335: max quantity is null when null', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'max_quantity' => 10,
    ]);

    $result = $this->service->updateQuantitySettings($component, [
        'min_quantity' => 0,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    expect($result['component']->max_quantity)->toBeNull();
});

// ── BR-336: Available quantity default is null (unlimited) ─────────────

test('BR-336: available quantity is null when empty string', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 20,
    ]);

    $result = $this->service->updateQuantitySettings($component, [
        'min_quantity' => 0,
        'max_quantity' => null,
        'available_quantity' => '',
    ]);

    expect($result['component']->available_quantity)->toBeNull();
});

// ── BR-337: Auto-toggle unavailable when available qty reaches 0 ──────

test('BR-337: auto-toggles unavailable when available quantity set to 0', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'is_available' => true,
        'available_quantity' => 20,
    ]);

    $result = $this->service->updateQuantitySettings($component, [
        'min_quantity' => 0,
        'max_quantity' => null,
        'available_quantity' => 0,
    ]);

    expect($result['component']->is_available)->toBeFalse()
        ->and($result['component']->available_quantity)->toBe(0);
});

test('BR-337: auto-toggle clamps negative to 0', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'is_available' => true,
        'available_quantity' => 5,
    ]);

    // Edge case: negative treated as 0
    $result = $this->service->updateQuantitySettings($component, [
        'min_quantity' => 0,
        'max_quantity' => null,
        'available_quantity' => -1,
    ]);

    expect($result['component']->is_available)->toBeFalse()
        ->and($result['component']->available_quantity)->toBe(0);
});

test('BR-337: does not auto-toggle when available quantity is positive', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'is_available' => true,
        'available_quantity' => 20,
    ]);

    $result = $this->service->updateQuantitySettings($component, [
        'min_quantity' => 0,
        'max_quantity' => null,
        'available_quantity' => 5,
    ]);

    expect($result['component']->is_available)->toBeTrue()
        ->and($result['component']->available_quantity)->toBe(5);
});

test('BR-337: does not auto-toggle when available quantity is null (unlimited)', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'is_available' => true,
        'available_quantity' => 20,
    ]);

    $result = $this->service->updateQuantitySettings($component, [
        'min_quantity' => 0,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    expect($result['component']->is_available)->toBeTrue()
        ->and($result['component']->available_quantity)->toBeNull();
});

// ── BR-340: Max must be >= min when both set ───────────────────────────

test('BR-340: rejects min > max', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
    ]);

    $result = $this->service->updateQuantitySettings($component, [
        'min_quantity' => 5,
        'max_quantity' => 3,
        'available_quantity' => null,
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('Minimum quantity cannot be greater than maximum quantity');
});

test('BR-340: allows min equal to max', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
    ]);

    $result = $this->service->updateQuantitySettings($component, [
        'min_quantity' => 5,
        'max_quantity' => 5,
        'available_quantity' => null,
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['component']->min_quantity)->toBe(5)
        ->and($result['component']->max_quantity)->toBe(5);
});

test('BR-340: allows min with null max (unlimited)', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
    ]);

    $result = $this->service->updateQuantitySettings($component, [
        'min_quantity' => 10,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['component']->min_quantity)->toBe(10)
        ->and($result['component']->max_quantity)->toBeNull();
});

// ── BR-345: Immediate effect ───────────────────────────────────────────

test('BR-345: quantity changes persist immediately', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'min_quantity' => 0,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    $this->service->updateQuantitySettings($component, [
        'min_quantity' => 2,
        'max_quantity' => 10,
        'available_quantity' => 50,
    ]);

    $fresh = MealComponent::find($component->id);
    expect($fresh->min_quantity)->toBe(2)
        ->and($fresh->max_quantity)->toBe(10)
        ->and($fresh->available_quantity)->toBe(50);
});

// ── Service: decrementAvailableQuantity() ──────────────────────────────

test('BR-338: decrement reduces available quantity by given amount', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 10,
        'is_available' => true,
    ]);

    $result = $this->service->decrementAvailableQuantity($component, 3);

    expect($result['success'])->toBeTrue()
        ->and($result['new_quantity'])->toBe(7)
        ->and($result['auto_unavailable'])->toBeFalse();
});

test('BR-338: decrement to 0 triggers auto-unavailable', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 3,
        'is_available' => true,
    ]);

    $result = $this->service->decrementAvailableQuantity($component, 3);

    expect($result['success'])->toBeTrue()
        ->and($result['new_quantity'])->toBe(0)
        ->and($result['auto_unavailable'])->toBeTrue();

    $this->assertDatabaseHas('meal_components', [
        'id' => $component->id,
        'available_quantity' => 0,
        'is_available' => false,
    ]);
});

test('BR-338: decrement with unlimited quantity returns null and no-op', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => null,
        'is_available' => true,
    ]);

    $result = $this->service->decrementAvailableQuantity($component, 5);

    expect($result['success'])->toBeTrue()
        ->and($result['new_quantity'])->toBeNull()
        ->and($result['auto_unavailable'])->toBeFalse();
});

test('BR-338: decrement does not go below 0', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 2,
        'is_available' => true,
    ]);

    $result = $this->service->decrementAvailableQuantity($component, 5);

    expect($result['new_quantity'])->toBe(0)
        ->and($result['auto_unavailable'])->toBeTrue();
});

test('BR-338: decrement by 1 is the default', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 5,
        'is_available' => true,
    ]);

    $result = $this->service->decrementAvailableQuantity($component);

    expect($result['new_quantity'])->toBe(4);
});

// ── Service: incrementAvailableQuantity() ──────────────────────────────

test('increment increases available quantity', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 5,
    ]);

    $result = $this->service->incrementAvailableQuantity($component, 3);

    expect($result['success'])->toBeTrue()
        ->and($result['new_quantity'])->toBe(8);
});

test('increment with unlimited quantity returns null', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => null,
    ]);

    $result = $this->service->incrementAvailableQuantity($component, 3);

    expect($result['new_quantity'])->toBeNull();
});

test('increment from 0 works correctly', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 0,
        'is_available' => false,
    ]);

    $result = $this->service->incrementAvailableQuantity($component, 10);

    expect($result['new_quantity'])->toBe(10);
    // Note: increment does not auto-toggle availability back
    // (cook must manually re-enable per F-124 scenario 6)
});

// ── Service: isLowStock() ──────────────────────────────────────────────

test('isLowStock returns true when available quantity is between 1 and 5', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 3,
        'is_available' => true,
    ]);

    expect($this->service->isLowStock($component))->toBeTrue();
});

test('isLowStock returns false when available quantity is above threshold', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 10,
        'is_available' => true,
    ]);

    expect($this->service->isLowStock($component))->toBeFalse();
});

test('isLowStock returns false when available quantity is 0', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 0,
        'is_available' => false,
    ]);

    expect($this->service->isLowStock($component))->toBeFalse();
});

test('isLowStock returns false when available quantity is unlimited', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => null,
    ]);

    expect($this->service->isLowStock($component))->toBeFalse();
});

test('isLowStock accepts custom threshold', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 8,
    ]);

    expect($this->service->isLowStock($component, 10))->toBeTrue()
        ->and($this->service->isLowStock($component, 5))->toBeFalse();
});

// ── Service: getStockStatus() ──────────────────────────────────────────

test('getStockStatus returns unlimited for null available quantity', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => null,
    ]);

    $status = $this->service->getStockStatus($component);

    expect($status['type'])->toBe('unlimited')
        ->and($status['label'])->toBe('Unlimited');
});

test('getStockStatus returns out_of_stock when available is 0', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 0,
        'is_available' => false,
    ]);

    $status = $this->service->getStockStatus($component);

    expect($status['type'])->toBe('out_of_stock')
        ->and($status['label'])->toBe('Out of stock');
});

test('getStockStatus returns low_stock when under threshold', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 3,
        'is_available' => true,
    ]);

    $status = $this->service->getStockStatus($component);

    expect($status['type'])->toBe('low_stock')
        ->and($status['label'])->toContain('3');
});

test('getStockStatus returns in_stock when above threshold', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 50,
        'is_available' => true,
    ]);

    $status = $this->service->getStockStatus($component);

    expect($status['type'])->toBe('in_stock')
        ->and($status['label'])->toContain('50');
});

// ── Model: isLowStock() ────────────────────────────────────────────────

test('model isLowStock returns true for low stock', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 4,
    ]);

    expect($component->isLowStock())->toBeTrue();
});

test('model isLowStock returns false for unlimited', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => null,
    ]);

    expect($component->isLowStock())->toBeFalse();
});

test('model isLowStock returns false for 0 (out of stock, not low)', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 0,
    ]);

    expect($component->isLowStock())->toBeFalse();
});

test('model isLowStock custom threshold', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 8,
    ]);

    expect($component->isLowStock(10))->toBeTrue()
        ->and($component->isLowStock(5))->toBeFalse();
});

// ── Model: isOutOfStock() ──────────────────────────────────────────────

test('model isOutOfStock returns true for 0', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 0,
    ]);

    expect($component->isOutOfStock())->toBeTrue();
});

test('model isOutOfStock returns false for positive', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 5,
    ]);

    expect($component->isOutOfStock())->toBeFalse();
});

test('model isOutOfStock returns false for unlimited', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => null,
    ]);

    expect($component->isOutOfStock())->toBeFalse();
});

// ── Edge Cases ─────────────────────────────────────────────────────────

test('updating quantity does not change other component fields', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'name_en' => 'Test Component',
        'name_fr' => 'Composant Test',
        'price' => 1500,
        'min_quantity' => 0,
        'max_quantity' => null,
        'available_quantity' => null,
        'is_available' => true,
        'position' => 1,
    ]);

    $this->service->updateQuantitySettings($component, [
        'min_quantity' => 2,
        'max_quantity' => 10,
        'available_quantity' => 50,
    ]);

    $fresh = MealComponent::find($component->id);
    expect($fresh->name_en)->toBe('Test Component')
        ->and($fresh->name_fr)->toBe('Composant Test')
        ->and($fresh->price)->toBe(1500)
        ->and($fresh->position)->toBe(1);
});

test('quantity settings can be reverted to unlimited', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'min_quantity' => 2,
        'max_quantity' => 10,
        'available_quantity' => 50,
    ]);

    $result = $this->service->updateQuantitySettings($component, [
        'min_quantity' => 0,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    expect($result['component']->min_quantity)->toBe(0)
        ->and($result['component']->max_quantity)->toBeNull()
        ->and($result['component']->available_quantity)->toBeNull();
});

test('replenishing stock from 0 with positive quantity works', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 0,
        'is_available' => false,
    ]);

    $result = $this->service->updateQuantitySettings($component, [
        'min_quantity' => 0,
        'max_quantity' => null,
        'available_quantity' => 30,
    ]);

    // Available quantity restored
    expect($result['component']->available_quantity)->toBe(30);
    // Note: is_available stays false -- cook must manually re-enable
    // (auto-unavailable is one-way; re-enable is explicit via F-123 toggle)
});

test('multiple sequential decrements work correctly', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 10,
        'is_available' => true,
    ]);

    $this->service->decrementAvailableQuantity($component, 3);
    $component->refresh();
    $this->service->decrementAvailableQuantity($component, 4);
    $component->refresh();
    $result = $this->service->decrementAvailableQuantity($component, 2);

    expect($result['new_quantity'])->toBe(1)
        ->and($result['auto_unavailable'])->toBeFalse();
});

test('decrement then increment restores quantity', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 10,
        'is_available' => true,
    ]);

    $this->service->decrementAvailableQuantity($component, 5);
    $component->refresh();
    $result = $this->service->incrementAvailableQuantity($component, 5);

    expect($result['new_quantity'])->toBe(10);
});

test('factory withQuantityLimits state works', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->withQuantityLimits(2, 10, 50)->create([
        'meal_id' => $meal->id,
    ]);

    expect($component->min_quantity)->toBe(2)
        ->and($component->max_quantity)->toBe(10)
        ->and($component->available_quantity)->toBe(50);
});

test('isLowStock at boundary value 5 returns true', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 5,
    ]);

    expect($component->isLowStock())->toBeTrue();
    expect($this->service->isLowStock($component))->toBeTrue();
});

test('isLowStock at boundary value 6 returns false', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 6,
    ]);

    expect($component->isLowStock())->toBeFalse();
    expect($this->service->isLowStock($component))->toBeFalse();
});
