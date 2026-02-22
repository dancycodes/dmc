<?php

/**
 * F-078: Cook Quick Actions Panel — Unit Tests
 *
 * Tests for CookDashboardService::getQuickActions() and formatPendingCount().
 *
 * BR-174: Default actions: Create New Meal, View Pending Orders, Update Availability, View Wallet
 * BR-175: "Complete Setup" appears first when setup is incomplete
 * BR-176: Pending count badge shown on "View Pending Orders" action
 * BR-177: Actions filtered by user permissions
 * BR-178: Each action has a URL for navigation
 * BR-179: Real-time updates via component state (covered by controller)
 * Edge: All permissions revoked from manager → empty actions array
 * Edge: 99+ pending orders → badge shows "99+"
 */

use App\Models\Tenant;
use App\Models\User;
use App\Services\CookDashboardService;

/**
 * Build a Tenant mock with a given setup state and cook_id.
 * Uses getAttribute mock because Eloquent intercepts property assignment.
 */
function mockTenant(bool $setupComplete, int $cookId): Tenant
{
    $tenant = Mockery::mock(Tenant::class);
    $tenant->shouldReceive('getAttribute')->with('cook_id')->andReturn($cookId);
    $tenant->shouldReceive('isSetupComplete')->andReturn($setupComplete);

    return $tenant;
}

/**
 * Build a User mock with given id and direct permissions array.
 * Uses getAttribute mock because Eloquent intercepts property assignment.
 */
function mockUser(int $id, array $permissions = []): User
{
    $user = Mockery::mock(User::class);
    $user->shouldReceive('getAttribute')->with('id')->andReturn($id);
    $user->shouldReceive('hasDirectPermission')
        ->andReturnUsing(fn (string $p) => in_array($p, $permissions, true));

    return $user;
}

// --- formatPendingCount ---

test('formatPendingCount returns string for values under 100', function (): void {
    expect(CookDashboardService::formatPendingCount(0))->toBe('0');
    expect(CookDashboardService::formatPendingCount(1))->toBe('1');
    expect(CookDashboardService::formatPendingCount(99))->toBe('99');
});

test('formatPendingCount returns 99+ for values over 99', function (): void {
    expect(CookDashboardService::formatPendingCount(100))->toBe('99+');
    expect(CookDashboardService::formatPendingCount(999))->toBe('99+');
});

// --- Cook with complete setup: 4 default actions ---

test('cook with complete setup sees 4 default quick actions', function (): void {
    $service = new CookDashboardService;
    $tenant = mockTenant(setupComplete: true, cookId: 1);
    $user = mockUser(id: 1);

    $actions = $service->getQuickActions($tenant, $user, pendingOrders: 0);

    $ids = array_column($actions, 'id');
    expect($ids)->not->toContain('complete-setup');
    expect($ids)->toContain('create-meal');
    expect($ids)->toContain('pending-orders');
    expect($ids)->toContain('update-availability');
    expect($ids)->toContain('view-wallet');
    expect(count($actions))->toBe(4);
});

// --- BR-175: Setup incomplete ---

test('cook with incomplete setup sees Complete Setup as first action', function (): void {
    $service = new CookDashboardService;
    $tenant = mockTenant(setupComplete: false, cookId: 1);
    $user = mockUser(id: 1);

    $actions = $service->getQuickActions($tenant, $user, pendingOrders: 0);

    expect($actions[0]['id'])->toBe('complete-setup');
    expect($actions[0]['color'])->toBe('warning');
    expect(count($actions))->toBe(5); // setup + 4 defaults
});

// --- BR-176: Pending order count badge ---

test('pending orders action has correct badge count', function (): void {
    $service = new CookDashboardService;
    $tenant = mockTenant(setupComplete: true, cookId: 1);
    $user = mockUser(id: 1);

    $actions = $service->getQuickActions($tenant, $user, pendingOrders: 7);
    $pendingAction = collect($actions)->firstWhere('id', 'pending-orders');

    expect($pendingAction['badge'])->toBe('7');
});

test('pending orders badge shows 99+ when count exceeds 99', function (): void {
    $service = new CookDashboardService;
    $tenant = mockTenant(setupComplete: true, cookId: 1);
    $user = mockUser(id: 1);

    $actions = $service->getQuickActions($tenant, $user, pendingOrders: 150);
    $pendingAction = collect($actions)->firstWhere('id', 'pending-orders');

    expect($pendingAction['badge'])->toBe('99+');
});

test('pending orders badge shows 0 when no pending orders', function (): void {
    $service = new CookDashboardService;
    $tenant = mockTenant(setupComplete: true, cookId: 1);
    $user = mockUser(id: 1);

    $actions = $service->getQuickActions($tenant, $user, pendingOrders: 0);
    $pendingAction = collect($actions)->firstWhere('id', 'pending-orders');

    expect($pendingAction['badge'])->toBe('0');
});

// --- BR-177: Permission filtering for managers ---

test('manager with all cook permissions sees all non-wallet actions', function (): void {
    $service = new CookDashboardService;
    $tenant = mockTenant(setupComplete: true, cookId: 1);
    $user = mockUser(id: 2, permissions: ['can-manage-meals', 'can-manage-orders']);

    $actions = $service->getQuickActions($tenant, $user, pendingOrders: 0);
    $ids = array_column($actions, 'id');

    expect($ids)->toContain('create-meal');
    expect($ids)->toContain('pending-orders');
    expect($ids)->toContain('update-availability');
    expect($ids)->not->toContain('view-wallet'); // Wallet never shown to managers
});

test('manager without wallet permission never sees wallet action', function (): void {
    $service = new CookDashboardService;
    $tenant = mockTenant(setupComplete: true, cookId: 1);
    $user = mockUser(id: 2, permissions: ['can-manage-meals', 'can-manage-orders']);

    $actions = $service->getQuickActions($tenant, $user, pendingOrders: 0);

    expect(array_column($actions, 'id'))->not->toContain('view-wallet');
});

test('manager without can-manage-orders cannot see pending orders action', function (): void {
    $service = new CookDashboardService;
    $tenant = mockTenant(setupComplete: true, cookId: 1);
    $user = mockUser(id: 2, permissions: ['can-manage-meals']); // no orders permission

    $actions = $service->getQuickActions($tenant, $user, pendingOrders: 5);
    $ids = array_column($actions, 'id');

    expect($ids)->not->toContain('pending-orders');
    expect($ids)->toContain('create-meal');
    expect($ids)->toContain('update-availability');
});

test('manager without can-manage-meals cannot see create-meal or update-availability', function (): void {
    $service = new CookDashboardService;
    $tenant = mockTenant(setupComplete: true, cookId: 1);
    $user = mockUser(id: 2, permissions: ['can-manage-orders']); // no meals permission

    $actions = $service->getQuickActions($tenant, $user, pendingOrders: 0);
    $ids = array_column($actions, 'id');

    expect($ids)->not->toContain('create-meal');
    expect($ids)->not->toContain('update-availability');
    expect($ids)->toContain('pending-orders');
});

test('manager with no permissions returns empty actions array', function (): void {
    $service = new CookDashboardService;
    $tenant = mockTenant(setupComplete: true, cookId: 1);
    $user = mockUser(id: 2, permissions: []); // no permissions at all

    $actions = $service->getQuickActions($tenant, $user, pendingOrders: 0);

    expect($actions)->toBeEmpty();
});

// --- Each action has a non-empty path ---

test('all actions have non-empty path', function (): void {
    $service = new CookDashboardService;
    $tenant = mockTenant(setupComplete: false, cookId: 1);
    $user = mockUser(id: 1); // cook = all actions

    $actions = $service->getQuickActions($tenant, $user, pendingOrders: 3);

    foreach ($actions as $action) {
        expect($action['path'])->not->toBeEmpty();
    }
});

// --- Cook (not manager) always gets wallet ---

test('cook always sees wallet action regardless of permissions', function (): void {
    $service = new CookDashboardService;
    $tenant = mockTenant(setupComplete: true, cookId: 5);
    $user = mockUser(id: 5); // user is the cook

    $actions = $service->getQuickActions($tenant, $user, pendingOrders: 0);

    expect(array_column($actions, 'id'))->toContain('view-wallet');
});
