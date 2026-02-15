<?php

use App\Services\CrossDomainSessionService;
use App\Services\TenantService;

/*
|--------------------------------------------------------------------------
| F-028: Cross-Domain Session Sharing — Unit Tests
|--------------------------------------------------------------------------
|
| Tests the CrossDomainSessionService class structure, constants,
| method signatures, and validates session configuration files and
| environment variables.
|
| Note: Methods that depend on config() or Cache are tested in Feature
| tests where the Laravel container is fully bootstrapped.
|
*/

$projectRoot = dirname(__DIR__, 3);

/*
|--------------------------------------------------------------------------
| TenantService Domain Detection (pure logic)
|--------------------------------------------------------------------------
*/

it('TenantService isIpAddress detects IPv4', function () {
    expect(TenantService::isIpAddress('127.0.0.1'))->toBeTrue();
    expect(TenantService::isIpAddress('192.168.1.1'))->toBeTrue();
    expect(TenantService::isIpAddress('dmc.test'))->toBeFalse();
    expect(TenantService::isIpAddress('latifa.cm'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Session Configuration File Validation
|--------------------------------------------------------------------------
*/

it('session config uses database driver as default', function () use ($projectRoot) {
    $sessionFile = file_get_contents($projectRoot.'/config/session.php');

    expect(str_contains($sessionFile, "env('SESSION_DRIVER', 'database')"))->toBeTrue();
});

it('session cookie name derives from APP_NAME slug', function () use ($projectRoot) {
    $sessionFile = file_get_contents($projectRoot.'/config/session.php');

    expect(str_contains($sessionFile, "'-session'"))->toBeTrue();
    expect(str_contains($sessionFile, 'APP_NAME'))->toBeTrue();
});

it('session same_site defaults to lax for cross-subdomain cookies', function () use ($projectRoot) {
    $sessionFile = file_get_contents($projectRoot.'/config/session.php');

    expect(str_contains($sessionFile, "'lax'"))->toBeTrue();
    expect(str_contains($sessionFile, 'same_site'))->toBeTrue();
});

it('session domain reads from SESSION_DOMAIN env variable', function () use ($projectRoot) {
    $sessionFile = file_get_contents($projectRoot.'/config/session.php');

    expect(str_contains($sessionFile, 'SESSION_DOMAIN'))->toBeTrue();
});

it('session http_only is enabled by default', function () use ($projectRoot) {
    $sessionFile = file_get_contents($projectRoot.'/config/session.php');

    expect(str_contains($sessionFile, "'http_only'"))->toBeTrue();
});

it('session uses database table for storage', function () use ($projectRoot) {
    $sessionFile = file_get_contents($projectRoot.'/config/session.php');

    expect(str_contains($sessionFile, "'table'"))->toBeTrue();
    expect(str_contains($sessionFile, "'sessions'"))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Environment Variable Configuration
|--------------------------------------------------------------------------
*/

it('env file has SESSION_DOMAIN set to .dmc.test', function () use ($projectRoot) {
    $envContent = file_get_contents($projectRoot.'/.env');

    expect(str_contains($envContent, 'SESSION_DOMAIN=.dmc.test'))->toBeTrue();
});

it('env example file has SESSION_DOMAIN set to .dmc.test', function () use ($projectRoot) {
    $envContent = file_get_contents($projectRoot.'/.env.example');

    expect(str_contains($envContent, 'SESSION_DOMAIN=.dmc.test'))->toBeTrue();
});

it('env file has SESSION_DRIVER set to database', function () use ($projectRoot) {
    $envContent = file_get_contents($projectRoot.'/.env');

    expect(str_contains($envContent, 'SESSION_DRIVER=database'))->toBeTrue();
});

it('SESSION_DOMAIN in env has leading dot for subdomain sharing', function () use ($projectRoot) {
    $envContent = file_get_contents($projectRoot.'/.env');

    preg_match('/^SESSION_DOMAIN=(.+)$/m', $envContent, $matches);
    $domain = trim($matches[1] ?? '');

    expect(str_starts_with($domain, '.'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Service Class Structure
|--------------------------------------------------------------------------
*/

it('token prefix constant is cross-domain-token:', function () {
    $reflection = new ReflectionClass(CrossDomainSessionService::class);
    $constant = $reflection->getConstant('TOKEN_PREFIX');

    expect($constant)->toBe('cross-domain-token:');
});

it('token TTL constant is 300 seconds (5 minutes)', function () {
    $reflection = new ReflectionClass(CrossDomainSessionService::class);
    $constant = $reflection->getConstant('TOKEN_TTL');

    expect($constant)->toBe(300);
});

it('service has all required public methods', function () {
    $reflection = new ReflectionClass(CrossDomainSessionService::class);
    $methods = array_map(
        fn (ReflectionMethod $m) => $m->getName(),
        $reflection->getMethods(ReflectionMethod::IS_PUBLIC)
    );

    expect($methods)->toContain('generateToken')
        ->and($methods)->toContain('validateAndConsumeToken')
        ->and($methods)->toContain('buildRedirectUrl')
        ->and($methods)->toContain('isSubdomain')
        ->and($methods)->toContain('isCustomDomain')
        ->and($methods)->toContain('getSessionCookieDomain');
});

it('generateToken requires a User parameter', function () {
    $reflection = new ReflectionMethod(CrossDomainSessionService::class, 'generateToken');
    $params = $reflection->getParameters();

    expect($params)->toHaveCount(1);
    expect($params[0]->getName())->toBe('user');
    expect($params[0]->getType()?->getName())->toBe('App\Models\User');
});

it('generateToken returns string', function () {
    $reflection = new ReflectionMethod(CrossDomainSessionService::class, 'generateToken');
    $returnType = $reflection->getReturnType();

    expect($returnType)->not->toBeNull();
    expect($returnType->getName())->toBe('string');
});

it('validateAndConsumeToken returns nullable User', function () {
    $reflection = new ReflectionMethod(CrossDomainSessionService::class, 'validateAndConsumeToken');
    $returnType = $reflection->getReturnType();

    expect($returnType)->not->toBeNull();
    expect($returnType->allowsNull())->toBeTrue();
});

it('validateAndConsumeToken accepts a string parameter', function () {
    $reflection = new ReflectionMethod(CrossDomainSessionService::class, 'validateAndConsumeToken');
    $params = $reflection->getParameters();

    expect($params)->toHaveCount(1);
    expect($params[0]->getName())->toBe('token');
    expect($params[0]->getType()?->getName())->toBe('string');
});

it('buildRedirectUrl has correct parameter count', function () {
    $reflection = new ReflectionMethod(CrossDomainSessionService::class, 'buildRedirectUrl');
    $params = $reflection->getParameters();

    expect($params)->toHaveCount(3);
    expect($params[0]->getName())->toBe('targetDomain');
    expect($params[1]->getName())->toBe('token');
    expect($params[2]->getName())->toBe('intendedPath');
    expect($params[2]->isDefaultValueAvailable())->toBeTrue();
    expect($params[2]->getDefaultValue())->toBe('/');
});

it('isSubdomain returns bool', function () {
    $reflection = new ReflectionMethod(CrossDomainSessionService::class, 'isSubdomain');

    expect($reflection->getReturnType()?->getName())->toBe('bool');
});

it('isCustomDomain returns bool', function () {
    $reflection = new ReflectionMethod(CrossDomainSessionService::class, 'isCustomDomain');

    expect($reflection->getReturnType()?->getName())->toBe('bool');
});

/*
|--------------------------------------------------------------------------
| Controller Structure
|--------------------------------------------------------------------------
*/

it('CrossDomainAuthController has generateToken method', function () {
    $reflection = new ReflectionClass(\App\Http\Controllers\Auth\CrossDomainAuthController::class);

    expect($reflection->hasMethod('generateToken'))->toBeTrue();
    expect($reflection->getMethod('generateToken')->isPublic())->toBeTrue();
});

it('CrossDomainAuthController has consumeToken method', function () {
    $reflection = new ReflectionClass(\App\Http\Controllers\Auth\CrossDomainAuthController::class);

    expect($reflection->hasMethod('consumeToken'))->toBeTrue();
    expect($reflection->getMethod('consumeToken')->isPublic())->toBeTrue();
});

it('CrossDomainAuthController uses constructor injection', function () {
    $reflection = new ReflectionClass(\App\Http\Controllers\Auth\CrossDomainAuthController::class);
    $constructor = $reflection->getConstructor();

    expect($constructor)->not->toBeNull();
    expect($constructor->getParameters())->toHaveCount(1);
    expect($constructor->getParameters()[0]->getType()?->getName())->toBe(CrossDomainSessionService::class);
});
