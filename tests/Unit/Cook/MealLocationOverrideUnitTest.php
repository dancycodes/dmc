<?php

use App\Models\DeliveryArea;
use App\Models\DeliveryAreaQuarter;
use App\Models\Meal;
use App\Models\MealLocationOverride;
use App\Models\PickupLocation;
use App\Models\Quarter;
use App\Models\QuarterGroup;
use App\Models\Town;
use App\Services\MealLocationOverrideService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| F-096: Meal-Specific Location Override — Unit Tests
|--------------------------------------------------------------------------
|
| Tests for MealLocationOverride model, Meal model updates,
| and MealLocationOverrideService. Verifies BR-306 through BR-314.
|
*/

beforeEach(function () {
    $result = $this->createTenantWithCook();
    $this->tenant = $result['tenant'];
    $this->cook = $result['cook'];

    // Create a meal for this tenant
    $this->meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'has_custom_locations' => false,
    ]);

    // Create delivery areas with quarters
    $this->town = Town::create(['name_en' => 'Douala', 'name_fr' => 'Douala', 'is_active' => true]);
    $this->deliveryArea = DeliveryArea::create(['tenant_id' => $this->tenant->id, 'town_id' => $this->town->id]);

    $this->quarter1 = Quarter::create(['town_id' => $this->town->id, 'name_en' => 'Akwa', 'name_fr' => 'Akwa', 'is_active' => true]);
    $this->quarter2 = Quarter::create(['town_id' => $this->town->id, 'name_en' => 'Bonanjo', 'name_fr' => 'Bonanjo', 'is_active' => true]);

    $this->daq1 = DeliveryAreaQuarter::create(['delivery_area_id' => $this->deliveryArea->id, 'quarter_id' => $this->quarter1->id, 'delivery_fee' => 500]);
    $this->daq2 = DeliveryAreaQuarter::create(['delivery_area_id' => $this->deliveryArea->id, 'quarter_id' => $this->quarter2->id, 'delivery_fee' => 700]);

    // Create a pickup location
    $this->pickup = PickupLocation::create([
        'tenant_id' => $this->tenant->id,
        'town_id' => $this->town->id,
        'quarter_id' => $this->quarter1->id,
        'name_en' => 'Central Market',
        'name_fr' => 'Marche Central',
        'address' => '123 Main Street',
    ]);

    $this->service = new MealLocationOverrideService;
});

// === Model Tests ===

test('MealLocationOverride model has correct fillable attributes', function () {
    $override = new MealLocationOverride;
    expect($override->getFillable())->toBe([
        'meal_id',
        'quarter_id',
        'pickup_location_id',
        'custom_delivery_fee',
    ]);
});

test('MealLocationOverride casts custom_delivery_fee to integer', function () {
    $override = MealLocationOverride::factory()
        ->forQuarter($this->quarter1)
        ->withCustomFee(1000)
        ->create(['meal_id' => $this->meal->id]);

    expect($override->custom_delivery_fee)->toBeInt();
});

test('MealLocationOverride belongs to meal', function () {
    $override = MealLocationOverride::factory()
        ->forQuarter($this->quarter1)
        ->create(['meal_id' => $this->meal->id]);

    expect($override->meal)->toBeInstanceOf(Meal::class)
        ->and($override->meal->id)->toBe($this->meal->id);
});

test('MealLocationOverride belongs to quarter', function () {
    $override = MealLocationOverride::factory()
        ->forQuarter($this->quarter1)
        ->create(['meal_id' => $this->meal->id]);

    expect($override->quarter)->toBeInstanceOf(Quarter::class)
        ->and($override->quarter->id)->toBe($this->quarter1->id);
});

test('MealLocationOverride belongs to pickup location', function () {
    $override = MealLocationOverride::factory()
        ->forPickup($this->pickup)
        ->create(['meal_id' => $this->meal->id]);

    expect($override->pickupLocation)->toBeInstanceOf(PickupLocation::class)
        ->and($override->pickupLocation->id)->toBe($this->pickup->id);
});

test('isDeliveryOverride returns true for quarter overrides', function () {
    $override = MealLocationOverride::factory()
        ->forQuarter($this->quarter1)
        ->create(['meal_id' => $this->meal->id]);

    expect($override->isDeliveryOverride())->toBeTrue()
        ->and($override->isPickupOverride())->toBeFalse();
});

test('isPickupOverride returns true for pickup overrides', function () {
    $override = MealLocationOverride::factory()
        ->forPickup($this->pickup)
        ->create(['meal_id' => $this->meal->id]);

    expect($override->isPickupOverride())->toBeTrue()
        ->and($override->isDeliveryOverride())->toBeFalse();
});

// === Meal Model Tests ===

test('Meal has has_custom_locations attribute with boolean cast', function () {
    expect($this->meal->has_custom_locations)->toBeFalse()
        ->and($this->meal->has_custom_locations)->toBeBool();
});

test('Meal has locationOverrides relationship', function () {
    MealLocationOverride::factory()
        ->forQuarter($this->quarter1)
        ->create(['meal_id' => $this->meal->id]);

    expect($this->meal->locationOverrides)->toHaveCount(1);
});

test('Meal location overrides cascade delete on meal deletion', function () {
    MealLocationOverride::factory()
        ->forQuarter($this->quarter1)
        ->create(['meal_id' => $this->meal->id]);
    MealLocationOverride::factory()
        ->forPickup($this->pickup)
        ->create(['meal_id' => $this->meal->id]);

    expect(MealLocationOverride::where('meal_id', $this->meal->id)->count())->toBe(2);

    $this->meal->forceDelete();

    expect(MealLocationOverride::where('meal_id', $this->meal->id)->count())->toBe(0);
});

// === Service: getLocationOverrideData ===

test('getLocationOverrideData returns delivery areas with quarters', function () {
    $data = $this->service->getLocationOverrideData($this->tenant, $this->meal);

    expect($data)->toHaveKeys(['delivery_areas', 'pickup_locations', 'current_overrides'])
        ->and($data['delivery_areas'])->toHaveCount(1)
        ->and($data['delivery_areas'][0]['town_name'])->toBe('Douala')
        ->and($data['delivery_areas'][0]['quarters'])->toHaveCount(2);
});

test('getLocationOverrideData returns pickup locations', function () {
    $data = $this->service->getLocationOverrideData($this->tenant, $this->meal);

    expect($data['pickup_locations'])->toHaveCount(1)
        ->and($data['pickup_locations'][0]['name'])->toBe('Central Market');
});

test('getLocationOverrideData returns current overrides when custom locations enabled', function () {
    $this->meal->update(['has_custom_locations' => true]);
    MealLocationOverride::create([
        'meal_id' => $this->meal->id,
        'quarter_id' => $this->quarter1->id,
        'custom_delivery_fee' => 1000,
    ]);
    MealLocationOverride::create([
        'meal_id' => $this->meal->id,
        'pickup_location_id' => $this->pickup->id,
    ]);

    $data = $this->service->getLocationOverrideData($this->tenant, $this->meal);

    expect($data['current_overrides']['selected_quarters'])->toHaveCount(1)
        ->and($data['current_overrides']['selected_quarters'][0]['quarter_id'])->toBe($this->quarter1->id)
        ->and($data['current_overrides']['selected_quarters'][0]['custom_fee'])->toBe(1000)
        ->and($data['current_overrides']['selected_pickups'])->toHaveCount(1)
        ->and($data['current_overrides']['selected_pickups'][0])->toBe($this->pickup->id);
});

test('getLocationOverrideData includes effective fee from quarter group', function () {
    $group = QuarterGroup::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Zone A',
        'delivery_fee' => 300,
    ]);
    $group->quarters()->attach($this->quarter1->id);

    $data = $this->service->getLocationOverrideData($this->tenant, $this->meal);

    $q1 = collect($data['delivery_areas'][0]['quarters'])->firstWhere('quarter_id', $this->quarter1->id);
    expect($q1['default_fee'])->toBe(300)
        ->and($q1['group_name'])->toBe('Zone A');
});

// === Service: saveOverrides ===

test('BR-307: saveOverrides enables custom locations on meal', function () {
    $result = $this->service->saveOverrides($this->tenant, $this->meal, [
        ['quarter_id' => $this->quarter1->id, 'custom_fee' => null],
    ], []);

    expect($result['success'])->toBeTrue()
        ->and($this->meal->fresh()->has_custom_locations)->toBeTrue();
});

test('BR-308: saveOverrides creates quarter overrides', function () {
    $this->service->saveOverrides($this->tenant, $this->meal, [
        ['quarter_id' => $this->quarter1->id, 'custom_fee' => null],
        ['quarter_id' => $this->quarter2->id, 'custom_fee' => 1500],
    ], []);

    $overrides = MealLocationOverride::where('meal_id', $this->meal->id)->whereNotNull('quarter_id')->get();
    expect($overrides)->toHaveCount(2);

    $q1Override = $overrides->firstWhere('quarter_id', $this->quarter1->id);
    expect($q1Override->custom_delivery_fee)->toBeNull();

    $q2Override = $overrides->firstWhere('quarter_id', $this->quarter2->id);
    expect($q2Override->custom_delivery_fee)->toBe(1500);
});

test('BR-308: saveOverrides creates pickup overrides', function () {
    $this->service->saveOverrides($this->tenant, $this->meal, [], [$this->pickup->id]);

    $overrides = MealLocationOverride::where('meal_id', $this->meal->id)->whereNotNull('pickup_location_id')->get();
    expect($overrides)->toHaveCount(1)
        ->and($overrides->first()->pickup_location_id)->toBe($this->pickup->id);
});

test('BR-309: saveOverrides stores custom delivery fee', function () {
    $this->service->saveOverrides($this->tenant, $this->meal, [
        ['quarter_id' => $this->quarter1->id, 'custom_fee' => 2000],
    ], []);

    $override = MealLocationOverride::where('meal_id', $this->meal->id)->first();
    expect($override->custom_delivery_fee)->toBe(2000);
});

test('BR-309: custom fee of 0 is allowed (free delivery)', function () {
    $this->service->saveOverrides($this->tenant, $this->meal, [
        ['quarter_id' => $this->quarter1->id, 'custom_fee' => 0],
    ], []);

    $override = MealLocationOverride::where('meal_id', $this->meal->id)->first();
    expect($override->custom_delivery_fee)->toBe(0);
});

test('BR-310: saveOverrides fails when no locations selected', function () {
    $result = $this->service->saveOverrides($this->tenant, $this->meal, [], []);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('At least one');
});

test('BR-314: saveOverrides rejects quarters not belonging to tenant', function () {
    $otherTown = Town::create(['name_en' => 'Yaounde', 'name_fr' => 'Yaounde', 'is_active' => true]);
    $otherQuarter = Quarter::create(['town_id' => $otherTown->id, 'name_en' => 'Bastos', 'name_fr' => 'Bastos', 'is_active' => true]);

    $result = $this->service->saveOverrides($this->tenant, $this->meal, [
        ['quarter_id' => $otherQuarter->id, 'custom_fee' => null],
    ], []);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('do not belong');
});

test('BR-314: saveOverrides rejects pickup locations not belonging to tenant', function () {
    $otherResult = $this->createTenantWithCook();
    $otherTenant = $otherResult['tenant'];
    $otherPickup = PickupLocation::create([
        'tenant_id' => $otherTenant->id,
        'town_id' => $this->town->id,
        'quarter_id' => $this->quarter1->id,
        'name_en' => 'Other Place',
        'name_fr' => 'Autre Endroit',
        'address' => '456 Other St',
    ]);

    $result = $this->service->saveOverrides($this->tenant, $this->meal, [], [$otherPickup->id]);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('do not belong');
});

test('saveOverrides replaces existing overrides', function () {
    // First save: both quarters
    $this->service->saveOverrides($this->tenant, $this->meal, [
        ['quarter_id' => $this->quarter1->id, 'custom_fee' => null],
        ['quarter_id' => $this->quarter2->id, 'custom_fee' => null],
    ], []);

    expect(MealLocationOverride::where('meal_id', $this->meal->id)->count())->toBe(2);

    // Second save: only one quarter
    $this->service->saveOverrides($this->tenant, $this->meal, [
        ['quarter_id' => $this->quarter1->id, 'custom_fee' => 800],
    ], []);

    $overrides = MealLocationOverride::where('meal_id', $this->meal->id)->get();
    expect($overrides)->toHaveCount(1)
        ->and($overrides->first()->quarter_id)->toBe($this->quarter1->id)
        ->and($overrides->first()->custom_delivery_fee)->toBe(800);
});

// === Service: removeOverrides ===

test('BR-311: removeOverrides deletes all overrides and resets flag', function () {
    $this->meal->update(['has_custom_locations' => true]);
    MealLocationOverride::create(['meal_id' => $this->meal->id, 'quarter_id' => $this->quarter1->id]);
    MealLocationOverride::create(['meal_id' => $this->meal->id, 'pickup_location_id' => $this->pickup->id]);

    $result = $this->service->removeOverrides($this->meal);

    expect($result['success'])->toBeTrue()
        ->and($this->meal->fresh()->has_custom_locations)->toBeFalse()
        ->and(MealLocationOverride::where('meal_id', $this->meal->id)->count())->toBe(0);
});

// === Service: getOverrideSummary ===

test('getOverrideSummary returns false for meals without custom locations', function () {
    $summary = $this->service->getOverrideSummary($this->meal);

    expect($summary['has_custom'])->toBeFalse()
        ->and($summary['quarter_count'])->toBe(0)
        ->and($summary['pickup_count'])->toBe(0);
});

test('getOverrideSummary returns counts for meals with custom locations', function () {
    $this->meal->update(['has_custom_locations' => true]);
    MealLocationOverride::create(['meal_id' => $this->meal->id, 'quarter_id' => $this->quarter1->id]);
    MealLocationOverride::create(['meal_id' => $this->meal->id, 'quarter_id' => $this->quarter2->id, 'custom_delivery_fee' => 1000]);
    MealLocationOverride::create(['meal_id' => $this->meal->id, 'pickup_location_id' => $this->pickup->id]);

    $summary = $this->service->getOverrideSummary($this->meal);

    expect($summary['has_custom'])->toBeTrue()
        ->and($summary['quarter_count'])->toBe(2)
        ->and($summary['pickup_count'])->toBe(1);
});

// === Edge Cases ===

test('quarter deletion cascades to meal location overrides', function () {
    MealLocationOverride::create([
        'meal_id' => $this->meal->id,
        'quarter_id' => $this->quarter1->id,
        'custom_delivery_fee' => 500,
    ]);

    expect(MealLocationOverride::where('meal_id', $this->meal->id)->count())->toBe(1);

    $this->quarter1->delete();

    expect(MealLocationOverride::where('meal_id', $this->meal->id)->count())->toBe(0);
});

test('pickup location deletion cascades to meal location overrides', function () {
    MealLocationOverride::create([
        'meal_id' => $this->meal->id,
        'pickup_location_id' => $this->pickup->id,
    ]);

    expect(MealLocationOverride::where('meal_id', $this->meal->id)->count())->toBe(1);

    $this->pickup->delete();

    expect(MealLocationOverride::where('meal_id', $this->meal->id)->count())->toBe(0);
});

test('custom fee higher than default is allowed', function () {
    $result = $this->service->saveOverrides($this->tenant, $this->meal, [
        ['quarter_id' => $this->quarter1->id, 'custom_fee' => 50000],
    ], []);

    expect($result['success'])->toBeTrue();
    $override = MealLocationOverride::where('meal_id', $this->meal->id)->first();
    expect($override->custom_delivery_fee)->toBe(50000);
});

test('mixed quarter and pickup overrides work together', function () {
    $result = $this->service->saveOverrides($this->tenant, $this->meal, [
        ['quarter_id' => $this->quarter1->id, 'custom_fee' => 800],
    ], [$this->pickup->id]);

    expect($result['success'])->toBeTrue();

    $overrides = MealLocationOverride::where('meal_id', $this->meal->id)->get();
    expect($overrides)->toHaveCount(2);

    $quarterOverride = $overrides->whereNotNull('quarter_id')->first();
    $pickupOverride = $overrides->whereNotNull('pickup_location_id')->first();

    expect($quarterOverride->quarter_id)->toBe($this->quarter1->id)
        ->and($quarterOverride->custom_delivery_fee)->toBe(800)
        ->and($pickupOverride->pickup_location_id)->toBe($this->pickup->id);
});

test('pickup only meal (no delivery quarters) is valid', function () {
    $result = $this->service->saveOverrides($this->tenant, $this->meal, [], [$this->pickup->id]);

    expect($result['success'])->toBeTrue();

    $overrides = MealLocationOverride::where('meal_id', $this->meal->id)->get();
    expect($overrides)->toHaveCount(1)
        ->and($overrides->first()->pickup_location_id)->toBe($this->pickup->id);
});

test('delivery only meal (no pickup) is valid', function () {
    $result = $this->service->saveOverrides($this->tenant, $this->meal, [
        ['quarter_id' => $this->quarter1->id, 'custom_fee' => null],
    ], []);

    expect($result['success'])->toBeTrue();

    $overrides = MealLocationOverride::where('meal_id', $this->meal->id)->get();
    expect($overrides)->toHaveCount(1)
        ->and($overrides->first()->quarter_id)->toBe($this->quarter1->id);
});
