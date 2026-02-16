<?php

use App\Models\PaymentMethod;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| F-038: Payment Method List — Feature Tests
|--------------------------------------------------------------------------
|
| Tests for displaying and managing saved payment methods.
|
| BR-156: All methods displayed, default first.
| BR-157: Phone numbers masked (only last 2 digits visible).
| BR-158: Provider icons/logos displayed.
| BR-159: Only one payment method can be default at a time.
| BR-160: Setting new default removes previous default.
| BR-161: "Add" button only if < 3 methods.
| BR-162: Each method has edit/delete links.
|
*/

beforeEach(function () {
    $this->seedRolesAndPermissions();
    $this->user = createUser();
});

/*
|--------------------------------------------------------------------------
| Index Page Tests
|--------------------------------------------------------------------------
*/

it('shows the payment method list page for authenticated users', function () {
    $response = $this->actingAs($this->user)->get('/profile/payment-methods');

    $response->assertOk();
    $response->assertViewIs('profile.payment-methods.index');
});

it('redirects unauthenticated users to login', function () {
    $response = $this->get('/profile/payment-methods');

    $response->assertRedirect('/login');
});

it('displays all saved payment methods', function () {
    PaymentMethod::factory()->forUser($this->user)->default()->create(['label' => 'MTN Main']);
    PaymentMethod::factory()->forUser($this->user)->create(['label' => 'Orange Work']);

    $response = $this->actingAs($this->user)->get('/profile/payment-methods');

    $response->assertOk();
    $response->assertSee('MTN Main');
    $response->assertSee('Orange Work');
});

it('shows the default badge on the default payment method (BR-156, BR-159)', function () {
    PaymentMethod::factory()->forUser($this->user)->default()->mtnMomo()->create(['label' => 'MTN Default']);
    PaymentMethod::factory()->forUser($this->user)->orangeMoney()->create(['label' => 'Orange Secondary']);

    $response = $this->actingAs($this->user)->get('/profile/payment-methods');

    $response->assertOk();
    $response->assertSee('MTN Default');
    $response->assertSee(__('Default'));
});

it('orders payment methods with default first (BR-156)', function () {
    PaymentMethod::factory()->forUser($this->user)->orangeMoney()->create(['label' => 'Alpha Method']);
    PaymentMethod::factory()->forUser($this->user)->default()->mtnMomo()->create(['label' => 'Zeta Method']);

    $response = $this->actingAs($this->user)->get('/profile/payment-methods');

    $response->assertOk();
    $response->assertViewHas('paymentMethods', function ($methods) {
        return $methods->first()->label === 'Zeta Method' && $methods->last()->label === 'Alpha Method';
    });
});

it('displays masked phone numbers (BR-157)', function () {
    PaymentMethod::factory()->forUser($this->user)->default()->mtnMomo()->create([
        'label' => 'MTN Test',
        'phone' => '+237670123478',
    ]);

    $response = $this->actingAs($this->user)->get('/profile/payment-methods');

    $response->assertOk();
    // Should show masked phone: +237 6** *** *78
    $response->assertSee('+237 6** *** *78');
    // Should NOT show the full phone number
    $response->assertDontSee('+237670123478');
});

it('displays provider labels (BR-158)', function () {
    PaymentMethod::factory()->forUser($this->user)->default()->mtnMomo()->create(['label' => 'My MTN']);

    $response = $this->actingAs($this->user)->get('/profile/payment-methods');

    $response->assertOk();
    $response->assertSee('MTN MoMo');
});

it('shows the add button when user has fewer than 3 methods (BR-161)', function () {
    PaymentMethod::factory()->forUser($this->user)->default()->create(['label' => 'Method 1']);

    $response = $this->actingAs($this->user)->get('/profile/payment-methods');

    $response->assertOk();
    $response->assertSee(__('Add Payment Method'));
});

it('hides the add button when user has 3 methods (BR-161)', function () {
    PaymentMethod::factory()->forUser($this->user)->default()->mtnMomo()->create(['label' => 'Method A']);
    PaymentMethod::factory()->forUser($this->user)->orangeMoney()->create(['label' => 'Method B']);
    PaymentMethod::factory()->forUser($this->user)->mtnMomo()->create(['label' => 'Method C']);

    $response = $this->actingAs($this->user)->get('/profile/payment-methods');

    $response->assertOk();
    $response->assertViewHas('canAddMore', false);
});

it('shows the empty state when user has no payment methods', function () {
    $response = $this->actingAs($this->user)->get('/profile/payment-methods');

    $response->assertOk();
    $response->assertSee(__('You have no saved payment methods.'));
    $response->assertSee(__('Add Your First Payment Method'));
});

it('shows edit and delete links for each method (BR-162)', function () {
    $method = PaymentMethod::factory()->forUser($this->user)->default()->create(['label' => 'Test Method']);

    $response = $this->actingAs($this->user)->get('/profile/payment-methods');

    $response->assertOk();
    $response->assertSee('/profile/payment-methods/'.$method->id.'/edit');
    $response->assertSee('/profile/payment-methods/'.$method->id.'/delete');
});

it('does not show other users payment methods (BR-153)', function () {
    $otherUser = createUser();
    PaymentMethod::factory()->forUser($otherUser)->default()->create(['label' => 'Other User Method']);
    PaymentMethod::factory()->forUser($this->user)->default()->create(['label' => 'My Method']);

    $response = $this->actingAs($this->user)->get('/profile/payment-methods');

    $response->assertOk();
    $response->assertSee('My Method');
    $response->assertDontSee('Other User Method');
});

it('passes the correct view data', function () {
    PaymentMethod::factory()->forUser($this->user)->default()->create();

    $response = $this->actingAs($this->user)->get('/profile/payment-methods');

    $response->assertOk();
    $response->assertViewHas('paymentMethods');
    $response->assertViewHas('canAddMore', true);
    $response->assertViewHas('maxMethods', PaymentMethod::MAX_PAYMENT_METHODS_PER_USER);
});

/*
|--------------------------------------------------------------------------
| Set as Default Tests
|--------------------------------------------------------------------------
*/

it('sets a payment method as default (BR-159, BR-160)', function () {
    $defaultMethod = PaymentMethod::factory()->forUser($this->user)->default()->mtnMomo()->create(['label' => 'Old Default']);
    $otherMethod = PaymentMethod::factory()->forUser($this->user)->orangeMoney()->create(['label' => 'New Default']);

    $response = $this->actingAs($this->user)->post('/profile/payment-methods/'.$otherMethod->id.'/set-default');

    $response->assertRedirect();
    expect($otherMethod->fresh()->is_default)->toBeTrue();
    expect($defaultMethod->fresh()->is_default)->toBeFalse();
});

it('shows toast on successful set default', function () {
    $defaultMethod = PaymentMethod::factory()->forUser($this->user)->default()->mtnMomo()->create(['label' => 'Old']);
    $otherMethod = PaymentMethod::factory()->forUser($this->user)->orangeMoney()->create(['label' => 'New']);

    $response = $this->actingAs($this->user)->post('/profile/payment-methods/'.$otherMethod->id.'/set-default');

    $response->assertRedirect();
    $response->assertSessionHas('toast.type', 'success');
    $response->assertSessionHas('toast.message', __('Default payment method updated.'));
});

it('returns info toast when method is already default', function () {
    $method = PaymentMethod::factory()->forUser($this->user)->default()->create();

    $response = $this->actingAs($this->user)->post('/profile/payment-methods/'.$method->id.'/set-default');

    $response->assertRedirect();
    $response->assertSessionHas('toast.type', 'info');
});

it('cannot set another user payment method as default', function () {
    $otherUser = createUser();
    $otherMethod = PaymentMethod::factory()->forUser($otherUser)->create();

    $response = $this->actingAs($this->user)->post('/profile/payment-methods/'.$otherMethod->id.'/set-default');

    $response->assertForbidden();
});

it('requires authentication to set default', function () {
    $method = PaymentMethod::factory()->create();

    $response = $this->post('/profile/payment-methods/'.$method->id.'/set-default');

    $response->assertRedirect('/login');
});

it('logs activity when setting default payment method', function () {
    $defaultMethod = PaymentMethod::factory()->forUser($this->user)->default()->mtnMomo()->create(['label' => 'Old']);
    $otherMethod = PaymentMethod::factory()->forUser($this->user)->orangeMoney()->create(['label' => 'New Default']);

    $this->actingAs($this->user)->post('/profile/payment-methods/'.$otherMethod->id.'/set-default');

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'payment_methods',
        'event' => 'updated',
        'subject_type' => PaymentMethod::class,
        'subject_id' => $otherMethod->id,
        'causer_type' => User::class,
        'causer_id' => $this->user->id,
    ]);
});

it('only removes default from the current user methods', function () {
    $otherUser = createUser();
    $otherDefault = PaymentMethod::factory()->forUser($otherUser)->default()->mtnMomo()->create(['label' => 'Other Default']);

    $defaultMethod = PaymentMethod::factory()->forUser($this->user)->default()->mtnMomo()->create(['label' => 'My Old']);
    $newDefault = PaymentMethod::factory()->forUser($this->user)->orangeMoney()->create(['label' => 'My New']);

    $this->actingAs($this->user)->post('/profile/payment-methods/'.$newDefault->id.'/set-default');

    // Other user's default should not be changed
    expect($otherDefault->fresh()->is_default)->toBeTrue();
    // My old default should be removed
    expect($defaultMethod->fresh()->is_default)->toBeFalse();
    // My new default should be set
    expect($newDefault->fresh()->is_default)->toBeTrue();
});
