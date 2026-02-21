<?php

use App\Services\ManagerDashboardService;
use Illuminate\Support\Facades\DB;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seedRolesAndPermissions();
    $this->service = app(ManagerDashboardService::class);
});

// ── isManager ──────────────────────────────────────────────────────────────

test('isManager returns false for the cook of a tenant', function () {
    ['tenant' => $tenant, 'cook' => $cook] = $this->createTenantWithCook();

    expect($this->service->isManager($cook, $tenant))->toBeFalse();
});

test('isManager returns true for a user in tenant_managers pivot', function () {
    ['tenant' => $tenant] = $this->createTenantWithCook();
    $manager = $this->createUserWithRole('manager');

    DB::table('tenant_managers')->insert([
        'tenant_id' => $tenant->id,
        'user_id' => $manager->id,
        'created_at' => now(),
    ]);

    expect($this->service->isManager($manager, $tenant))->toBeTrue();
});

test('isManager returns false for a user not in tenant_managers', function () {
    ['tenant' => $tenant] = $this->createTenantWithCook();
    $user = $this->createUserWithRole('client');

    expect($this->service->isManager($user, $tenant))->toBeFalse();
});

// ── managerCanAccessPath ────────────────────────────────────────────────────

test('manager can access dashboard home without any permissions', function () {
    $manager = $this->createUserWithRole('manager');

    expect($this->service->managerCanAccessPath($manager, 'dashboard'))->toBeTrue();
});

test('manager cannot access orders without can-manage-orders permission', function () {
    $manager = $this->createUserWithRole('manager');

    expect($this->service->managerCanAccessPath($manager, 'dashboard/orders'))->toBeFalse();
});

test('manager can access orders with can-manage-orders permission', function () {
    $manager = $this->createUserWithRole('manager');
    $manager->givePermissionTo('can-manage-orders');

    expect($this->service->managerCanAccessPath($manager, 'dashboard/orders'))->toBeTrue();
});

test('manager can access order detail sub-path with can-manage-orders permission', function () {
    $manager = $this->createUserWithRole('manager');
    $manager->givePermissionTo('can-manage-orders');

    expect($this->service->managerCanAccessPath($manager, 'dashboard/orders/123'))->toBeTrue();
});

test('manager cannot access wallet (cook-reserved) even with all permissions', function () {
    $manager = $this->createUserWithRole('manager');
    foreach (\App\Services\ManagerPermissionService::DELEGATABLE_PERMISSIONS as $perm) {
        $manager->givePermissionTo($perm);
    }

    expect($this->service->managerCanAccessPath($manager, 'dashboard/wallet'))->toBeFalse();
    expect($this->service->managerCanAccessPath($manager, 'dashboard/wallet/transactions'))->toBeFalse();
});

test('manager cannot access settings (cook-reserved)', function () {
    $manager = $this->createUserWithRole('manager');

    expect($this->service->managerCanAccessPath($manager, 'dashboard/settings'))->toBeFalse();
});

test('manager cannot access managers section (cook-reserved)', function () {
    $manager = $this->createUserWithRole('manager');

    expect($this->service->managerCanAccessPath($manager, 'dashboard/managers'))->toBeFalse();
});

test('manager cannot access profile (cook-reserved)', function () {
    $manager = $this->createUserWithRole('manager');

    expect($this->service->managerCanAccessPath($manager, 'dashboard/profile'))->toBeFalse();
});

test('manager cannot access setup (cook-reserved)', function () {
    $manager = $this->createUserWithRole('manager');

    expect($this->service->managerCanAccessPath($manager, 'dashboard/setup'))->toBeFalse();
});

test('manager can access meals with can-manage-meals permission', function () {
    $manager = $this->createUserWithRole('manager');
    $manager->givePermissionTo('can-manage-meals');

    expect($this->service->managerCanAccessPath($manager, 'dashboard/meals'))->toBeTrue();
    expect($this->service->managerCanAccessPath($manager, 'dashboard/tags'))->toBeTrue();
    expect($this->service->managerCanAccessPath($manager, 'dashboard/selling-units'))->toBeTrue();
});

test('manager can access schedule with can-manage-schedules permission', function () {
    $manager = $this->createUserWithRole('manager');
    $manager->givePermissionTo('can-manage-schedules');

    expect($this->service->managerCanAccessPath($manager, 'dashboard/schedule'))->toBeTrue();
});

test('manager can access locations with can-manage-locations permission', function () {
    $manager = $this->createUserWithRole('manager');
    $manager->givePermissionTo('can-manage-locations');

    expect($this->service->managerCanAccessPath($manager, 'dashboard/locations'))->toBeTrue();
});

test('manager can access analytics with can-view-cook-analytics permission', function () {
    $manager = $this->createUserWithRole('manager');
    $manager->givePermissionTo('can-view-cook-analytics');

    expect($this->service->managerCanAccessPath($manager, 'dashboard/analytics'))->toBeTrue();
});

test('manager cannot access unknown path', function () {
    $manager = $this->createUserWithRole('manager');

    expect($this->service->managerCanAccessPath($manager, 'dashboard/unknown-section'))->toBeFalse();
});

// ── hasAnyPermission ────────────────────────────────────────────────────────

test('hasAnyPermission returns false when manager has no delegatable permissions', function () {
    $manager = $this->createUserWithRole('manager');

    expect($this->service->hasAnyPermission($manager))->toBeFalse();
});

test('hasAnyPermission returns true when manager has at least one permission', function () {
    $manager = $this->createUserWithRole('manager');
    $manager->givePermissionTo('can-manage-orders');

    expect($this->service->hasAnyPermission($manager))->toBeTrue();
});

// ── getManagedTenants ───────────────────────────────────────────────────────

test('getManagedTenants returns all tenants for manager', function () {
    ['tenant' => $tenantA] = $this->createTenantWithCook();
    ['tenant' => $tenantB] = $this->createTenantWithCook();
    $manager = $this->createUserWithRole('manager');

    DB::table('tenant_managers')->insert([
        ['tenant_id' => $tenantA->id, 'user_id' => $manager->id, 'created_at' => now()],
        ['tenant_id' => $tenantB->id, 'user_id' => $manager->id, 'created_at' => now()],
    ]);

    $result = $this->service->getManagedTenants($manager);

    expect($result)->toHaveCount(2);
});

test('getManagedTenants returns empty collection when manager has no tenants', function () {
    $manager = $this->createUserWithRole('manager');

    $result = $this->service->getManagedTenants($manager);

    expect($result)->toBeEmpty();
});

test('getManagedTenants includes first_letter accessor', function () {
    ['tenant' => $tenant] = $this->createTenantWithCook();
    $manager = $this->createUserWithRole('manager');

    DB::table('tenant_managers')->insert([
        'tenant_id' => $tenant->id,
        'user_id' => $manager->id,
        'created_at' => now(),
    ]);

    $result = $this->service->getManagedTenants($manager);

    expect($result->first()->first_letter)->toBe(mb_strtoupper(mb_substr($tenant->name, 0, 1)));
});

// ── getSectionName ──────────────────────────────────────────────────────────

test('getSectionName extracts section from dashboard path', function () {
    expect($this->service->getSectionName('dashboard/orders'))->toBe('orders');
    expect($this->service->getSectionName('dashboard/orders/123'))->toBe('orders');
    expect($this->service->getSectionName('dashboard/meals'))->toBe('meals');
    expect($this->service->getSectionName('dashboard'))->toBe('home');
});

// ── getTenantDashboardUrl ───────────────────────────────────────────────────

test('getTenantDashboardUrl returns subdomain URL when no custom domain', function () {
    ['tenant' => $tenant] = $this->createTenantWithCook();

    $row = (object) [
        'slug' => $tenant->slug,
        'domain' => null,
    ];

    $url = $this->service->getTenantDashboardUrl($row);

    expect($url)->toContain($tenant->slug.'.')
        ->toContain('/dashboard');
});

test('getTenantDashboardUrl returns custom domain URL when set', function () {
    $row = (object) [
        'slug' => 'latifa',
        'domain' => 'latifa.cm',
    ];

    $url = $this->service->getTenantDashboardUrl($row);

    expect($url)->toContain('latifa.cm')
        ->toContain('/dashboard');
});
