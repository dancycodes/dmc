<?php

/**
 * F-133: Delivery Areas & Fees Display — Unit Tests
 *
 * Tests the delivery display data logic in TenantLandingService.
 * Covers: town hierarchy, fees, groups, pickup locations, edge cases.
 */

use App\Models\DeliveryArea;
use App\Models\DeliveryAreaQuarter;
use App\Models\PickupLocation;
use App\Models\Quarter;
use App\Models\QuarterGroup;
use App\Models\Tenant;
use App\Models\Town;
use App\Services\TenantLandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(TenantLandingService::class);
    $this->tenant = Tenant::factory()->create(['is_active' => true]);
});

describe('getDeliveryDisplayData', function () {
    it('returns empty state when no delivery areas exist', function () {
        $data = $this->service->getDeliveryDisplayData($this->tenant);

        expect($data['hasDeliveryAreas'])->toBeFalse()
            ->and($data['towns'])->toBeEmpty()
            ->and($data['pickupLocations'])->toBeEmpty()
            ->and($data['hasPickupLocations'])->toBeFalse();
    });

    it('returns WhatsApp link when cook has WhatsApp number', function () {
        $this->tenant->update(['whatsapp' => '+237612345678']);

        $data = $this->service->getDeliveryDisplayData($this->tenant);

        expect($data['whatsappLink'])->toBe('https://wa.me/237612345678');
    });

    it('returns null WhatsApp link when cook has no WhatsApp number', function () {
        $this->tenant->update(['whatsapp' => null]);

        $data = $this->service->getDeliveryDisplayData($this->tenant);

        expect($data['whatsappLink'])->toBeNull();
    });

    it('returns delivery areas organized by town with quarters and fees', function () {
        $town = Town::create(['name_en' => 'Douala', 'name_fr' => 'Douala', 'is_active' => true]);
        $quarter1 = Quarter::create(['town_id' => $town->id, 'name_en' => 'Akwa', 'name_fr' => 'Akwa', 'is_active' => true]);
        $quarter2 = Quarter::create(['town_id' => $town->id, 'name_en' => 'Bonanjo', 'name_fr' => 'Bonanjo', 'is_active' => true]);

        $area = DeliveryArea::create(['tenant_id' => $this->tenant->id, 'town_id' => $town->id]);
        DeliveryAreaQuarter::create(['delivery_area_id' => $area->id, 'quarter_id' => $quarter1->id, 'delivery_fee' => 500]);
        DeliveryAreaQuarter::create(['delivery_area_id' => $area->id, 'quarter_id' => $quarter2->id, 'delivery_fee' => 1000]);

        $data = $this->service->getDeliveryDisplayData($this->tenant);

        expect($data['hasDeliveryAreas'])->toBeTrue()
            ->and($data['towns'])->toHaveCount(1)
            ->and($data['towns'][0]['name'])->toBe('Douala')
            ->and($data['towns'][0]['quarters'])->toHaveCount(2)
            ->and($data['towns'][0]['quarterCount'])->toBe(2);
    });

    it('displays Free delivery for zero fee quarters', function () {
        $town = Town::create(['name_en' => 'Yaounde', 'name_fr' => 'Yaound\u00e9', 'is_active' => true]);
        $quarter = Quarter::create(['town_id' => $town->id, 'name_en' => 'Bastos', 'name_fr' => 'Bastos', 'is_active' => true]);

        $area = DeliveryArea::create(['tenant_id' => $this->tenant->id, 'town_id' => $town->id]);
        DeliveryAreaQuarter::create(['delivery_area_id' => $area->id, 'quarter_id' => $quarter->id, 'delivery_fee' => 0]);

        $data = $this->service->getDeliveryDisplayData($this->tenant);

        $quarterData = $data['towns'][0]['quarters'][0];
        expect($quarterData['isFree'])->toBeTrue()
            ->and($quarterData['formattedFee'])->toBe(__('Free delivery'));
    });

    it('formats non-zero fees with XAF currency', function () {
        $town = Town::create(['name_en' => 'Douala', 'name_fr' => 'Douala', 'is_active' => true]);
        $quarter = Quarter::create(['town_id' => $town->id, 'name_en' => 'Deido', 'name_fr' => 'Deido', 'is_active' => true]);

        $area = DeliveryArea::create(['tenant_id' => $this->tenant->id, 'town_id' => $town->id]);
        DeliveryAreaQuarter::create(['delivery_area_id' => $area->id, 'quarter_id' => $quarter->id, 'delivery_fee' => 1500]);

        $data = $this->service->getDeliveryDisplayData($this->tenant);

        expect($data['towns'][0]['quarters'][0]['formattedFee'])->toBe('1,500 XAF')
            ->and($data['towns'][0]['quarters'][0]['isFree'])->toBeFalse();
    });

    it('uses group fee when quarter belongs to a group', function () {
        $town = Town::create(['name_en' => 'Douala', 'name_fr' => 'Douala', 'is_active' => true]);
        $quarter = Quarter::create(['town_id' => $town->id, 'name_en' => 'Akwa', 'name_fr' => 'Akwa', 'is_active' => true]);

        $area = DeliveryArea::create(['tenant_id' => $this->tenant->id, 'town_id' => $town->id]);
        DeliveryAreaQuarter::create(['delivery_area_id' => $area->id, 'quarter_id' => $quarter->id, 'delivery_fee' => 1000]);

        $group = QuarterGroup::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Downtown',
            'delivery_fee' => 500,
        ]);
        $group->quarters()->attach($quarter->id);

        $data = $this->service->getDeliveryDisplayData($this->tenant);

        $quarterData = $data['towns'][0]['quarters'][0];
        expect($quarterData['fee'])->toBe(500)
            ->and($quarterData['groupName'])->toBe('Downtown')
            ->and($quarterData['groupId'])->toBe($group->id);
    });

    it('supports multiple towns', function () {
        $town1 = Town::create(['name_en' => 'Douala', 'name_fr' => 'Douala', 'is_active' => true]);
        $town2 = Town::create(['name_en' => 'Yaounde', 'name_fr' => 'Yaound\u00e9', 'is_active' => true]);

        $quarter1 = Quarter::create(['town_id' => $town1->id, 'name_en' => 'Akwa', 'name_fr' => 'Akwa', 'is_active' => true]);
        $quarter2 = Quarter::create(['town_id' => $town2->id, 'name_en' => 'Bastos', 'name_fr' => 'Bastos', 'is_active' => true]);

        $area1 = DeliveryArea::create(['tenant_id' => $this->tenant->id, 'town_id' => $town1->id]);
        $area2 = DeliveryArea::create(['tenant_id' => $this->tenant->id, 'town_id' => $town2->id]);
        DeliveryAreaQuarter::create(['delivery_area_id' => $area1->id, 'quarter_id' => $quarter1->id, 'delivery_fee' => 500]);
        DeliveryAreaQuarter::create(['delivery_area_id' => $area2->id, 'quarter_id' => $quarter2->id, 'delivery_fee' => 800]);

        $data = $this->service->getDeliveryDisplayData($this->tenant);

        expect($data['towns'])->toHaveCount(2)
            ->and($data['towns'][0]['name'])->toBe('Douala')
            ->and($data['towns'][1]['name'])->toBe('Yaounde');
    });

    it('returns pickup locations with full address details', function () {
        $town = Town::create(['name_en' => 'Douala', 'name_fr' => 'Douala', 'is_active' => true]);
        $quarter = Quarter::create(['town_id' => $town->id, 'name_en' => 'Akwa', 'name_fr' => 'Akwa', 'is_active' => true]);

        $area = DeliveryArea::create(['tenant_id' => $this->tenant->id, 'town_id' => $town->id]);
        DeliveryAreaQuarter::create(['delivery_area_id' => $area->id, 'quarter_id' => $quarter->id, 'delivery_fee' => 500]);

        PickupLocation::create([
            'tenant_id' => $this->tenant->id,
            'town_id' => $town->id,
            'quarter_id' => $quarter->id,
            'name_en' => 'Main Kitchen',
            'name_fr' => 'Cuisine Principale',
            'address' => 'Rue des Manguiers',
        ]);

        $data = $this->service->getDeliveryDisplayData($this->tenant);

        expect($data['hasPickupLocations'])->toBeTrue()
            ->and($data['pickupLocations'])->toHaveCount(1)
            ->and($data['pickupLocations'][0]['name'])->toBe('Main Kitchen')
            ->and($data['pickupLocations'][0]['town'])->toBe('Douala')
            ->and($data['pickupLocations'][0]['fullAddress'])->toContain('Rue des Manguiers');
    });

    it('hides pickup section when no pickup locations exist', function () {
        $town = Town::create(['name_en' => 'Douala', 'name_fr' => 'Douala', 'is_active' => true]);
        $quarter = Quarter::create(['town_id' => $town->id, 'name_en' => 'Akwa', 'name_fr' => 'Akwa', 'is_active' => true]);

        $area = DeliveryArea::create(['tenant_id' => $this->tenant->id, 'town_id' => $town->id]);
        DeliveryAreaQuarter::create(['delivery_area_id' => $area->id, 'quarter_id' => $quarter->id, 'delivery_fee' => 500]);

        $data = $this->service->getDeliveryDisplayData($this->tenant);

        expect($data['hasPickupLocations'])->toBeFalse()
            ->and($data['pickupLocations'])->toBeEmpty();
    });

    it('displays quarter names in current locale', function () {
        app()->setLocale('fr');

        $town = Town::create(['name_en' => 'Douala', 'name_fr' => 'Douala', 'is_active' => true]);
        $quarter = Quarter::create(['town_id' => $town->id, 'name_en' => 'Downtown', 'name_fr' => 'Centre-ville', 'is_active' => true]);

        $area = DeliveryArea::create(['tenant_id' => $this->tenant->id, 'town_id' => $town->id]);
        DeliveryAreaQuarter::create(['delivery_area_id' => $area->id, 'quarter_id' => $quarter->id, 'delivery_fee' => 500]);

        $data = $this->service->getDeliveryDisplayData($this->tenant);

        expect($data['towns'][0]['quarters'][0]['name'])->toBe('Centre-ville');

        app()->setLocale('en');
    });

    it('handles high delivery fee display normally', function () {
        $town = Town::create(['name_en' => 'Douala', 'name_fr' => 'Douala', 'is_active' => true]);
        $quarter = Quarter::create(['town_id' => $town->id, 'name_en' => 'Remote', 'name_fr' => 'Lointain', 'is_active' => true]);

        $area = DeliveryArea::create(['tenant_id' => $this->tenant->id, 'town_id' => $town->id]);
        DeliveryAreaQuarter::create(['delivery_area_id' => $area->id, 'quarter_id' => $quarter->id, 'delivery_fee' => 10000]);

        $data = $this->service->getDeliveryDisplayData($this->tenant);

        expect($data['towns'][0]['quarters'][0]['formattedFee'])->toBe('10,000 XAF');
    });

    it('sorts towns alphabetically by locale name', function () {
        $townZ = Town::create(['name_en' => 'Ziguinchor', 'name_fr' => 'Ziguinchor', 'is_active' => true]);
        $townA = Town::create(['name_en' => 'Bamenda', 'name_fr' => 'Bamenda', 'is_active' => true]);

        $quarterZ = Quarter::create(['town_id' => $townZ->id, 'name_en' => 'Center', 'name_fr' => 'Centre', 'is_active' => true]);
        $quarterA = Quarter::create(['town_id' => $townA->id, 'name_en' => 'Market', 'name_fr' => 'March\u00e9', 'is_active' => true]);

        $areaZ = DeliveryArea::create(['tenant_id' => $this->tenant->id, 'town_id' => $townZ->id]);
        $areaA = DeliveryArea::create(['tenant_id' => $this->tenant->id, 'town_id' => $townA->id]);
        DeliveryAreaQuarter::create(['delivery_area_id' => $areaZ->id, 'quarter_id' => $quarterZ->id, 'delivery_fee' => 500]);
        DeliveryAreaQuarter::create(['delivery_area_id' => $areaA->id, 'quarter_id' => $quarterA->id, 'delivery_fee' => 500]);

        $data = $this->service->getDeliveryDisplayData($this->tenant);

        expect($data['towns'][0]['name'])->toBe('Bamenda')
            ->and($data['towns'][1]['name'])->toBe('Ziguinchor');
    });

    it('groups quarters with same group fee together', function () {
        $town = Town::create(['name_en' => 'Douala', 'name_fr' => 'Douala', 'is_active' => true]);
        $quarter1 = Quarter::create(['town_id' => $town->id, 'name_en' => 'Akwa', 'name_fr' => 'Akwa', 'is_active' => true]);
        $quarter2 = Quarter::create(['town_id' => $town->id, 'name_en' => 'Bonanjo', 'name_fr' => 'Bonanjo', 'is_active' => true]);

        $area = DeliveryArea::create(['tenant_id' => $this->tenant->id, 'town_id' => $town->id]);
        DeliveryAreaQuarter::create(['delivery_area_id' => $area->id, 'quarter_id' => $quarter1->id, 'delivery_fee' => 1000]);
        DeliveryAreaQuarter::create(['delivery_area_id' => $area->id, 'quarter_id' => $quarter2->id, 'delivery_fee' => 1000]);

        $group = QuarterGroup::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Downtown',
            'delivery_fee' => 750,
        ]);
        $group->quarters()->attach([$quarter1->id, $quarter2->id]);

        $data = $this->service->getDeliveryDisplayData($this->tenant);

        // Both quarters should have the same group fee and group name
        $quarters = $data['towns'][0]['quarters'];
        expect($quarters)->toHaveCount(2)
            ->and($quarters[0]['groupId'])->toBe($group->id)
            ->and($quarters[1]['groupId'])->toBe($group->id)
            ->and($quarters[0]['fee'])->toBe(750)
            ->and($quarters[1]['fee'])->toBe(750);
    });
});

describe('getLandingPageData includes delivery display', function () {
    it('includes deliveryDisplay key in landing page data', function () {
        $data = $this->service->getLandingPageData($this->tenant);

        expect($data)->toHaveKey('deliveryDisplay')
            ->and($data['deliveryDisplay'])->toHaveKey('hasDeliveryAreas')
            ->and($data['deliveryDisplay'])->toHaveKey('towns')
            ->and($data['deliveryDisplay'])->toHaveKey('pickupLocations')
            ->and($data['deliveryDisplay'])->toHaveKey('hasPickupLocations')
            ->and($data['deliveryDisplay'])->toHaveKey('whatsappLink');
    });
});
