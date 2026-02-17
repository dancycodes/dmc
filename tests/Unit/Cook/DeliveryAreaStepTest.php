<?php

use App\Models\DeliveryArea;
use App\Models\DeliveryAreaQuarter;
use App\Models\PickupLocation;
use App\Models\Quarter;
use App\Models\Tenant;
use App\Models\Town;
use App\Services\DeliveryAreaService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new DeliveryAreaService;
});

// ===== DeliveryArea Model Tests =====

test('delivery area belongs to tenant', function () {
    $tenant = Tenant::factory()->create();
    $town = Town::factory()->create();
    $area = DeliveryArea::factory()->create([
        'tenant_id' => $tenant->id,
        'town_id' => $town->id,
    ]);

    expect($area->tenant->id)->toBe($tenant->id);
});

test('delivery area belongs to town', function () {
    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $area = DeliveryArea::factory()->create(['town_id' => $town->id]);

    expect($area->town->name_en)->toBe('Douala');
});

test('delivery area has many delivery area quarters', function () {
    $area = DeliveryArea::factory()->create();
    $quarter = Quarter::factory()->create(['town_id' => $area->town_id]);
    DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $area->id,
        'quarter_id' => $quarter->id,
        'delivery_fee' => 500,
    ]);

    expect($area->deliveryAreaQuarters)->toHaveCount(1);
    expect($area->deliveryAreaQuarters->first()->delivery_fee)->toBe(500);
});

// ===== DeliveryAreaQuarter Model Tests =====

test('delivery area quarter belongs to delivery area', function () {
    $area = DeliveryArea::factory()->create();
    $quarter = Quarter::factory()->create(['town_id' => $area->town_id]);
    $daq = DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $area->id,
        'quarter_id' => $quarter->id,
    ]);

    expect($daq->deliveryArea->id)->toBe($area->id);
});

test('delivery area quarter casts delivery fee to integer', function () {
    $area = DeliveryArea::factory()->create();
    $quarter = Quarter::factory()->create(['town_id' => $area->town_id]);
    $daq = DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $area->id,
        'quarter_id' => $quarter->id,
        'delivery_fee' => 300,
    ]);

    expect($daq->delivery_fee)->toBeInt();
    expect($daq->delivery_fee)->toBe(300);
});

// ===== PickupLocation Model Tests =====

test('pickup location belongs to tenant', function () {
    $tenant = Tenant::factory()->create();
    $town = Town::factory()->create();
    $quarter = Quarter::factory()->create(['town_id' => $town->id]);
    $pickup = PickupLocation::factory()->create([
        'tenant_id' => $tenant->id,
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
    ]);

    expect($pickup->tenant->id)->toBe($tenant->id);
});

test('pickup location uses translatable trait', function () {
    $pickup = PickupLocation::factory()->create([
        'name_en' => 'My Kitchen',
        'name_fr' => 'Ma Cuisine',
    ]);

    app()->setLocale('en');
    expect($pickup->name)->toBe('My Kitchen');

    app()->setLocale('fr');
    expect($pickup->name)->toBe('Ma Cuisine');
});

// ===== DeliveryAreaService Tests =====

test('service adds town to tenant delivery areas', function () {
    $tenant = Tenant::factory()->create();

    $result = $this->service->addTown($tenant, 'Douala', 'Douala');

    expect($result['success'])->toBeTrue();
    expect($result['delivery_area'])->toBeInstanceOf(DeliveryArea::class);
    expect(DeliveryArea::where('tenant_id', $tenant->id)->count())->toBe(1);
});

test('service prevents duplicate town name for same tenant', function () {
    $tenant = Tenant::factory()->create();

    $this->service->addTown($tenant, 'Douala', 'Douala');
    $result = $this->service->addTown($tenant, 'Douala', 'Douala');

    expect($result['success'])->toBeFalse();
    expect($result['error'])->not->toBeEmpty();
});

test('service allows same town name for different tenants', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    $result1 = $this->service->addTown($tenant1, 'Douala', 'Douala');
    $result2 = $this->service->addTown($tenant2, 'Douala', 'Douala');

    expect($result1['success'])->toBeTrue();
    expect($result2['success'])->toBeTrue();
});

test('service case insensitive duplicate town check', function () {
    $tenant = Tenant::factory()->create();

    $this->service->addTown($tenant, 'Douala', 'Douala');
    $result = $this->service->addTown($tenant, 'douala', 'douala');

    expect($result['success'])->toBeFalse();
});

test('service adds quarter to delivery area with fee', function () {
    $tenant = Tenant::factory()->create();
    $townResult = $this->service->addTown($tenant, 'Douala', 'Douala');

    $result = $this->service->addQuarter(
        $tenant,
        $townResult['delivery_area']->id,
        'Bonaberi',
        'Bonaberi',
        500,
    );

    expect($result['success'])->toBeTrue();
    expect($result['quarter_data']['delivery_fee'])->toBe(500);
});

test('service allows zero delivery fee for free delivery', function () {
    $tenant = Tenant::factory()->create();
    $townResult = $this->service->addTown($tenant, 'Douala', 'Douala');

    $result = $this->service->addQuarter(
        $tenant,
        $townResult['delivery_area']->id,
        'Akwa',
        'Akwa',
        0,
    );

    expect($result['success'])->toBeTrue();
    expect($result['quarter_data']['delivery_fee'])->toBe(0);
});

test('service warns on high delivery fee', function () {
    $tenant = Tenant::factory()->create();
    $townResult = $this->service->addTown($tenant, 'Douala', 'Douala');

    $result = $this->service->addQuarter(
        $tenant,
        $townResult['delivery_area']->id,
        'Bonaberi',
        'Bonaberi',
        15000,
    );

    expect($result['success'])->toBeTrue();
    expect($result['warning'])->not->toBeEmpty();
});

test('service prevents duplicate quarter name within same town', function () {
    $tenant = Tenant::factory()->create();
    $townResult = $this->service->addTown($tenant, 'Douala', 'Douala');
    $areaId = $townResult['delivery_area']->id;

    $this->service->addQuarter($tenant, $areaId, 'Bonaberi', 'Bonaberi', 500);
    $result = $this->service->addQuarter($tenant, $areaId, 'Bonaberi', 'Bonaberi', 300);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->not->toBeEmpty();
});

test('service removes town cascading quarters', function () {
    $tenant = Tenant::factory()->create();
    $townResult = $this->service->addTown($tenant, 'Douala', 'Douala');
    $areaId = $townResult['delivery_area']->id;

    $this->service->addQuarter($tenant, $areaId, 'Bonaberi', 'Bonaberi', 500);

    expect(DeliveryAreaQuarter::where('delivery_area_id', $areaId)->count())->toBe(1);

    $this->service->removeTown($tenant, $areaId);

    expect(DeliveryArea::where('id', $areaId)->count())->toBe(0);
    expect(DeliveryAreaQuarter::where('delivery_area_id', $areaId)->count())->toBe(0);
});

test('service removes individual quarter', function () {
    $tenant = Tenant::factory()->create();
    $townResult = $this->service->addTown($tenant, 'Douala', 'Douala');
    $areaId = $townResult['delivery_area']->id;

    $q1 = $this->service->addQuarter($tenant, $areaId, 'Bonaberi', 'Bonaberi', 500);
    $q2 = $this->service->addQuarter($tenant, $areaId, 'Akwa', 'Akwa', 300);

    $this->service->removeQuarter($tenant, $q1['quarter_data']['id']);

    expect(DeliveryAreaQuarter::where('delivery_area_id', $areaId)->count())->toBe(1);
});

test('service checks minimum setup correctly', function () {
    $tenant = Tenant::factory()->create();

    expect($this->service->hasMinimumSetup($tenant))->toBeFalse();

    $townResult = $this->service->addTown($tenant, 'Douala', 'Douala');
    expect($this->service->hasMinimumSetup($tenant))->toBeFalse();

    $this->service->addQuarter(
        $tenant,
        $townResult['delivery_area']->id,
        'Bonaberi',
        'Bonaberi',
        500,
    );
    expect($this->service->hasMinimumSetup($tenant))->toBeTrue();
});

test('service adds pickup location', function () {
    $tenant = Tenant::factory()->create();
    $townResult = $this->service->addTown($tenant, 'Douala', 'Douala');
    $this->service->addQuarter(
        $tenant,
        $townResult['delivery_area']->id,
        'Akwa',
        'Akwa',
        0,
    );

    // Get quarter ID from DB
    $quarter = Quarter::where('name_en', 'Akwa')->first();

    $result = $this->service->addPickupLocation(
        $tenant,
        'My Kitchen',
        'Ma Cuisine',
        $townResult['delivery_area']->town_id,
        $quarter->id,
        'Behind Akwa Palace Hotel',
    );

    expect($result['success'])->toBeTrue();
    expect($result['pickup']['name_en'])->toBe('My Kitchen');
    expect($result['pickup']['address'])->toBe('Behind Akwa Palace Hotel');
});

test('service rejects pickup location for non-delivery town', function () {
    $tenant = Tenant::factory()->create();
    $town = Town::factory()->create();
    $quarter = Quarter::factory()->create(['town_id' => $town->id]);

    $result = $this->service->addPickupLocation(
        $tenant,
        'Test',
        'Test',
        $town->id,
        $quarter->id,
        'Some address',
    );

    expect($result['success'])->toBeFalse();
    expect($result['error'])->not->toBeEmpty();
});

test('service removes pickup location', function () {
    $tenant = Tenant::factory()->create();
    $townResult = $this->service->addTown($tenant, 'Douala', 'Douala');
    $this->service->addQuarter(
        $tenant,
        $townResult['delivery_area']->id,
        'Akwa',
        'Akwa',
        0,
    );

    $quarter = Quarter::where('name_en', 'Akwa')->first();
    $pickupResult = $this->service->addPickupLocation(
        $tenant,
        'My Kitchen',
        'Ma Cuisine',
        $townResult['delivery_area']->town_id,
        $quarter->id,
        'Behind Hotel',
    );

    $success = $this->service->removePickupLocation($tenant, $pickupResult['pickup']['id']);
    expect($success)->toBeTrue();
    expect(PickupLocation::where('tenant_id', $tenant->id)->count())->toBe(0);
});

test('service returns delivery areas data correctly', function () {
    $tenant = Tenant::factory()->create();
    $townResult = $this->service->addTown($tenant, 'Douala', 'Douala');
    $this->service->addQuarter(
        $tenant,
        $townResult['delivery_area']->id,
        'Bonaberi',
        'Bonaberi',
        500,
    );
    $this->service->addQuarter(
        $tenant,
        $townResult['delivery_area']->id,
        'Akwa',
        'Akwa',
        0,
    );

    $data = $this->service->getDeliveryAreasData($tenant);

    expect($data)->toHaveCount(1);
    expect($data[0]['quarters'])->toHaveCount(2);
    expect($data[0]['town_name_en'])->toBe('Douala');
});

test('service returns pickup locations data correctly', function () {
    $tenant = Tenant::factory()->create();
    $townResult = $this->service->addTown($tenant, 'Douala', 'Douala');
    $this->service->addQuarter(
        $tenant,
        $townResult['delivery_area']->id,
        'Akwa',
        'Akwa',
        0,
    );

    $quarter = Quarter::where('name_en', 'Akwa')->first();
    $this->service->addPickupLocation(
        $tenant,
        'My Kitchen',
        'Ma Cuisine',
        $townResult['delivery_area']->town_id,
        $quarter->id,
        'Near Market',
    );

    $data = $this->service->getPickupLocationsData($tenant);

    expect($data)->toHaveCount(1);
    expect($data[0]['name_en'])->toBe('My Kitchen');
    expect($data[0]['address'])->toBe('Near Market');
});

// ===== Tenant Model Relationship Tests =====

test('tenant has many delivery areas', function () {
    $tenant = Tenant::factory()->create();
    $town = Town::factory()->create();
    DeliveryArea::factory()->create([
        'tenant_id' => $tenant->id,
        'town_id' => $town->id,
    ]);

    expect($tenant->deliveryAreas)->toHaveCount(1);
});

test('tenant has many pickup locations', function () {
    $tenant = Tenant::factory()->create();
    $town = Town::factory()->create();
    $quarter = Quarter::factory()->create(['town_id' => $town->id]);
    PickupLocation::factory()->create([
        'tenant_id' => $tenant->id,
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
    ]);

    expect($tenant->pickupLocations)->toHaveCount(1);
});

// ===== Town Model Relationship Tests =====

test('town has many delivery areas', function () {
    $town = Town::factory()->create();
    DeliveryArea::factory()->create(['town_id' => $town->id]);

    expect($town->deliveryAreas)->toHaveCount(1);
});
