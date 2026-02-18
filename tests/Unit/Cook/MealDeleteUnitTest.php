<?php

use App\Models\Meal;
use App\Services\MealService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| F-111: Meal Delete — Unit Tests
|--------------------------------------------------------------------------
|
| Tests for the MealService::canDeleteMeal(), deleteMeal(), and
| getCompletedOrderCount() methods. Verifies business rules BR-218
| through BR-226.
|
*/

beforeEach(function () {
    $this->seedRolesAndPermissions();
    $result = createTenantWithCook();
    $this->tenant = $result['tenant'];
    $this->cook = $result['cook'];
    $this->mealService = new MealService;
});

// ============================================
// MealService::canDeleteMeal() tests
// ============================================

test('can delete a draft meal with no orders', function () {
    $meal = Meal::factory()->draft()->create(['tenant_id' => $this->tenant->id]);

    $result = $this->mealService->canDeleteMeal($meal);

    expect($result['can_delete'])->toBeTrue()
        ->and($result['pending_count'])->toBe(0);
});

test('can delete a live meal with no orders', function () {
    $meal = Meal::factory()->live()->create(['tenant_id' => $this->tenant->id]);

    $result = $this->mealService->canDeleteMeal($meal);

    expect($result['can_delete'])->toBeTrue()
        ->and($result['pending_count'])->toBe(0);
});

test('can delete an unavailable meal with no orders', function () {
    $meal = Meal::factory()->unavailable()->create(['tenant_id' => $this->tenant->id]);

    $result = $this->mealService->canDeleteMeal($meal);

    expect($result['can_delete'])->toBeTrue();
});

test('can delete meal returns true when orders table does not exist (forward-compatible)', function () {
    // Orders table does not exist yet in this project
    expect(Schema::hasTable('orders'))->toBeFalse();

    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);

    $result = $this->mealService->canDeleteMeal($meal);

    expect($result['can_delete'])->toBeTrue()
        ->and($result['pending_count'])->toBe(0);
});

test('canDeleteMeal returns structured array with required keys', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);

    $result = $this->mealService->canDeleteMeal($meal);

    expect($result)->toHaveKey('can_delete')
        ->toHaveKey('pending_count');
});

// ============================================
// MealService::deleteMeal() tests
// ============================================

test('soft deletes a draft meal with no orders (BR-218)', function () {
    $meal = Meal::factory()->draft()->create(['tenant_id' => $this->tenant->id]);

    $result = $this->mealService->deleteMeal($meal);

    expect($result['success'])->toBeTrue()
        ->and($result['meal'])->toBeInstanceOf(Meal::class);

    // Verify soft deleted
    $this->assertSoftDeleted('meals', ['id' => $meal->id]);
});

test('soft deletes a live meal with no pending orders', function () {
    $meal = Meal::factory()->live()->create(['tenant_id' => $this->tenant->id]);

    $result = $this->mealService->deleteMeal($meal);

    expect($result['success'])->toBeTrue();
    $this->assertSoftDeleted('meals', ['id' => $meal->id]);
});

test('soft deleted meal is hidden from default queries (BR-220, BR-221)', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $mealId = $meal->id;

    $this->mealService->deleteMeal($meal);

    // Default query should not return soft-deleted meals
    expect(Meal::find($mealId))->toBeNull();

    // But withTrashed should find it (BR-222 — order history preserved)
    expect(Meal::withTrashed()->find($mealId))->not->toBeNull()
        ->and(Meal::withTrashed()->find($mealId)->deleted_at)->not->toBeNull();
});

test('soft deleted meal preserves all data (BR-222)', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Ndole Special',
        'name_fr' => 'Ndole Speciale',
        'price' => 3000,
        'status' => Meal::STATUS_LIVE,
    ]);

    $this->mealService->deleteMeal($meal);

    $deletedMeal = Meal::withTrashed()->find($meal->id);
    expect($deletedMeal->name_en)->toBe('Ndole Special')
        ->and($deletedMeal->name_fr)->toBe('Ndole Speciale')
        ->and($deletedMeal->price)->toBe(3000)
        ->and($deletedMeal->status)->toBe(Meal::STATUS_LIVE);
});

test('deleteMeal returns success array with expected structure', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);

    $result = $this->mealService->deleteMeal($meal);

    expect($result)->toHaveKey('success')
        ->toHaveKey('meal')
        ->toHaveKey('completed_order_count')
        ->and($result['success'])->toBeTrue()
        ->and($result['completed_order_count'])->toBe(0);
});

// ============================================
// MealService::getCompletedOrderCount() tests
// ============================================

test('completed order count returns 0 when orders table does not exist', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);

    $count = $this->mealService->getCompletedOrderCount($meal);

    expect($count)->toBe(0);
});

// ============================================
// Meal Model — SoftDeletes behavior
// ============================================

test('meal model uses SoftDeletes trait', function () {
    $meal = new Meal;

    expect(in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($meal)))
        ->toBeTrue();
});

test('deleted meal does not appear in tenant meals relationship', function () {
    $meal1 = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $meal2 = Meal::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->mealService->deleteMeal($meal1);

    $meals = $this->tenant->meals()->get();
    expect($meals)->toHaveCount(1)
        ->and($meals->first()->id)->toBe($meal2->id);
});

test('deleted meal still appears in withTrashed query', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $mealId = $meal->id;

    $this->mealService->deleteMeal($meal);

    $allMeals = Meal::withTrashed()->where('tenant_id', $this->tenant->id)->get();
    expect($allMeals)->toHaveCount(1)
        ->and($allMeals->first()->id)->toBe($mealId)
        ->and($allMeals->first()->trashed())->toBeTrue();
});

test('multiple meals can be deleted independently', function () {
    $meal1 = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $meal2 = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $meal3 = Meal::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->mealService->deleteMeal($meal1);
    $this->mealService->deleteMeal($meal3);

    $remainingMeals = $this->tenant->meals()->get();
    expect($remainingMeals)->toHaveCount(1)
        ->and($remainingMeals->first()->id)->toBe($meal2->id);

    $allMeals = Meal::withTrashed()->where('tenant_id', $this->tenant->id)->get();
    expect($allMeals)->toHaveCount(3);
});

test('deleted meal is not visible in forTenant scope', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->mealService->deleteMeal($meal);

    $meals = Meal::forTenant($this->tenant->id)->get();
    expect($meals)->toHaveCount(0);
});

test('deleted meal is not visible in live scope', function () {
    $meal = Meal::factory()->live()->create(['tenant_id' => $this->tenant->id]);

    $this->mealService->deleteMeal($meal);

    $meals = Meal::live()->get();
    expect($meals)->toHaveCount(0);
});

test('deleted meal is not visible in available scope', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_available' => true,
    ]);

    $this->mealService->deleteMeal($meal);

    $meals = Meal::available()->get();
    expect($meals)->toHaveCount(0);
});

// ============================================
// Meal Model — orders relationship
// ============================================

test('meal has orders relationship defined', function () {
    $meal = new Meal;

    expect(method_exists($meal, 'orders'))->toBeTrue();
});

// ============================================
// Delete route existence
// ============================================

test('delete route is registered', function () {
    $route = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
        ->first(function ($route) {
            return $route->getName() === 'cook.meals.destroy';
        });

    expect($route)->not->toBeNull()
        ->and($route->methods())->toContain('DELETE')
        ->and($route->uri())->toContain('meals');
});
