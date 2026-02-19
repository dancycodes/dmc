<?php

/**
 * F-145: Delivery Fee Calculation — Unit Tests
 *
 * Tests CheckoutService delivery fee calculation methods including:
 * - BR-307: Delivery fee is determined by the selected quarter
 * - BR-308: If the quarter belongs to a fee group, the group fee is used
 * - BR-309: If the quarter has an individual fee (no group), the individual fee is used
 * - BR-310: A fee of 0 XAF is displayed as "Free delivery"
 * - BR-311: The fee is displayed in the format: "Delivery to {quarter}: {fee} XAF"
 * - BR-312: The fee updates reactively when the quarter selection changes
 * - BR-313: The delivery fee is added to the order total (F-146)
 * - BR-314: Pickup orders have no delivery fee (always 0)
 * - BR-315: All text must be localized via __()
 */

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

// -- calculateDeliveryFee tests (BR-307, BR-308, BR-309) --

test('calculateDeliveryFee returns individual quarter fee', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Bonaberi', 'name_fr' => 'Bonaberi']);
    $deliveryArea = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $deliveryArea->id,
        'quarter_id' => $quarter->id,
        'delivery_fee' => 1000,
    ]);

    $fee = $this->service->calculateDeliveryFee($tenant->id, $quarter->id);

    expect($fee)->toBe(1000);
});

test('calculateDeliveryFee returns group fee when quarter is in a group (BR-308)', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Akwa', 'name_fr' => 'Akwa']);
    $deliveryArea = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $deliveryArea->id,
        'quarter_id' => $quarter->id,
        'delivery_fee' => 1500, // Individual fee
    ]);

    // Create a group with fee 500 and assign the quarter to it
    $group = QuarterGroup::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Centre',
        'delivery_fee' => 500,
    ]);
    $group->quarters()->attach($quarter->id);

    $fee = $this->service->calculateDeliveryFee($tenant->id, $quarter->id);

    expect($fee)->toBe(500); // Group fee overrides individual
});

test('calculateDeliveryFee returns individual fee when quarter has no group (BR-309)', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Deido', 'name_fr' => 'Deido']);
    $deliveryArea = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $deliveryArea->id,
        'quarter_id' => $quarter->id,
        'delivery_fee' => 800,
    ]);

    $fee = $this->service->calculateDeliveryFee($tenant->id, $quarter->id);

    expect($fee)->toBe(800);
});

test('calculateDeliveryFee returns 0 for a free delivery quarter', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Deido', 'name_fr' => 'Deido']);
    $deliveryArea = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $deliveryArea->id,
        'quarter_id' => $quarter->id,
        'delivery_fee' => 0,
    ]);

    $fee = $this->service->calculateDeliveryFee($tenant->id, $quarter->id);

    expect($fee)->toBe(0);
});

test('calculateDeliveryFee returns 0 for invalid quarter not in delivery area', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Unknown', 'name_fr' => 'Inconnu']);
    // No delivery area configured for this quarter

    $fee = $this->service->calculateDeliveryFee($tenant->id, $quarter->id);

    expect($fee)->toBe(0);
});

test('calculateDeliveryFee uses correct group for multi-tenant isolation', function () {
    $data1 = test()->createTenantWithCook();
    $data2 = test()->createTenantWithCook();
    $tenant1 = $data1['tenant'];
    $tenant2 = $data2['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Akwa', 'name_fr' => 'Akwa']);

    // Tenant 1: individual fee = 1000, no group
    $da1 = DeliveryArea::factory()->create(['tenant_id' => $tenant1->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $da1->id,
        'quarter_id' => $quarter->id,
        'delivery_fee' => 1000,
    ]);

    // Tenant 2: individual fee = 1000, but group fee = 300
    $da2 = DeliveryArea::factory()->create(['tenant_id' => $tenant2->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $da2->id,
        'quarter_id' => $quarter->id,
        'delivery_fee' => 1000,
    ]);
    $group = QuarterGroup::factory()->create([
        'tenant_id' => $tenant2->id,
        'name' => 'Centre',
        'delivery_fee' => 300,
    ]);
    $group->quarters()->attach($quarter->id);

    $fee1 = $this->service->calculateDeliveryFee($tenant1->id, $quarter->id);
    $fee2 = $this->service->calculateDeliveryFee($tenant2->id, $quarter->id);

    expect($fee1)->toBe(1000) // No group for tenant 1
        ->and($fee2)->toBe(300); // Group fee for tenant 2
});

// -- getStoredDeliveryFee tests (BR-313, BR-314) --

test('getStoredDeliveryFee returns fee stored in session for delivery orders', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Bonaberi', 'name_fr' => 'Bonaberi']);
    $deliveryArea = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $deliveryArea->id,
        'quarter_id' => $quarter->id,
        'delivery_fee' => 750,
    ]);

    $this->service->setDeliveryMethod($tenant->id, CheckoutService::METHOD_DELIVERY);
    $this->service->setDeliveryLocation($tenant->id, [
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
        'neighbourhood' => 'Near the market',
    ]);

    $fee = $this->service->getStoredDeliveryFee($tenant->id);

    expect($fee)->toBe(750);
});

test('getStoredDeliveryFee returns 0 for pickup orders (BR-314)', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $this->service->setDeliveryMethod($tenant->id, CheckoutService::METHOD_PICKUP);

    $fee = $this->service->getStoredDeliveryFee($tenant->id);

    expect($fee)->toBe(0);
});

test('getStoredDeliveryFee returns 0 when no fee stored yet', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $fee = $this->service->getStoredDeliveryFee($tenant->id);

    expect($fee)->toBe(0);
});

test('getStoredDeliveryFee returns 0 for pickup even if delivery fee was previously stored', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Bonaberi', 'name_fr' => 'Bonaberi']);
    $deliveryArea = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $deliveryArea->id,
        'quarter_id' => $quarter->id,
        'delivery_fee' => 1000,
    ]);

    // First set delivery with fee
    $this->service->setDeliveryMethod($tenant->id, CheckoutService::METHOD_DELIVERY);
    $this->service->setDeliveryLocation($tenant->id, [
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
        'neighbourhood' => 'Near the market',
    ]);

    // Now switch to pickup
    $this->service->setDeliveryMethod($tenant->id, CheckoutService::METHOD_PICKUP);

    $fee = $this->service->getStoredDeliveryFee($tenant->id);

    expect($fee)->toBe(0);
});

// -- setDeliveryLocation stores delivery fee --

test('setDeliveryLocation automatically calculates and stores delivery fee', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Akwa', 'name_fr' => 'Akwa']);
    $deliveryArea = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $deliveryArea->id,
        'quarter_id' => $quarter->id,
        'delivery_fee' => 1200,
    ]);

    $this->service->setDeliveryMethod($tenant->id, CheckoutService::METHOD_DELIVERY);
    $this->service->setDeliveryLocation($tenant->id, [
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
        'neighbourhood' => 'Near Total station',
    ]);

    $checkoutData = $this->service->getCheckoutData($tenant->id);

    expect($checkoutData['delivery_fee'])->toBe(1200)
        ->and($checkoutData['delivery_location']['quarter_id'])->toBe($quarter->id);
});

test('setDeliveryLocation stores group fee when quarter is in a group', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Bonanjo', 'name_fr' => 'Bonanjo']);
    $deliveryArea = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $deliveryArea->id,
        'quarter_id' => $quarter->id,
        'delivery_fee' => 1500,
    ]);

    $group = QuarterGroup::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Downtown',
        'delivery_fee' => 500,
    ]);
    $group->quarters()->attach($quarter->id);

    $this->service->setDeliveryMethod($tenant->id, CheckoutService::METHOD_DELIVERY);
    $this->service->setDeliveryLocation($tenant->id, [
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
        'neighbourhood' => 'Behind the bank',
    ]);

    $checkoutData = $this->service->getCheckoutData($tenant->id);

    expect($checkoutData['delivery_fee'])->toBe(500); // Group fee, not individual
});

test('delivery fee updates when quarter selection changes', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter1 = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Akwa', 'name_fr' => 'Akwa']);
    $quarter2 = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Bonaberi', 'name_fr' => 'Bonaberi']);
    $deliveryArea = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $deliveryArea->id,
        'quarter_id' => $quarter1->id,
        'delivery_fee' => 500,
    ]);
    DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $deliveryArea->id,
        'quarter_id' => $quarter2->id,
        'delivery_fee' => 1000,
    ]);

    $this->service->setDeliveryMethod($tenant->id, CheckoutService::METHOD_DELIVERY);

    // First selection: quarter1 with 500 fee
    $this->service->setDeliveryLocation($tenant->id, [
        'town_id' => $town->id,
        'quarter_id' => $quarter1->id,
        'neighbourhood' => 'Near Akwa',
    ]);

    expect($this->service->getStoredDeliveryFee($tenant->id))->toBe(500);

    // Change to quarter2 with 1000 fee
    $this->service->setDeliveryLocation($tenant->id, [
        'town_id' => $town->id,
        'quarter_id' => $quarter2->id,
        'neighbourhood' => 'Near Bonaberi',
    ]);

    expect($this->service->getStoredDeliveryFee($tenant->id))->toBe(1000);
});

// -- getDeliveryFeeDisplayData tests (BR-310, BR-311) --

test('getDeliveryFeeDisplayData shows free delivery for 0 fee (BR-310)', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Deido', 'name_fr' => 'Deido']);
    $deliveryArea = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $deliveryArea->id,
        'quarter_id' => $quarter->id,
        'delivery_fee' => 0,
    ]);

    $this->service->setDeliveryMethod($tenant->id, CheckoutService::METHOD_DELIVERY);
    $this->service->setDeliveryLocation($tenant->id, [
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
        'neighbourhood' => 'Near the church',
    ]);

    $displayData = $this->service->getDeliveryFeeDisplayData($tenant->id);

    expect($displayData['fee'])->toBe(0)
        ->and($displayData['is_free'])->toBeTrue()
        ->and($displayData['quarter_name'])->toBe('Deido')
        ->and($displayData['display_text'])->toContain('Deido')
        ->and($displayData['display_text'])->toContain('Free delivery');
});

test('getDeliveryFeeDisplayData shows fee amount for non-zero fee (BR-311)', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Bonaberi', 'name_fr' => 'Bonaberi']);
    $deliveryArea = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $deliveryArea->id,
        'quarter_id' => $quarter->id,
        'delivery_fee' => 1000,
    ]);

    $this->service->setDeliveryMethod($tenant->id, CheckoutService::METHOD_DELIVERY);
    $this->service->setDeliveryLocation($tenant->id, [
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
        'neighbourhood' => 'Near the bridge',
    ]);

    $displayData = $this->service->getDeliveryFeeDisplayData($tenant->id);

    expect($displayData['fee'])->toBe(1000)
        ->and($displayData['is_free'])->toBeFalse()
        ->and($displayData['quarter_name'])->toBe('Bonaberi')
        ->and($displayData['display_text'])->toContain('Bonaberi')
        ->and($displayData['display_text'])->toContain('1,000')
        ->and($displayData['display_text'])->toContain('XAF');
});

test('getDeliveryFeeDisplayData returns pickup no fee message for pickup orders (BR-314)', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $this->service->setDeliveryMethod($tenant->id, CheckoutService::METHOD_PICKUP);

    $displayData = $this->service->getDeliveryFeeDisplayData($tenant->id);

    expect($displayData['fee'])->toBe(0)
        ->and($displayData['is_free'])->toBeTrue()
        ->and($displayData['quarter_name'])->toBeNull()
        ->and($displayData['display_text'])->toContain('Pickup');
});

test('getDeliveryFeeDisplayData shows localized quarter name for French locale', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Akwa North', 'name_fr' => 'Akwa Nord']);
    $deliveryArea = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $deliveryArea->id,
        'quarter_id' => $quarter->id,
        'delivery_fee' => 600,
    ]);

    $this->service->setDeliveryMethod($tenant->id, CheckoutService::METHOD_DELIVERY);
    $this->service->setDeliveryLocation($tenant->id, [
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
        'neighbourhood' => 'Pres du marche',
    ]);

    app()->setLocale('fr');
    $displayData = $this->service->getDeliveryFeeDisplayData($tenant->id);

    expect($displayData['quarter_name'])->toBe('Akwa Nord');
});

// -- Edge cases --

test('very high delivery fee is displayed normally', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Remote', 'name_fr' => 'Lointain']);
    $deliveryArea = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $deliveryArea->id,
        'quarter_id' => $quarter->id,
        'delivery_fee' => 50000,
    ]);

    $fee = $this->service->calculateDeliveryFee($tenant->id, $quarter->id);

    expect($fee)->toBe(50000);
});

test('checkout data includes delivery_fee key in default structure', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $checkoutData = $this->service->getCheckoutData($tenant->id);

    expect($checkoutData)->toHaveKey('delivery_fee')
        ->and($checkoutData['delivery_fee'])->toBeNull();
});

test('clearing checkout data resets delivery fee', function () {
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

    $this->service->setDeliveryMethod($tenant->id, CheckoutService::METHOD_DELIVERY);
    $this->service->setDeliveryLocation($tenant->id, [
        'town_id' => $town->id,
        'quarter_id' => $quarter->id,
        'neighbourhood' => 'Here',
    ]);

    expect($this->service->getStoredDeliveryFee($tenant->id))->toBe(1000);

    $this->service->clearCheckoutData($tenant->id);

    expect($this->service->getStoredDeliveryFee($tenant->id))->toBe(0);
});

test('free delivery quarter in a group with 0 fee returns 0', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
    $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Deido', 'name_fr' => 'Deido']);
    $deliveryArea = DeliveryArea::factory()->create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
    DeliveryAreaQuarter::factory()->create([
        'delivery_area_id' => $deliveryArea->id,
        'quarter_id' => $quarter->id,
        'delivery_fee' => 500,
    ]);

    // Group with 0 fee overrides individual fee
    $group = QuarterGroup::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Free Zone',
        'delivery_fee' => 0,
    ]);
    $group->quarters()->attach($quarter->id);

    $fee = $this->service->calculateDeliveryFee($tenant->id, $quarter->id);

    expect($fee)->toBe(0);
});
