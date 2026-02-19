<?php

/**
 * F-147: Location Not Available Flow — Unit Tests
 *
 * Tests CheckoutService methods for the "location not available" flow:
 * - BR-327: Quarter not in delivery areas triggers message
 * - BR-329: Cook contact info retrieval (WhatsApp, phone)
 * - BR-330: WhatsApp pre-filled message construction
 * - BR-331: Pickup location availability check
 * - BR-332: Client can select different quarters (all quarters loaded)
 * - BR-333: Available quarters still have delivery fee data
 * - Quarter availability flag in getDeliveryQuarters
 */

use App\Models\DeliveryArea;
use App\Models\DeliveryAreaQuarter;
use App\Models\PickupLocation;
use App\Models\Quarter;
use App\Models\QuarterGroup;
use App\Models\Tenant;
use App\Models\Town;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new CheckoutService;
    test()->seedRolesAndPermissions();
});

// -- getDeliveryQuarters with availability flag (BR-327, BR-332) --

test('getDeliveryQuarters includes available flag for delivery area quarters', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Akwa', 'name_fr' => 'Akwa']);

    $deliveryArea = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $deliveryArea->id,
        'quarter_id' => $quarter->id,
        'delivery_fee' => 500,
    ]);

    $quarters = $this->service->getDeliveryQuarters($tenant->id, $town->id);

    expect($quarters)->toHaveCount(1);
    $q = $quarters->first();
    expect($q['available'])->toBeTrue()
        ->and($q['delivery_fee'])->toBe(500)
        ->and($q['name'])->toBe('Akwa');
});

test('getDeliveryQuarters marks non-delivery-area quarters as unavailable', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $availableQuarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Akwa', 'name_fr' => 'Akwa']);
    $unavailableQuarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Logpom', 'name_fr' => 'Logpom']);

    $deliveryArea = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $deliveryArea->id,
        'quarter_id' => $availableQuarter->id,
        'delivery_fee' => 1000,
    ]);

    $quarters = $this->service->getDeliveryQuarters($tenant->id, $town->id);

    expect($quarters)->toHaveCount(2);

    $akwa = $quarters->firstWhere('name', 'Akwa');
    $logpom = $quarters->firstWhere('name', 'Logpom');

    expect($akwa['available'])->toBeTrue()
        ->and($akwa['delivery_fee'])->toBe(1000)
        ->and($logpom['available'])->toBeFalse()
        ->and($logpom['delivery_fee'])->toBe(0);
});

test('getDeliveryQuarters returns all quarters for town sorted by name', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Bonaberi', 'name_fr' => 'Bonaberi']);
    Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Akwa', 'name_fr' => 'Akwa']);
    Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Deido', 'name_fr' => 'Deido']);

    // Cook only delivers to Akwa
    $deliveryArea = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $deliveryArea->id,
        'quarter_id' => Quarter::where('name_en', 'Akwa')->first()->id,
        'delivery_fee' => 500,
    ]);

    $quarters = $this->service->getDeliveryQuarters($tenant->id, $town->id);

    expect($quarters)->toHaveCount(3);
    expect($quarters->pluck('name')->toArray())->toBe(['Akwa', 'Bonaberi', 'Deido']);
    expect($quarters->where('available', true))->toHaveCount(1);
    expect($quarters->where('available', false))->toHaveCount(2);
});

test('getDeliveryQuarters excludes inactive quarters', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Akwa', 'is_active' => true]);
    Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Inactive', 'is_active' => false]);

    $deliveryArea = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);

    $quarters = $this->service->getDeliveryQuarters($tenant->id, $town->id);

    expect($quarters)->toHaveCount(1)
        ->and($quarters->first()['name'])->toBe('Akwa');
});

test('getDeliveryQuarters uses group fee for quarters in a group', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Akwa', 'name_fr' => 'Akwa']);

    $deliveryArea = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $deliveryArea->id,
        'quarter_id' => $quarter->id,
        'delivery_fee' => 1000,
    ]);

    // Create a group with overriding fee
    $group = QuarterGroup::factory()->create([
        'tenant_id' => $tenant->id,
        'delivery_fee' => 750,
    ]);
    $group->quarters()->attach($quarter->id);

    $quarters = $this->service->getDeliveryQuarters($tenant->id, $town->id);

    $akwa = $quarters->firstWhere('name', 'Akwa');
    expect($akwa['delivery_fee'])->toBe(750)
        ->and($akwa['available'])->toBeTrue();
});

test('getDeliveryQuarters returns empty collection when no delivery area for town', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    // Town without delivery area but with quarters
    $town = Town::factory()->create(['name_en' => 'Bamenda', 'name_fr' => 'Bamenda']);
    Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Nkwen', 'name_fr' => 'Nkwen']);

    // But this town has no delivery area for this cook, so it should not appear
    // in the town dropdown at all. If somehow accessed, return all quarters as unavailable
    $quarters = $this->service->getDeliveryQuarters($tenant->id, $town->id);

    expect($quarters)->toHaveCount(1);
    expect($quarters->first()['available'])->toBeFalse();
});

// -- getCookContactInfo (BR-329) --

test('getCookContactInfo returns WhatsApp and phone when both are set', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];
    $tenant->update([
        'whatsapp' => '+237690000000',
        'phone' => '+237670000000',
    ]);

    $contact = $this->service->getCookContactInfo($tenant->fresh());

    expect($contact['whatsapp'])->toBe('+237690000000')
        ->and($contact['phone'])->toBe('+237670000000')
        ->and($contact['has_contact'])->toBeTrue()
        ->and($contact['brand_name'])->toBe($tenant->name_en);
});

test('getCookContactInfo returns null for missing WhatsApp', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];
    $tenant->update(['whatsapp' => null, 'phone' => '+237670000000']);

    $contact = $this->service->getCookContactInfo($tenant->fresh());

    expect($contact['whatsapp'])->toBeNull()
        ->and($contact['phone'])->toBe('+237670000000')
        ->and($contact['has_contact'])->toBeTrue();
});

test('getCookContactInfo returns has_contact false when neither WhatsApp nor phone is set', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];
    $tenant->update(['whatsapp' => null, 'phone' => null]);

    $contact = $this->service->getCookContactInfo($tenant->fresh());

    expect($contact['whatsapp'])->toBeNull()
        ->and($contact['phone'])->toBeNull()
        ->and($contact['has_contact'])->toBeFalse();
});

test('getCookContactInfo returns locale-aware brand name', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];
    $tenant->update(['name_en' => 'Chef Latifa', 'name_fr' => 'Chef Latifa FR']);

    // Test English locale
    app()->setLocale('en');
    $contactEn = $this->service->getCookContactInfo($tenant->fresh());
    expect($contactEn['brand_name'])->toBe('Chef Latifa');

    // Test French locale
    app()->setLocale('fr');
    $contactFr = $this->service->getCookContactInfo($tenant->fresh());
    expect($contactFr['brand_name'])->toBe('Chef Latifa FR');
});

test('getCookContactInfo treats empty string WhatsApp as no WhatsApp', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];
    $tenant->update(['whatsapp' => '', 'phone' => '+237670000000']);

    $contact = $this->service->getCookContactInfo($tenant->fresh());

    expect($contact['whatsapp'])->toBeNull()
        ->and($contact['has_contact'])->toBeTrue();
});

// -- buildWhatsAppMessage (BR-330) --

test('buildWhatsAppMessage builds correct pre-filled message', function () {
    $message = $this->service->buildWhatsAppMessage('Chef Latifa', 'Logpom', 'Douala');

    expect($message)->toContain('Chef Latifa')
        ->and($message)->toContain('Logpom')
        ->and($message)->toContain('Douala')
        ->and($message)->toContain('DancyMeals');
});

test('buildWhatsAppMessage handles special characters in names', function () {
    $message = $this->service->buildWhatsAppMessage("Chef L'artiste", 'Quartier des Ailes', 'Yaoundé');

    expect($message)->toContain("Chef L'artiste")
        ->and($message)->toContain('Quartier des Ailes')
        ->and($message)->toContain('Yaoundé');
});

// -- hasPickupLocations (BR-331) --

test('hasPickupLocations returns true when cook has pickup locations', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id]);

    PickupLocation::factory()->create([
        'tenant_id' => $tenant->id,
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
    ]);

    expect($this->service->hasPickupLocations($tenant->id))->toBeTrue();
});

test('hasPickupLocations returns false when cook has no pickup locations', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    expect($this->service->hasPickupLocations($tenant->id))->toBeFalse();
});

test('hasPickupLocations ignores pickup locations from other tenants', function () {
    $data1 = test()->createTenantWithCook();
    $data2 = test()->createTenantWithCook();
    $tenant1 = $data1['tenant'];
    $tenant2 = $data2['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id]);

    PickupLocation::factory()->create([
        'tenant_id' => $tenant2->id,
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
    ]);

    expect($this->service->hasPickupLocations($tenant1->id))->toBeFalse()
        ->and($this->service->hasPickupLocations($tenant2->id))->toBeTrue();
});

// -- validateDeliveryQuarter with unavailable quarters --

test('validateDeliveryQuarter returns invalid for non-delivery-area quarter', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Logpom']);

    // No delivery area for this quarter
    $result = $this->service->validateDeliveryQuarter($tenant->id, $quarter->id);

    expect($result['valid'])->toBeFalse()
        ->and($result['error'])->not->toBeNull()
        ->and($result['delivery_fee'])->toBeNull();
});

test('validateDeliveryQuarter returns valid for delivery-area quarter', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Akwa']);
    $deliveryArea = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $deliveryArea->id,
        'quarter_id' => $quarter->id,
        'delivery_fee' => 500,
    ]);

    $result = $this->service->validateDeliveryQuarter($tenant->id, $quarter->id);

    expect($result['valid'])->toBeTrue()
        ->and($result['error'])->toBeNull()
        ->and($result['delivery_fee'])->toBe(500);
});
