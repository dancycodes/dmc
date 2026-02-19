<?php

/**
 * F-141: Delivery Location Selection — Unit Tests
 *
 * Tests CheckoutService delivery location methods including:
 * - Delivery towns retrieval (BR-274)
 * - Quarter filtering by town (BR-275)
 * - Saved address matching (BR-278, BR-279)
 * - Delivery location session persistence
 * - Quarter validation against delivery areas
 * - Edge cases (single town, single quarter, group fee override)
 */

use App\Models\Address;
use App\Models\DeliveryArea;
use App\Models\DeliveryAreaQuarter;
use App\Models\Quarter;
use App\Models\QuarterGroup;
use App\Models\Town;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new CheckoutService;
    test()->seedRolesAndPermissions();
});

// -- getDeliveryTowns tests (BR-274) --

test('getDeliveryTowns returns towns where cook has delivery areas', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town1 = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $town2 = Town::factory()->create(['name_en' => 'Yaounde', 'name_fr' => 'Yaoundé']);
    Town::factory()->create(['name_en' => 'Bamenda', 'name_fr' => 'Bamenda']); // Not in delivery areas

    DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town1->id]);
    DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town2->id]);

    $towns = $this->service->getDeliveryTowns($tenant->id);

    expect($towns)->toHaveCount(2)
        ->and($towns->pluck('id')->toArray())->toContain($town1->id, $town2->id);
});

test('getDeliveryTowns returns empty when no delivery areas', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $towns = $this->service->getDeliveryTowns($tenant->id);

    expect($towns)->toHaveCount(0);
});

test('getDeliveryTowns returns towns ordered by locale name', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $townB = Town::factory()->create(['name_en' => 'Bamenda', 'name_fr' => 'Bamenda']);
    $townA = Town::factory()->create(['name_en' => 'Akonolinga', 'name_fr' => 'Akonolinga']);

    DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $townB->id]);
    DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $townA->id]);

    app()->setLocale('en');
    $towns = $this->service->getDeliveryTowns($tenant->id);

    expect($towns->first()->name_en)->toBe('Akonolinga')
        ->and($towns->last()->name_en)->toBe('Bamenda');
});

test('getDeliveryTowns only returns towns for the specific tenant', function () {
    $data1 = test()->createTenantWithCook();
    $data2 = test()->createTenantWithCook();

    $town1 = Town::factory()->create(['name_en' => 'Douala']);
    $town2 = Town::factory()->create(['name_en' => 'Yaounde']);

    DeliveryArea::factory()->create(['tenant_id' => $data1['tenant']->id, 'town_id' => $town1->id]);
    DeliveryArea::factory()->create(['tenant_id' => $data2['tenant']->id, 'town_id' => $town2->id]);

    $towns = $this->service->getDeliveryTowns($data1['tenant']->id);

    expect($towns)->toHaveCount(1)
        ->and($towns->first()->id)->toBe($town1->id);
});

// -- getDeliveryQuarters tests (BR-275) --

test('getDeliveryQuarters returns quarters for a town with fees', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter1 = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Akwa', 'name_fr' => 'Akwa']);
    $quarter2 = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Bonanjo', 'name_fr' => 'Bonanjo']);

    $deliveryArea = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create(['delivery_area_id' => $deliveryArea->id, 'quarter_id' => $quarter1->id, 'delivery_fee' => 500]);
    DeliveryAreaQuarter::factory()->create(['delivery_area_id' => $deliveryArea->id, 'quarter_id' => $quarter2->id, 'delivery_fee' => 1000]);

    $quarters = $this->service->getDeliveryQuarters($tenant->id, $town->id);

    expect($quarters)->toHaveCount(2);

    $akwa = $quarters->firstWhere('id', $quarter1->id);
    expect($akwa['name'])->toBe('Akwa')
        ->and($akwa['delivery_fee'])->toBe(500);
});

test('getDeliveryQuarters returns empty for town not in delivery areas', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala']);

    $quarters = $this->service->getDeliveryQuarters($tenant->id, $town->id);

    expect($quarters)->toHaveCount(0);
});

test('getDeliveryQuarters returns quarters sorted by name', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala']);
    $quarterZ = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Zenith']);
    $quarterA = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Akwa']);

    $da = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create(['delivery_area_id' => $da->id, 'quarter_id' => $quarterZ->id, 'delivery_fee' => 500]);
    DeliveryAreaQuarter::factory()->create(['delivery_area_id' => $da->id, 'quarter_id' => $quarterA->id, 'delivery_fee' => 300]);

    app()->setLocale('en');
    $quarters = $this->service->getDeliveryQuarters($tenant->id, $town->id);

    expect($quarters->first()['name'])->toBe('Akwa')
        ->and($quarters->last()['name'])->toBe('Zenith');
});

test('getDeliveryQuarters uses group fee when quarter belongs to a group (F-090)', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Akwa']);

    $da = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create(['delivery_area_id' => $da->id, 'quarter_id' => $quarter->id, 'delivery_fee' => 500]);

    // Create a group with a different fee
    $group = QuarterGroup::factory()->create(['tenant_id' => $tenant->id, 'delivery_fee' => 200]);
    $group->quarters()->attach($quarter->id);

    $quarters = $this->service->getDeliveryQuarters($tenant->id, $town->id);

    expect($quarters->first()['delivery_fee'])->toBe(200); // Group fee overrides
});

// -- getMatchingSavedAddresses tests (BR-278, BR-279) --

test('getMatchingSavedAddresses returns addresses matching delivery areas', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];
    $user = createUser();

    $town = Town::factory()->create(['name_en' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Akwa']);
    $quarterOutside = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Bonaberi']);

    $da = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create(['delivery_area_id' => $da->id, 'quarter_id' => $quarter->id]);

    // Matching address
    $matchingAddress = Address::factory()->create([
        'user_id' => $user->id,
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
        'label' => 'Home',
        'neighbourhood' => 'Rue de la Joie',
    ]);

    // Non-matching address (quarter not in delivery area)
    Address::factory()->create([
        'user_id' => $user->id,
        'town_id' => $town->id,
        'quarter_id' => $quarterOutside->id,
        'label' => 'Work',
    ]);

    $addresses = $this->service->getMatchingSavedAddresses($tenant->id, $user->id);

    expect($addresses)->toHaveCount(1)
        ->and($addresses->first()->id)->toBe($matchingAddress->id);
});

test('getMatchingSavedAddresses returns empty when no matching addresses', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];
    $user = createUser();

    $town = Town::factory()->create(['name_en' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id]);

    // Address in a quarter the cook does NOT deliver to
    Address::factory()->create([
        'user_id' => $user->id,
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
        'label' => 'Home',
    ]);

    $addresses = $this->service->getMatchingSavedAddresses($tenant->id, $user->id);

    expect($addresses)->toHaveCount(0);
});

test('getMatchingSavedAddresses orders by default address first', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];
    $user = createUser();

    $town = Town::factory()->create(['name_en' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id]);

    $da = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create(['delivery_area_id' => $da->id, 'quarter_id' => $quarter->id]);

    Address::factory()->create([
        'user_id' => $user->id,
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
        'label' => 'Work',
        'is_default' => false,
    ]);

    $defaultAddress = Address::factory()->create([
        'user_id' => $user->id,
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
        'label' => 'Home',
        'is_default' => true,
    ]);

    $addresses = $this->service->getMatchingSavedAddresses($tenant->id, $user->id);

    expect($addresses)->toHaveCount(2)
        ->and($addresses->first()->id)->toBe($defaultAddress->id);
});

test('getMatchingSavedAddresses returns empty when no delivery areas configured', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];
    $user = createUser();

    $town = Town::factory()->create(['name_en' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id]);

    Address::factory()->create([
        'user_id' => $user->id,
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
    ]);

    $addresses = $this->service->getMatchingSavedAddresses($tenant->id, $user->id);

    expect($addresses)->toHaveCount(0);
});

// -- setDeliveryLocation / getDeliveryLocation tests --

test('setDeliveryLocation stores location data in session', function () {
    $tenantId = 1;

    $this->service->setDeliveryLocation($tenantId, [
        'town_id' => 10,
        'quarter_id' => 20,
        'neighbourhood' => 'Rue des Manguiers',
    ]);

    $location = $this->service->getDeliveryLocation($tenantId);

    expect($location)->not->toBeNull()
        ->and($location['town_id'])->toBe(10)
        ->and($location['quarter_id'])->toBe(20)
        ->and($location['neighbourhood'])->toBe('Rue des Manguiers');
});

test('getDeliveryLocation returns null when not set', function () {
    expect($this->service->getDeliveryLocation(999))->toBeNull();
});

test('setDeliveryLocation trims neighbourhood whitespace', function () {
    $tenantId = 1;

    $this->service->setDeliveryLocation($tenantId, [
        'town_id' => 10,
        'quarter_id' => 20,
        'neighbourhood' => '  Rue des Manguiers  ',
    ]);

    $location = $this->service->getDeliveryLocation($tenantId);

    expect($location['neighbourhood'])->toBe('Rue des Manguiers');
});

test('setDeliveryLocation overwrites previous location', function () {
    $tenantId = 1;

    $this->service->setDeliveryLocation($tenantId, [
        'town_id' => 10,
        'quarter_id' => 20,
        'neighbourhood' => 'Old Address',
    ]);

    $this->service->setDeliveryLocation($tenantId, [
        'town_id' => 30,
        'quarter_id' => 40,
        'neighbourhood' => 'New Address',
    ]);

    $location = $this->service->getDeliveryLocation($tenantId);

    expect($location['town_id'])->toBe(30)
        ->and($location['quarter_id'])->toBe(40)
        ->and($location['neighbourhood'])->toBe('New Address');
});

test('delivery location persists alongside delivery method', function () {
    $tenantId = 1;

    $this->service->setDeliveryMethod($tenantId, CheckoutService::METHOD_DELIVERY);
    $this->service->setDeliveryLocation($tenantId, [
        'town_id' => 10,
        'quarter_id' => 20,
        'neighbourhood' => 'Akwa Nord',
    ]);

    expect($this->service->getDeliveryMethod($tenantId))->toBe('delivery')
        ->and($this->service->getDeliveryLocation($tenantId)['town_id'])->toBe(10);
});

test('clearCheckoutData removes delivery location too', function () {
    $tenantId = 1;

    $this->service->setDeliveryMethod($tenantId, CheckoutService::METHOD_DELIVERY);
    $this->service->setDeliveryLocation($tenantId, [
        'town_id' => 10,
        'quarter_id' => 20,
        'neighbourhood' => 'Test',
    ]);

    $this->service->clearCheckoutData($tenantId);

    expect($this->service->getDeliveryMethod($tenantId))->toBeNull()
        ->and($this->service->getDeliveryLocation($tenantId))->toBeNull();
});

// -- validateDeliveryQuarter tests --

test('validateDeliveryQuarter returns valid for quarter in delivery area', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Akwa']);

    $da = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create(['delivery_area_id' => $da->id, 'quarter_id' => $quarter->id, 'delivery_fee' => 500]);

    $result = $this->service->validateDeliveryQuarter($tenant->id, $quarter->id);

    expect($result['valid'])->toBeTrue()
        ->and($result['error'])->toBeNull()
        ->and($result['delivery_fee'])->toBe(500);
});

test('validateDeliveryQuarter returns invalid for quarter not in delivery area', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Bonaberi']);

    $result = $this->service->validateDeliveryQuarter($tenant->id, $quarter->id);

    expect($result['valid'])->toBeFalse()
        ->and($result['error'])->not->toBeNull()
        ->and($result['delivery_fee'])->toBeNull();
});

test('validateDeliveryQuarter returns group fee when quarter is in a group', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Akwa']);

    $da = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create(['delivery_area_id' => $da->id, 'quarter_id' => $quarter->id, 'delivery_fee' => 500]);

    $group = QuarterGroup::factory()->create(['tenant_id' => $tenant->id, 'delivery_fee' => 200]);
    $group->quarters()->attach($quarter->id);

    $result = $this->service->validateDeliveryQuarter($tenant->id, $quarter->id);

    expect($result['valid'])->toBeTrue()
        ->and($result['delivery_fee'])->toBe(200); // Group fee overrides
});

test('validateDeliveryQuarter returns zero fee for free delivery quarter', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id]);

    $da = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create(['delivery_area_id' => $da->id, 'quarter_id' => $quarter->id, 'delivery_fee' => 0]);

    $result = $this->service->validateDeliveryQuarter($tenant->id, $quarter->id);

    expect($result['valid'])->toBeTrue()
        ->and($result['delivery_fee'])->toBe(0);
});

// -- Tenant isolation tests --

test('delivery location data is isolated per tenant', function () {
    $this->service->setDeliveryLocation(1, [
        'town_id' => 10,
        'quarter_id' => 20,
        'neighbourhood' => 'Tenant 1 Address',
    ]);

    $this->service->setDeliveryLocation(2, [
        'town_id' => 30,
        'quarter_id' => 40,
        'neighbourhood' => 'Tenant 2 Address',
    ]);

    expect($this->service->getDeliveryLocation(1)['town_id'])->toBe(10)
        ->and($this->service->getDeliveryLocation(2)['town_id'])->toBe(30);
});

// -- getCheckoutData default shape test --

test('getCheckoutData includes delivery_location key', function () {
    $data = $this->service->getCheckoutData(999);

    expect($data)->toHaveKey('delivery_method')
        ->and($data)->toHaveKey('delivery_location')
        ->and($data['delivery_location'])->toBeNull();
});
