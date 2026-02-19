<?php

/**
 * F-142: Pickup Location Selection — Unit Tests
 *
 * Tests CheckoutService pickup location methods including:
 * - Pickup locations retrieval (BR-284)
 * - Pickup location session persistence (BR-289)
 * - Pickup location validation (BR-286)
 * - Auto-selection for single location (BR-287)
 * - Locale-aware sorting (BR-291)
 * - Tenant isolation
 */

use App\Models\PickupLocation;
use App\Models\Quarter;
use App\Models\Town;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new CheckoutService;
    test()->seedRolesAndPermissions();
});

// -- getPickupLocations tests (BR-284) --

test('getPickupLocations returns all pickup locations for a tenant', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Akwa', 'name_fr' => 'Akwa']);

    PickupLocation::factory()->create([
        'tenant_id' => $tenant->id,
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
        'name_en' => 'Kitchen A',
        'name_fr' => 'Cuisine A',
    ]);
    PickupLocation::factory()->create([
        'tenant_id' => $tenant->id,
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
        'name_en' => 'Market Stand',
        'name_fr' => 'Stand du Marché',
    ]);

    $locations = $this->service->getPickupLocations($tenant->id);

    expect($locations)->toHaveCount(2);
});

test('getPickupLocations returns empty collection when no locations exist', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $locations = $this->service->getPickupLocations($tenant->id);

    expect($locations)->toHaveCount(0);
});

test('getPickupLocations returns locations sorted by locale name', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id]);

    PickupLocation::factory()->create([
        'tenant_id' => $tenant->id,
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
        'name_en' => 'Zenith Location',
        'name_fr' => 'Zénith Lieu',
    ]);
    PickupLocation::factory()->create([
        'tenant_id' => $tenant->id,
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
        'name_en' => 'Alpha Location',
        'name_fr' => 'Alpha Lieu',
    ]);

    app()->setLocale('en');
    $locations = $this->service->getPickupLocations($tenant->id);

    expect($locations->first()->name_en)->toBe('Alpha Location')
        ->and($locations->last()->name_en)->toBe('Zenith Location');
});

test('getPickupLocations eager loads town and quarter relationships', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Akwa', 'name_fr' => 'Akwa']);

    PickupLocation::factory()->create([
        'tenant_id' => $tenant->id,
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
        'name_en' => 'Kitchen',
        'name_fr' => 'Cuisine',
    ]);

    $locations = $this->service->getPickupLocations($tenant->id);

    expect($locations->first()->relationLoaded('town'))->toBeTrue()
        ->and($locations->first()->relationLoaded('quarter'))->toBeTrue()
        ->and($locations->first()->town->name_en)->toBe('Douala')
        ->and($locations->first()->quarter->name_en)->toBe('Akwa');
});

test('getPickupLocations only returns locations for the specific tenant', function () {
    $data1 = test()->createTenantWithCook();
    $data2 = test()->createTenantWithCook();

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id]);

    PickupLocation::factory()->create([
        'tenant_id' => $data1['tenant']->id,
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
        'name_en' => 'Tenant 1 Kitchen',
    ]);
    PickupLocation::factory()->create([
        'tenant_id' => $data2['tenant']->id,
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
        'name_en' => 'Tenant 2 Kitchen',
    ]);

    $locations = $this->service->getPickupLocations($data1['tenant']->id);

    expect($locations)->toHaveCount(1)
        ->and($locations->first()->name_en)->toBe('Tenant 1 Kitchen');
});

// -- setPickupLocation / getPickupLocationId tests (BR-289) --

test('setPickupLocation stores pickup location ID in session', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $this->service->setPickupLocation($tenant->id, 42);

    $storedId = $this->service->getPickupLocationId($tenant->id);

    expect($storedId)->toBe(42);
});

test('getPickupLocationId returns null when no pickup location is stored', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $storedId = $this->service->getPickupLocationId($tenant->id);

    expect($storedId)->toBeNull();
});

test('pickup location persists alongside delivery method in session', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $this->service->setDeliveryMethod($tenant->id, CheckoutService::METHOD_PICKUP);
    $this->service->setPickupLocation($tenant->id, 99);

    expect($this->service->getDeliveryMethod($tenant->id))->toBe('pickup')
        ->and($this->service->getPickupLocationId($tenant->id))->toBe(99);
});

test('clearCheckoutData clears pickup location ID', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $this->service->setPickupLocation($tenant->id, 55);
    $this->service->clearCheckoutData($tenant->id);

    expect($this->service->getPickupLocationId($tenant->id))->toBeNull();
});

test('pickup location is isolated per tenant', function () {
    $data1 = test()->createTenantWithCook();
    $data2 = test()->createTenantWithCook();

    $this->service->setPickupLocation($data1['tenant']->id, 10);
    $this->service->setPickupLocation($data2['tenant']->id, 20);

    expect($this->service->getPickupLocationId($data1['tenant']->id))->toBe(10)
        ->and($this->service->getPickupLocationId($data2['tenant']->id))->toBe(20);
});

// -- validatePickupLocation tests (BR-286) --

test('validatePickupLocation returns valid for existing pickup location', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id]);

    $location = PickupLocation::factory()->create([
        'tenant_id' => $tenant->id,
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
        'name_en' => 'Kitchen',
    ]);

    $result = $this->service->validatePickupLocation($tenant->id, $location->id);

    expect($result['valid'])->toBeTrue()
        ->and($result['error'])->toBeNull()
        ->and($result['pickup_location'])->toBeInstanceOf(PickupLocation::class)
        ->and($result['pickup_location']->id)->toBe($location->id);
});

test('validatePickupLocation returns invalid for non-existent pickup location', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $result = $this->service->validatePickupLocation($tenant->id, 99999);

    expect($result['valid'])->toBeFalse()
        ->and($result['error'])->not->toBeNull()
        ->and($result['pickup_location'])->toBeNull();
});

test('validatePickupLocation returns invalid for another tenants location', function () {
    $data1 = test()->createTenantWithCook();
    $data2 = test()->createTenantWithCook();

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id]);

    $location = PickupLocation::factory()->create([
        'tenant_id' => $data2['tenant']->id,
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
        'name_en' => 'Other Kitchen',
    ]);

    $result = $this->service->validatePickupLocation($data1['tenant']->id, $location->id);

    expect($result['valid'])->toBeFalse()
        ->and($result['error'])->not->toBeNull();
});

// -- getCheckoutData includes pickup_location_id --

test('getCheckoutData includes pickup_location_id key', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $checkoutData = $this->service->getCheckoutData($tenant->id);

    expect($checkoutData)->toHaveKey('pickup_location_id')
        ->and($checkoutData['pickup_location_id'])->toBeNull();
});

test('getCheckoutData returns stored pickup_location_id', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $this->service->setPickupLocation($tenant->id, 77);

    $checkoutData = $this->service->getCheckoutData($tenant->id);

    expect($checkoutData['pickup_location_id'])->toBe(77);
});

// -- French locale sorting (BR-291) --

test('getPickupLocations sorts by French name when locale is fr', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id]);

    PickupLocation::factory()->create([
        'tenant_id' => $tenant->id,
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
        'name_en' => 'Zebra Kitchen',
        'name_fr' => 'Zèbre Cuisine',
    ]);
    PickupLocation::factory()->create([
        'tenant_id' => $tenant->id,
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
        'name_en' => 'Alpha Kitchen',
        'name_fr' => 'Alpha Cuisine',
    ]);

    app()->setLocale('fr');
    $locations = $this->service->getPickupLocations($tenant->id);

    expect($locations->first()->name_fr)->toBe('Alpha Cuisine')
        ->and($locations->last()->name_fr)->toBe('Zèbre Cuisine');
});
