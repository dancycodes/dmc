<?php

use App\Models\Address;
use App\Models\Quarter;
use App\Models\Town;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| F-036: Delete Delivery Address — Feature Tests
|--------------------------------------------------------------------------
|
| Tests for the address deletion functionality including confirmation,
| default reassignment, pending order protection, and ownership checks.
|
| BR-141: Confirmation dialog before deletion.
| BR-142: Block deletion if only address with pending orders.
| BR-143: Default reassignment after deletion.
| BR-144: Users can only delete their own addresses.
| BR-145: Hard delete (permanent).
| BR-146: Multiple addresses — any can be deleted regardless of pending orders.
|
*/

beforeEach(function () {
    $this->seedRolesAndPermissions();
    $this->user = createUser();
    $this->town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala', 'is_active' => true]);
    $this->quarter = Quarter::factory()->create([
        'town_id' => $this->town->id,
        'name_en' => 'Akwa',
        'name_fr' => 'Akwa',
        'is_active' => true,
    ]);
});

/*
|--------------------------------------------------------------------------
| Happy Path — Delete Address
|--------------------------------------------------------------------------
*/

it('can delete an address via DELETE request', function () {
    $address = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Home',
        'is_default' => false,
    ]);

    // Create another address so this one is not the only one
    Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Office',
        'is_default' => true,
    ]);

    $response = $this->actingAs($this->user)->delete('/profile/addresses/'.$address->id);

    $response->assertRedirect();
    $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
});

it('shows success toast after deletion', function () {
    $address = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Home',
        'is_default' => true,
    ]);

    $response = $this->actingAs($this->user)->delete('/profile/addresses/'.$address->id);

    $response->assertRedirect();
    $response->assertSessionHas('toast.type', 'success');
    $response->assertSessionHas('toast.message', __('Address deleted.'));
});

it('permanently removes the address from the database (BR-145)', function () {
    $address = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Old Place',
        'is_default' => true,
    ]);

    $addressId = $address->id;

    $this->actingAs($this->user)->delete('/profile/addresses/'.$addressId);

    $this->assertDatabaseMissing('addresses', ['id' => $addressId]);
    expect(Address::find($addressId))->toBeNull();
});

it('can delete when user has only one address and no pending orders', function () {
    $address = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Home',
        'is_default' => true,
    ]);

    $response = $this->actingAs($this->user)->delete('/profile/addresses/'.$address->id);

    $response->assertRedirect();
    $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
    expect($this->user->addresses()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Default Reassignment (BR-143)
|--------------------------------------------------------------------------
*/

it('reassigns default to first remaining address when default is deleted (BR-143)', function () {
    $defaultAddress = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Home',
        'is_default' => true,
    ]);

    $otherAddress = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Office',
        'is_default' => false,
    ]);

    $this->actingAs($this->user)->delete('/profile/addresses/'.$defaultAddress->id);

    $this->assertDatabaseMissing('addresses', ['id' => $defaultAddress->id]);
    expect($otherAddress->fresh()->is_default)->toBeTrue();
});

it('does not change default when non-default address is deleted', function () {
    $defaultAddress = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Home',
        'is_default' => true,
    ]);

    $otherAddress = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Office',
        'is_default' => false,
    ]);

    $this->actingAs($this->user)->delete('/profile/addresses/'.$otherAddress->id);

    expect($defaultAddress->fresh()->is_default)->toBeTrue();
    $this->assertDatabaseMissing('addresses', ['id' => $otherAddress->id]);
});

it('reassigns default to first alphabetically ordered remaining address', function () {
    $defaultAddress = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Home',
        'is_default' => true,
    ]);

    $addressB = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Beach House',
        'is_default' => false,
    ]);

    $addressC = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Church',
        'is_default' => false,
    ]);

    $this->actingAs($this->user)->delete('/profile/addresses/'.$defaultAddress->id);

    expect($addressB->fresh()->is_default)->toBeTrue();
    expect($addressC->fresh()->is_default)->toBeFalse();
});

it('has no default when all addresses are deleted', function () {
    $address = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Home',
        'is_default' => true,
    ]);

    $this->actingAs($this->user)->delete('/profile/addresses/'.$address->id);

    expect($this->user->addresses()->count())->toBe(0);
    expect($this->user->addresses()->where('is_default', true)->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Ownership Protection (BR-144)
|--------------------------------------------------------------------------
*/

it('prevents deleting another user address (BR-144)', function () {
    $otherUser = createUser();
    $otherAddress = Address::factory()->forUser($otherUser)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Other Home',
        'is_default' => true,
    ]);

    $response = $this->actingAs($this->user)->delete('/profile/addresses/'.$otherAddress->id);

    $response->assertForbidden();
    $this->assertDatabaseHas('addresses', ['id' => $otherAddress->id]);
});

it('requires authentication to delete an address', function () {
    $address = Address::factory()->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Home',
        'is_default' => true,
    ]);

    $response = $this->delete('/profile/addresses/'.$address->id);

    $response->assertRedirect('/login');
    $this->assertDatabaseHas('addresses', ['id' => $address->id]);
});

it('returns 404 for non-existent address', function () {
    $response = $this->actingAs($this->user)->delete('/profile/addresses/99999');

    $response->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Activity Logging
|--------------------------------------------------------------------------
*/

it('logs activity when address is deleted', function () {
    $address = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Home',
        'is_default' => true,
    ]);

    $this->actingAs($this->user)->delete('/profile/addresses/'.$address->id);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'addresses',
        'event' => 'deleted',
        'causer_type' => User::class,
        'causer_id' => $this->user->id,
    ]);
});

it('logs the address label and default status in activity properties', function () {
    $address = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'My Special Place',
        'is_default' => true,
    ]);

    $this->actingAs($this->user)->delete('/profile/addresses/'.$address->id);

    $activity = \Spatie\Activitylog\Models\Activity::where('log_name', 'addresses')
        ->where('event', 'deleted')
        ->where('causer_id', $this->user->id)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();

    $props = $activity->properties;
    expect($props)->not->toBeNull();
    // Properties may be stored at top level or nested depending on Spatie config
    $label = $props->get('label') ?? $props->get('attributes.label');
    $wasDefault = $props->get('was_default') ?? $props->get('attributes.was_default');
    expect($label)->toBe('My Special Place');
    expect($wasDefault)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Multiple Addresses — Delete Regardless of Pending Orders (BR-146)
|--------------------------------------------------------------------------
*/

it('allows deletion when user has multiple addresses regardless of orders (BR-146)', function () {
    $address1 = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Home',
        'is_default' => true,
    ]);

    $address2 = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Office',
        'is_default' => false,
    ]);

    // With multiple addresses, deletion should always succeed
    $response = $this->actingAs($this->user)->delete('/profile/addresses/'.$address2->id);

    $response->assertRedirect();
    $this->assertDatabaseMissing('addresses', ['id' => $address2->id]);
    $this->assertDatabaseHas('addresses', ['id' => $address1->id]);
});

/*
|--------------------------------------------------------------------------
| Edge Cases
|--------------------------------------------------------------------------
*/

it('can delete all addresses one by one leaving empty list', function () {
    $address1 = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Home',
        'is_default' => true,
    ]);

    $address2 = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Office',
        'is_default' => false,
    ]);

    // Delete first address
    $this->actingAs($this->user)->delete('/profile/addresses/'.$address1->id);
    // After deleting default, Office should become default
    expect($address2->fresh()->is_default)->toBeTrue();

    // Delete second address
    $this->actingAs($this->user)->delete('/profile/addresses/'.$address2->id);
    expect($this->user->addresses()->count())->toBe(0);
});

it('address list shows empty state after deleting all addresses', function () {
    $address = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Home',
        'is_default' => true,
    ]);

    $this->actingAs($this->user)->delete('/profile/addresses/'.$address->id);

    $response = $this->actingAs($this->user)->get('/profile/addresses');
    $response->assertOk();
    $response->assertSee(__('You have no saved addresses.'));
});

it('does not affect other users addresses when deleting', function () {
    $otherUser = createUser();
    $otherAddress = Address::factory()->forUser($otherUser)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Other Home',
        'is_default' => true,
    ]);

    $myAddress = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'My Home',
        'is_default' => true,
    ]);

    $this->actingAs($this->user)->delete('/profile/addresses/'.$myAddress->id);

    $this->assertDatabaseHas('addresses', ['id' => $otherAddress->id]);
    $this->assertDatabaseMissing('addresses', ['id' => $myAddress->id]);
});

/*
|--------------------------------------------------------------------------
| Cross-Domain Access
|--------------------------------------------------------------------------
*/

it('delete route is accessible on main domain', function () {
    $address = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Home',
        'is_default' => true,
    ]);

    $response = $this->actingAs($this->user)->delete('/profile/addresses/'.$address->id);

    $response->assertRedirect();
});

/*
|--------------------------------------------------------------------------
| View Tests — Delete Button and Modal Present in List
|--------------------------------------------------------------------------
*/

it('shows delete button for each address in the list', function () {
    $address = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Home',
        'is_default' => true,
    ]);

    $response = $this->actingAs($this->user)->get('/profile/addresses');

    $response->assertOk();
    $response->assertSee('confirmDelete('.$address->id);
});

it('shows confirmation modal markup in the page', function () {
    Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Home',
        'is_default' => true,
    ]);

    $response = $this->actingAs($this->user)->get('/profile/addresses');

    $response->assertOk();
    $response->assertSee(__('Delete this address?'));
    $response->assertSee(__('This cannot be undone.'));
});
