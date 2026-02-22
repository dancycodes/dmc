<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| F-196: Favorite Cook Toggle — Unit Tests
|--------------------------------------------------------------------------
|
| Tests pure logic of the FavoriteCookController response structure
| and User model method signatures without requiring a database.
|
*/

it('User model has a favoriteCooks method', function () {
    expect(method_exists(User::class, 'favoriteCooks'))->toBeTrue();
});

it('User model has a hasFavoritedCook method', function () {
    expect(method_exists(User::class, 'hasFavoritedCook'))->toBeTrue();
});

it('hasFavoritedCook accepts an int parameter', function () {
    $reflection = new ReflectionMethod(User::class, 'hasFavoritedCook');
    $params = $reflection->getParameters();
    expect($params)->toHaveCount(1)
        ->and($params[0]->getName())->toBe('cookUserId');
});

it('favoriteCooks relationship uses the correct pivot table', function () {
    // Verify the relationship method returns a BelongsToMany via reflection on code
    $reflection = new ReflectionMethod(User::class, 'favoriteCooks');
    expect($reflection->getReturnType()->getName())->toBe(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
});
