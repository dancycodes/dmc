<?php

/**
 * F-140: Delivery/Pickup Choice Selection — Unit Tests
 *
 * Tests CheckoutService business logic including:
 * - Available options detection (BR-267, BR-268)
 * - Delivery method storage and persistence (BR-271)
 * - Method validation against available options
 * - Session isolation per tenant
 */

use App\Models\DeliveryArea;
use App\Models\PickupLocation;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new CheckoutService;
    test()->seedRolesAndPermissions();
});

// -- getAvailableOptions tests --

test('getAvailableOptions returns has_delivery true when delivery areas exist', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    DeliveryArea::factory()->create(['tenant_id' => $tenant->id]);

    $options = $this->service->getAvailableOptions($tenant->id);

    expect($options['has_delivery'])->toBeTrue()
        ->and($options['delivery_area_count'])->toBe(1);
});

test('getAvailableOptions returns has_delivery false when no delivery areas', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $options = $this->service->getAvailableOptions($tenant->id);

    expect($options['has_delivery'])->toBeFalse()
        ->and($options['delivery_area_count'])->toBe(0);
});

test('getAvailableOptions returns has_pickup true when pickup locations exist', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    PickupLocation::factory()->create(['tenant_id' => $tenant->id]);

    $options = $this->service->getAvailableOptions($tenant->id);

    expect($options['has_pickup'])->toBeTrue()
        ->and($options['pickup_location_count'])->toBe(1);
});

test('getAvailableOptions returns has_pickup false when no pickup locations', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $options = $this->service->getAvailableOptions($tenant->id);

    expect($options['has_pickup'])->toBeFalse()
        ->and($options['pickup_location_count'])->toBe(0);
});

test('getAvailableOptions returns both true when both configured', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    DeliveryArea::factory()->create(['tenant_id' => $tenant->id]);
    PickupLocation::factory()->create(['tenant_id' => $tenant->id]);

    $options = $this->service->getAvailableOptions($tenant->id);

    expect($options['has_delivery'])->toBeTrue()
        ->and($options['has_pickup'])->toBeTrue();
});

// -- setDeliveryMethod / getDeliveryMethod tests --

test('setDeliveryMethod stores choice in session', function () {
    $tenantId = 1;

    $this->service->setDeliveryMethod($tenantId, CheckoutService::METHOD_DELIVERY);

    expect($this->service->getDeliveryMethod($tenantId))->toBe('delivery');
});

test('setDeliveryMethod persists across calls (BR-271)', function () {
    $tenantId = 1;

    $this->service->setDeliveryMethod($tenantId, CheckoutService::METHOD_PICKUP);

    $method = $this->service->getDeliveryMethod($tenantId);
    expect($method)->toBe('pickup');
});

test('getDeliveryMethod returns null when not set', function () {
    expect($this->service->getDeliveryMethod(999))->toBeNull();
});

test('setDeliveryMethod can change from delivery to pickup', function () {
    $tenantId = 1;

    $this->service->setDeliveryMethod($tenantId, CheckoutService::METHOD_DELIVERY);
    expect($this->service->getDeliveryMethod($tenantId))->toBe('delivery');

    $this->service->setDeliveryMethod($tenantId, CheckoutService::METHOD_PICKUP);
    expect($this->service->getDeliveryMethod($tenantId))->toBe('pickup');
});

// -- validateMethodSelection tests --

test('validateMethodSelection returns valid for delivery when available', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];
    DeliveryArea::factory()->create(['tenant_id' => $tenant->id]);

    $result = $this->service->validateMethodSelection($tenant->id, 'delivery');

    expect($result['valid'])->toBeTrue()
        ->and($result['error'])->toBeNull();
});

test('validateMethodSelection returns valid for pickup when available', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];
    PickupLocation::factory()->create(['tenant_id' => $tenant->id]);

    $result = $this->service->validateMethodSelection($tenant->id, 'pickup');

    expect($result['valid'])->toBeTrue()
        ->and($result['error'])->toBeNull();
});

test('validateMethodSelection returns invalid for delivery when no areas (BR-268)', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $result = $this->service->validateMethodSelection($tenant->id, 'delivery');

    expect($result['valid'])->toBeFalse()
        ->and($result['error'])->not->toBeNull();
});

test('validateMethodSelection returns invalid for pickup when no locations (BR-267)', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $result = $this->service->validateMethodSelection($tenant->id, 'pickup');

    expect($result['valid'])->toBeFalse()
        ->and($result['error'])->not->toBeNull();
});

test('validateMethodSelection returns invalid for unknown method', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $result = $this->service->validateMethodSelection($tenant->id, 'drone');

    expect($result['valid'])->toBeFalse()
        ->and($result['error'])->not->toBeNull();
});

// -- clearCheckoutData tests --

test('clearCheckoutData removes session data', function () {
    $tenantId = 1;

    $this->service->setDeliveryMethod($tenantId, CheckoutService::METHOD_DELIVERY);
    expect($this->service->getDeliveryMethod($tenantId))->toBe('delivery');

    $this->service->clearCheckoutData($tenantId);
    expect($this->service->getDeliveryMethod($tenantId))->toBeNull();
});

// -- getCheckoutData tests --

test('getCheckoutData returns defaults when empty', function () {
    $data = $this->service->getCheckoutData(999);

    expect($data)->toHaveKey('delivery_method')
        ->and($data['delivery_method'])->toBeNull();
});

// -- tenant isolation tests --

test('checkout data is isolated per tenant', function () {
    $this->service->setDeliveryMethod(1, CheckoutService::METHOD_DELIVERY);
    $this->service->setDeliveryMethod(2, CheckoutService::METHOD_PICKUP);

    expect($this->service->getDeliveryMethod(1))->toBe('delivery')
        ->and($this->service->getDeliveryMethod(2))->toBe('pickup');
});

// -- constant tests --

test('delivery method constants are correct', function () {
    expect(CheckoutService::METHOD_DELIVERY)->toBe('delivery')
        ->and(CheckoutService::METHOD_PICKUP)->toBe('pickup');
});
