<?php

/**
 * F-149: Payment Method Selection — Unit Tests
 *
 * Tests CheckoutService payment method selection methods including:
 * - BR-345: Available payment methods: MTN Mobile Money, Orange Money, Wallet Balance
 * - BR-346: Wallet Balance only if admin enabled AND balance >= total
 * - BR-347: Wallet visible but disabled if balance < total
 * - BR-348: Mobile money requires phone number, pre-filled from profile or saved methods
 * - BR-349: Previously used payment methods offered as saved options
 * - BR-350: Total to pay displayed prominently
 * - BR-351: Phone number must match Cameroon format
 * - BR-352: Pay Now triggers F-150 (Flutterwave) or F-153 (wallet)
 * - BR-353: All text localized via __()
 */

use App\Models\PaymentMethod;
use App\Models\PlatformSetting;
use App\Services\CheckoutService;
use App\Services\PlatformSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new CheckoutService;
    test()->seedRolesAndPermissions();
});

// -- setPaymentMethod / getPaymentProvider / getPaymentPhone tests --

test('setPaymentMethod stores provider and phone in checkout session', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $this->service->setPaymentMethod($tenant->id, 'mtn_momo', '+237655123456');

    expect($this->service->getPaymentProvider($tenant->id))->toBe('mtn_momo');
    expect($this->service->getPaymentPhone($tenant->id))->toBe('+237655123456');
});

test('setPaymentMethod stores wallet provider without phone', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $this->service->setPaymentMethod($tenant->id, 'wallet');

    expect($this->service->getPaymentProvider($tenant->id))->toBe('wallet');
    expect($this->service->getPaymentPhone($tenant->id))->toBeNull();
});

test('getPaymentProvider returns null when no payment method set', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    expect($this->service->getPaymentProvider($tenant->id))->toBeNull();
});

test('setPaymentMethod overwrites previous selection', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $this->service->setPaymentMethod($tenant->id, 'mtn_momo', '+237655111111');
    $this->service->setPaymentMethod($tenant->id, 'orange_money', '+237699222222');

    expect($this->service->getPaymentProvider($tenant->id))->toBe('orange_money');
    expect($this->service->getPaymentPhone($tenant->id))->toBe('+237699222222');
});

// -- getPaymentOptions tests (BR-345, BR-349) --

test('getPaymentOptions returns MTN and Orange providers (BR-345)', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];
    $user = $data['cook'];

    $options = $this->service->getPaymentOptions($tenant->id, $user->id, 5000);

    expect($options['providers'])->toHaveCount(2);
    expect($options['providers'][0]['id'])->toBe('mtn_momo');
    expect($options['providers'][1]['id'])->toBe('orange_money');
});

test('getPaymentOptions returns saved methods for user (BR-349)', function () {
    $data = test()->createTenantWithCook();
    $user = test()->createUserWithRole('client');

    PaymentMethod::factory()->create([
        'user_id' => $user->id,
        'provider' => 'mtn_momo',
        'phone' => '+237655123456',
        'label' => 'My MTN',
        'is_default' => true,
    ]);

    PaymentMethod::factory()->create([
        'user_id' => $user->id,
        'provider' => 'orange_money',
        'phone' => '+237699123456',
        'label' => 'My Orange',
    ]);

    $options = $this->service->getPaymentOptions($data['tenant']->id, $user->id, 5000);

    expect($options['saved_methods'])->toHaveCount(2);
    expect($options['saved_methods']->first()->is_default)->toBeTrue();
});

test('getPaymentOptions returns empty saved methods for user with none', function () {
    $data = test()->createTenantWithCook();
    $user = test()->createUserWithRole('client');

    $options = $this->service->getPaymentOptions($data['tenant']->id, $user->id, 5000);

    expect($options['saved_methods'])->toHaveCount(0);
});

// -- Wallet option tests (BR-346, BR-347) --

test('wallet option is hidden when admin disables wallet payments (BR-346)', function () {
    $data = test()->createTenantWithCook();
    $user = test()->createUserWithRole('client');

    // Disable wallet payments
    PlatformSetting::updateOrCreate(
        ['key' => 'wallet_enabled'],
        ['value' => '0', 'type' => 'boolean', 'group' => 'features']
    );
    app(PlatformSettingService::class)->clearCache();

    $options = $this->service->getPaymentOptions($data['tenant']->id, $user->id, 5000);

    expect($options['wallet']['visible'])->toBeFalse();
    expect($options['wallet']['enabled'])->toBeFalse();
});

test('wallet option is visible but insufficient when balance < total (BR-347/BR-300)', function () {
    $data = test()->createTenantWithCook();
    $user = test()->createUserWithRole('client');

    // Enable wallet payments
    PlatformSetting::updateOrCreate(
        ['key' => 'wallet_enabled'],
        ['value' => '1', 'type' => 'boolean', 'group' => 'features']
    );
    app(PlatformSettingService::class)->clearCache();

    // F-168 BR-300: Wallet is hidden when balance is 0, so create wallet with partial balance
    \App\Models\ClientWallet::create([
        'user_id' => $user->id,
        'balance' => 1000,
        'currency' => 'XAF',
    ]);

    $options = $this->service->getPaymentOptions($data['tenant']->id, $user->id, 5000);

    expect($options['wallet']['visible'])->toBeTrue();
    expect($options['wallet']['enabled'])->toBeTrue();
    expect($options['wallet']['sufficient'])->toBeFalse();
    expect($options['wallet']['balance'])->toBe(1000);
    expect($options['wallet']['partial_available'])->toBeTrue();
});

test('wallet option visible and enabled when balance sufficient and admin enabled', function () {
    $data = test()->createTenantWithCook();
    $user = test()->createUserWithRole('client');

    PlatformSetting::updateOrCreate(
        ['key' => 'wallet_enabled'],
        ['value' => '1', 'type' => 'boolean', 'group' => 'features']
    );
    app(PlatformSettingService::class)->clearCache();

    // F-168: Create wallet with sufficient balance
    \App\Models\ClientWallet::create([
        'user_id' => $user->id,
        'balance' => 10000,
        'currency' => 'XAF',
    ]);

    $options = $this->service->getPaymentOptions($data['tenant']->id, $user->id, 5000);

    expect($options['wallet']['visible'])->toBeTrue();
    expect($options['wallet']['sufficient'])->toBeTrue();
    expect($options['wallet']['enabled'])->toBeTrue();
});

// -- validatePaymentSelection tests (BR-345, BR-346, BR-351) --

test('validatePaymentSelection rejects invalid provider', function () {
    $result = $this->service->validatePaymentSelection('stripe', null, 1, 5000);

    expect($result['valid'])->toBeFalse();
    expect($result['error'])->not->toBeNull();
});

test('validatePaymentSelection accepts MTN MoMo with valid phone', function () {
    $result = $this->service->validatePaymentSelection(
        'mtn_momo',
        '+237655123456',
        1,
        5000
    );

    expect($result['valid'])->toBeTrue();
    expect($result['error'])->toBeNull();
});

test('validatePaymentSelection accepts Orange Money with valid phone', function () {
    $result = $this->service->validatePaymentSelection(
        'orange_money',
        '+237699123456',
        1,
        5000
    );

    expect($result['valid'])->toBeTrue();
    expect($result['error'])->toBeNull();
});

test('validatePaymentSelection rejects mobile money without phone (BR-348)', function () {
    $result = $this->service->validatePaymentSelection('mtn_momo', null, 1, 5000);

    expect($result['valid'])->toBeFalse();
    expect($result['error'])->not->toBeNull();
});

test('validatePaymentSelection rejects mobile money with empty phone', function () {
    $result = $this->service->validatePaymentSelection('mtn_momo', '', 1, 5000);

    expect($result['valid'])->toBeFalse();
});

test('validatePaymentSelection rejects mobile money with invalid phone format (BR-351)', function () {
    $result = $this->service->validatePaymentSelection('mtn_momo', '+2371234', 1, 5000);

    expect($result['valid'])->toBeFalse();
});

test('validatePaymentSelection rejects wallet when admin disabled', function () {
    $data = test()->createTenantWithCook();
    $user = test()->createUserWithRole('client');

    PlatformSetting::updateOrCreate(
        ['key' => 'wallet_enabled'],
        ['value' => '0', 'type' => 'boolean', 'group' => 'features']
    );
    app(PlatformSettingService::class)->clearCache();

    $result = $this->service->validatePaymentSelection('wallet', null, $user->id, 5000);

    expect($result['valid'])->toBeFalse();
    expect($result['error'])->toContain(__('Wallet payments are not available.'));
});

test('validatePaymentSelection rejects wallet when insufficient balance', function () {
    $data = test()->createTenantWithCook();
    $user = test()->createUserWithRole('client');

    PlatformSetting::updateOrCreate(
        ['key' => 'wallet_enabled'],
        ['value' => '1', 'type' => 'boolean', 'group' => 'features']
    );
    app(PlatformSettingService::class)->clearCache();

    // F-168 BR-300: With 0 balance, wallet is hidden entirely
    // Create wallet with some balance but insufficient for full payment
    \App\Models\ClientWallet::create([
        'user_id' => $user->id,
        'balance' => 1000,
        'currency' => 'XAF',
    ]);

    $result = $this->service->validatePaymentSelection('wallet', null, $user->id, 5000);

    expect($result['valid'])->toBeFalse();
    expect($result['error'])->toContain(__('Insufficient wallet balance for full payment.'));
});

// -- getPaymentPrefillPhone tests (BR-348) --

test('getPaymentPrefillPhone returns session payment phone first', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $this->service->setPaymentMethod($tenant->id, 'mtn_momo', '+237655999999');

    $result = $this->service->getPaymentPrefillPhone($tenant->id, '+237655111111');

    expect($result)->toBe('+237655999999');
});

test('getPaymentPrefillPhone returns saved method phone for provider', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];
    $user = test()->createUserWithRole('client');

    $method = PaymentMethod::factory()->create([
        'user_id' => $user->id,
        'provider' => 'mtn_momo',
        'phone' => '+237655444444',
        'is_default' => true,
    ]);

    $savedMethods = PaymentMethod::where('user_id', $user->id)->get();

    $result = $this->service->getPaymentPrefillPhone(
        $tenant->id,
        '+237655111111',
        'mtn_momo',
        $savedMethods
    );

    expect($result)->toBe('+237655444444');
});

test('getPaymentPrefillPhone returns user profile phone as fallback', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $result = $this->service->getPaymentPrefillPhone($tenant->id, '+237655111111');

    expect($result)->toBe('+237655111111');
});

test('getPaymentPrefillPhone returns empty string when no phone available', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $result = $this->service->getPaymentPrefillPhone($tenant->id, null);

    expect($result)->toBe('');
});

// -- getPaymentBackUrl test --

test('getPaymentBackUrl returns summary URL', function () {
    $result = $this->service->getPaymentBackUrl();

    expect($result)->toContain('/checkout/summary');
});

// -- clearCheckoutData clears payment data --

test('clearCheckoutData removes payment method from session', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];

    $this->service->setPaymentMethod($tenant->id, 'mtn_momo', '+237655123456');
    $this->service->clearCheckoutData($tenant->id);

    expect($this->service->getPaymentProvider($tenant->id))->toBeNull();
    expect($this->service->getPaymentPhone($tenant->id))->toBeNull();
});
