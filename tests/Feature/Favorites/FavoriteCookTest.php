<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| F-196: Favorite Cook Toggle — Feature Tests
|--------------------------------------------------------------------------
|
| Tests the User::favoriteCooks() BelongsToMany relationship and the
| hasFavoritedCook() helper method. Uses RefreshDatabase (auto-applied
| for Feature tests via Pest.php).
|
*/

describe('User::favoriteCooks relationship', function () {
    it('returns a belongs-to-many relationship', function () {
        $user = User::factory()->create();
        $relationship = $user->favoriteCooks();
        expect($relationship)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
    });

    it('allows attaching a cook to favorites', function () {
        $user = User::factory()->create();
        $cook = User::factory()->create();

        $user->favoriteCooks()->attach($cook->id, ['created_at' => now()]);

        expect($user->favoriteCooks()->where('cook_user_id', $cook->id)->exists())->toBeTrue();
    });

    it('allows detaching a cook from favorites', function () {
        $user = User::factory()->create();
        $cook = User::factory()->create();

        $user->favoriteCooks()->attach($cook->id, ['created_at' => now()]);
        $user->favoriteCooks()->detach($cook->id);

        expect($user->favoriteCooks()->where('cook_user_id', $cook->id)->exists())->toBeFalse();
    });

    it('is idempotent — detach of non-favorited cook does not throw', function () {
        $user = User::factory()->create();
        $cook = User::factory()->create();

        // Detaching without prior attach should not throw
        $user->favoriteCooks()->detach($cook->id);

        expect($user->favoriteCooks()->where('cook_user_id', $cook->id)->exists())->toBeFalse();
    });
});

describe('User::hasFavoritedCook helper', function () {
    it('returns true when the cook is favorited', function () {
        $user = User::factory()->create();
        $cook = User::factory()->create();

        $user->favoriteCooks()->attach($cook->id, ['created_at' => now()]);

        expect($user->hasFavoritedCook($cook->id))->toBeTrue();
    });

    it('returns false when the cook is not favorited', function () {
        $user = User::factory()->create();
        $cook = User::factory()->create();

        expect($user->hasFavoritedCook($cook->id))->toBeFalse();
    });

    it('returns false for a non-existent cook user id', function () {
        $user = User::factory()->create();

        expect($user->hasFavoritedCook(99999))->toBeFalse();
    });

    it('correctly distinguishes between different cooks', function () {
        $user = User::factory()->create();
        $cook1 = User::factory()->create();
        $cook2 = User::factory()->create();

        $user->favoriteCooks()->attach($cook1->id, ['created_at' => now()]);

        expect($user->hasFavoritedCook($cook1->id))->toBeTrue()
            ->and($user->hasFavoritedCook($cook2->id))->toBeFalse();
    });
});
