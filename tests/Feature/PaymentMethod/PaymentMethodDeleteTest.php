<?php

use App\Models\PaymentMethod;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| F-040: Delete Payment Method — Feature Tests
|--------------------------------------------------------------------------
|
| Tests for the payment method deletion functionality including confirmation
| modal markup, default reassignment, ownership checks, and activity logging.
|
| BR-170: Confirmation dialog before deletion.
| BR-171: Payment methods can always be deleted (no order dependency).
| BR-172: If deleted method was default, first remaining becomes default.
| BR-173: Users can only delete their own payment methods.
| BR-174: Hard delete (permanent).
|
*/

beforeEach(function () {
    $this->seedRolesAndPermissions();
    $this->user = createUser();
});

/*
|--------------------------------------------------------------------------
| Happy Path — Delete Payment Method
|--------------------------------------------------------------------------
*/

it('can delete a payment method via DELETE request', function () {
    $defaultMethod = PaymentMethod::factory()->forUser($this->user)->default()->mtnMomo()->create(['label' => 'MTN Main']);
    $otherMethod = PaymentMethod::factory()->forUser($this->user)->orangeMoney()->create(['label' => 'Orange Backup']);

    $response = $this->actingAs($this->user)->delete('/profile/payment-methods/'.$otherMethod->id);

    $response->assertRedirect();
    $this->assertDatabaseMissing('payment_methods', ['id' => $otherMethod->id]);
    $this->assertDatabaseHas('payment_methods', ['id' => $defaultMethod->id]);
});

it('shows success toast after deletion', function () {
    $method = PaymentMethod::factory()->forUser($this->user)->default()->mtnMomo()->create(['label' => 'MTN Test']);

    $response = $this->actingAs($this->user)->delete('/profile/payment-methods/'.$method->id);

    $response->assertRedirect();
    $response->assertSessionHas('toast.type', 'success');
    $response->assertSessionHas('toast.message', __('Payment method deleted.'));
});

it('permanently removes the payment method from the database (BR-174)', function () {
    $method = PaymentMethod::factory()->forUser($this->user)->default()->mtnMomo()->create(['label' => 'MTN Old']);
    $methodId = $method->id;

    $this->actingAs($this->user)->delete('/profile/payment-methods/'.$methodId);

    $this->assertDatabaseMissing('payment_methods', ['id' => $methodId]);
    expect(PaymentMethod::find($methodId))->toBeNull();
});

it('can delete when user has only one payment method (BR-171)', function () {
    $method = PaymentMethod::factory()->forUser($this->user)->default()->mtnMomo()->create(['label' => 'Only Method']);

    $response = $this->actingAs($this->user)->delete('/profile/payment-methods/'.$method->id);

    $response->assertRedirect();
    $this->assertDatabaseMissing('payment_methods', ['id' => $method->id]);
    expect($this->user->paymentMethods()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Default Reassignment (BR-172)
|--------------------------------------------------------------------------
*/

it('reassigns default to first remaining method when default is deleted (BR-172)', function () {
    $defaultMethod = PaymentMethod::factory()->forUser($this->user)->default()->mtnMomo()->create(['label' => 'MTN Default']);
    $otherMethod = PaymentMethod::factory()->forUser($this->user)->orangeMoney()->create(['label' => 'Orange Backup']);

    $this->actingAs($this->user)->delete('/profile/payment-methods/'.$defaultMethod->id);

    $this->assertDatabaseMissing('payment_methods', ['id' => $defaultMethod->id]);
    expect($otherMethod->fresh()->is_default)->toBeTrue();
});

it('does not change default when non-default method is deleted', function () {
    $defaultMethod = PaymentMethod::factory()->forUser($this->user)->default()->mtnMomo()->create(['label' => 'MTN Default']);
    $otherMethod = PaymentMethod::factory()->forUser($this->user)->orangeMoney()->create(['label' => 'Orange Secondary']);

    $this->actingAs($this->user)->delete('/profile/payment-methods/'.$otherMethod->id);

    expect($defaultMethod->fresh()->is_default)->toBeTrue();
    $this->assertDatabaseMissing('payment_methods', ['id' => $otherMethod->id]);
});

it('reassigns default to first alphabetically ordered remaining method', function () {
    $defaultMethod = PaymentMethod::factory()->forUser($this->user)->default()->mtnMomo()->create(['label' => 'MTN Default']);
    $methodB = PaymentMethod::factory()->forUser($this->user)->orangeMoney()->create(['label' => 'Alpha Method']);
    $methodC = PaymentMethod::factory()->forUser($this->user)->mtnMomo()->create(['label' => 'Beta Method']);

    $this->actingAs($this->user)->delete('/profile/payment-methods/'.$defaultMethod->id);

    expect($methodB->fresh()->is_default)->toBeTrue();
    expect($methodC->fresh()->is_default)->toBeFalse();
});

it('has no default when all payment methods are deleted', function () {
    $method = PaymentMethod::factory()->forUser($this->user)->default()->mtnMomo()->create(['label' => 'Only Method']);

    $this->actingAs($this->user)->delete('/profile/payment-methods/'.$method->id);

    expect($this->user->paymentMethods()->count())->toBe(0);
    expect($this->user->paymentMethods()->where('is_default', true)->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Ownership Protection (BR-173)
|--------------------------------------------------------------------------
*/

it('prevents deleting another user payment method (BR-173)', function () {
    $otherUser = createUser();
    $otherMethod = PaymentMethod::factory()->forUser($otherUser)->default()->mtnMomo()->create(['label' => 'Other MTN']);

    $response = $this->actingAs($this->user)->delete('/profile/payment-methods/'.$otherMethod->id);

    $response->assertForbidden();
    $this->assertDatabaseHas('payment_methods', ['id' => $otherMethod->id]);
});

it('requires authentication to delete a payment method', function () {
    $method = PaymentMethod::factory()->create(['label' => 'Test Method']);

    $response = $this->delete('/profile/payment-methods/'.$method->id);

    $response->assertRedirect('/login');
    $this->assertDatabaseHas('payment_methods', ['id' => $method->id]);
});

it('returns 404 for non-existent payment method', function () {
    $response = $this->actingAs($this->user)->delete('/profile/payment-methods/99999');

    $response->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Activity Logging
|--------------------------------------------------------------------------
*/

it('logs activity when payment method is deleted', function () {
    $method = PaymentMethod::factory()->forUser($this->user)->default()->mtnMomo()->create(['label' => 'MTN Test']);

    $this->actingAs($this->user)->delete('/profile/payment-methods/'.$method->id);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'payment_methods',
        'event' => 'deleted',
        'causer_type' => User::class,
        'causer_id' => $this->user->id,
    ]);
});

it('logs the method label, provider, and default status in activity properties', function () {
    $method = PaymentMethod::factory()->forUser($this->user)->default()->mtnMomo()->create(['label' => 'My MTN MoMo']);

    $this->actingAs($this->user)->delete('/profile/payment-methods/'.$method->id);

    $activity = \Spatie\Activitylog\Models\Activity::where('log_name', 'payment_methods')
        ->where('event', 'deleted')
        ->where('causer_id', $this->user->id)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();

    $props = $activity->properties;
    expect($props)->not->toBeNull();
    $label = $props->get('label') ?? $props->get('attributes.label');
    $provider = $props->get('provider') ?? $props->get('attributes.provider');
    $wasDefault = $props->get('was_default') ?? $props->get('attributes.was_default');
    expect($label)->toBe('My MTN MoMo');
    expect($provider)->toBe('mtn_momo');
    expect($wasDefault)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Edge Cases
|--------------------------------------------------------------------------
*/

it('can delete all methods one by one leaving empty list', function () {
    $method1 = PaymentMethod::factory()->forUser($this->user)->default()->mtnMomo()->create(['label' => 'MTN Main']);
    $method2 = PaymentMethod::factory()->forUser($this->user)->orangeMoney()->create(['label' => 'Orange Backup']);

    // Delete first (default) method
    $this->actingAs($this->user)->delete('/profile/payment-methods/'.$method1->id);
    expect($method2->fresh()->is_default)->toBeTrue();

    // Delete second method
    $this->actingAs($this->user)->delete('/profile/payment-methods/'.$method2->id);
    expect($this->user->paymentMethods()->count())->toBe(0);
});

it('payment method list shows empty state after deleting all methods', function () {
    $method = PaymentMethod::factory()->forUser($this->user)->default()->mtnMomo()->create(['label' => 'Last Method']);

    $this->actingAs($this->user)->delete('/profile/payment-methods/'.$method->id);

    $response = $this->actingAs($this->user)->get('/profile/payment-methods');
    $response->assertOk();
    $response->assertSee(__('You have no saved payment methods.'));
});

it('does not affect other users payment methods when deleting', function () {
    $otherUser = createUser();
    $otherMethod = PaymentMethod::factory()->forUser($otherUser)->default()->mtnMomo()->create(['label' => 'Other MTN']);

    $myMethod = PaymentMethod::factory()->forUser($this->user)->default()->orangeMoney()->create(['label' => 'My Orange']);

    $this->actingAs($this->user)->delete('/profile/payment-methods/'.$myMethod->id);

    $this->assertDatabaseHas('payment_methods', ['id' => $otherMethod->id]);
    $this->assertDatabaseMissing('payment_methods', ['id' => $myMethod->id]);
});

/*
|--------------------------------------------------------------------------
| View Tests — Delete Button and Modal Present in List
|--------------------------------------------------------------------------
*/

it('shows delete button for each payment method in the list', function () {
    $method = PaymentMethod::factory()->forUser($this->user)->default()->mtnMomo()->create(['label' => 'MTN Main']);

    $response = $this->actingAs($this->user)->get('/profile/payment-methods');

    $response->assertOk();
    $response->assertSee('confirmDelete('.$method->id);
});

it('shows confirmation modal markup in the page', function () {
    PaymentMethod::factory()->forUser($this->user)->default()->mtnMomo()->create(['label' => 'MTN Test']);

    $response = $this->actingAs($this->user)->get('/profile/payment-methods');

    $response->assertOk();
    $response->assertSee(__('Delete this payment method?'));
    $response->assertSee(__('This cannot be undone.'));
});

it('delete route is accessible on main domain', function () {
    $method = PaymentMethod::factory()->forUser($this->user)->default()->mtnMomo()->create(['label' => 'MTN Main']);

    $response = $this->actingAs($this->user)->delete('/profile/payment-methods/'.$method->id);

    $response->assertRedirect();
});
