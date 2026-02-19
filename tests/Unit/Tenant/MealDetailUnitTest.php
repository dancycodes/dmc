<?php

/**
 * F-129: Meal Detail View — Unit Tests
 *
 * Tests for TenantLandingService meal detail data building,
 * component availability, quantity limits, schedule, locations.
 */

use App\Models\ComponentRequirementRule;
use App\Models\CookSchedule;
use App\Models\DeliveryArea;
use App\Models\Meal;
use App\Models\MealComponent;
use App\Models\MealImage;
use App\Models\MealSchedule;
use App\Models\PickupLocation;
use App\Models\Quarter;
use App\Models\Tenant;
use App\Models\Town;
use App\Services\TenantLandingService;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SellingUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new RoleAndPermissionSeeder)->run();
    (new SellingUnitSeeder)->run();
    $this->tenant = Tenant::factory()->create();
    $this->meal = Meal::factory()->live()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Ndole Special',
        'name_fr' => 'Ndole Special FR',
        'description_en' => 'A delicious traditional Cameroonian dish',
        'description_fr' => 'Un delicieux plat traditionnel camerounais',
        'estimated_prep_time' => 45,
    ]);
    $this->service = app(TenantLandingService::class);
});

// --- Core Meal Data ---

test('getMealDetailData returns core meal fields', function () {
    $data = $this->service->getMealDetailData($this->meal, $this->tenant);

    expect($data)->toHaveKeys(['meal', 'components', 'schedule', 'locations'])
        ->and($data['meal'])->toHaveKeys(['id', 'name', 'description', 'images', 'tags', 'prepTime', 'allUnavailable', 'hasComponents'])
        ->and($data['meal']['id'])->toBe($this->meal->id)
        ->and($data['meal']['name'])->toBe('Ndole Special')
        ->and($data['meal']['description'])->toBe('A delicious traditional Cameroonian dish')
        ->and($data['meal']['prepTime'])->toBe(45);
});

test('meal name resolves to current locale', function () {
    app()->setLocale('fr');

    $data = $this->service->getMealDetailData($this->meal, $this->tenant);

    expect($data['meal']['name'])->toBe('Ndole Special FR')
        ->and($data['meal']['description'])->toBe('Un delicieux plat traditionnel camerounais');
});

test('meal images are returned ordered by position', function () {
    MealImage::factory()->create([
        'meal_id' => $this->meal->id,
        'position' => 2,
        'path' => 'meals/image2.jpg',
    ]);
    MealImage::factory()->create([
        'meal_id' => $this->meal->id,
        'position' => 1,
        'path' => 'meals/image1.jpg',
    ]);

    $data = $this->service->getMealDetailData($this->meal, $this->tenant);

    expect($data['meal']['images'])->toHaveCount(2)
        ->and($data['meal']['images'][0]['url'])->toContain('image1.jpg')
        ->and($data['meal']['images'][1]['url'])->toContain('image2.jpg');
});

test('no images returns empty array', function () {
    $data = $this->service->getMealDetailData($this->meal, $this->tenant);

    expect($data['meal']['images'])->toBeEmpty();
});

test('hasComponents is true when components exist', function () {
    MealComponent::factory()->create(['meal_id' => $this->meal->id]);

    $data = $this->service->getMealDetailData($this->meal, $this->tenant);

    expect($data['meal']['hasComponents'])->toBeTrue();
});

test('hasComponents is false when no components', function () {
    $data = $this->service->getMealDetailData($this->meal, $this->tenant);

    expect($data['meal']['hasComponents'])->toBeFalse();
});

// --- Component Availability ---

test('available component shows success status', function () {
    MealComponent::factory()->create([
        'meal_id' => $this->meal->id,
        'is_available' => true,
        'available_quantity' => null,
        'price' => 1500,
    ]);

    $data = $this->service->getMealDetailData($this->meal, $this->tenant);

    expect($data['components'][0]['isAvailable'])->toBeTrue()
        ->and($data['components'][0]['availabilityColor'])->toBe('success');
});

test('low stock component shows warning status', function () {
    MealComponent::factory()->create([
        'meal_id' => $this->meal->id,
        'is_available' => true,
        'available_quantity' => 3,
        'price' => 1000,
    ]);

    $data = $this->service->getMealDetailData($this->meal, $this->tenant);

    expect($data['components'][0]['availabilityColor'])->toBe('warning');
});

test('sold out component shows danger status and disabled', function () {
    MealComponent::factory()->create([
        'meal_id' => $this->meal->id,
        'is_available' => true,
        'available_quantity' => 0,
        'price' => 2000,
    ]);

    $data = $this->service->getMealDetailData($this->meal, $this->tenant);

    expect($data['components'][0]['isAvailable'])->toBeFalse()
        ->and($data['components'][0]['availabilityColor'])->toBe('danger');
});

test('unavailable component is disabled', function () {
    MealComponent::factory()->unavailable()->create([
        'meal_id' => $this->meal->id,
    ]);

    $data = $this->service->getMealDetailData($this->meal, $this->tenant);

    expect($data['components'][0]['isAvailable'])->toBeFalse()
        ->and($data['components'][0]['availabilityColor'])->toBe('danger');
});

test('all components unavailable sets allUnavailable flag', function () {
    MealComponent::factory()->unavailable()->create(['meal_id' => $this->meal->id]);
    MealComponent::factory()->unavailable()->create(['meal_id' => $this->meal->id]);

    $data = $this->service->getMealDetailData($this->meal, $this->tenant);

    expect($data['meal']['allUnavailable'])->toBeTrue();
});

// --- Free Add-on ---

test('free component shows Free as formatted price', function () {
    MealComponent::factory()->withPrice(0)->create([
        'meal_id' => $this->meal->id,
    ]);

    $data = $this->service->getMealDetailData($this->meal, $this->tenant);

    expect($data['components'][0]['isFree'])->toBeTrue()
        ->and($data['components'][0]['price'])->toBe(0);
});

// --- Quantity Limits ---

test('max selectable respects max_quantity setting', function () {
    MealComponent::factory()->create([
        'meal_id' => $this->meal->id,
        'max_quantity' => 5,
        'available_quantity' => null,
    ]);

    $data = $this->service->getMealDetailData($this->meal, $this->tenant);

    expect($data['components'][0]['maxSelectable'])->toBe(5);
});

test('max selectable respects available_quantity when lower', function () {
    MealComponent::factory()->create([
        'meal_id' => $this->meal->id,
        'max_quantity' => 10,
        'available_quantity' => 3,
    ]);

    $data = $this->service->getMealDetailData($this->meal, $this->tenant);

    expect($data['components'][0]['maxSelectable'])->toBe(3);
});

test('unlimited quantity returns 99 as upper bound', function () {
    MealComponent::factory()->create([
        'meal_id' => $this->meal->id,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    $data = $this->service->getMealDetailData($this->meal, $this->tenant);

    expect($data['components'][0]['maxSelectable'])->toBe(99);
});

test('min quantity defaults to component min_quantity or 1', function () {
    MealComponent::factory()->create([
        'meal_id' => $this->meal->id,
        'min_quantity' => 2,
    ]);

    $data = $this->service->getMealDetailData($this->meal, $this->tenant);

    expect($data['components'][0]['minQuantity'])->toBe(2);
});

// --- Requirement Rules ---

test('component requirement rules are returned in plain language', function () {
    $component = MealComponent::factory()->create([
        'meal_id' => $this->meal->id,
        'name_en' => 'Extra Sauce',
    ]);
    $targetComponent = MealComponent::factory()->create([
        'meal_id' => $this->meal->id,
        'name_en' => 'Main Dish',
    ]);

    ComponentRequirementRule::factory()
        ->requiresAnyOf()
        ->withTargets([$targetComponent->id])
        ->create([
            'meal_component_id' => $component->id,
        ]);

    $data = $this->service->getMealDetailData($this->meal, $this->tenant);

    // Find the Extra Sauce component
    $sauceComponent = collect($data['components'])->firstWhere('name', 'Extra Sauce');

    expect($sauceComponent['requirements'])->toHaveCount(1)
        ->and($sauceComponent['requirements'][0]['type'])->toBe('requires_any_of')
        ->and($sauceComponent['requirements'][0]['components'])->toContain('Main Dish');
});

// --- Schedule ---

test('schedule uses meal-specific schedule when available', function () {
    MealSchedule::factory()->create([
        'meal_id' => $this->meal->id,
        'day_of_week' => 'monday',
        'is_available' => true,
    ]);

    $data = $this->service->getMealDetailData($this->meal, $this->tenant);

    expect($data['schedule']['hasSchedule'])->toBeTrue()
        ->and($data['schedule']['source'])->toBe('meal');
});

test('schedule falls back to cook schedule when no meal schedule', function () {
    CookSchedule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'day_of_week' => 'tuesday',
        'is_available' => true,
    ]);

    $data = $this->service->getMealDetailData($this->meal, $this->tenant);

    expect($data['schedule']['hasSchedule'])->toBeTrue()
        ->and($data['schedule']['source'])->toBe('cook');
});

test('no schedule returns hasSchedule false', function () {
    $data = $this->service->getMealDetailData($this->meal, $this->tenant);

    expect($data['schedule']['hasSchedule'])->toBeFalse()
        ->and($data['schedule']['source'])->toBe('none')
        ->and($data['schedule']['entries'])->toBeEmpty();
});

// --- Locations ---

test('locations include delivery towns with quarters', function () {
    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Bonanjo']);

    $area = DeliveryArea::factory()->create([
        'tenant_id' => $this->tenant->id,
        'town_id' => $town->id,
    ]);

    // Create the delivery area quarter relationship
    $area->deliveryAreaQuarters()->create([
        'quarter_id' => $quarter->id,
        'delivery_fee' => 500,
    ]);

    $data = $this->service->getMealDetailData($this->meal, $this->tenant);

    expect($data['locations']['hasLocations'])->toBeTrue()
        ->and($data['locations']['deliveryTowns'])->toHaveCount(1)
        ->and($data['locations']['deliveryTowns'][0]['name'])->toBe('Douala');
});

test('locations include pickup locations', function () {
    $town = Town::factory()->create(['name_en' => 'Yaounde']);
    PickupLocation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Main Kitchen',
        'town_id' => $town->id,
        'address' => '123 Main St',
    ]);

    $data = $this->service->getMealDetailData($this->meal, $this->tenant);

    expect($data['locations']['hasLocations'])->toBeTrue()
        ->and($data['locations']['pickupLocations'])->toHaveCount(1)
        ->and($data['locations']['pickupLocations'][0]['name'])->toBe('Main Kitchen');
});

test('no locations returns hasLocations false', function () {
    $data = $this->service->getMealDetailData($this->meal, $this->tenant);

    expect($data['locations']['hasLocations'])->toBeFalse()
        ->and($data['locations']['deliveryTowns'])->toBeEmpty()
        ->and($data['locations']['pickupLocations'])->toBeEmpty();
});

// --- Price Formatting ---

test('formatPrice returns correct XAF format', function () {
    expect(TenantLandingService::formatPrice(1500))->toBe('1,500 XAF')
        ->and(TenantLandingService::formatPrice(0))->toBe('0 XAF')
        ->and(TenantLandingService::formatPrice(10000))->toBe('10,000 XAF');
});
