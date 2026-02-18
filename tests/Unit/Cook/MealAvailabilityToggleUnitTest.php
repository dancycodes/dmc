<?php

use App\Models\Meal;
use App\Services\MealService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| F-113: Meal Availability Toggle — Unit Tests
|--------------------------------------------------------------------------
|
| Tests for MealService::toggleAvailability() method and related model
| behaviour. BR-235 to BR-243 coverage.
|
| HTTP endpoint behaviour is verified via Playwright in Phase 3.
|
*/

beforeEach(function () {
    $this->seedRolesAndPermissions();
    $result = createTenantWithCook();
    $this->tenant = $result['tenant'];
    $this->cook = $result['cook'];
    $this->tenant->update(['cook_id' => $this->cook->id]);
    $this->mealService = new MealService;
});

// ── MealService::toggleAvailability() — Available to Unavailable ──────

it('toggles an available meal to unavailable', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_available' => true,
    ]);

    $result = $this->mealService->toggleAvailability($meal);

    expect($result['success'])->toBeTrue()
        ->and($result['old_availability'])->toBeTrue()
        ->and($result['new_availability'])->toBeFalse()
        ->and($meal->fresh()->is_available)->toBeFalse();
});

it('toggles an unavailable meal to available', function () {
    $meal = Meal::factory()->unavailable()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $result = $this->mealService->toggleAvailability($meal);

    expect($result['success'])->toBeTrue()
        ->and($result['old_availability'])->toBeFalse()
        ->and($result['new_availability'])->toBeTrue()
        ->and($meal->fresh()->is_available)->toBeTrue();
});

// ── BR-235: Availability is separate from status ──────────────────────

it('does not change status when toggling availability (BR-235)', function () {
    $meal = Meal::factory()->live()->create([
        'tenant_id' => $this->tenant->id,
        'is_available' => true,
    ]);

    $this->mealService->toggleAvailability($meal);

    expect($meal->fresh()->status)->toBe('live')
        ->and($meal->fresh()->is_available)->toBeFalse();
});

it('does not change status on draft meals when toggling availability (BR-235)', function () {
    $meal = Meal::factory()->draft()->create([
        'tenant_id' => $this->tenant->id,
        'is_available' => true,
    ]);

    $this->mealService->toggleAvailability($meal);

    expect($meal->fresh()->status)->toBe('draft')
        ->and($meal->fresh()->is_available)->toBeFalse();
});

// ── BR-238: Draft meal availability takes effect on going live ────────

it('preserves availability setting when draft meal goes live (BR-238)', function () {
    $meal = Meal::factory()->draft()->unavailable()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    // Add component so it can go live
    \App\Models\MealComponent::factory()->create(['meal_id' => $meal->id]);

    $this->mealService->toggleStatus($meal->fresh());

    // After going live, should still be unavailable
    expect($meal->fresh()->status)->toBe('live')
        ->and($meal->fresh()->is_available)->toBeFalse();
});

// ── BR-240: Immediate effect ─────────────────────────────────────────

it('applies change immediately to database (BR-240)', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_available' => true,
    ]);

    $result = $this->mealService->toggleAvailability($meal);

    // Verify by re-querying the database directly
    $dbMeal = Meal::find($meal->id);
    expect($dbMeal->is_available)->toBeFalse();
});

// ── Return values ────────────────────────────────────────────────────

it('returns correct structure on toggle', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_available' => true,
    ]);

    $result = $this->mealService->toggleAvailability($meal);

    expect($result)->toHaveKeys(['success', 'meal', 'old_availability', 'new_availability'])
        ->and($result['success'])->toBeTrue()
        ->and($result['meal'])->toBeInstanceOf(Meal::class);
});

it('returns correct old and new availability for both directions', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_available' => true,
    ]);

    // Available -> Unavailable
    $result = $this->mealService->toggleAvailability($meal);
    expect($result['old_availability'])->toBeTrue()
        ->and($result['new_availability'])->toBeFalse();

    // Unavailable -> Available
    $result = $this->mealService->toggleAvailability($meal->fresh());
    expect($result['old_availability'])->toBeFalse()
        ->and($result['new_availability'])->toBeTrue();
});

// ── Edge Cases ───────────────────────────────────────────────────────

it('rapid toggling produces correct final state', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_available' => true,
    ]);

    $this->mealService->toggleAvailability($meal);           // available -> unavailable
    $this->mealService->toggleAvailability($meal->fresh());  // unavailable -> available
    $this->mealService->toggleAvailability($meal->fresh());  // available -> unavailable

    expect($meal->fresh()->is_available)->toBeFalse();
});

it('does not affect other meal fields when toggling (BR-243)', function () {
    $meal = Meal::factory()->live()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Availability Test 999',
        'name_fr' => 'Test Disponibilite 999',
        'price' => 3000,
        'status' => Meal::STATUS_LIVE,
        'position' => 7,
    ]);

    $this->mealService->toggleAvailability($meal);

    $updatedMeal = $meal->fresh();
    expect($updatedMeal->name_en)->toBe('Availability Test 999')
        ->and($updatedMeal->name_fr)->toBe('Test Disponibilite 999')
        ->and($updatedMeal->price)->toBe(3000)
        ->and($updatedMeal->status)->toBe('live')
        ->and($updatedMeal->position)->toBe(7)
        ->and($updatedMeal->is_available)->toBeFalse();
});

// ── Model Scope Tests ────────────────────────────────────────────────

it('has scope for available meals', function () {
    Meal::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'is_available' => true,
    ]);
    Meal::factory()->count(3)->unavailable()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $available = Meal::available()->forTenant($this->tenant->id)->count();
    $total = Meal::forTenant($this->tenant->id)->count();

    expect($available)->toBe(2)
        ->and($total)->toBe(5);
});

// ── Route Definition Tests ───────────────────────────────────────────

it('has the toggle-availability route defined', function () {
    $route = route('cook.meals.toggle-availability', ['meal' => 1]);
    expect($route)->toContain('toggle-availability');
});

it('toggle-availability route uses PATCH method', function () {
    $routes = collect(app('router')->getRoutes()->getRoutes());
    $toggleRoute = $routes->first(function ($route) {
        return $route->getName() === 'cook.meals.toggle-availability';
    });

    expect($toggleRoute)->not->toBeNull()
        ->and($toggleRoute->methods())->toContain('PATCH');
});

// ── HTTP Endpoint Tests (with proper tenant context) ─────────────────

it('toggles available to unavailable via HTTP endpoint (BR-240)', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_available' => true,
    ]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);
    $tenantUrl = 'https://'.$this->tenant->slug.'.'.$mainDomain;

    $response = $this->actingAs($this->cook)
        ->patch($tenantUrl.'/dashboard/meals/'.$meal->id.'/toggle-availability');

    expect($response->status())->toBeRedirect();
    expect($meal->fresh()->is_available)->toBeFalse();
});

it('toggles unavailable to available via HTTP endpoint', function () {
    $meal = Meal::factory()->unavailable()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);
    $tenantUrl = 'https://'.$this->tenant->slug.'.'.$mainDomain;

    $response = $this->actingAs($this->cook)
        ->patch($tenantUrl.'/dashboard/meals/'.$meal->id.'/toggle-availability');

    expect($response->status())->toBeRedirect();
    expect($meal->fresh()->is_available)->toBeTrue();
});

it('returns 403 without manage-meals permission (BR-242)', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_available' => true,
    ]);
    $client = createUser('client');

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);
    $tenantUrl = 'https://'.$this->tenant->slug.'.'.$mainDomain;

    $response = $this->actingAs($client)
        ->patch($tenantUrl.'/dashboard/meals/'.$meal->id.'/toggle-availability');

    expect($response->status())->toBe(403);
});

it('returns 404 for meals from another tenant', function () {
    $otherResult = createTenantWithCook();
    $otherResult['tenant']->update(['cook_id' => $otherResult['cook']->id]);
    $meal = Meal::factory()->create([
        'tenant_id' => $otherResult['tenant']->id,
        'is_available' => true,
    ]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);
    $tenantUrl = 'https://'.$this->tenant->slug.'.'.$mainDomain;

    $response = $this->actingAs($this->cook)
        ->patch($tenantUrl.'/dashboard/meals/'.$meal->id.'/toggle-availability');

    expect($response->status())->toBe(404);
});

it('logs activity when availability changes (BR-241)', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_available' => true,
    ]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);
    $tenantUrl = 'https://'.$this->tenant->slug.'.'.$mainDomain;

    $this->actingAs($this->cook)
        ->patch($tenantUrl.'/dashboard/meals/'.$meal->id.'/toggle-availability');

    $activity = \Spatie\Activitylog\Models\Activity::latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toContain('availability changed')
        ->and($activity->properties['action'])->toBe('meal_availability_toggled')
        ->and($activity->properties['old_availability'])->toBeTrue()
        ->and($activity->properties['new_availability'])->toBeFalse();
});

it('shows correct toast message when marking unavailable', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_available' => true,
    ]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);
    $tenantUrl = 'https://'.$this->tenant->slug.'.'.$mainDomain;

    $response = $this->actingAs($this->cook)
        ->patch($tenantUrl.'/dashboard/meals/'.$meal->id.'/toggle-availability');

    $response->assertSessionHas('success');
});

it('shows correct toast message when marking available', function () {
    $meal = Meal::factory()->unavailable()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);
    $tenantUrl = 'https://'.$this->tenant->slug.'.'.$mainDomain;

    $response = $this->actingAs($this->cook)
        ->patch($tenantUrl.'/dashboard/meals/'.$meal->id.'/toggle-availability');

    $response->assertSessionHas('success');
});
