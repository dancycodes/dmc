<?php

$projectRoot = dirname(__DIR__, 2);

test('config database.php default connection falls back to pgsql', function () use ($projectRoot) {
    $configContent = file_get_contents($projectRoot.'/config/database.php');

    expect($configContent)->toContain("'default' => env('DB_CONNECTION', 'pgsql')");
});

test('config database.php pgsql connection has correct driver', function () use ($projectRoot) {
    $configContent = file_get_contents($projectRoot.'/config/database.php');

    expect($configContent)->toContain("'driver' => 'pgsql'");
});

test('config database.php pgsql fallback database is dancymeals', function () use ($projectRoot) {
    $configContent = file_get_contents($projectRoot.'/config/database.php');

    expect($configContent)->toContain("'database' => env('DB_DATABASE', 'dancymeals')");
});

test('config database.php pgsql fallback username is postgres', function () use ($projectRoot) {
    $configContent = file_get_contents($projectRoot.'/config/database.php');

    expect($configContent)->toContain("'username' => env('DB_USERNAME', 'postgres')");
});

test('env file sets database connection to pgsql', function () use ($projectRoot) {
    $envContent = file_get_contents($projectRoot.'/.env');

    expect($envContent)->toContain('DB_CONNECTION=pgsql');
});

test('env file sets database name to dancymeals', function () use ($projectRoot) {
    $envContent = file_get_contents($projectRoot.'/.env');

    expect($envContent)->toContain('DB_DATABASE=dancymeals');
});

test('env file contains all six DB variables', function () use ($projectRoot) {
    $envContent = file_get_contents($projectRoot.'/.env');

    expect($envContent)
        ->toContain('DB_CONNECTION=')
        ->toContain('DB_HOST=')
        ->toContain('DB_PORT=')
        ->toContain('DB_DATABASE=')
        ->toContain('DB_USERNAME=')
        ->toContain('DB_PASSWORD=');
});

test('env.example sets database name to dancymeals', function () use ($projectRoot) {
    $envContent = file_get_contents($projectRoot.'/.env.example');

    expect($envContent)->toContain('DB_DATABASE=dancymeals');
});

test('env.testing file exists with test database configuration', function () use ($projectRoot) {
    $envTestingPath = $projectRoot.'/.env.testing';

    expect(file_exists($envTestingPath))->toBeTrue();

    $envContent = file_get_contents($envTestingPath);

    expect($envContent)
        ->toContain('DB_CONNECTION=pgsql')
        ->toContain('DB_DATABASE=dancymeals_test');
});

test('phpunit.xml configures pgsql for testing', function () use ($projectRoot) {
    $xmlContent = file_get_contents($projectRoot.'/phpunit.xml');

    expect($xmlContent)
        ->toContain('DB_CONNECTION')
        ->toContain('pgsql')
        ->toContain('DB_DATABASE')
        ->toContain('dancymeals_test');
});
