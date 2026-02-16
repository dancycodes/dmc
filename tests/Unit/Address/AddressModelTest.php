<?php

use App\Http\Controllers\AddressController;
use App\Http\Requests\Address\StoreAddressRequest;
use App\Models\Address;
use App\Models\Quarter;
use App\Models\Town;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| F-033: Add Delivery Address — Unit Tests
|--------------------------------------------------------------------------
|
| Unit tests for the address model, town/quarter models, controller,
| form request, view file presence, translation keys, and blade structure.
|
*/

$projectRoot = dirname(__DIR__, 3);

/*
|--------------------------------------------------------------------------
| Address Model Tests
|--------------------------------------------------------------------------
*/

it('defines the correct max addresses per user constant', function () {
    expect(Address::MAX_ADDRESSES_PER_USER)->toBe(5);
});

it('has the correct table name', function () {
    $address = new Address;
    expect($address->getTable())->toBe('addresses');
});

it('has the correct fillable attributes', function () {
    $address = new Address;
    expect($address->getFillable())->toBe([
        'user_id',
        'label',
        'town_id',
        'quarter_id',
        'neighbourhood',
        'additional_directions',
        'is_default',
        'latitude',
        'longitude',
    ]);
});

it('casts is_default to boolean', function () {
    $address = new Address;
    $casts = $address->getCasts();

    expect($casts)->toHaveKey('is_default', 'boolean');
});

it('casts latitude and longitude to decimal', function () {
    $address = new Address;
    $casts = $address->getCasts();

    expect($casts)
        ->toHaveKey('latitude', 'decimal:7')
        ->toHaveKey('longitude', 'decimal:7');
});

it('address has user relationship method', function () {
    $address = new Address;
    expect(method_exists($address, 'user'))->toBeTrue();
});

it('address has town relationship method', function () {
    $address = new Address;
    expect(method_exists($address, 'town'))->toBeTrue();
});

it('address has quarter relationship method', function () {
    $address = new Address;
    expect(method_exists($address, 'quarter'))->toBeTrue();
});

it('address uses LogsActivityTrait', function () {
    $address = new Address;
    expect(in_array(\App\Traits\LogsActivityTrait::class, class_uses_recursive($address)))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Town Model Tests
|--------------------------------------------------------------------------
*/

it('town has the correct table name', function () {
    $town = new Town;
    expect($town->getTable())->toBe('towns');
});

it('town has the correct fillable attributes', function () {
    $town = new Town;
    expect($town->getFillable())->toBe([
        'name_en',
        'name_fr',
        'is_active',
    ]);
});

it('town casts is_active to boolean', function () {
    $town = new Town;
    $casts = $town->getCasts();

    expect($casts)->toHaveKey('is_active', 'boolean');
});

it('town has quarters relationship method', function () {
    $town = new Town;
    expect(method_exists($town, 'quarters'))->toBeTrue();
});

it('town has addresses relationship method', function () {
    $town = new Town;
    expect(method_exists($town, 'addresses'))->toBeTrue();
});

it('town uses HasTranslatable trait', function () {
    $town = new Town;
    expect(in_array(\App\Traits\HasTranslatable::class, class_uses_recursive($town)))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Quarter Model Tests
|--------------------------------------------------------------------------
*/

it('quarter has the correct table name', function () {
    $quarter = new Quarter;
    expect($quarter->getTable())->toBe('quarters');
});

it('quarter has the correct fillable attributes', function () {
    $quarter = new Quarter;
    expect($quarter->getFillable())->toBe([
        'town_id',
        'name_en',
        'name_fr',
        'is_active',
    ]);
});

it('quarter casts is_active to boolean', function () {
    $quarter = new Quarter;
    $casts = $quarter->getCasts();

    expect($casts)->toHaveKey('is_active', 'boolean');
});

it('quarter has town relationship method', function () {
    $quarter = new Quarter;
    expect(method_exists($quarter, 'town'))->toBeTrue();
});

it('quarter has addresses relationship method', function () {
    $quarter = new Quarter;
    expect(method_exists($quarter, 'addresses'))->toBeTrue();
});

it('quarter uses HasTranslatable trait', function () {
    $quarter = new Quarter;
    expect(in_array(\App\Traits\HasTranslatable::class, class_uses_recursive($quarter)))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| User Model — addresses relationship
|--------------------------------------------------------------------------
*/

it('user model has addresses relationship method', function () {
    $user = new User;
    expect(method_exists($user, 'addresses'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Controller Tests
|--------------------------------------------------------------------------
*/

it('AddressController has a create method', function () {
    $controller = new AddressController;
    expect(method_exists($controller, 'create'))->toBeTrue();
});

it('AddressController has a store method', function () {
    $controller = new AddressController;
    expect(method_exists($controller, 'store'))->toBeTrue();
});

it('AddressController has a quarters method', function () {
    $controller = new AddressController;
    expect(method_exists($controller, 'quarters'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Form Request Tests
|--------------------------------------------------------------------------
*/

it('StoreAddressRequest class exists', function () {
    expect(class_exists(StoreAddressRequest::class))->toBeTrue();
});

it('StoreAddressRequest extends FormRequest', function () {
    expect(is_subclass_of(StoreAddressRequest::class, \Illuminate\Foundation\Http\FormRequest::class))->toBeTrue();
});

it('StoreAddressRequest has rules method', function () {
    expect(method_exists(StoreAddressRequest::class, 'rules'))->toBeTrue();
});

it('StoreAddressRequest has messages method', function () {
    expect(method_exists(StoreAddressRequest::class, 'messages'))->toBeTrue();
});

it('StoreAddressRequest has authorize method', function () {
    expect(method_exists(StoreAddressRequest::class, 'authorize'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| View & Route Tests
|--------------------------------------------------------------------------
*/

it('create blade view exists', function () use ($projectRoot) {
    expect(file_exists($projectRoot.'/resources/views/profile/addresses/create.blade.php'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Translation Key Tests
|--------------------------------------------------------------------------
*/

it('has required English translation keys', function () use ($projectRoot) {
    $enJson = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);

    expect($enJson)
        ->toHaveKey('Add Delivery Address')
        ->toHaveKey('Address Limit Reached')
        ->toHaveKey('Address Label')
        ->toHaveKey('Town')
        ->toHaveKey('Quarter')
        ->toHaveKey('Neighbourhood')
        ->toHaveKey('Additional Directions')
        ->toHaveKey('Save Address')
        ->toHaveKey('Address saved successfully.')
        ->toHaveKey('Address label is required.')
        ->toHaveKey('Town is required.')
        ->toHaveKey('Quarter is required.');
});

it('has required French translation keys', function () use ($projectRoot) {
    $frJson = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);

    expect($frJson)
        ->toHaveKey('Add Delivery Address')
        ->toHaveKey('Address Limit Reached')
        ->toHaveKey('Address Label')
        ->toHaveKey('Town')
        ->toHaveKey('Quarter')
        ->toHaveKey('Neighbourhood')
        ->toHaveKey('Additional Directions')
        ->toHaveKey('Save Address')
        ->toHaveKey('Address saved successfully.');
});
