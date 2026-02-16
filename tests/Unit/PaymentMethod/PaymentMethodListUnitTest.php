<?php

use App\Models\PaymentMethod;

/*
|--------------------------------------------------------------------------
| F-038: Payment Method List — Unit Tests
|--------------------------------------------------------------------------
|
| Unit tests for the maskedPhone() method, view file existence,
| route definitions, and controller method signatures.
|
*/

$projectRoot = dirname(__DIR__, 3);

/*
|--------------------------------------------------------------------------
| Model: maskedPhone() Method
|--------------------------------------------------------------------------
*/

it('masks phone number showing only last 2 digits (BR-157)', function () {
    $method = new PaymentMethod(['phone' => '+237670123478']);
    expect($method->maskedPhone())->toBe('+237 6** *** *78');
});

it('masks different phone numbers correctly', function () {
    $method = new PaymentMethod(['phone' => '+237691234599']);
    expect($method->maskedPhone())->toBe('+237 6** *** *99');
});

it('masks phone number with provider prefix 650', function () {
    $method = new PaymentMethod(['phone' => '+237650987612']);
    expect($method->maskedPhone())->toBe('+237 6** *** *12');
});

it('masks phone number with provider prefix 655', function () {
    $method = new PaymentMethod(['phone' => '+237655432100']);
    expect($method->maskedPhone())->toBe('+237 6** *** *00');
});

it('handles short phone numbers gracefully', function () {
    $method = new PaymentMethod(['phone' => '+237']);
    expect($method->maskedPhone())->toBeString();
});

it('handles very short phone numbers by returning them as-is', function () {
    $method = new PaymentMethod(['phone' => '123']);
    expect($method->maskedPhone())->toBe('123');
});

/*
|--------------------------------------------------------------------------
| Model: providerLabel() Method
|--------------------------------------------------------------------------
*/

it('returns MTN MoMo label for mtn_momo provider', function () {
    $method = new PaymentMethod(['provider' => 'mtn_momo']);
    expect($method->providerLabel())->toBe('MTN MoMo');
});

it('returns Orange Money label for orange_money provider', function () {
    $method = new PaymentMethod(['provider' => 'orange_money']);
    expect($method->providerLabel())->toBe('Orange Money');
});

it('returns raw provider value for unknown provider', function () {
    $method = new PaymentMethod(['provider' => 'unknown_provider']);
    expect($method->providerLabel())->toBe('unknown_provider');
});

/*
|--------------------------------------------------------------------------
| View File Existence
|--------------------------------------------------------------------------
*/

it('has the payment method list view file', function () use ($projectRoot) {
    expect(file_exists($projectRoot.'/resources/views/profile/payment-methods/index.blade.php'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Route Definitions (file-based check)
|--------------------------------------------------------------------------
*/

it('registers the payment methods index route in web.php', function () use ($projectRoot) {
    $routes = file_get_contents($projectRoot.'/routes/web.php');
    expect($routes)->toContain("'payment-methods.index'");
});

it('registers the payment methods set-default route in web.php', function () use ($projectRoot) {
    $routes = file_get_contents($projectRoot.'/routes/web.php');
    expect($routes)->toContain("'payment-methods.set-default'");
});

/*
|--------------------------------------------------------------------------
| Controller Method Existence
|--------------------------------------------------------------------------
*/

it('has the index method on PaymentMethodController', function () {
    $controller = new \App\Http\Controllers\PaymentMethodController;
    expect(method_exists($controller, 'index'))->toBeTrue();
});

it('has the setDefault method on PaymentMethodController', function () {
    $controller = new \App\Http\Controllers\PaymentMethodController;
    expect(method_exists($controller, 'setDefault'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Translation Keys
|--------------------------------------------------------------------------
*/

it('has English translations for payment method list strings', function () use ($projectRoot) {
    $translations = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);

    expect($translations)->toHaveKey('Payment Methods')
        ->toHaveKey('Manage your saved mobile money numbers.')
        ->toHaveKey('You have no saved payment methods.')
        ->toHaveKey('Add Your First Payment Method')
        ->toHaveKey('Default payment method updated.')
        ->toHaveKey('This payment method is already your default.')
        ->toHaveKey(':count of :max methods');
});

it('has French translations for payment method list strings', function () use ($projectRoot) {
    $translations = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);

    expect($translations)->toHaveKey('Payment Methods')
        ->toHaveKey('Manage your saved mobile money numbers.')
        ->toHaveKey('You have no saved payment methods.')
        ->toHaveKey('Add Your First Payment Method')
        ->toHaveKey('Default payment method updated.')
        ->toHaveKey('This payment method is already your default.')
        ->toHaveKey(':count of :max methods');
});

/*
|--------------------------------------------------------------------------
| Masked Phone Edge Cases
|--------------------------------------------------------------------------
*/

it('masks phone with last two digits being zeros', function () {
    $method = new PaymentMethod(['phone' => '+237670000000']);
    expect($method->maskedPhone())->toBe('+237 6** *** *00');
});

it('masks phone with last two digits being 99', function () {
    $method = new PaymentMethod(['phone' => '+237680000099']);
    expect($method->maskedPhone())->toBe('+237 6** *** *99');
});
