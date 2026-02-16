<?php

use App\Models\Address;
use App\Models\Quarter;
use App\Models\Town;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| F-035: Edit Delivery Address — Feature Tests
|--------------------------------------------------------------------------
|
| Tests for the edit address page and update functionality.
|
| BR-135: Same validation rules as add form (F-033).
| BR-136: Form pre-populated with current address values.
| BR-137: Label uniqueness excludes the current address.
| BR-138: Town change resets quarter dropdown.
| BR-139: Users can only edit their own addresses.
| BR-140: Save via Gale without page reload.
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
    $this->address = Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Home',
        'neighbourhood' => 'Carrefour Agip',
        'additional_directions' => 'Behind the blue pharmacy',
        'is_default' => true,
    ]);
});

/*
|--------------------------------------------------------------------------
| Edit Form Display Tests (BR-136)
|--------------------------------------------------------------------------
*/

it('shows the edit form for authenticated users', function () {
    $response = $this->actingAs($this->user)->get('/profile/addresses/'.$this->address->id.'/edit');

    $response->assertOk();
    $response->assertViewIs('profile.addresses.edit');
});

it('pre-populates the edit form with current address data (BR-136)', function () {
    $response = $this->actingAs($this->user)->get('/profile/addresses/'.$this->address->id.'/edit');

    $response->assertOk();
    $response->assertViewHas('address', function ($address) {
        return $address->id === $this->address->id
            && $address->label === 'Home'
            && $address->town_id === $this->town->id
            && $address->quarter_id === $this->quarter->id
            && $address->neighbourhood === 'Carrefour Agip'
            && $address->additional_directions === 'Behind the blue pharmacy';
    });
});

it('passes towns list to the edit form', function () {
    $response = $this->actingAs($this->user)->get('/profile/addresses/'.$this->address->id.'/edit');

    $response->assertOk();
    $response->assertViewHas('towns');
    $towns = $response->viewData('towns');
    expect($towns)->toHaveCount(1);
    expect($towns->first()->id)->toBe($this->town->id);
});

it('passes pre-loaded quarters for current town to edit form (BR-138)', function () {
    $response = $this->actingAs($this->user)->get('/profile/addresses/'.$this->address->id.'/edit');

    $response->assertOk();
    $response->assertViewHas('quarters');
    $quarters = $response->viewData('quarters');
    expect($quarters)->toBeArray();
    expect($quarters)->toHaveCount(1);
    expect($quarters[0]['id'])->toBe($this->quarter->id);
    expect($quarters[0]['name'])->toBe('Akwa');
});

it('redirects unauthenticated users to login', function () {
    $response = $this->get('/profile/addresses/'.$this->address->id.'/edit');

    $response->assertRedirect('/login');
});

it('returns 404 for non-existent address', function () {
    $response = $this->actingAs($this->user)->get('/profile/addresses/99999/edit');

    $response->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Authorization Tests (BR-139)
|--------------------------------------------------------------------------
*/

it('prevents editing another users address (BR-139)', function () {
    $otherUser = createUser();
    $otherAddress = Address::factory()->forUser($otherUser)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Other Home',
    ]);

    $response = $this->actingAs($this->user)->get('/profile/addresses/'.$otherAddress->id.'/edit');

    $response->assertForbidden();
});

it('prevents updating another users address via POST (BR-139)', function () {
    $otherUser = createUser();
    $otherAddress = Address::factory()->forUser($otherUser)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Other Home',
    ]);

    $response = $this->actingAs($this->user)->post('/profile/addresses/'.$otherAddress->id, [
        'label' => 'Hijacked',
        'town_id' => $this->town->id,
        'quarter_id' => $this->quarter->id,
    ]);

    $response->assertForbidden();
    expect($otherAddress->fresh()->label)->toBe('Other Home');
});

/*
|--------------------------------------------------------------------------
| Address Update Tests
|--------------------------------------------------------------------------
*/

it('updates address with valid data', function () {
    $response = $this->actingAs($this->user)->post('/profile/addresses/'.$this->address->id, [
        'label' => 'Work',
        'town_id' => $this->town->id,
        'quarter_id' => $this->quarter->id,
        'neighbourhood' => 'Rue de la Paix',
        'additional_directions' => 'Next to the market',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('toast.type', 'success');
    $response->assertSessionHas('toast.message', __('Address updated successfully.'));

    $updated = $this->address->fresh();
    expect($updated->label)->toBe('Work');
    expect($updated->neighbourhood)->toBe('Rue de la Paix');
    expect($updated->additional_directions)->toBe('Next to the market');
});

it('updates address with changed town and quarter', function () {
    $newTown = Town::factory()->create(['name_en' => 'Yaounde', 'name_fr' => 'Yaoundé', 'is_active' => true]);
    $newQuarter = Quarter::factory()->create([
        'town_id' => $newTown->id,
        'name_en' => 'Bastos',
        'name_fr' => 'Bastos',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->post('/profile/addresses/'.$this->address->id, [
        'label' => 'Home',
        'town_id' => $newTown->id,
        'quarter_id' => $newQuarter->id,
        'neighbourhood' => 'Near embassy',
    ]);

    $response->assertRedirect();
    $updated = $this->address->fresh();
    expect($updated->town_id)->toBe($newTown->id);
    expect($updated->quarter_id)->toBe($newQuarter->id);
    expect($updated->neighbourhood)->toBe('Near embassy');
});

it('allows keeping the same label during update (BR-137)', function () {
    $response = $this->actingAs($this->user)->post('/profile/addresses/'.$this->address->id, [
        'label' => 'Home',
        'town_id' => $this->town->id,
        'quarter_id' => $this->quarter->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('toast.type', 'success');
});

it('clears optional fields when set to null', function () {
    $response = $this->actingAs($this->user)->post('/profile/addresses/'.$this->address->id, [
        'label' => 'Home',
        'town_id' => $this->town->id,
        'quarter_id' => $this->quarter->id,
        'neighbourhood' => null,
        'additional_directions' => null,
    ]);

    $response->assertRedirect();
    $updated = $this->address->fresh();
    expect($updated->neighbourhood)->toBeNull();
    expect($updated->additional_directions)->toBeNull();
});

it('updates latitude and longitude when provided', function () {
    $response = $this->actingAs($this->user)->post('/profile/addresses/'.$this->address->id, [
        'label' => 'Home',
        'town_id' => $this->town->id,
        'quarter_id' => $this->quarter->id,
        'latitude' => 4.0510564,
        'longitude' => 9.7678687,
    ]);

    $response->assertRedirect();
    $updated = $this->address->fresh();
    expect((float) $updated->latitude)->toBe(4.0510564);
    expect((float) $updated->longitude)->toBe(9.7678687);
});

/*
|--------------------------------------------------------------------------
| Validation Tests (BR-135, BR-137)
|--------------------------------------------------------------------------
*/

it('validates label is required', function () {
    $response = $this->actingAs($this->user)->post('/profile/addresses/'.$this->address->id, [
        'label' => '',
        'town_id' => $this->town->id,
        'quarter_id' => $this->quarter->id,
    ]);

    $response->assertSessionHasErrors('label');
});

it('validates label max length', function () {
    $response = $this->actingAs($this->user)->post('/profile/addresses/'.$this->address->id, [
        'label' => str_repeat('A', 51),
        'town_id' => $this->town->id,
        'quarter_id' => $this->quarter->id,
    ]);

    $response->assertSessionHasErrors('label');
});

it('validates duplicate label among users other addresses (BR-137)', function () {
    Address::factory()->forUser($this->user)->forTownAndQuarter($this->town, $this->quarter)->create([
        'label' => 'Office',
    ]);

    $response = $this->actingAs($this->user)->post('/profile/addresses/'.$this->address->id, [
        'label' => 'Office',
        'town_id' => $this->town->id,
        'quarter_id' => $this->quarter->id,
    ]);

    $response->assertSessionHasErrors('label');
});

it('validates town is required', function () {
    $response = $this->actingAs($this->user)->post('/profile/addresses/'.$this->address->id, [
        'label' => 'Home',
        'town_id' => '',
        'quarter_id' => $this->quarter->id,
    ]);

    $response->assertSessionHasErrors('town_id');
});

it('validates town must exist', function () {
    $response = $this->actingAs($this->user)->post('/profile/addresses/'.$this->address->id, [
        'label' => 'Home',
        'town_id' => 99999,
        'quarter_id' => $this->quarter->id,
    ]);

    $response->assertSessionHasErrors('town_id');
});

it('validates quarter is required', function () {
    $response = $this->actingAs($this->user)->post('/profile/addresses/'.$this->address->id, [
        'label' => 'Home',
        'town_id' => $this->town->id,
        'quarter_id' => '',
    ]);

    $response->assertSessionHasErrors('quarter_id');
});

it('validates quarter must belong to selected town', function () {
    $otherTown = Town::factory()->create(['name_en' => 'Yaounde', 'is_active' => true]);
    $otherQuarter = Quarter::factory()->create([
        'town_id' => $otherTown->id,
        'name_en' => 'Bastos',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->post('/profile/addresses/'.$this->address->id, [
        'label' => 'Home',
        'town_id' => $this->town->id,
        'quarter_id' => $otherQuarter->id,
    ]);

    $response->assertSessionHasErrors('quarter_id');
});

it('validates additional directions max length', function () {
    $response = $this->actingAs($this->user)->post('/profile/addresses/'.$this->address->id, [
        'label' => 'Home',
        'town_id' => $this->town->id,
        'quarter_id' => $this->quarter->id,
        'additional_directions' => str_repeat('A', 501),
    ]);

    $response->assertSessionHasErrors('additional_directions');
});

it('validates neighbourhood max length', function () {
    $response = $this->actingAs($this->user)->post('/profile/addresses/'.$this->address->id, [
        'label' => 'Home',
        'town_id' => $this->town->id,
        'quarter_id' => $this->quarter->id,
        'neighbourhood' => str_repeat('A', 256),
    ]);

    $response->assertSessionHasErrors('neighbourhood');
});

/*
|--------------------------------------------------------------------------
| Activity Logging Tests
|--------------------------------------------------------------------------
*/

it('logs activity when address is updated', function () {
    $this->actingAs($this->user)->post('/profile/addresses/'.$this->address->id, [
        'label' => 'Updated Home',
        'town_id' => $this->town->id,
        'quarter_id' => $this->quarter->id,
    ]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'addresses',
        'event' => 'updated',
        'subject_type' => Address::class,
        'subject_id' => $this->address->id,
        'causer_type' => User::class,
        'causer_id' => $this->user->id,
    ]);
});

/*
|--------------------------------------------------------------------------
| Does Not Affect Default Status
|--------------------------------------------------------------------------
*/

it('does not change default status when editing address', function () {
    $response = $this->actingAs($this->user)->post('/profile/addresses/'.$this->address->id, [
        'label' => 'Updated Label',
        'town_id' => $this->town->id,
        'quarter_id' => $this->quarter->id,
    ]);

    $response->assertRedirect();
    expect($this->address->fresh()->is_default)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Route Registration Tests
|--------------------------------------------------------------------------
*/

it('has a named route for address edit', function () {
    $url = route('addresses.edit', $this->address);
    expect($url)->toContain('/profile/addresses/'.$this->address->id.'/edit');
});

it('has a named route for address update', function () {
    $url = route('addresses.update', $this->address);
    expect($url)->toContain('/profile/addresses/'.$this->address->id);
});
