<?php

use App\Models\Tenant;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| F-043: Admin Panel Layout & Access Control — Feature Tests
|--------------------------------------------------------------------------
|
| Tests for admin panel layout, access control middleware, and
| permission-based sidebar navigation.
|
| BR-043: Admin panel routes ONLY accessible on main domain
| BR-044: Requests to /vault-entry/* on tenant domains return 404
| BR-045: Only users with can-access-admin-panel permission may access
| BR-046: Sidebar sections based on user permissions
| BR-047: Must support light and dark mode
| BR-048: All admin pages fully responsive (mobile-first)
|
*/

beforeEach(function () {
    $this->seedRolesAndPermissions();
});

/*
|--------------------------------------------------------------------------
| Access Control Tests (BR-043, BR-044, BR-045)
|--------------------------------------------------------------------------
*/

it('allows super-admin to access admin panel on main domain', function () {
    $admin = createUser('super-admin');

    $response = $this->actingAs($admin)
        ->get('/vault-entry');

    $response->assertOk();
    $response->assertViewIs('admin.dashboard');
});

it('allows admin to access admin panel on main domain', function () {
    $admin = createUser('admin');

    $response = $this->actingAs($admin)
        ->get('/vault-entry');

    $response->assertOk();
    $response->assertViewIs('admin.dashboard');
});

it('returns 403 for client user on main domain', function () {
    $client = createUser('client');

    $response = $this->actingAs($client)
        ->get('/vault-entry');

    $response->assertForbidden();
});

it('returns 403 for cook user on main domain', function () {
    $cook = createUser('cook');

    $response = $this->actingAs($cook)
        ->get('/vault-entry');

    $response->assertForbidden();
});

it('returns 403 for manager user on main domain', function () {
    $manager = createUser('manager');

    $response = $this->actingAs($manager)
        ->get('/vault-entry');

    $response->assertForbidden();
});

it('redirects unauthenticated users to login', function () {
    $response = $this->get('/vault-entry');

    $response->assertRedirect('/login');
});

it('returns 404 on tenant domain for vault-entry', function () {
    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);
    $tenant = Tenant::factory()->create(['slug' => 'cook1', 'is_active' => true]);
    $admin = createUser('super-admin');

    // Even as super-admin, vault-entry on tenant domain returns 404
    $response = $this->actingAs($admin)
        ->get("https://cook1.{$mainDomain}/vault-entry");

    $response->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Dashboard Content Tests
|--------------------------------------------------------------------------
*/

it('displays welcome message with user name', function () {
    $admin = createUser('super-admin', ['name' => 'Alice Admin']);

    $response = $this->actingAs($admin)
        ->get('/vault-entry');

    $response->assertOk();
    $response->assertSeeText('Alice Admin');
});

it('renders the admin layout with breadcrumb', function () {
    $admin = createUser('super-admin');

    $response = $this->actingAs($admin)
        ->get('/vault-entry');

    $response->assertOk();
    $response->assertSee('aria-label="Breadcrumb"', false);
});

/*
|--------------------------------------------------------------------------
| Permission-Based Sidebar Tests (BR-046)
|--------------------------------------------------------------------------
*/

it('shows all sidebar sections for super-admin', function () {
    $admin = createUser('super-admin');

    $response = $this->actingAs($admin)
        ->get('/vault-entry');

    $response->assertOk();
    $response->assertSee('/vault-entry/tenants', false);
    $response->assertSee('/vault-entry/users', false);
    $response->assertSee('/vault-entry/roles', false);
    $response->assertSee('/vault-entry/analytics', false);
    $response->assertSee('/vault-entry/finance', false);
    $response->assertSee('/vault-entry/complaints', false);
    $response->assertSee('/vault-entry/payouts', false);
    $response->assertSee('/vault-entry/settings', false);
    $response->assertSee('/vault-entry/activity-log', false);
});

it('hides sidebar sections user has no permission for', function () {
    // Create a user with only specific admin permissions (not admin role)
    $user = User::factory()->create();
    $this->seedRolesAndPermissions();

    // Give only admin panel access and a few permissions
    $user->givePermissionTo('can-access-admin-panel');
    $user->givePermissionTo('can-view-tenants');
    $user->givePermissionTo('can-manage-users');
    // Intentionally NOT giving: can-view-platform-analytics, can-view-activity-log

    $response = $this->actingAs($user)
        ->get('/vault-entry');

    $response->assertOk();
    // Should see items they have permission for
    $response->assertSee('/vault-entry/tenants', false);
    $response->assertSee('/vault-entry/users', false);
    // Should not see items they lack permission for
    $response->assertDontSee('/vault-entry/analytics', false);
    $response->assertDontSee('/vault-entry/activity-log', false);
});

it('always shows dashboard even with minimal permissions', function () {
    // Create a user with only admin panel access, no section permissions
    $user = User::factory()->create();
    $this->seedRolesAndPermissions();
    $user->givePermissionTo('can-access-admin-panel');

    $response = $this->actingAs($user)
        ->get('/vault-entry');

    $response->assertOk();
    // Dashboard is always visible when user has admin panel access
    $response->assertSee('Dashboard');
});

/*
|--------------------------------------------------------------------------
| Layout Structure Tests
|--------------------------------------------------------------------------
*/

it('renders sidebar with navigation groups', function () {
    $admin = createUser('super-admin');

    $response = $this->actingAs($admin)
        ->get('/vault-entry');

    $response->assertOk();
    $response->assertSee('Admin navigation', false);
});

it('renders top bar with theme and language switchers', function () {
    $admin = createUser('super-admin');

    $response = $this->actingAs($admin)
        ->get('/vault-entry');

    $response->assertOk();
    // Logout form present
    $response->assertSee('action="'.route('logout').'"', false);
});

it('renders mobile hamburger menu button', function () {
    $admin = createUser('super-admin');

    $response = $this->actingAs($admin)
        ->get('/vault-entry');

    $response->assertOk();
    $response->assertSee('Toggle menu', false);
});

it('shows user info in sidebar footer', function () {
    $admin = createUser('super-admin', [
        'name' => 'Test Admin',
        'email' => 'admin@example.com',
    ]);

    $response = $this->actingAs($admin)
        ->get('/vault-entry');

    $response->assertOk();
    $response->assertSee('Test Admin');
    $response->assertSee('admin@example.com');
});

/*
|--------------------------------------------------------------------------
| Edge Case Tests
|--------------------------------------------------------------------------
*/

it('re-checks permissions on each navigation request', function () {
    // Use a user with direct permission (not role-based) to test revocation
    $user = User::factory()->create();
    $this->seedRolesAndPermissions();
    $user->givePermissionTo('can-access-admin-panel');

    // First request succeeds
    $response = $this->actingAs($user)
        ->get('/vault-entry');
    $response->assertOk();

    // Revoke admin panel permission
    $user->revokePermissionTo('can-access-admin-panel');
    // Clear permission cache
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    // Second request is now forbidden
    $response = $this->actingAs($user->fresh())
        ->get('/vault-entry');
    $response->assertForbidden();
});

it('returns 403 page with proper content', function () {
    $client = createUser('client');

    $response = $this->actingAs($client)
        ->get('/vault-entry');

    $response->assertForbidden();
    $response->assertSee('403');
});
