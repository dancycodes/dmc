<?php

test('the application boots and returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

test('application name config is DancyMeals', function () {
    expect(config('app.name'))->toBe('DancyMeals');
});

test('application url config is set for local development', function () {
    expect(config('app.url'))->toBe('https://dmc.test');
});

test('pgsql database connection is configured', function () {
    expect(config('database.connections.pgsql'))->toBeArray();
    expect(config('database.connections.pgsql.driver'))->toBe('pgsql');
});

test('application encryption key is set', function () {
    expect(config('app.key'))->not->toBeNull();
    expect(config('app.key'))->not->toBeEmpty();
});

test('application timezone is Africa/Douala', function () {
    expect(config('app.timezone'))->toBe('Africa/Douala');
});

test('config app name fallback is DancyMeals', function () {
    $configContent = file_get_contents(base_path('config/app.php'));

    expect($configContent)->toContain("'DancyMeals'");
});
