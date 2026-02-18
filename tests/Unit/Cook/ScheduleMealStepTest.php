<?php

use App\Models\Meal;
use App\Models\MealComponent;
use App\Models\Schedule;
use App\Models\Tenant;
use App\Services\SetupWizardService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new SetupWizardService;
});

// ===== Schedule Model Tests =====

test('schedule belongs to tenant', function () {
    $tenant = Tenant::factory()->create();
    $schedule = Schedule::factory()->create([
        'tenant_id' => $tenant->id,
        'day_of_week' => Schedule::MONDAY,
    ]);

    expect($schedule->tenant->id)->toBe($tenant->id);
});

test('schedule has day label accessor', function () {
    $schedule = Schedule::factory()->create([
        'day_of_week' => Schedule::MONDAY,
    ]);

    expect($schedule->day_label)->toBe('Mon');
});

test('schedule casts day_of_week to integer', function () {
    $schedule = Schedule::factory()->create([
        'day_of_week' => Schedule::WEDNESDAY,
    ]);

    expect($schedule->day_of_week)->toBeInt();
    expect($schedule->day_of_week)->toBe(Schedule::WEDNESDAY);
});

test('schedule casts is_available to boolean', function () {
    $schedule = Schedule::factory()->create([
        'is_available' => true,
    ]);

    expect($schedule->is_available)->toBeBool();
    expect($schedule->is_available)->toBeTrue();
});

test('schedule has correct day constants', function () {
    expect(Schedule::SUNDAY)->toBe(0);
    expect(Schedule::MONDAY)->toBe(1);
    expect(Schedule::TUESDAY)->toBe(2);
    expect(Schedule::WEDNESDAY)->toBe(3);
    expect(Schedule::THURSDAY)->toBe(4);
    expect(Schedule::FRIDAY)->toBe(5);
    expect(Schedule::SATURDAY)->toBe(6);
});

test('schedule day labels cover all days', function () {
    expect(Schedule::DAY_LABELS)->toHaveCount(7);
    expect(Schedule::DAY_LABELS[Schedule::SUNDAY])->toBe('Sun');
    expect(Schedule::DAY_LABELS[Schedule::SATURDAY])->toBe('Sat');
});

test('schedule enforces unique tenant day constraint', function () {
    $tenant = Tenant::factory()->create();
    Schedule::factory()->create([
        'tenant_id' => $tenant->id,
        'day_of_week' => Schedule::MONDAY,
    ]);

    expect(fn () => Schedule::factory()->create([
        'tenant_id' => $tenant->id,
        'day_of_week' => Schedule::MONDAY,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('schedule allows same day for different tenants', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    Schedule::factory()->create([
        'tenant_id' => $tenant1->id,
        'day_of_week' => Schedule::MONDAY,
    ]);
    $schedule2 = Schedule::factory()->create([
        'tenant_id' => $tenant2->id,
        'day_of_week' => Schedule::MONDAY,
    ]);

    expect($schedule2->exists)->toBeTrue();
});

// ===== Meal Model Tests =====

test('meal belongs to tenant', function () {
    $tenant = Tenant::factory()->create();
    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    expect($meal->tenant->id)->toBe($tenant->id);
});

test('meal has many components', function () {
    $meal = Meal::factory()->create();
    MealComponent::factory()->count(3)->create(['meal_id' => $meal->id]);

    expect($meal->components)->toHaveCount(3);
});

test('meal casts price to integer', function () {
    $meal = Meal::factory()->create(['price' => 2500]);

    expect($meal->price)->toBeInt();
    expect($meal->price)->toBe(2500);
});

test('meal casts is_active to boolean', function () {
    $meal = Meal::factory()->create(['is_active' => true]);

    expect($meal->is_active)->toBeBool();
    expect($meal->is_active)->toBeTrue();
});

test('meal active scope filters correctly', function () {
    $tenant = Tenant::factory()->create();
    Meal::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    Meal::factory()->create(['tenant_id' => $tenant->id, 'is_active' => false]);

    expect(Meal::query()->active()->count())->toBe(1);
});

test('meal defaults to is_active true', function () {
    $meal = Meal::factory()->create();

    expect($meal->is_active)->toBeTrue();
});

test('meal has translatable name', function () {
    $meal = Meal::factory()->create([
        'name_en' => 'Ndole',
        'name_fr' => 'Ndole FR',
    ]);

    app()->setLocale('en');
    expect($meal->name)->toBe('Ndole');

    app()->setLocale('fr');
    expect($meal->name)->toBe('Ndole FR');
});

// ===== MealComponent Model Tests =====

test('meal component belongs to meal', function () {
    $meal = Meal::factory()->create(['name_en' => 'Test Meal']);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id]);

    expect($component->meal->name_en)->toBe('Test Meal');
});

test('meal component has translatable name', function () {
    $component = MealComponent::factory()->create([
        'name_en' => 'Spinach',
        'name_fr' => 'Epinards',
    ]);

    app()->setLocale('en');
    expect($component->name)->toBe('Spinach');

    app()->setLocale('fr');
    expect($component->name)->toBe('Epinards');
});

test('meal component allows null description', function () {
    $component = MealComponent::factory()->create([
        'description_en' => null,
        'description_fr' => null,
    ]);

    expect($component->description_en)->toBeNull();
    expect($component->description_fr)->toBeNull();
});

test('meal components cascade delete with meal', function () {
    $meal = Meal::factory()->create();
    MealComponent::factory()->count(3)->create(['meal_id' => $meal->id]);

    expect(MealComponent::where('meal_id', $meal->id)->count())->toBe(3);

    // F-108: Meal now uses SoftDeletes, so forceDelete() is needed to trigger FK cascade
    $meal->forceDelete();

    expect(MealComponent::where('meal_id', $meal->id)->count())->toBe(0);
});

// ===== SetupWizardService Tests =====

test('has active meal returns true when meal with component exists', function () {
    $tenant = Tenant::factory()->create();
    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'is_active' => true,
    ]);
    MealComponent::factory()->create(['meal_id' => $meal->id]);

    expect($this->service->hasActiveMeal($tenant))->toBeTrue();
});

test('has active meal returns false when no meals exist', function () {
    $tenant = Tenant::factory()->create();

    expect($this->service->hasActiveMeal($tenant))->toBeFalse();
});

test('has active meal returns false when meal is inactive', function () {
    $tenant = Tenant::factory()->create();
    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'is_active' => false,
    ]);
    MealComponent::factory()->create(['meal_id' => $meal->id]);

    expect($this->service->hasActiveMeal($tenant))->toBeFalse();
});

test('has active meal returns false when meal has no components', function () {
    $tenant = Tenant::factory()->create();
    Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'is_active' => true,
    ]);

    expect($this->service->hasActiveMeal($tenant))->toBeFalse();
});

test('get schedule data returns all 7 days', function () {
    $tenant = Tenant::factory()->create();
    $data = $this->service->getScheduleData($tenant);

    expect($data)->toHaveCount(7);
    expect($data[0]['day'])->toBe(0);  // Sunday
    expect($data[6]['day'])->toBe(6);  // Saturday
});

test('get schedule data pre-fills existing schedule', function () {
    $tenant = Tenant::factory()->create();
    Schedule::factory()->create([
        'tenant_id' => $tenant->id,
        'day_of_week' => Schedule::MONDAY,
        'start_time' => '11:00',
        'end_time' => '20:00',
        'is_available' => true,
    ]);

    $data = $this->service->getScheduleData($tenant);

    // Monday (index 1) should be enabled
    $monday = collect($data)->firstWhere('day', Schedule::MONDAY);
    expect($monday['enabled'])->toBeTrue();
    expect($monday['start_time'])->toBe('11:00');
    expect($monday['end_time'])->toBe('20:00');

    // Sunday (index 0) should not be enabled
    $sunday = collect($data)->firstWhere('day', Schedule::SUNDAY);
    expect($sunday['enabled'])->toBeFalse();
});

test('get meals data returns meals with components', function () {
    $tenant = Tenant::factory()->create();
    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'name_en' => 'Ndole',
        'price' => 2500,
    ]);
    MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'name_en' => 'Spinach',
    ]);

    $data = $this->service->getMealsData($tenant);

    expect($data)->toHaveCount(1);
    expect($data[0]['name_en'])->toBe('Ndole');
    expect($data[0]['price'])->toBe(2500);
    expect($data[0]['components'])->toHaveCount(1);
    expect($data[0]['components'][0]['name_en'])->toBe('Spinach');
});

test('get meals data returns empty array when no meals', function () {
    $tenant = Tenant::factory()->create();
    $data = $this->service->getMealsData($tenant);

    expect($data)->toBeArray();
    expect($data)->toBeEmpty();
});

test('tenant has meals relationship', function () {
    $tenant = Tenant::factory()->create();
    Meal::factory()->count(2)->create(['tenant_id' => $tenant->id]);

    expect($tenant->meals)->toHaveCount(2);
});

test('tenant has schedules relationship', function () {
    $tenant = Tenant::factory()->create();
    Schedule::factory()->create([
        'tenant_id' => $tenant->id,
        'day_of_week' => Schedule::MONDAY,
    ]);
    Schedule::factory()->create([
        'tenant_id' => $tenant->id,
        'day_of_week' => Schedule::TUESDAY,
    ]);

    expect($tenant->schedules)->toHaveCount(2);
});

test('step 4 marked complete when active meal with component exists', function () {
    $tenant = Tenant::factory()->create();

    expect($this->service->isStepComplete($tenant, 4))->toBeFalse();

    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'is_active' => true,
    ]);
    MealComponent::factory()->create(['meal_id' => $meal->id]);

    $this->service->markStepComplete($tenant, 4);
    $tenant->refresh();

    expect($this->service->isStepComplete($tenant, 4))->toBeTrue();
});

test('can go live with brand info delivery area and active meal', function () {
    $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);

    $tenant = Tenant::factory()->create([
        'name_en' => 'Test Cook',
        'name_fr' => 'Test Cuisinier',
        'whatsapp' => '+237670000000',
    ]);

    // Add delivery area with quarter
    $town = \App\Models\Town::factory()->create();
    $deliveryArea = \App\Models\DeliveryArea::factory()->create([
        'tenant_id' => $tenant->id,
        'town_id' => $town->id,
    ]);
    $quarter = \App\Models\Quarter::factory()->create(['town_id' => $town->id]);
    \App\Models\DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $deliveryArea->id,
        'quarter_id' => $quarter->id,
        'delivery_fee' => 500,
    ]);

    // Add meal with component
    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'is_active' => true,
    ]);
    MealComponent::factory()->create(['meal_id' => $meal->id]);

    expect($this->service->canGoLive($tenant))->toBeTrue();
});

// ===== Factory Tests =====

test('schedule factory creates valid schedule', function () {
    $schedule = Schedule::factory()->create();

    expect($schedule->tenant_id)->not->toBeNull();
    expect($schedule->day_of_week)->toBeGreaterThanOrEqual(0);
    expect($schedule->day_of_week)->toBeLessThanOrEqual(6);
    expect($schedule->is_available)->toBeTrue();
});

test('meal factory creates valid meal', function () {
    $meal = Meal::factory()->create();

    expect($meal->tenant_id)->not->toBeNull();
    expect($meal->name_en)->not->toBeEmpty();
    expect($meal->name_fr)->not->toBeEmpty();
    expect($meal->price)->toBeGreaterThan(0);
    expect($meal->is_active)->toBeTrue();
});

test('meal component factory creates valid component', function () {
    $component = MealComponent::factory()->create();

    expect($component->meal_id)->not->toBeNull();
    expect($component->name_en)->not->toBeEmpty();
    expect($component->name_fr)->not->toBeEmpty();
});

test('meal factory inactive state works', function () {
    $meal = Meal::factory()->inactive()->create();

    expect($meal->is_active)->toBeFalse();
});

test('schedule factory unavailable state works', function () {
    $schedule = Schedule::factory()->unavailable()->create();

    expect($schedule->is_available)->toBeFalse();
});
