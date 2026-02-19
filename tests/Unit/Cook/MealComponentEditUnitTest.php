<?php

use App\Models\Meal;
use App\Models\MealComponent;
use App\Models\SellingUnit;
use App\Models\User;
use App\Services\MealComponentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| F-119: Meal Component Edit — Unit Tests
|--------------------------------------------------------------------------
|
| Tests for MealComponentService::updateComponent(), activity logging,
| and BR-292 to BR-297 coverage.
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
    $this->componentService = new MealComponentService;
});

// ── Service: updateComponent() ──────────────────────────────────────

test('updateComponent updates name_en and name_fr', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'name_en' => 'Old Name EN',
        'name_fr' => 'Old Name FR',
    ]);

    $result = $this->componentService->updateComponent($component, [
        'name_en' => 'New Name EN',
        'name_fr' => 'New Name FR',
        'price' => $component->price,
        'selling_unit' => $component->selling_unit,
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['component']->name_en)->toBe('New Name EN');
    expect($result['component']->name_fr)->toBe('New Name FR');
});

test('updateComponent updates price', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'price' => 1000,
    ]);

    $result = $this->componentService->updateComponent($component, [
        'name_en' => $component->name_en,
        'name_fr' => $component->name_fr,
        'price' => 1200,
        'selling_unit' => $component->selling_unit,
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['component']->price)->toBe(1200);
});

test('updateComponent updates selling unit', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $plateUnit = SellingUnit::where('name_en', 'Plate')->where('is_standard', true)->first();
    $bowlUnit = SellingUnit::where('name_en', 'Bowl')->where('is_standard', true)->first();
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'selling_unit' => (string) $plateUnit->id,
    ]);

    $result = $this->componentService->updateComponent($component, [
        'name_en' => $component->name_en,
        'name_fr' => $component->name_fr,
        'price' => $component->price,
        'selling_unit' => (string) $bowlUnit->id,
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['component']->selling_unit)->toBe((string) $bowlUnit->id);
});

test('updateComponent updates quantity limits', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'min_quantity' => 0,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    $result = $this->componentService->updateComponent($component, [
        'name_en' => $component->name_en,
        'name_fr' => $component->name_fr,
        'price' => $component->price,
        'selling_unit' => $component->selling_unit,
        'min_quantity' => 2,
        'max_quantity' => 5,
        'available_quantity' => 10,
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['component']->min_quantity)->toBe(2);
    expect($result['component']->max_quantity)->toBe(5);
    expect($result['component']->available_quantity)->toBe(10);
});

test('updateComponent fails when min quantity exceeds max quantity', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id]);

    $result = $this->componentService->updateComponent($component, [
        'name_en' => $component->name_en,
        'name_fr' => $component->name_fr,
        'price' => $component->price,
        'selling_unit' => $component->selling_unit,
        'min_quantity' => 10,
        'max_quantity' => 5,
    ]);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('Minimum quantity');
});

test('updateComponent fails with invalid selling unit', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id]);

    $result = $this->componentService->updateComponent($component, [
        'name_en' => $component->name_en,
        'name_fr' => $component->name_fr,
        'price' => $component->price,
        'selling_unit' => 'invalid_unit',
    ]);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('Invalid selling unit');
});

test('updateComponent returns old values for activity logging', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'name_en' => 'Original EN',
        'price' => 500,
    ]);

    $result = $this->componentService->updateComponent($component, [
        'name_en' => 'Updated EN',
        'name_fr' => $component->name_fr,
        'price' => 700,
        'selling_unit' => $component->selling_unit,
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['old_values'])->toHaveKey('name_en', 'Original EN');
    expect($result['old_values'])->toHaveKey('price', 500);
});

// ── BR-297: Auto-unavailable when available_quantity set to 0 ──────

test('updateComponent auto-toggles unavailable when available_quantity set to 0', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'is_available' => true,
        'available_quantity' => 10,
    ]);

    $result = $this->componentService->updateComponent($component, [
        'name_en' => $component->name_en,
        'name_fr' => $component->name_fr,
        'price' => $component->price,
        'selling_unit' => $component->selling_unit,
        'available_quantity' => 0,
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['component']->is_available)->toBeFalse();
    expect($result['component']->available_quantity)->toBe(0);
});

test('updateComponent preserves availability when available_quantity is non-zero', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'is_available' => true,
        'available_quantity' => 10,
    ]);

    $result = $this->componentService->updateComponent($component, [
        'name_en' => $component->name_en,
        'name_fr' => $component->name_fr,
        'price' => $component->price,
        'selling_unit' => $component->selling_unit,
        'available_quantity' => 5,
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['component']->is_available)->toBeTrue();
});

test('updateComponent allows null available_quantity for unlimited', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 10,
    ]);

    $result = $this->componentService->updateComponent($component, [
        'name_en' => $component->name_en,
        'name_fr' => $component->name_fr,
        'price' => $component->price,
        'selling_unit' => $component->selling_unit,
        'available_quantity' => null,
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['component']->available_quantity)->toBeNull();
    expect($result['component']->hasUnlimitedAvailableQuantity())->toBeTrue();
});

// ── Edge Cases ──────────────────────────────────────────────────────

test('updateComponent succeeds with same values (no-op)', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $plateUnit = SellingUnit::where('name_en', 'Plate')->where('is_standard', true)->first();
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'name_en' => 'Test EN',
        'name_fr' => 'Test FR',
        'price' => 1000,
        'selling_unit' => (string) $plateUnit->id,
    ]);

    $result = $this->componentService->updateComponent($component, [
        'name_en' => 'Test EN',
        'name_fr' => 'Test FR',
        'price' => 1000,
        'selling_unit' => (string) $plateUnit->id,
    ]);

    expect($result['success'])->toBeTrue();
});

test('updateComponent trims name whitespace', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id]);

    $result = $this->componentService->updateComponent($component, [
        'name_en' => '  Trimmed Name  ',
        'name_fr' => '  Nom Coupe  ',
        'price' => $component->price,
        'selling_unit' => $component->selling_unit,
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['component']->name_en)->toBe('Trimmed Name');
    expect($result['component']->name_fr)->toBe('Nom Coupe');
});

test('updateComponent handles empty string for max_quantity as null', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'max_quantity' => 5,
    ]);

    $result = $this->componentService->updateComponent($component, [
        'name_en' => $component->name_en,
        'name_fr' => $component->name_fr,
        'price' => $component->price,
        'selling_unit' => $component->selling_unit,
        'max_quantity' => '',
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['component']->max_quantity)->toBeNull();
});

test('updateComponent handles all standard selling units', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $standardUnits = SellingUnit::where('is_standard', true)->get();

    foreach ($standardUnits as $unitModel) {
        $component = MealComponent::factory()->create([
            'meal_id' => $meal->id,
        ]);

        $result = $this->componentService->updateComponent($component, [
            'name_en' => $component->name_en,
            'name_fr' => $component->name_fr,
            'price' => $component->price,
            'selling_unit' => (string) $unitModel->id,
        ]);

        expect($result['success'])->toBeTrue();
        expect($result['component']->selling_unit)->toBe((string) $unitModel->id);
    }
});

// ── UpdateMealComponentRequest ──────────────────────────────────────

test('UpdateMealComponentRequest authorizes user with manage-meals permission', function () {
    $this->actingAs($this->cook);
    $request = new \App\Http\Requests\Cook\UpdateMealComponentRequest;
    $request->setUserResolver(fn () => $this->cook);

    expect($request->authorize())->toBeTrue();
});

test('UpdateMealComponentRequest denies user without manage-meals permission', function () {
    $user = User::factory()->create();
    $user->assignRole('client');

    $request = new \App\Http\Requests\Cook\UpdateMealComponentRequest;
    $request->setUserResolver(fn () => $user);

    expect($request->authorize())->toBeFalse();
});

test('UpdateMealComponentRequest has correct validation rules', function () {
    $request = new \App\Http\Requests\Cook\UpdateMealComponentRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('name_en');
    expect($rules)->toHaveKey('name_fr');
    expect($rules)->toHaveKey('price');
    expect($rules)->toHaveKey('selling_unit');
    expect($rules)->toHaveKey('min_quantity');
    expect($rules)->toHaveKey('max_quantity');
    expect($rules)->toHaveKey('available_quantity');
});

test('UpdateMealComponentRequest has custom error messages', function () {
    $request = new \App\Http\Requests\Cook\UpdateMealComponentRequest;
    $messages = $request->messages();

    expect($messages)->not->toBeEmpty();
    expect($messages)->toHaveKey('name_en.required');
    expect($messages)->toHaveKey('price.required');
    expect($messages)->toHaveKey('price.min');
});

// ── Controller Route ────────────────────────────────────────────────

test('meal component update route exists', function () {
    $route = collect(\Illuminate\Support\Facades\Route::getRoutes())
        ->first(function ($route) {
            return str_contains($route->uri(), 'meals/{meal}/components/{component}')
                && in_array('PUT', $route->methods());
        });

    expect($route)->not->toBeNull();
    expect($route->getAction()['controller'])->toContain('MealComponentController@update');
});

// ── Activity Logging ────────────────────────────────────────────────

test('component edit is logged with old and new values', function () {
    $this->actingAs($this->cook);
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'name_en' => 'Original Name',
        'price' => 500,
    ]);

    $result = $this->componentService->updateComponent($component, [
        'name_en' => 'Updated Name',
        'name_fr' => $component->name_fr,
        'price' => 700,
        'selling_unit' => $component->selling_unit,
    ]);

    expect($result['success'])->toBeTrue();

    // Old values returned for controller to log
    expect($result['old_values'])->toHaveKey('name_en', 'Original Name');
    expect($result['old_values'])->toHaveKey('price', 500);

    // New values on component
    expect($result['component']->name_en)->toBe('Updated Name');
    expect($result['component']->price)->toBe(700);
});

// ── Database Persistence ────────────────────────────────────────────

test('updateComponent persists changes to database', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'name_en' => 'Before',
        'price' => 100,
    ]);

    $this->componentService->updateComponent($component, [
        'name_en' => 'After',
        'name_fr' => $component->name_fr,
        'price' => 200,
        'selling_unit' => $component->selling_unit,
    ]);

    $this->assertDatabaseHas('meal_components', [
        'id' => $component->id,
        'name_en' => 'After',
        'price' => 200,
    ]);
});

test('updateComponent does not change position', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'position' => 3,
    ]);

    $result = $this->componentService->updateComponent($component, [
        'name_en' => 'Updated',
        'name_fr' => $component->name_fr,
        'price' => $component->price,
        'selling_unit' => $component->selling_unit,
    ]);

    expect($result['component']->position)->toBe(3);
});
