<?php

use App\Models\Address;
use App\Models\Quarter;
use App\Models\Town;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| F-034: Delivery Address List — Feature Tests
|--------------------------------------------------------------------------
|
| Tests for the address list page and set-default functionality.
|
| BR-128: All addresses displayed, default first.
| BR-129: Default address visually distinguished.
| BR-130: Only one address can be default at a time.
| BR-131: Setting new default removes previous default.
| BR-132: "Add Address" button shown only if < 5 addresses.
| BR-133: Each address has edit and delete actions.
| BR-134: Localized town/quarter names.
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
| Address List Page Tests
|--------------------------------------------------------------------------
*/

it('shows the address list page for authenticated users', function () {
    $response = $this->actingAs($this->user)->get('/profile/addresses');

    $response->assertOk();
    $response->assertViewIs('profile.addresses.index');
});

it('redirects unauthenticated users to login', function () {
    $response = $this->get('/profile/addresses');

    $response->assertRedirect('/login');
});

it('displays all saved addresses with their details', function () {
    $address1 = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Home',
        'neighbourhood' => 'Carrefour Agip',
        'is_default' => true,
    ]);

    $address2 = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Office',
        'neighbourhood' => 'Total Makepe',
        'is_default' => false,
    ]);

    $response = $this->actingAs($this->user)->get('/profile/addresses');

    $response->assertOk();
    $response->assertSee('Home');
    $response->assertSee('Office');
    $response->assertSee('Douala');
    $response->assertSee('Akwa');
    $response->assertSee('Carrefour Agip');
    $response->assertSee('Total Makepe');
});

it('shows default badge on the default address (BR-129)', function () {
    Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Home',
        'is_default' => true,
    ]);

    Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Office',
        'is_default' => false,
    ]);

    $response = $this->actingAs($this->user)->get('/profile/addresses');

    $response->assertOk();
    $response->assertSee(__('Default'));
});

it('orders addresses with default first (BR-128)', function () {
    Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Office',
        'is_default' => false,
    ]);

    Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Home',
        'is_default' => true,
    ]);

    $response = $this->actingAs($this->user)->get('/profile/addresses');
    $response->assertOk();

    $viewAddresses = $response->viewData('addresses');
    expect($viewAddresses->first()->is_default)->toBeTrue();
    expect($viewAddresses->first()->label)->toBe('Home');
});

it('shows empty state when user has no addresses', function () {
    $response = $this->actingAs($this->user)->get('/profile/addresses');

    $response->assertOk();
    $response->assertSee(__('You have no saved addresses.'));
    $response->assertSee(__('Add Your First Address'));
});

it('shows Add Address button when user has fewer than 5 addresses (BR-132)', function () {
    Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Home',
        'is_default' => true,
    ]);

    $response = $this->actingAs($this->user)->get('/profile/addresses');

    $response->assertOk();
    $response->assertSee(__('Add Address'));
});

it('hides Add Address button when user has 5 addresses (BR-132)', function () {
    $labels = ['Home', 'Office', 'School', 'Gym', 'Church'];
    foreach ($labels as $index => $label) {
        Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
            'label' => $label,
            'is_default' => $index === 0,
        ]);
    }

    $response = $this->actingAs($this->user)->get('/profile/addresses');

    $response->assertOk();
    $response->assertDontSee(__('Add Address'));
    $viewData = $response->viewData('canAddMore');
    expect($viewData)->toBeFalse();
});

it('shows edit and delete links for each address (BR-133)', function () {
    $address = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Home',
        'is_default' => true,
    ]);

    $response = $this->actingAs($this->user)->get('/profile/addresses');

    $response->assertOk();
    $response->assertSee('/profile/addresses/'.$address->id.'/edit');
    $response->assertSee('/profile/addresses/'.$address->id.'/delete');
});

it('does not show other users addresses', function () {
    $otherUser = createUser();
    Address::factory()->forUser($otherUser)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Other Home',
        'is_default' => true,
    ]);

    $response = $this->actingAs($this->user)->get('/profile/addresses');

    $response->assertOk();
    $response->assertDontSee('Other Home');
});

it('eager loads town and quarter relationships', function () {
    Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Home',
        'is_default' => true,
    ]);

    $response = $this->actingAs($this->user)->get('/profile/addresses');
    $response->assertOk();

    $addresses = $response->viewData('addresses');
    expect($addresses->first()->relationLoaded('town'))->toBeTrue();
    expect($addresses->first()->relationLoaded('quarter'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Set Default Address Tests
|--------------------------------------------------------------------------
*/

it('can set an address as default', function () {
    $address1 = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Home',
        'is_default' => true,
    ]);

    $address2 = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Office',
        'is_default' => false,
    ]);

    $response = $this->actingAs($this->user)->post('/profile/addresses/'.$address2->id.'/set-default');

    $response->assertRedirect();
    expect($address2->fresh()->is_default)->toBeTrue();
    expect($address1->fresh()->is_default)->toBeFalse();
});

it('removes default from previous address when setting new default (BR-131)', function () {
    $address1 = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Home',
        'is_default' => true,
    ]);

    $address2 = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Office',
        'is_default' => false,
    ]);

    $address3 = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'School',
        'is_default' => false,
    ]);

    $this->actingAs($this->user)->post('/profile/addresses/'.$address3->id.'/set-default');

    expect($address1->fresh()->is_default)->toBeFalse();
    expect($address2->fresh()->is_default)->toBeFalse();
    expect($address3->fresh()->is_default)->toBeTrue();
});

it('prevents setting another users address as default', function () {
    $otherUser = createUser();
    $otherAddress = Address::factory()->forUser($otherUser)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Other Home',
        'is_default' => false,
    ]);

    $response = $this->actingAs($this->user)->post('/profile/addresses/'.$otherAddress->id.'/set-default');

    $response->assertForbidden();
    expect($otherAddress->fresh()->is_default)->toBeFalse();
});

it('returns info toast when address is already default', function () {
    $address = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Home',
        'is_default' => true,
    ]);

    $response = $this->actingAs($this->user)->post('/profile/addresses/'.$address->id.'/set-default');

    $response->assertRedirect();
    $response->assertSessionHas('toast.type', 'info');
});

it('logs activity when setting default address', function () {
    $address = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Office',
        'is_default' => false,
    ]);

    $this->actingAs($this->user)->post('/profile/addresses/'.$address->id.'/set-default');

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'addresses',
        'event' => 'updated',
        'subject_type' => Address::class,
        'subject_id' => $address->id,
        'causer_type' => User::class,
        'causer_id' => $this->user->id,
    ]);
});

it('requires authentication to set default address', function () {
    $address = Address::factory()->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Home',
        'is_default' => false,
    ]);

    $response = $this->post('/profile/addresses/'.$address->id.'/set-default');

    $response->assertRedirect('/login');
});

it('returns 404 for non-existent address', function () {
    $response = $this->actingAs($this->user)->post('/profile/addresses/99999/set-default');

    $response->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Cross-Domain Access Tests
|--------------------------------------------------------------------------
*/

it('address list page is accessible on main domain', function () {
    $response = $this->actingAs($this->user)->get('/profile/addresses');

    $response->assertOk();
});
