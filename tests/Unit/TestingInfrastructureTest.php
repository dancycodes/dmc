<?php

/*
|--------------------------------------------------------------------------
| Testing Infrastructure Unit Tests
|--------------------------------------------------------------------------
|
| These tests verify the testing configuration files are properly set up.
| They check phpunit.xml, .env.testing, and Pest.php configuration without
| requiring database access.
|
*/

$projectRoot = dirname(__DIR__, 2);

test('phpunit.xml exists and is valid XML', function () use ($projectRoot) {
    $path = $projectRoot.'/phpunit.xml';

    expect(file_exists($path))->toBeTrue();

    $xml = simplexml_load_file($path);
    expect($xml)->not->toBeFalse();
});

test('phpunit.xml configures PostgreSQL test database', function () use ($projectRoot) {
    $xml = simplexml_load_file($projectRoot.'/phpunit.xml');
    $envVars = [];

    foreach ($xml->php->env as $env) {
        $envVars[(string) $env['name']] = (string) $env['value'];
    }

    expect($envVars)->toHaveKey('DB_CONNECTION', 'pgsql');
    expect($envVars)->toHaveKey('DB_DATABASE', 'dancymeals_test');
    expect($envVars)->toHaveKey('DB_HOST', '127.0.0.1');
    expect($envVars)->toHaveKey('DB_PORT', '5432');
});

test('phpunit.xml configures testing-appropriate drivers', function () use ($projectRoot) {
    $xml = simplexml_load_file($projectRoot.'/phpunit.xml');
    $envVars = [];

    foreach ($xml->php->env as $env) {
        $envVars[(string) $env['name']] = (string) $env['value'];
    }

    expect($envVars)->toHaveKey('APP_ENV', 'testing');
    expect($envVars)->toHaveKey('CACHE_STORE', 'array');
    expect($envVars)->toHaveKey('MAIL_MAILER', 'array');
    expect($envVars)->toHaveKey('SESSION_DRIVER', 'array');
    expect($envVars)->toHaveKey('QUEUE_CONNECTION', 'sync');
});

test('phpunit.xml has Unit and Feature test suites', function () use ($projectRoot) {
    $xml = simplexml_load_file($projectRoot.'/phpunit.xml');
    $suiteNames = [];

    foreach ($xml->testsuites->testsuite as $suite) {
        $suiteNames[] = (string) $suite['name'];
    }

    expect($suiteNames)->toContain('Unit');
    expect($suiteNames)->toContain('Feature');
});

test('.env.testing exists with correct database name', function () use ($projectRoot) {
    $path = $projectRoot.'/.env.testing';

    expect(file_exists($path))->toBeTrue();

    $content = file_get_contents($path);

    expect($content)->toContain('DB_DATABASE=dancymeals_test');
    expect($content)->toContain('DB_CONNECTION=pgsql');
    expect($content)->toContain('APP_ENV=testing');
});

test('.env.testing uses array drivers for isolation', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/.env.testing');

    expect($content)->toContain('CACHE_STORE=array');
    expect($content)->toContain('MAIL_MAILER=array');
    expect($content)->toContain('SESSION_DRIVER=array');
    expect($content)->toContain('QUEUE_CONNECTION=sync');
});

test('.env.testing has reduced bcrypt rounds for speed', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/.env.testing');

    expect($content)->toContain('BCRYPT_ROUNDS=4');
});

test('Pest.php configuration file exists', function () use ($projectRoot) {
    expect(file_exists($projectRoot.'/tests/Pest.php'))->toBeTrue();
});

test('Pest.php binds the base TestCase class', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/tests/Pest.php');

    expect($content)->toContain('Tests\TestCase::class');
});

test('Pest.php uses RefreshDatabase in Feature tests', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/tests/Pest.php');

    expect($content)->toContain('RefreshDatabase::class');
    expect($content)->toContain("->in('Feature')");
});

test('TestCase.php exists with helper methods', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/tests/TestCase.php');

    expect($content)->toContain('createUserWithRole');
    expect($content)->toContain('actingAsRole');
    expect($content)->toContain('createTenantWithCook');
    expect($content)->toContain('seedRolesAndPermissions');
});

test('no Dusk or browser test dependencies exist', function () use ($projectRoot) {
    $composerJson = json_decode(file_get_contents($projectRoot.'/composer.json'), true);

    $allDeps = array_merge(
        $composerJson['require'] ?? [],
        $composerJson['require-dev'] ?? [],
    );

    expect($allDeps)->not->toHaveKey('laravel/dusk');
});

test('Pest is installed as a dev dependency', function () use ($projectRoot) {
    $composerJson = json_decode(file_get_contents($projectRoot.'/composer.json'), true);

    expect($composerJson['require-dev'])->toHaveKey('pestphp/pest');
});
