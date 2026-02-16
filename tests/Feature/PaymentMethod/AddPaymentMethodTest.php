<?php

use App\Models\PaymentMethod;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| F-037: Add Payment Method — Feature Tests
|--------------------------------------------------------------------------
|
| Tests for adding saved mobile money payment methods.
|
| BR-147: Maximum 3 saved payment methods per user.
| BR-148: Label required, unique per user, max 50 characters.
| BR-149: Provider required, mtn_momo or orange_money.
| BR-150: Phone must be valid Cameroon mobile number.
| BR-151: Phone prefix must match selected provider.
| BR-152: First payment method auto-set as default.
| BR-153: Payment methods are user-scoped, not tenant-scoped.
| BR-154: Phone stored in normalized +237XXXXXXXXX format.
| BR-155: All text localized via __().
|
*/

beforeEach(function () {
    $this->seedRolesAndPermissions();
    $this->user = createUser();
});

/*
|--------------------------------------------------------------------------
| Create Page Tests
|--------------------------------------------------------------------------
*/

it('shows the add payment method page for authenticated users', function () {
    $response = $this->actingAs($this->user)->get('/profile/payment-methods/create');

    $response->assertOk();
    $response->assertViewIs('profile.payment-methods.create');
});

it('redirects unauthenticated users to login', function () {
    $response = $this->get('/profile/payment-methods/create');

    $response->assertRedirect('/login');
});

it('shows the form when user has fewer than 3 methods', function () {
    $response = $this->actingAs($this->user)->get('/profile/payment-methods/create');

    $response->assertOk();
    $response->assertSee(__('Add Payment Method'));
    $response->assertSee(__('Save Payment Method'));
});

it('shows the limit message when user has 3 methods', function () {
    PaymentMethod::factory()->count(3)->forUser($this->user)->create();

    $response = $this->actingAs($this->user)->get('/profile/payment-methods/create');

    $response->assertOk();
    $response->assertSee(__('Payment Method Limit Reached'));
});

it('passes the correct method count and max to the view', function () {
    PaymentMethod::factory()->forUser($this->user)->create(['label' => 'First']);

    $response = $this->actingAs($this->user)->get('/profile/payment-methods/create');

    $response->assertOk();
    $response->assertViewHas('methodCount', 1);
    $response->assertViewHas('maxMethods', 3);
    $response->assertViewHas('canAddMore', true);
});

/*
|--------------------------------------------------------------------------
| Store — Happy Path Tests
|--------------------------------------------------------------------------
*/

it('saves a valid MTN MoMo payment method', function () {
    $response = $this->actingAs($this->user)->post('/profile/payment-methods', [
        'label' => 'MTN Main',
        'provider' => 'mtn_momo',
        'phone' => '670123456',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('payment_methods', [
        'user_id' => $this->user->id,
        'label' => 'MTN Main',
        'provider' => 'mtn_momo',
        'phone' => '+237670123456',
    ]);
});

it('saves a valid Orange Money payment method', function () {
    $response = $this->actingAs($this->user)->post('/profile/payment-methods', [
        'label' => 'Orange Personal',
        'provider' => 'orange_money',
        'phone' => '690123456',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('payment_methods', [
        'user_id' => $this->user->id,
        'label' => 'Orange Personal',
        'provider' => 'orange_money',
        'phone' => '+237690123456',
    ]);
});

it('auto-sets first payment method as default (BR-152)', function () {
    $this->actingAs($this->user)->post('/profile/payment-methods', [
        'label' => 'MTN Main',
        'provider' => 'mtn_momo',
        'phone' => '670123456',
    ]);

    $method = $this->user->paymentMethods()->first();
    expect($method->is_default)->toBeTrue();
});

it('does not auto-set subsequent methods as default', function () {
    PaymentMethod::factory()->forUser($this->user)->default()->mtnMomo()->create(['label' => 'First']);

    $this->actingAs($this->user)->post('/profile/payment-methods', [
        'label' => 'Second',
        'provider' => 'orange_money',
        'phone' => '690123456',
    ]);

    $second = $this->user->paymentMethods()->where('label', 'Second')->first();
    expect($second->is_default)->toBeFalse();
});

it('normalizes phone number with spaces and dashes (BR-154)', function () {
    $this->actingAs($this->user)->post('/profile/payment-methods', [
        'label' => 'MTN Main',
        'provider' => 'mtn_momo',
        'phone' => '6 70 12 34 56',
    ]);

    $this->assertDatabaseHas('payment_methods', [
        'user_id' => $this->user->id,
        'phone' => '+237670123456',
    ]);
});

it('normalizes phone number with +237 prefix', function () {
    $this->actingAs($this->user)->post('/profile/payment-methods', [
        'label' => 'MTN Main',
        'provider' => 'mtn_momo',
        'phone' => '+237670123456',
    ]);

    $this->assertDatabaseHas('payment_methods', [
        'user_id' => $this->user->id,
        'phone' => '+237670123456',
    ]);
});

it('shows success toast after saving', function () {
    $response = $this->actingAs($this->user)->post('/profile/payment-methods', [
        'label' => 'MTN Main',
        'provider' => 'mtn_momo',
        'phone' => '670123456',
    ]);

    $response->assertSessionHas('toast.message', __('Payment method saved.'));
});

it('logs activity when creating a payment method', function () {
    $this->actingAs($this->user)->post('/profile/payment-methods', [
        'label' => 'MTN Main',
        'provider' => 'mtn_momo',
        'phone' => '670123456',
    ]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'payment_methods',
        'event' => 'created',
        'causer_id' => $this->user->id,
    ]);
});

/*
|--------------------------------------------------------------------------
| Store — Validation Error Tests
|--------------------------------------------------------------------------
*/

it('rejects when maximum methods reached (BR-147)', function () {
    PaymentMethod::factory()->count(3)->forUser($this->user)->create();

    $response = $this->actingAs($this->user)->post('/profile/payment-methods', [
        'label' => 'Fourth',
        'provider' => 'mtn_momo',
        'phone' => '670123456',
    ]);

    $response->assertRedirect();
    expect($this->user->paymentMethods()->count())->toBe(3);
});

it('rejects missing label', function () {
    $response = $this->actingAs($this->user)->post('/profile/payment-methods', [
        'label' => '',
        'provider' => 'mtn_momo',
        'phone' => '670123456',
    ]);

    $response->assertSessionHasErrors('label');
});

it('rejects label exceeding 50 characters (BR-148)', function () {
    $response = $this->actingAs($this->user)->post('/profile/payment-methods', [
        'label' => str_repeat('a', 51),
        'provider' => 'mtn_momo',
        'phone' => '670123456',
    ]);

    $response->assertSessionHasErrors('label');
});

it('rejects duplicate label per user (BR-148)', function () {
    PaymentMethod::factory()->forUser($this->user)->create(['label' => 'MTN Main']);

    $response = $this->actingAs($this->user)->post('/profile/payment-methods', [
        'label' => 'MTN Main',
        'provider' => 'orange_money',
        'phone' => '690123456',
    ]);

    $response->assertSessionHasErrors('label');
});

it('allows same label for different users', function () {
    $otherUser = createUser();
    PaymentMethod::factory()->forUser($otherUser)->create(['label' => 'MTN Main']);

    $response = $this->actingAs($this->user)->post('/profile/payment-methods', [
        'label' => 'MTN Main',
        'provider' => 'mtn_momo',
        'phone' => '670123456',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('payment_methods', [
        'user_id' => $this->user->id,
        'label' => 'MTN Main',
    ]);
});

it('rejects missing provider', function () {
    $response = $this->actingAs($this->user)->post('/profile/payment-methods', [
        'label' => 'Test',
        'provider' => '',
        'phone' => '670123456',
    ]);

    $response->assertSessionHasErrors('provider');
});

it('rejects invalid provider (BR-149)', function () {
    $response = $this->actingAs($this->user)->post('/profile/payment-methods', [
        'label' => 'Test',
        'provider' => 'paypal',
        'phone' => '670123456',
    ]);

    $response->assertSessionHasErrors('provider');
});

it('rejects missing phone number', function () {
    $response = $this->actingAs($this->user)->post('/profile/payment-methods', [
        'label' => 'Test',
        'provider' => 'mtn_momo',
        'phone' => '',
    ]);

    $response->assertSessionHasErrors('phone');
});

it('rejects invalid Cameroon phone format (BR-150)', function () {
    $response = $this->actingAs($this->user)->post('/profile/payment-methods', [
        'label' => 'Test',
        'provider' => 'mtn_momo',
        'phone' => '123456789',
    ]);

    $response->assertSessionHasErrors('phone');
});

it('rejects phone with wrong provider prefix (BR-151) — MTN number for Orange', function () {
    $response = $this->actingAs($this->user)->post('/profile/payment-methods', [
        'label' => 'Test',
        'provider' => 'orange_money',
        'phone' => '670123456',
    ]);

    $response->assertSessionHasErrors('phone');
});

it('rejects phone with wrong provider prefix (BR-151) — Orange number for MTN', function () {
    $response = $this->actingAs($this->user)->post('/profile/payment-methods', [
        'label' => 'Test',
        'provider' => 'mtn_momo',
        'phone' => '690123456',
    ]);

    $response->assertSessionHasErrors('phone');
});

it('rejects same phone under different provider', function () {
    PaymentMethod::factory()->forUser($this->user)->create([
        'label' => 'MTN Main',
        'provider' => 'mtn_momo',
        'phone' => '+237670123456',
    ]);

    $response = $this->actingAs($this->user)->post('/profile/payment-methods', [
        'label' => 'Orange Copy',
        'provider' => 'orange_money',
        'phone' => '670123456',
    ]);

    $response->assertSessionHasErrors('phone');
});

it('allows same phone with same provider but different label', function () {
    PaymentMethod::factory()->forUser($this->user)->create([
        'label' => 'MTN Main',
        'provider' => 'mtn_momo',
        'phone' => '+237670123456',
    ]);

    $response = $this->actingAs($this->user)->post('/profile/payment-methods', [
        'label' => 'MTN Secondary',
        'provider' => 'mtn_momo',
        'phone' => '670123456',
    ]);

    $response->assertRedirect();
    expect($this->user->paymentMethods()->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| User Scope Tests (BR-153)
|--------------------------------------------------------------------------
*/

it('does not count other users methods toward the limit', function () {
    $otherUser = createUser();
    PaymentMethod::factory()->count(3)->forUser($otherUser)->create();

    $response = $this->actingAs($this->user)->get('/profile/payment-methods/create');

    $response->assertOk();
    $response->assertViewHas('canAddMore', true);
});

it('requires authentication for store', function () {
    $response = $this->post('/profile/payment-methods', [
        'label' => 'Test',
        'provider' => 'mtn_momo',
        'phone' => '670123456',
    ]);

    $response->assertRedirect('/login');
});

/*
|--------------------------------------------------------------------------
| Provider-Specific Prefix Tests (BR-151)
|--------------------------------------------------------------------------
*/

it('accepts MTN prefix 67X', function () {
    $response = $this->actingAs($this->user)->post('/profile/payment-methods', [
        'label' => 'Test',
        'provider' => 'mtn_momo',
        'phone' => '671234567',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('payment_methods', ['phone' => '+237671234567']);
});

it('accepts MTN prefix 68X', function () {
    $response = $this->actingAs($this->user)->post('/profile/payment-methods', [
        'label' => 'Test',
        'provider' => 'mtn_momo',
        'phone' => '681234567',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('payment_methods', ['phone' => '+237681234567']);
});

it('accepts MTN prefix 650-654', function () {
    $response = $this->actingAs($this->user)->post('/profile/payment-methods', [
        'label' => 'Test',
        'provider' => 'mtn_momo',
        'phone' => '650123456',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('payment_methods', ['phone' => '+237650123456']);
});

it('accepts Orange prefix 69X', function () {
    $response = $this->actingAs($this->user)->post('/profile/payment-methods', [
        'label' => 'Test',
        'provider' => 'orange_money',
        'phone' => '691234567',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('payment_methods', ['phone' => '+237691234567']);
});

it('accepts Orange prefix 655-659', function () {
    $response = $this->actingAs($this->user)->post('/profile/payment-methods', [
        'label' => 'Test',
        'provider' => 'orange_money',
        'phone' => '655123456',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('payment_methods', ['phone' => '+237655123456']);
});
