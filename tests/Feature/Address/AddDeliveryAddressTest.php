<?php

use App\Models\Address;
use App\Models\Quarter;
use App\Models\Town;
use App\Models\User;

beforeEach(function () {
    $this->seedRolesAndPermissions();
});

describe('create form page', function () {
    it('requires authentication', function () {
        $this->get('/profile/addresses/create')
            ->assertRedirect('/login');
    });

    it('displays the add address form for authenticated user', function () {
        $user = User::factory()->create();
        Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);

        $this->actingAs($user)
            ->get('/profile/addresses/create')
            ->assertOk()
            ->assertSee(__('Add Delivery Address'))
            ->assertSee('Douala');
    });

    it('shows limit message when user has 5 addresses', function () {
        $user = User::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->forTown($town)->create();

        for ($i = 0; $i < 5; $i++) {
            Address::factory()->forUser($user)->forTownAndQuarter($town, $quarter)->create([
                'label' => "Address {$i}",
            ]);
        }

        $this->actingAs($user)
            ->get('/profile/addresses/create')
            ->assertOk()
            ->assertSee(__('Address Limit Reached'));
    });

    it('shows the form when user has fewer than 5 addresses', function () {
        $user = User::factory()->create();
        Town::factory()->create();

        $this->actingAs($user)
            ->get('/profile/addresses/create')
            ->assertOk()
            ->assertSee(__('Save Address'));
    });
});

describe('store address', function () {
    it('requires authentication to store an address', function () {
        $this->post('/profile/addresses')
            ->assertRedirect('/login');
    });

    it('saves a valid address', function () {
        $user = User::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->forTown($town)->create();

        $this->actingAs($user)
            ->post('/profile/addresses', [
                'label' => 'Home',
                'town_id' => $town->id,
                'quarter_id' => $quarter->id,
                'neighbourhood' => 'Carrefour Agip',
                'additional_directions' => 'Behind the blue pharmacy',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('addresses', [
            'user_id' => $user->id,
            'label' => 'Home',
            'town_id' => $town->id,
            'quarter_id' => $quarter->id,
            'neighbourhood' => 'Carrefour Agip',
        ]);
    });

    it('sets first address as default', function () {
        $user = User::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->forTown($town)->create();

        $this->actingAs($user)
            ->post('/profile/addresses', [
                'label' => 'Home',
                'town_id' => $town->id,
                'quarter_id' => $quarter->id,
            ]);

        $address = $user->addresses()->first();
        expect($address->is_default)->toBeTrue();
    });

    it('does not set subsequent addresses as default', function () {
        $user = User::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->forTown($town)->create();

        Address::factory()->forUser($user)->forTownAndQuarter($town, $quarter)->default()->create([
            'label' => 'Existing',
        ]);

        $this->actingAs($user)
            ->post('/profile/addresses', [
                'label' => 'Office',
                'town_id' => $town->id,
                'quarter_id' => $quarter->id,
            ]);

        $newAddress = $user->addresses()->where('label', 'Office')->first();
        expect($newAddress->is_default)->toBeFalse();
    });

    it('enforces maximum 5 addresses', function () {
        $user = User::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->forTown($town)->create();

        for ($i = 0; $i < 5; $i++) {
            Address::factory()->forUser($user)->forTownAndQuarter($town, $quarter)->create([
                'label' => "Address {$i}",
            ]);
        }

        $this->actingAs($user)
            ->post('/profile/addresses', [
                'label' => 'Sixth',
                'town_id' => $town->id,
                'quarter_id' => $quarter->id,
            ])
            ->assertRedirect();

        expect($user->addresses()->count())->toBe(5);
    });

    it('requires label', function () {
        $user = User::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->forTown($town)->create();

        $this->actingAs($user)
            ->post('/profile/addresses', [
                'label' => '',
                'town_id' => $town->id,
                'quarter_id' => $quarter->id,
            ])
            ->assertSessionHasErrors('label');
    });

    it('requires unique label per user', function () {
        $user = User::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->forTown($town)->create();

        Address::factory()->forUser($user)->forTownAndQuarter($town, $quarter)->create([
            'label' => 'Home',
        ]);

        $this->actingAs($user)
            ->post('/profile/addresses', [
                'label' => 'Home',
                'town_id' => $town->id,
                'quarter_id' => $quarter->id,
            ])
            ->assertSessionHasErrors('label');
    });

    it('allows same label for different users', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->forTown($town)->create();

        Address::factory()->forUser($user1)->forTownAndQuarter($town, $quarter)->create([
            'label' => 'Home',
        ]);

        $this->actingAs($user2)
            ->post('/profile/addresses', [
                'label' => 'Home',
                'town_id' => $town->id,
                'quarter_id' => $quarter->id,
            ])
            ->assertRedirect();

        expect($user2->addresses()->count())->toBe(1);
    });

    it('requires town', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/profile/addresses', [
                'label' => 'Home',
                'town_id' => '',
                'quarter_id' => 1,
            ])
            ->assertSessionHasErrors('town_id');
    });

    it('requires quarter', function () {
        $user = User::factory()->create();
        $town = Town::factory()->create();

        $this->actingAs($user)
            ->post('/profile/addresses', [
                'label' => 'Home',
                'town_id' => $town->id,
                'quarter_id' => '',
            ])
            ->assertSessionHasErrors('quarter_id');
    });

    it('validates quarter belongs to selected town', function () {
        $user = User::factory()->create();
        $town1 = Town::factory()->create();
        $town2 = Town::factory()->create();
        $quarter = Quarter::factory()->forTown($town2)->create();

        $this->actingAs($user)
            ->post('/profile/addresses', [
                'label' => 'Home',
                'town_id' => $town1->id,
                'quarter_id' => $quarter->id,
            ])
            ->assertSessionHasErrors('quarter_id');
    });

    it('enforces label max length of 50', function () {
        $user = User::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->forTown($town)->create();

        $this->actingAs($user)
            ->post('/profile/addresses', [
                'label' => str_repeat('a', 51),
                'town_id' => $town->id,
                'quarter_id' => $quarter->id,
            ])
            ->assertSessionHasErrors('label');
    });

    it('enforces directions max length of 500', function () {
        $user = User::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->forTown($town)->create();

        $this->actingAs($user)
            ->post('/profile/addresses', [
                'label' => 'Home',
                'town_id' => $town->id,
                'quarter_id' => $quarter->id,
                'additional_directions' => str_repeat('a', 501),
            ])
            ->assertSessionHasErrors('additional_directions');
    });

    it('saves optional fields as null when not provided', function () {
        $user = User::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->forTown($town)->create();

        $this->actingAs($user)
            ->post('/profile/addresses', [
                'label' => 'Home',
                'town_id' => $town->id,
                'quarter_id' => $quarter->id,
            ]);

        $address = $user->addresses()->first();
        expect($address->neighbourhood)->toBeNull()
            ->and($address->additional_directions)->toBeNull()
            ->and($address->latitude)->toBeNull()
            ->and($address->longitude)->toBeNull();
    });

    it('logs activity when address is created', function () {
        $user = User::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->forTown($town)->create();

        $this->actingAs($user)
            ->post('/profile/addresses', [
                'label' => 'Home',
                'town_id' => $town->id,
                'quarter_id' => $quarter->id,
            ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'addresses',
            'event' => 'created',
            'causer_id' => $user->id,
        ]);
    });
});

describe('quarters endpoint', function () {
    it('requires authentication', function () {
        $this->post('/profile/addresses/quarters')
            ->assertRedirect('/login');
    });

    it('returns quarters for a given town', function () {
        $user = User::factory()->create();
        $town = Town::factory()->create();
        Quarter::factory()->forTown($town)->count(3)->create();

        $this->actingAs($user)
            ->post('/profile/addresses/quarters', ['town_id' => $town->id])
            ->assertSuccessful();
    });
});
