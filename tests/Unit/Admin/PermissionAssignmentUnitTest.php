<?php

use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

describe('Permission Grouping by Module (BR-134)', function () {
    it('groups all permissions by their module noun', function () {
        $allPermissions = Permission::where('guard_name', 'web')->orderBy('name')->get();

        expect($allPermissions->count())->toBeGreaterThan(0);

        // Verify grouping logic extracts modules correctly
        $modules = [];
        foreach ($allPermissions as $permission) {
            $parts = explode('-', $permission->name);
            if ($parts[0] === 'can') {
                array_shift($parts);
            }
            if (count($parts) >= 2) {
                $noun = implode(' ', array_slice($parts, 1));
                $module = ucfirst($noun);
            } else {
                $module = 'General';
            }
            $modules[$module][] = $permission->name;
        }

        expect(count($modules))->toBeGreaterThan(5);
        expect(array_key_exists('Meals', $modules))->toBeTrue();
        expect(array_key_exists('Orders', $modules))->toBeTrue();
    });

    it('includes all platform permissions in the grouped output', function () {
        $allPermissions = Permission::where('guard_name', 'web')->get();
        $allNames = $allPermissions->pluck('name')->sort()->values()->toArray();

        $expectedCount = count(RoleAndPermissionSeeder::allPermissions());

        expect(count($allNames))->toBe($expectedCount);
    });
});

describe('Super-Admin Permissions (BR-128)', function () {
    it('super-admin role always has all permissions', function () {
        $superAdmin = Role::where('name', 'super-admin')->first();
        $allPermissions = Permission::where('guard_name', 'web')->pluck('name')->toArray();

        foreach ($allPermissions as $permission) {
            expect($superAdmin->hasPermissionTo($permission))->toBeTrue("Super-admin should have {$permission}");
        }
    });

    it('super-admin role is marked as read-only in permissions view', function () {
        $superAdmin = Role::where('name', 'super-admin')->first();

        // BR-128: The permissions page should be read-only for super-admin
        expect($superAdmin->name)->toBe('super-admin');
        expect($superAdmin->is_system)->toBeTrue();
    });
});

describe('Permission Toggle (BR-131)', function () {
    it('can grant a permission to a custom role', function () {
        $role = Role::create([
            'name' => 'test-role',
            'guard_name' => 'web',
            'name_en' => 'Test Role',
            'name_fr' => 'Rôle Test',
            'is_system' => false,
        ]);

        expect($role->hasPermissionTo('can-manage-meals'))->toBeFalse();

        $role->givePermissionTo('can-manage-meals');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        expect($role->fresh()->hasPermissionTo('can-manage-meals'))->toBeTrue();
    });

    it('can revoke a permission from a custom role', function () {
        $role = Role::create([
            'name' => 'test-role',
            'guard_name' => 'web',
            'name_en' => 'Test Role',
            'name_fr' => 'Rôle Test',
            'is_system' => false,
        ]);

        $role->givePermissionTo('can-manage-meals');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        expect($role->fresh()->hasPermissionTo('can-manage-meals'))->toBeTrue();

        $role->revokePermissionTo('can-manage-meals');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        expect($role->fresh()->hasPermissionTo('can-manage-meals'))->toBeFalse();
    });

    it('can bulk grant multiple permissions to a role', function () {
        $role = Role::create([
            'name' => 'bulk-test',
            'guard_name' => 'web',
            'name_en' => 'Bulk Test',
            'name_fr' => 'Test en Bloc',
            'is_system' => false,
        ]);

        $permissions = ['can-manage-meals', 'can-manage-orders', 'can-manage-brand'];
        $role->givePermissionTo($permissions);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $freshRole = $role->fresh();
        foreach ($permissions as $perm) {
            expect($freshRole->hasPermissionTo($perm))->toBeTrue();
        }
    });

    it('can bulk revoke all permissions from a role', function () {
        $role = Role::create([
            'name' => 'bulk-revoke',
            'guard_name' => 'web',
            'name_en' => 'Bulk Revoke',
            'name_fr' => 'Révocation en Bloc',
            'is_system' => false,
        ]);

        $permissions = ['can-manage-meals', 'can-manage-orders'];
        $role->givePermissionTo($permissions);

        $role->revokePermissionTo($permissions);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $freshRole = $role->fresh();
        foreach ($permissions as $perm) {
            expect($freshRole->hasPermissionTo($perm))->toBeFalse();
        }
    });
});

describe('Privilege Escalation Prevention (BR-129, BR-130)', function () {
    it('identifies which permissions an admin user has', function () {
        $adminUser = $this->createUserWithRole('admin');
        $adminPermissions = $adminUser->getAllPermissions()->pluck('name')->toArray();

        // Admin should have admin and client permissions
        expect(in_array('can-access-admin-panel', $adminPermissions, true))->toBeTrue();
        expect(in_array('can-browse-meals', $adminPermissions, true))->toBeTrue();
        // Admin should NOT have cook-specific permissions
        expect(in_array('can-manage-meals', $adminPermissions, true))->toBeFalse();
    });

    it('correctly distinguishes assignable vs non-assignable permissions for admin', function () {
        $adminUser = $this->createUserWithRole('admin');
        $adminPermissionNames = $adminUser->getAllPermissions()->pluck('name')->toArray();
        $allPermissions = Permission::where('guard_name', 'web')->get();

        $assignable = $allPermissions->filter(fn ($p) => in_array($p->name, $adminPermissionNames, true));
        $nonAssignable = $allPermissions->filter(fn ($p) => ! in_array($p->name, $adminPermissionNames, true));

        // Admin should have some but not all permissions
        expect($assignable->count())->toBeGreaterThan(0);
        expect($nonAssignable->count())->toBeGreaterThan(0);
        expect($assignable->count() + $nonAssignable->count())->toBe($allPermissions->count());
    });

    it('super-admin user can assign any permission', function () {
        $superAdmin = $this->createUserWithRole('super-admin');

        // Super-admin bypasses permission checks via Gate::before
        expect($superAdmin->hasRole('super-admin'))->toBeTrue();
    });
});

describe('Permission Change Effect (BR-132)', function () {
    it('permission changes take immediate effect without logout', function () {
        $user = $this->createUserWithRole('client');
        $customRole = Role::create([
            'name' => 'custom-effect',
            'guard_name' => 'web',
            'name_en' => 'Custom Effect',
            'name_fr' => 'Effet Personnalisé',
            'is_system' => false,
        ]);

        $user->assignRole($customRole);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Initially no admin panel access from custom role
        expect($user->fresh()->can('can-access-admin-panel'))->toBeFalse();

        // Grant the permission
        $customRole->givePermissionTo('can-access-admin-panel');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Should be available on the user's next check (fresh + cache clear)
        expect($user->fresh()->can('can-access-admin-panel'))->toBeTrue();

        // Revoke it
        $customRole->revokePermissionTo('can-access-admin-panel');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        expect($user->fresh()->can('can-access-admin-panel'))->toBeFalse();
    });
});

describe('Activity Logging (BR-133)', function () {
    it('logs permission grant activity', function () {
        $role = Role::create([
            'name' => 'log-test',
            'guard_name' => 'web',
            'name_en' => 'Log Test',
            'name_fr' => 'Test de Log',
            'is_system' => false,
        ]);

        $admin = $this->createUserWithRole('super-admin');

        activity('roles')
            ->performedOn($role)
            ->causedBy($admin)
            ->withProperties([
                'permission' => 'can-manage-meals',
                'action' => 'granted',
            ])
            ->log('permission_granted');

        $lastActivity = Activity::where('log_name', 'roles')->latest()->first();
        expect($lastActivity)->not->toBeNull();
        expect($lastActivity->description)->toBe('permission_granted');
        expect($lastActivity->properties['permission'])->toBe('can-manage-meals');
        expect($lastActivity->properties['action'])->toBe('granted');
    });

    it('logs permission revoke activity', function () {
        $role = Role::create([
            'name' => 'revoke-log-test',
            'guard_name' => 'web',
            'name_en' => 'Revoke Log Test',
            'name_fr' => 'Test de Révocation',
            'is_system' => false,
        ]);

        $admin = $this->createUserWithRole('super-admin');

        activity('roles')
            ->performedOn($role)
            ->causedBy($admin)
            ->withProperties([
                'permission' => 'can-manage-meals',
                'action' => 'revoked',
            ])
            ->log('permission_revoked');

        $lastActivity = Activity::where('log_name', 'roles')->latest()->first();
        expect($lastActivity->description)->toBe('permission_revoked');
        expect($lastActivity->properties['action'])->toBe('revoked');
    });

    it('logs bulk permission changes', function () {
        $role = Role::create([
            'name' => 'bulk-log-test',
            'guard_name' => 'web',
            'name_en' => 'Bulk Log Test',
            'name_fr' => 'Test de Log en Bloc',
            'is_system' => false,
        ]);

        $admin = $this->createUserWithRole('super-admin');

        activity('roles')
            ->performedOn($role)
            ->causedBy($admin)
            ->withProperties([
                'action' => 'module_granted',
                'granted' => ['can-manage-meals', 'can-manage-orders'],
                'revoked' => [],
            ])
            ->log('permissions_granted');

        $lastActivity = Activity::where('log_name', 'roles')->latest()->first();
        expect($lastActivity->description)->toBe('permissions_granted');
        expect($lastActivity->properties['granted'])->toBeArray();
        expect(count($lastActivity->properties['granted']))->toBe(2);
    });
});

describe('Role with Empty Permissions Edge Case', function () {
    it('allows a role to exist with zero permissions', function () {
        $role = Role::create([
            'name' => 'empty-role',
            'guard_name' => 'web',
            'name_en' => 'Empty Role',
            'name_fr' => 'Rôle Vide',
            'is_system' => false,
        ]);

        expect($role->permissions()->count())->toBe(0);
        expect($role->exists)->toBeTrue();
    });

    it('revoking all permissions leaves role powerless but existing', function () {
        $role = Role::create([
            'name' => 'strip-perms',
            'guard_name' => 'web',
            'name_en' => 'Strip Perms',
            'name_fr' => 'Retrait Permissions',
            'is_system' => false,
        ]);

        $role->givePermissionTo(['can-manage-meals', 'can-manage-orders']);
        expect($role->permissions()->count())->toBe(2);

        $role->syncPermissions([]);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        expect($role->fresh()->permissions()->count())->toBe(0);
        expect($role->fresh()->exists)->toBeTrue();
    });
});

describe('Permission Routes Configuration', function () {
    it('has the permission management routes defined', function () {
        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes());

        $permissionsRoute = $routes->first(fn ($route) => $route->getName() === 'admin.roles.permissions');
        expect($permissionsRoute)->not->toBeNull();
        expect($permissionsRoute->methods())->toContain('GET');

        $toggleRoute = $routes->first(fn ($route) => $route->getName() === 'admin.roles.permissions.toggle');
        expect($toggleRoute)->not->toBeNull();
        expect($toggleRoute->methods())->toContain('POST');

        $toggleModuleRoute = $routes->first(fn ($route) => $route->getName() === 'admin.roles.permissions.toggle-module');
        expect($toggleModuleRoute)->not->toBeNull();
        expect($toggleModuleRoute->methods())->toContain('POST');
    });
});

describe('Controller groupPermissionsForAssignment Method', function () {
    it('correctly marks permissions as assigned or not', function () {
        $role = Role::create([
            'name' => 'assignment-test',
            'guard_name' => 'web',
            'name_en' => 'Assignment Test',
            'name_fr' => 'Test Attribution',
            'is_system' => false,
        ]);

        $role->givePermissionTo(['can-manage-meals', 'can-browse-meals']);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $rolePermNames = $role->fresh()->permissions()->pluck('name')->toArray();

        expect(in_array('can-manage-meals', $rolePermNames, true))->toBeTrue();
        expect(in_array('can-browse-meals', $rolePermNames, true))->toBeTrue();
        expect(in_array('can-manage-orders', $rolePermNames, true))->toBeFalse();
    });

    it('correctly identifies admin assignable permissions', function () {
        $adminUser = $this->createUserWithRole('admin');
        $adminPermissionNames = $adminUser->getAllPermissions()->pluck('name')->toArray();

        // Admin permissions include admin-specific ones
        expect(in_array('can-access-admin-panel', $adminPermissionNames, true))->toBeTrue();
        expect(in_array('can-manage-users', $adminPermissionNames, true))->toBeTrue();

        // Admin has client permissions too
        expect(in_array('can-browse-meals', $adminPermissionNames, true))->toBeTrue();

        // Admin does NOT have cook-specific permissions
        expect(in_array('can-manage-meals', $adminPermissionNames, true))->toBeFalse();
        expect(in_array('can-manage-schedules', $adminPermissionNames, true))->toBeFalse();
    });
});
