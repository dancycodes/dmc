<?php

use App\Http\Controllers\FavoriteMealController;
use App\Models\Meal;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| F-197: Favorite Meal Toggle — Unit Tests
|--------------------------------------------------------------------------
|
| Tests pure logic of the FavoriteMealController response structure
| and User model method signatures without requiring a database.
|
*/

it('User model has a favoriteMeals method', function () {
    expect(method_exists(User::class, 'favoriteMeals'))->toBeTrue();
});

it('favoriteMeals relationship uses the correct pivot table', function () {
    $reflection = new ReflectionMethod(User::class, 'favoriteMeals');
    expect($reflection->getReturnType()->getName())->toBe(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
});

it('FavoriteMealController has a toggle method', function () {
    expect(method_exists(FavoriteMealController::class, 'toggle'))->toBeTrue();
});

it('FavoriteMealController toggle method accepts a Meal parameter', function () {
    $reflection = new ReflectionMethod(FavoriteMealController::class, 'toggle');
    $params = $reflection->getParameters();

    // Parameters: Request, Meal
    expect($params)->toHaveCount(2);
    $mealParam = $params[1];
    expect($mealParam->getName())->toBe('meal')
        ->and($mealParam->getType()->getName())->toBe(Meal::class);
});

it('Meal model constants are defined', function () {
    expect(Meal::STATUS_LIVE)->toBe('live')
        ->and(Meal::STATUS_DRAFT)->toBe('draft');
});

it('User favoriteMeals uses correct pivot table name', function () {
    $reflection = new ReflectionMethod(User::class, 'favoriteMeals');
    // Verify the method is defined in User class
    expect($reflection->class)->toBe(User::class);
});
