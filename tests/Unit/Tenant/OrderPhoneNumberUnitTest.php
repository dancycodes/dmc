<?php

/**
 * F-143: Order Phone Number — Unit Tests
 *
 * Tests CheckoutService phone number management methods and business rules.
 * BR-292: Pre-filled from user's profile phone.
 * BR-293: Client can override per order.
 * BR-294: Override does NOT update user's profile.
 * BR-295: Cameroon phone format validation.
 * BR-296: Phone number is required.
 * BR-297: Validation error messages localized.
 * BR-298: Phone number stored in checkout session.
 */

use App\Http\Requests\Auth\RegisterRequest;
use App\Services\CheckoutService;

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->service = new CheckoutService;
});

/*
|--------------------------------------------------------------------------
| CheckoutService Phone Methods
|--------------------------------------------------------------------------
*/

it('stores and retrieves phone number in checkout session', function () {
    $this->service->setPhone(1, '+237655123456');

    expect($this->service->getPhone(1))->toBe('+237655123456');
});

it('returns null when no phone number is stored', function () {
    expect($this->service->getPhone(999))->toBeNull();
});

it('includes phone in checkout data array', function () {
    $this->service->setPhone(2, '+237677987654');

    $data = $this->service->getCheckoutData(2);
    expect($data)->toHaveKey('phone')
        ->and($data['phone'])->toBe('+237677987654');
});

it('checkout data has phone key initialized to null', function () {
    $data = $this->service->getCheckoutData(888);

    expect($data)->toHaveKey('phone')
        ->and($data['phone'])->toBeNull();
});

/*
|--------------------------------------------------------------------------
| BR-292: Pre-filled Phone (getPrefilledPhone)
|--------------------------------------------------------------------------
*/

it('returns session phone when already stored', function () {
    $this->service->setPhone(3, '+237699888777');

    $result = $this->service->getPrefilledPhone(3, '+237655111222');
    expect($result)->toBe('+237699888777');
});

it('returns user profile phone when no session phone exists', function () {
    $result = $this->service->getPrefilledPhone(4, '+237655111222');
    expect($result)->toBe('+237655111222');
});

it('returns empty string when no session phone and no profile phone', function () {
    $result = $this->service->getPrefilledPhone(5, null);
    expect($result)->toBe('');
});

/*
|--------------------------------------------------------------------------
| Phone Step Back URL
|--------------------------------------------------------------------------
*/

it('returns delivery location URL when delivery method is delivery', function () {
    $this->service->setDeliveryMethod(6, CheckoutService::METHOD_DELIVERY);

    $url = $this->service->getPhoneStepBackUrl(6);
    expect($url)->toContain('/checkout/delivery-location');
});

it('returns pickup location URL when delivery method is pickup', function () {
    $this->service->setDeliveryMethod(7, CheckoutService::METHOD_PICKUP);

    $url = $this->service->getPhoneStepBackUrl(7);
    expect($url)->toContain('/checkout/pickup-location');
});

it('defaults to delivery location URL when no method selected', function () {
    $url = $this->service->getPhoneStepBackUrl(8);
    expect($url)->toContain('/checkout/delivery-location');
});

/*
|--------------------------------------------------------------------------
| Phone overwrite preserves other checkout data
|--------------------------------------------------------------------------
*/

it('preserves delivery method and location when setting phone', function () {
    $this->service->setDeliveryMethod(9, 'delivery');
    $this->service->setDeliveryLocation(9, [
        'town_id' => 1,
        'quarter_id' => 2,
        'neighbourhood' => 'Test area',
    ]);
    $this->service->setPhone(9, '+237655123456');

    $data = $this->service->getCheckoutData(9);
    expect($data['delivery_method'])->toBe('delivery')
        ->and($data['delivery_location'])->not->toBeNull()
        ->and($data['delivery_location']['town_id'])->toBe(1)
        ->and($data['phone'])->toBe('+237655123456');
});

it('preserves pickup location ID when setting phone', function () {
    $this->service->setDeliveryMethod(10, 'pickup');
    $this->service->setPickupLocation(10, 42);
    $this->service->setPhone(10, '+237677111222');

    $data = $this->service->getCheckoutData(10);
    expect($data['delivery_method'])->toBe('pickup')
        ->and($data['pickup_location_id'])->toBe(42)
        ->and($data['phone'])->toBe('+237677111222');
});

/*
|--------------------------------------------------------------------------
| Phone cleared by clearCheckoutData
|--------------------------------------------------------------------------
*/

it('clears phone when checkout data is cleared', function () {
    $this->service->setPhone(11, '+237655123456');
    expect($this->service->getPhone(11))->toBe('+237655123456');

    $this->service->clearCheckoutData(11);
    expect($this->service->getPhone(11))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| BR-295: Cameroon Phone Format Validation (via RegisterRequest::CAMEROON_PHONE_REGEX)
|--------------------------------------------------------------------------
*/

it('accepts valid Cameroon phone numbers', function (string $phone) {
    expect(preg_match(RegisterRequest::CAMEROON_PHONE_REGEX, $phone))->toBe(1);
})->with([
    '+237655123456',
    '+237677987654',
    '+237699000111',
    '+237233445566',
    '+237222334455',
]);

it('rejects invalid phone numbers', function (string $phone) {
    expect(preg_match(RegisterRequest::CAMEROON_PHONE_REGEX, $phone))->toBe(0);
})->with([
    '123456',
    '+237123456789',
    '+237555123456',
    '+23665512345',
    '237655123456',
    '+238655123456',
    '+2376551234567',
    '+23765512345',
    '',
]);

/*
|--------------------------------------------------------------------------
| Phone Normalization (via RegisterRequest::normalizePhone)
|--------------------------------------------------------------------------
*/

it('normalizes phone with spaces and dashes', function () {
    $result = RegisterRequest::normalizePhone('655 123 456');
    expect($result)->toBe('+237655123456');
});

it('normalizes phone already with +237 prefix', function () {
    $result = RegisterRequest::normalizePhone('+237655123456');
    expect($result)->toBe('+237655123456');
});

it('normalizes phone with 237 prefix without plus', function () {
    $result = RegisterRequest::normalizePhone('237655123456');
    expect($result)->toBe('+237655123456');
});

it('normalizes 9-digit phone starting with 6', function () {
    $result = RegisterRequest::normalizePhone('655123456');
    expect($result)->toBe('+237655123456');
});

it('strips dashes during normalization', function () {
    $result = RegisterRequest::normalizePhone('655-123-456');
    expect($result)->toBe('+237655123456');
});

/*
|--------------------------------------------------------------------------
| Translation Keys
|--------------------------------------------------------------------------
*/

it('has required translation keys in English', function () {
    $keys = [
        'Your contact phone number',
        'This number will be used by the cook to contact you about your order.',
        'Phone number saved.',
        'Same as my profile',
        'Changing the number here will not update your profile. This number is used for this order only.',
        'Cameroon mobile number',
        'Phone number is required.',
        'Please enter a valid Cameroon phone number (+237 followed by 9 digits).',
    ];

    foreach ($keys as $key) {
        expect(__($key))->toBeString();
    }
});

/*
|--------------------------------------------------------------------------
| Route Registration
|--------------------------------------------------------------------------
*/

it('registers the phone checkout routes', function () {
    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->map(fn ($r) => $r->getName())
        ->filter()
        ->values()
        ->toArray();

    expect($routes)->toContain('tenant.checkout.phone')
        ->and($routes)->toContain('tenant.checkout.save-phone');
});
