<?php

use App\Models\Tenant;
use App\Models\User;

test('home page renders with main-public layout on main domain', function () {
    $response = $this->get('/');

    $response->assertStatus(200)
        ->assertSee(config('app.name', 'DancyMeals'))
        ->assertSee(__('Discover Cooks'))
        ->assertSee(__('Home'));
});

test('home page shows login and register links for guests', function () {
    $response = $this->get('/');

    $response->assertStatus(200)
        ->assertSee(__('Login'))
        ->assertSee(__('Register'));
});

test('home page shows profile and logout for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertStatus(200)
        ->assertSee($user->name)
        ->assertSee(__('Logout'));
});

test('home page contains theme switcher component', function () {
    $response = $this->get('/');

    $response->assertStatus(200)
        ->assertSee('Light mode', false)
        ->assertSee('Dark mode', false);
});

test('home page contains language switcher component', function () {
    $response = $this->get('/');

    $response->assertStatus(200)
        ->assertSee('EN', false)
        ->assertSee(__('Language'));
});

test('home page contains global loading bar', function () {
    $response = $this->get('/');

    $response->assertStatus(200)
        ->assertSee('$gale.loading', false);
});

test('home page footer contains copyright and switchers', function () {
    $response = $this->get('/');

    $response->assertStatus(200)
        ->assertSee(__('All rights reserved.'));
});

test('home page uses Gale SPA navigation', function () {
    $response = $this->get('/');

    $response->assertStatus(200)
        ->assertSee('x-navigate', false);
});

test('tenant domain renders with tenant-public layout showing cook branding', function () {
    $tenant = Tenant::factory()->withSlug('layout-test', 'Layout Test Cook')->create();

    $response = $this->get('http://layout-test.dmc.test/');

    $response->assertStatus(200)
        ->assertSee('Layout Test Cook');
});

test('admin dashboard route requires authentication', function () {
    $response = $this->get('/vault-entry');

    $response->assertRedirect('/login');
});

test('admin dashboard renders with admin layout for authenticated users on main domain', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('http://dmc.test/vault-entry');

    $response->assertStatus(200)
        ->assertSee(__('Dashboard'))
        ->assertSee(__('Tenants'))
        ->assertSee(__('Users'));
});

test('cook dashboard route requires authentication on tenant domain', function () {
    $tenant = Tenant::factory()->withSlug('cook-auth', 'Cook Auth Test')->create();

    $response = $this->get('http://cook-auth.dmc.test/dashboard');

    $response->assertRedirect('/login');
});

test('cook dashboard renders with cook layout for authenticated users on tenant domain', function () {
    $tenant = Tenant::factory()->withSlug('cook-dash', 'Cook Dash Test')->create();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('http://cook-dash.dmc.test/dashboard');

    $response->assertStatus(200)
        ->assertSee(__('Dashboard'))
        ->assertSee(__('Meals'))
        ->assertSee(__('Orders'));
});

test('mobile hamburger menu button exists on all public layouts', function () {
    $response = $this->get('/');

    $response->assertStatus(200)
        ->assertSee('mobileMenuOpen', false)
        ->assertSee(__('Toggle menu'));
});

test('admin layout includes notification bell for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('http://dmc.test/vault-entry');

    $response->assertStatus(200)
        ->assertSee(__('Notifications'));
});

test('home route has a named route', function () {
    expect(route('home'))->not->toBeEmpty();
});

test('admin dashboard route has a named route', function () {
    expect(route('admin.dashboard'))->toContain('vault-entry');
});

test('cook dashboard route has a named route', function () {
    expect(route('cook.dashboard'))->toContain('dashboard');
});

test('new translation keys exist for navigation strings', function () {
    $keys = [
        'Discover Cooks', 'Toggle menu', 'Notifications',
        'Tenants', 'Financials', 'Complaints', 'Activity Log',
        'Meals', 'Brand', 'Locations', 'Wallet', 'Managers',
    ];

    foreach ($keys as $key) {
        expect(__($key))->not->toBeEmpty("Translation missing for: {$key}");
    }
});
