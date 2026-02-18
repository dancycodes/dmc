<?php

use App\Models\Meal;
use App\Models\MealComponent;
use App\Services\MealService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| F-112: Meal Status Toggle (Draft/Live) — Unit Tests
|--------------------------------------------------------------------------
|
| Tests for MealService::toggleStatus() method and related model behaviour.
| BR-227 to BR-234 coverage.
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

// ── MealService::toggleStatus() — Draft to Live ─────────────────────

it('toggles a draft meal with components to live', function () {
    $meal = Meal::factory()->draft()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal->id]);

    $result = $this->mealService->toggleStatus($meal);

    expect($result['success'])->toBeTrue()
        ->and($result['old_status'])->toBe('draft')
        ->and($result['new_status'])->toBe('live')
        ->and($meal->fresh()->status)->toBe('live');
});

it('blocks going live without components (BR-227)', function () {
    $meal = Meal::factory()->draft()->create(['tenant_id' => $this->tenant->id]);

    $result = $this->mealService->toggleStatus($meal);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('component')
        ->and($meal->fresh()->status)->toBe('draft');
});

it('allows toggling to live with multiple components', function () {
    $meal = Meal::factory()->draft()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->count(3)->create(['meal_id' => $meal->id]);

    $result = $this->mealService->toggleStatus($meal);

    expect($result['success'])->toBeTrue()
        ->and($result['new_status'])->toBe('live');
});

it('allows going live when meal is also unavailable (edge case)', function () {
    $meal = Meal::factory()->draft()->unavailable()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal->id]);

    $result = $this->mealService->toggleStatus($meal);

    expect($result['success'])->toBeTrue()
        ->and($result['new_status'])->toBe('live')
        ->and($meal->fresh()->is_available)->toBeFalse();
});

// ── MealService::toggleStatus() — Live to Draft ─────────────────────

it('toggles a live meal to draft', function () {
    $meal = Meal::factory()->live()->create(['tenant_id' => $this->tenant->id]);

    $result = $this->mealService->toggleStatus($meal);

    expect($result['success'])->toBeTrue()
        ->and($result['old_status'])->toBe('live')
        ->and($result['new_status'])->toBe('draft')
        ->and($meal->fresh()->status)->toBe('draft');
});

it('does not change availability when toggling to draft', function () {
    $meal = Meal::factory()->live()->create([
        'tenant_id' => $this->tenant->id,
        'is_available' => false,
    ]);

    $result = $this->mealService->toggleStatus($meal);

    expect($result['success'])->toBeTrue()
        ->and($meal->fresh()->is_available)->toBeFalse();
});

// ── MealService::toggleStatus() — Return values ────────────────────

it('returns correct old and new status values for both directions', function () {
    $meal = Meal::factory()->draft()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal->id]);

    // Draft -> Live
    $result = $this->mealService->toggleStatus($meal);
    expect($result['old_status'])->toBe('draft')
        ->and($result['new_status'])->toBe('live');

    // Live -> Draft
    $result = $this->mealService->toggleStatus($meal->fresh());
    expect($result['old_status'])->toBe('live')
        ->and($result['new_status'])->toBe('draft');
});

it('returns success true with meal object on successful toggle', function () {
    $meal = Meal::factory()->draft()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal->id]);

    $result = $this->mealService->toggleStatus($meal);

    expect($result)->toHaveKeys(['success', 'meal', 'old_status', 'new_status'])
        ->and($result['success'])->toBeTrue()
        ->and($result['meal'])->toBeInstanceOf(Meal::class);
});

it('returns success false with error on failed toggle', function () {
    $meal = Meal::factory()->draft()->create(['tenant_id' => $this->tenant->id]);

    $result = $this->mealService->toggleStatus($meal);

    expect($result)->toHaveKeys(['success', 'error'])
        ->and($result['success'])->toBeFalse()
        ->and($result['error'])->toBeString();
});

// ── Edge Cases ──────────────────────────────────────────────────────

it('rapid toggling produces correct final state', function () {
    $meal = Meal::factory()->draft()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal->id]);

    $this->mealService->toggleStatus($meal);           // draft -> live
    $this->mealService->toggleStatus($meal->fresh());  // live -> draft
    $this->mealService->toggleStatus($meal->fresh());  // draft -> live

    expect($meal->fresh()->status)->toBe('live');
});

it('does not affect other meal fields when toggling', function () {
    $meal = Meal::factory()->draft()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Test Meal 999',
        'name_fr' => 'Repas Test 999',
        'price' => 2500,
        'is_available' => true,
        'position' => 5,
    ]);
    MealComponent::factory()->create(['meal_id' => $meal->id]);

    $this->mealService->toggleStatus($meal);

    $updatedMeal = $meal->fresh();
    expect($updatedMeal->name_en)->toBe('Test Meal 999')
        ->and($updatedMeal->name_fr)->toBe('Repas Test 999')
        ->and($updatedMeal->price)->toBe(2500)
        ->and($updatedMeal->is_available)->toBeTrue()
        ->and($updatedMeal->position)->toBe(5)
        ->and($updatedMeal->status)->toBe('live');
});

// ── Meal Model Tests ────────────────────────────────────────────────

it('has correct status constants (BR-234)', function () {
    expect(Meal::STATUS_DRAFT)->toBe('draft')
        ->and(Meal::STATUS_LIVE)->toBe('live')
        ->and(Meal::STATUSES)->toBe(['draft', 'live']);
});

it('has isDraft and isLive helper methods', function () {
    $draftMeal = Meal::factory()->draft()->create(['tenant_id' => $this->tenant->id]);
    expect($draftMeal->isDraft())->toBeTrue()
        ->and($draftMeal->isLive())->toBeFalse();

    $liveMeal = Meal::factory()->live()->create(['tenant_id' => $this->tenant->id]);
    expect($liveMeal->isLive())->toBeTrue()
        ->and($liveMeal->isDraft())->toBeFalse();
});

it('has scope for draft and live meals', function () {
    Meal::factory()->draft()->count(2)->create(['tenant_id' => $this->tenant->id]);
    Meal::factory()->live()->count(3)->create(['tenant_id' => $this->tenant->id]);

    $drafts = Meal::draft()->forTenant($this->tenant->id)->count();
    $lives = Meal::live()->forTenant($this->tenant->id)->count();

    expect($drafts)->toBe(2)
        ->and($lives)->toBe(3);
});

it('components relationship returns related components', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->count(2)->create(['meal_id' => $meal->id]);

    expect($meal->components)->toHaveCount(2);
});

// ── Route Definition Tests ──────────────────────────────────────────

it('has the toggle-status route defined', function () {
    $route = route('cook.meals.toggle-status', ['meal' => 1]);
    expect($route)->toContain('toggle-status');
});

it('toggle-status route uses PATCH method', function () {
    $routes = collect(app('router')->getRoutes()->getRoutes());
    $toggleRoute = $routes->first(function ($route) {
        return $route->getName() === 'cook.meals.toggle-status';
    });

    expect($toggleRoute)->not->toBeNull()
        ->and($toggleRoute->methods())->toContain('PATCH');
});

// ── HTTP Endpoint Tests (with proper tenant context) ────────────────

it('toggles draft to live via HTTP endpoint (BR-231)', function () {
    $meal = Meal::factory()->draft()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal->id]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);
    $tenantUrl = 'https://'.$this->tenant->slug.'.'.$mainDomain;

    $response = $this->actingAs($this->cook)
        ->patch($tenantUrl.'/dashboard/meals/'.$meal->id.'/toggle-status');

    expect($response->status())->toBeRedirect();
    expect($meal->fresh()->status)->toBe('live');
});

it('toggles live to draft via HTTP endpoint', function () {
    $meal = Meal::factory()->live()->create(['tenant_id' => $this->tenant->id]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);
    $tenantUrl = 'https://'.$this->tenant->slug.'.'.$mainDomain;

    $response = $this->actingAs($this->cook)
        ->patch($tenantUrl.'/dashboard/meals/'.$meal->id.'/toggle-status');

    expect($response->status())->toBeRedirect();
    expect($meal->fresh()->status)->toBe('draft');
});

it('blocks going live via HTTP when no components (BR-227)', function () {
    $meal = Meal::factory()->draft()->create(['tenant_id' => $this->tenant->id]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);
    $tenantUrl = 'https://'.$this->tenant->slug.'.'.$mainDomain;

    $response = $this->actingAs($this->cook)
        ->patch($tenantUrl.'/dashboard/meals/'.$meal->id.'/toggle-status');

    expect($meal->fresh()->status)->toBe('draft');
    $response->assertSessionHas('error');
});

it('returns 403 without manage-meals permission (BR-233)', function () {
    $meal = Meal::factory()->draft()->create(['tenant_id' => $this->tenant->id]);
    $client = createUser('client');

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);
    $tenantUrl = 'https://'.$this->tenant->slug.'.'.$mainDomain;

    $response = $this->actingAs($client)
        ->patch($tenantUrl.'/dashboard/meals/'.$meal->id.'/toggle-status');

    expect($response->status())->toBe(403);
});

it('returns 404 for meals from another tenant', function () {
    $otherResult = createTenantWithCook();
    $otherResult['tenant']->update(['cook_id' => $otherResult['cook']->id]);
    $meal = Meal::factory()->live()->create(['tenant_id' => $otherResult['tenant']->id]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);
    $tenantUrl = 'https://'.$this->tenant->slug.'.'.$mainDomain;

    $response = $this->actingAs($this->cook)
        ->patch($tenantUrl.'/dashboard/meals/'.$meal->id.'/toggle-status');

    expect($response->status())->toBe(404);
});

it('logs activity when status changes (BR-232)', function () {
    $meal = Meal::factory()->draft()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal->id]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);
    $tenantUrl = 'https://'.$this->tenant->slug.'.'.$mainDomain;

    $this->actingAs($this->cook)
        ->patch($tenantUrl.'/dashboard/meals/'.$meal->id.'/toggle-status');

    $activity = \Spatie\Activitylog\Models\Activity::latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toContain('status changed')
        ->and($activity->properties['action'])->toBe('meal_status_toggled')
        ->and($activity->properties['old_status'])->toBe('draft')
        ->and($activity->properties['new_status'])->toBe('live');
});
