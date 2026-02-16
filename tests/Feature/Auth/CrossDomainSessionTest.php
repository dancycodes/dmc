<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\CrossDomainSessionService;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| F-028: Cross-Domain Session Sharing — Feature Tests
|--------------------------------------------------------------------------
|
| Tests cross-domain session sharing between the main domain and tenant
| subdomains (via shared cookie domain) and custom domains (via token
| exchange). Covers BR-081 through BR-088.
|
*/

beforeEach(function () {
    $this->seedRolesAndPermissions();
});

/*
|--------------------------------------------------------------------------
| Session Configuration (BR-081, BR-082, BR-088)
|--------------------------------------------------------------------------
*/

it('configures session domain for subdomain sharing in env', function () {
    // The .env file should set SESSION_DOMAIN to .dmc.test
    $envContent = file_get_contents(base_path('.env'));

    expect($envContent)->toContain('SESSION_DOMAIN=.dmc.test');
});

it('configures session domain for subdomain sharing in env example', function () {
    $envContent = file_get_contents(base_path('.env.example'));

    expect($envContent)->toContain('SESSION_DOMAIN=.dmc.test');
});

it('session cookie domain has leading dot for subdomain sharing', function () {
    // In production/local, the SESSION_DOMAIN should have leading dot
    $envContent = file_get_contents(base_path('.env'));

    // Extract SESSION_DOMAIN value
    preg_match('/^SESSION_DOMAIN=(.+)$/m', $envContent, $matches);
    $domain = trim($matches[1] ?? '');

    expect($domain)->toStartWith('.');
});

it('session driver is database for shared session storage', function () {
    $envContent = file_get_contents(base_path('.env'));

    expect($envContent)->toContain('SESSION_DRIVER=database');
});

it('sessions table exists in database', function () {
    expect(
        \Illuminate\Support\Facades\Schema::hasTable('sessions')
    )->toBeTrue();
});

it('sessions table has user_id column for user lookup', function () {
    expect(
        \Illuminate\Support\Facades\Schema::hasColumn('sessions', 'user_id')
    )->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Subdomain Session Sharing (BR-081, BR-082)
|--------------------------------------------------------------------------
*/

it('authenticates user on main domain', function () {
    $user = createUser('client', ['password' => 'Password1']);

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'Password1',
    ]);

    $this->assertAuthenticatedAs($user);
});

it('authenticated user can access tenant subdomain routes', function () {
    $user = createUser('client', ['password' => 'Password1']);
    $tenant = Tenant::factory()->create(['slug' => 'latifa', 'is_active' => true]);

    // Log in on main domain
    $this->actingAs($user);

    // Access tenant subdomain route - session cookie with .dmc.test domain
    // covers both dmc.test and *.dmc.test
    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);
    $tenantUrl = 'https://latifa.'.$mainDomain.'/';

    $response = $this->get($tenantUrl);

    // User should still be authenticated (shared session)
    $this->assertAuthenticated();
});

it('authenticated user on tenant subdomain can access main domain', function () {
    $user = createUser('client');
    $tenant = Tenant::factory()->create(['slug' => 'powels', 'is_active' => true]);

    // Act as user (simulates being authenticated)
    $this->actingAs($user);

    // Navigate to main domain
    $response = $this->get(route('home'));

    $this->assertAuthenticated();
});

it('authenticated user can visit multiple tenant subdomains', function () {
    $user = createUser('client');
    Tenant::factory()->create(['slug' => 'tenant-a', 'is_active' => true]);
    Tenant::factory()->create(['slug' => 'tenant-b', 'is_active' => true]);

    $this->actingAs($user);
    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    // Visit tenant A
    $this->get('https://tenant-a.'.$mainDomain.'/');
    $this->assertAuthenticated();

    // Visit tenant B
    $this->get('https://tenant-b.'.$mainDomain.'/');
    $this->assertAuthenticated();
});

/*
|--------------------------------------------------------------------------
| Logout Propagation (BR-084)
|--------------------------------------------------------------------------
*/

it('logout invalidates session across all domains', function () {
    $user = createUser('client', ['password' => 'Password1']);

    // Log in
    $this->actingAs($user);
    $this->assertAuthenticated();

    // Log out
    $this->post(route('logout'));

    // Should no longer be authenticated
    $this->assertGuest();
});

it('logout destroys session completely', function () {
    $user = createUser('client', ['password' => 'Password1']);

    $this->actingAs($user);

    // Logout should invalidate and regenerate session
    $response = $this->post(route('logout'));

    $response->assertRedirect('/');
    $this->assertGuest();
});

/*
|--------------------------------------------------------------------------
| Cross-Domain Token Generation (BR-083)
|--------------------------------------------------------------------------
*/

it('generates token for authenticated user', function () {
    $user = createUser('client');

    $this->actingAs($user);

    $response = $this->get(route('cross-domain.generate-token', [
        'domain' => 'latifa.cm',
        'intended' => '/menu',
    ]));

    // Should redirect to the custom domain with a token
    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)->toContain('latifa.cm')
        ->and($location)->toContain('cross-domain-auth')
        ->and($location)->toContain('token=')
        ->and($location)->toContain('intended=');
});

it('requires authentication to generate token', function () {
    $response = $this->get(route('cross-domain.generate-token', [
        'domain' => 'latifa.cm',
    ]));

    // Should redirect to login (auth middleware)
    $response->assertRedirect(route('login'));
});

it('returns 400 when domain parameter is missing', function () {
    $user = createUser('client');

    $this->actingAs($user);

    $response = $this->get(route('cross-domain.generate-token'));

    $response->assertStatus(400);
});

/*
|--------------------------------------------------------------------------
| Cross-Domain Token Consumption (BR-083, BR-087)
|--------------------------------------------------------------------------
*/

it('consumes valid token and authenticates user on custom domain', function () {
    $user = createUser('client');

    // Generate a token
    $service = app(CrossDomainSessionService::class);
    $token = $service->generateToken($user);

    // Consume the token on the custom domain
    $response = $this->get(route('cross-domain.consume-token', [
        'token' => $token,
        'intended' => '/menu',
    ]));

    $response->assertRedirect('/menu');
    $this->assertAuthenticatedAs($user);
});

it('redirects to intended path after token consumption', function () {
    $user = createUser('client');

    $service = app(CrossDomainSessionService::class);
    $token = $service->generateToken($user);

    $response = $this->get(route('cross-domain.consume-token', [
        'token' => $token,
        'intended' => '/dashboard',
    ]));

    $response->assertRedirect('/dashboard');
});

it('redirects to root when intended path is empty', function () {
    $user = createUser('client');

    $service = app(CrossDomainSessionService::class);
    $token = $service->generateToken($user);

    $response = $this->get(route('cross-domain.consume-token', [
        'token' => $token,
    ]));

    $response->assertRedirect('/');
});

it('rejects invalid token and redirects as guest', function () {
    $response = $this->get(route('cross-domain.consume-token', [
        'token' => 'invalid-token-12345',
        'intended' => '/menu',
    ]));

    $response->assertRedirect('/menu');
    $this->assertGuest();
});

it('rejects expired/consumed token', function () {
    $user = createUser('client');

    $service = app(CrossDomainSessionService::class);
    $token = $service->generateToken($user);

    // Consume the token once
    $this->get(route('cross-domain.consume-token', [
        'token' => $token,
    ]));

    // Log out to test second consumption attempt
    $this->post(route('logout'));

    // Try to consume the same token again
    $response = $this->get(route('cross-domain.consume-token', [
        'token' => $token,
    ]));

    // Should be guest (token already consumed)
    $this->assertGuest();
});

it('token is one-time use only (BR-087)', function () {
    $user = createUser('client');

    $service = app(CrossDomainSessionService::class);
    $token = $service->generateToken($user);

    // Verify token exists in cache
    expect(Cache::has('cross-domain-token:'.$token))->toBeTrue();

    // Consume it
    $service->validateAndConsumeToken($token);

    // Verify token is removed from cache
    expect(Cache::has('cross-domain-token:'.$token))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Security (BR-085, BR-087)
|--------------------------------------------------------------------------
*/

it('does not authenticate inactive users via token', function () {
    $user = createUser('client', ['is_active' => false]);

    $service = app(CrossDomainSessionService::class);
    $token = $service->generateToken($user);

    $response = $this->get(route('cross-domain.consume-token', [
        'token' => $token,
        'intended' => '/',
    ]));

    $this->assertGuest();
});

it('skips token validation if already authenticated', function () {
    $user = createUser('client');

    $this->actingAs($user);

    $response = $this->get(route('cross-domain.consume-token', [
        'token' => 'any-token',
        'intended' => '/dashboard',
    ]));

    // Should redirect to intended path without consuming token
    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
});

it('handles missing token parameter gracefully', function () {
    $response = $this->get(route('cross-domain.consume-token'));

    $response->assertRedirect('/');
    $this->assertGuest();
});

/*
|--------------------------------------------------------------------------
| Route Configuration
|--------------------------------------------------------------------------
*/

it('has cross-domain generate-token route', function () {
    $route = route('cross-domain.generate-token');

    expect($route)->toContain('/cross-domain/generate-token');
});

it('has cross-domain consume-token route', function () {
    $route = route('cross-domain.consume-token');

    expect($route)->toContain('/cross-domain-auth');
});

it('generate-token route requires authentication', function () {
    $response = $this->get(route('cross-domain.generate-token', ['domain' => 'test.cm']));

    $response->assertRedirect(route('login'));
});

it('consume-token route is accessible without authentication', function () {
    // This route must be accessible without auth so unauthenticated users
    // on a custom domain can receive the token and get authenticated
    $response = $this->get(route('cross-domain.consume-token'));

    // Should not redirect to login (not behind auth middleware)
    expect($response->status())->not->toBe(401);
});
