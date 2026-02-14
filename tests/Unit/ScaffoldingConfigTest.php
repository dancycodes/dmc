<?php

$projectRoot = dirname(__DIR__, 2);

test('env file sets application name to DancyMeals', function () use ($projectRoot) {
    $envContent = file_get_contents($projectRoot.'/.env');

    expect($envContent)->toContain('APP_NAME=DancyMeals');
});

test('env file sets database driver to pgsql', function () use ($projectRoot) {
    $envContent = file_get_contents($projectRoot.'/.env');

    expect($envContent)->toContain('DB_CONNECTION=pgsql');
});

test('env file sets app url to dm.test', function () use ($projectRoot) {
    $envContent = file_get_contents($projectRoot.'/.env');

    expect($envContent)->toContain('APP_URL=http://dm.test');
});

test('env file has debug mode enabled', function () use ($projectRoot) {
    $envContent = file_get_contents($projectRoot.'/.env');

    expect($envContent)->toContain('APP_DEBUG=true');
});

test('config app.php sets timezone to Africa/Douala', function () use ($projectRoot) {
    $configContent = file_get_contents($projectRoot.'/config/app.php');

    expect($configContent)->toContain("'timezone' => 'Africa/Douala'");
});
