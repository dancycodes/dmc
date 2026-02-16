<?php

use App\Models\PaymentMethod;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| F-037: Add Payment Method — Unit Tests
|--------------------------------------------------------------------------
|
| Unit tests for PaymentMethod model methods, validation helpers,
| and phone normalization logic.
|
*/

$projectRoot = dirname(__DIR__, 2);

/*
|--------------------------------------------------------------------------
| Model Constants
|--------------------------------------------------------------------------
*/

it('defines the correct maximum payment methods per user', function () {
    expect(PaymentMethod::MAX_PAYMENT_METHODS_PER_USER)->toBe(3);
});

it('defines the correct providers list', function () {
    expect(PaymentMethod::PROVIDERS)->toBe(['mtn_momo', 'orange_money']);
});

it('defines provider labels', function () {
    expect(PaymentMethod::PROVIDER_LABELS)->toBe([
        'mtn_momo' => 'MTN MoMo',
        'orange_money' => 'Orange Money',
    ]);
});

it('defines provider prefixes for MTN', function () {
    $prefixes = PaymentMethod::PROVIDER_PREFIXES['mtn_momo'];
    expect($prefixes)->toContain('67', '68', '650', '651', '652', '653', '654');
});

it('defines provider prefixes for Orange', function () {
    $prefixes = PaymentMethod::PROVIDER_PREFIXES['orange_money'];
    expect($prefixes)->toContain('69', '655', '656', '657', '658', '659');
});

/*
|--------------------------------------------------------------------------
| Phone Normalization (BR-154)
|--------------------------------------------------------------------------
*/

it('normalizes a plain 9-digit number', function () {
    expect(PaymentMethod::normalizePhone('670123456'))->toBe('+237670123456');
});

it('normalizes a number with +237 prefix', function () {
    expect(PaymentMethod::normalizePhone('+237670123456'))->toBe('+237670123456');
});

it('normalizes a number with 237 prefix (no plus)', function () {
    expect(PaymentMethod::normalizePhone('237670123456'))->toBe('+237670123456');
});

it('strips spaces from phone number', function () {
    expect(PaymentMethod::normalizePhone('6 70 12 34 56'))->toBe('+237670123456');
});

it('strips dashes from phone number', function () {
    expect(PaymentMethod::normalizePhone('670-123-456'))->toBe('+237670123456');
});

it('strips parentheses from phone number', function () {
    expect(PaymentMethod::normalizePhone('(670)123456'))->toBe('+237670123456');
});

it('handles +237 with spaces', function () {
    expect(PaymentMethod::normalizePhone('+237 670 123 456'))->toBe('+237670123456');
});

/*
|--------------------------------------------------------------------------
| Phone Validation (BR-150)
|--------------------------------------------------------------------------
*/

it('validates a correct Cameroon mobile number', function () {
    expect(PaymentMethod::isValidCameroonPhone('670123456'))->toBeTrue();
    expect(PaymentMethod::isValidCameroonPhone('690123456'))->toBeTrue();
    expect(PaymentMethod::isValidCameroonPhone('655123456'))->toBeTrue();
});

it('rejects numbers not starting with 6', function () {
    expect(PaymentMethod::isValidCameroonPhone('222123456'))->toBeFalse();
    expect(PaymentMethod::isValidCameroonPhone('333123456'))->toBeFalse();
});

it('rejects numbers with wrong digit count', function () {
    expect(PaymentMethod::isValidCameroonPhone('67012345'))->toBeFalse();    // 8 digits
    expect(PaymentMethod::isValidCameroonPhone('6701234567'))->toBeFalse();  // 10 digits
});

it('rejects empty phone', function () {
    expect(PaymentMethod::isValidCameroonPhone(''))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Provider-Phone Matching (BR-151)
|--------------------------------------------------------------------------
*/

it('matches MTN prefix 67 to mtn_momo', function () {
    expect(PaymentMethod::phoneMatchesProvider('670123456', 'mtn_momo'))->toBeTrue();
});

it('matches MTN prefix 68 to mtn_momo', function () {
    expect(PaymentMethod::phoneMatchesProvider('680123456', 'mtn_momo'))->toBeTrue();
});

it('matches MTN prefix 650 to mtn_momo', function () {
    expect(PaymentMethod::phoneMatchesProvider('650123456', 'mtn_momo'))->toBeTrue();
});

it('matches MTN prefix 654 to mtn_momo', function () {
    expect(PaymentMethod::phoneMatchesProvider('654123456', 'mtn_momo'))->toBeTrue();
});

it('matches Orange prefix 69 to orange_money', function () {
    expect(PaymentMethod::phoneMatchesProvider('690123456', 'orange_money'))->toBeTrue();
});

it('matches Orange prefix 655 to orange_money', function () {
    expect(PaymentMethod::phoneMatchesProvider('655123456', 'orange_money'))->toBeTrue();
});

it('matches Orange prefix 659 to orange_money', function () {
    expect(PaymentMethod::phoneMatchesProvider('659123456', 'orange_money'))->toBeTrue();
});

it('rejects MTN number for orange_money provider', function () {
    expect(PaymentMethod::phoneMatchesProvider('670123456', 'orange_money'))->toBeFalse();
});

it('rejects Orange number for mtn_momo provider', function () {
    expect(PaymentMethod::phoneMatchesProvider('690123456', 'mtn_momo'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Provider Label Helper
|--------------------------------------------------------------------------
*/

it('returns the correct provider label for mtn_momo', function () {
    $method = new PaymentMethod(['provider' => 'mtn_momo']);
    expect($method->providerLabel())->toBe('MTN MoMo');
});

it('returns the correct provider label for orange_money', function () {
    $method = new PaymentMethod(['provider' => 'orange_money']);
    expect($method->providerLabel())->toBe('Orange Money');
});

/*
|--------------------------------------------------------------------------
| Model Casts
|--------------------------------------------------------------------------
*/

it('casts is_default to boolean', function () {
    $method = new PaymentMethod(['is_default' => 1]);
    expect($method->is_default)->toBeBool()->toBeTrue();

    $method2 = new PaymentMethod(['is_default' => 0]);
    expect($method2->is_default)->toBeBool()->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Model Fillable
|--------------------------------------------------------------------------
*/

it('has the correct fillable attributes', function () {
    $method = new PaymentMethod;
    expect($method->getFillable())->toBe([
        'user_id',
        'label',
        'provider',
        'phone',
        'is_default',
    ]);
});

/*
|--------------------------------------------------------------------------
| Relationships
|--------------------------------------------------------------------------
*/

it('PaymentMethod model defines user relationship method', function () {
    expect(method_exists(PaymentMethod::class, 'user'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| User Model Relationship
|--------------------------------------------------------------------------
*/

it('User model defines paymentMethods relationship method', function () {
    expect(method_exists(User::class, 'paymentMethods'))->toBeTrue();
});
