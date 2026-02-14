<?php

declare(strict_types=1);

use Database\Seeders\RoleAndPermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

describe('Role Creation', function () {
    it('creates all five roles after seeding', function () {
        $this->seed(RoleAndPermissionSeeder::class);

        $roles = Role::pluck('name')->toArray();

        expect($roles)->toContain('super-admin')
            ->toContain('admin')
            ->toContain('cook')
            ->toContain('manager')
            ->toContain('client');
        expect(Role::count())->toBe(5);
    });

    it('uses web guard for all roles', function () {
        $this->seed(RoleAndPermissionSeeder::class);

        Role::all()->each(function ($role) {
            expect($role->guard_name)->toBe('web');
        });
    });
});

describe('Permission Creation', function () {
    it('creates all defined permissions after seeding', function () {
        $this->seed(RoleAndPermissionSeeder::class);

        $expectedCount = count(RoleAndPermissionSeeder::allPermissions());

        expect(Permission::count())->toBe($expectedCount);
    });

    it('uses web guard for all permissions', function () {
        $this->seed(RoleAndPermissionSeeder::class);

        Permission::all()->each(function ($permission) {
            expect($permission->guard_name)->toBe('web');
        });
    });

    it('creates all admin permissions', function () {
        $this->seed(RoleAndPermissionSeeder::class);

        foreach (RoleAndPermissionSeeder::adminPermissions() as $permName) {
            expect(Permission::findByName($permName, 'web'))->not->toBeNull();
        }
    });

    it('creates all cook permissions', function () {
        $this->seed(RoleAndPermissionSeeder::class);

        foreach (RoleAndPermissionSeeder::cookPermissions() as $permName) {
            expect(Permission::findByName($permName, 'web'))->not->toBeNull();
        }
    });

    it('creates all client permissions', function () {
        $this->seed(RoleAndPermissionSeeder::class);

        foreach (RoleAndPermissionSeeder::clientPermissions() as $permName) {
            expect(Permission::findByName($permName, 'web'))->not->toBeNull();
        }
    });
});

describe('Super-Admin Role Permissions', function () {
    it('has all permissions in the system', function () {
        $this->seed(RoleAndPermissionSeeder::class);

        $superAdmin = Role::findByName('super-admin', 'web');
        $totalPermissions = Permission::count();

        expect($superAdmin->permissions->count())->toBe($totalPermissions);
    });
});

describe('Admin Role Permissions', function () {
    it('has admin and client permissions', function () {
        $this->seed(RoleAndPermissionSeeder::class);

        $admin = Role::findByName('admin', 'web');
        $expectedCount = count(RoleAndPermissionSeeder::adminPermissions())
            + count(RoleAndPermissionSeeder::clientPermissions());

        expect($admin->permissions->count())->toBe($expectedCount);
    });

    it('has all admin-specific permissions', function () {
        $this->seed(RoleAndPermissionSeeder::class);

        $admin = Role::findByName('admin', 'web');

        foreach (RoleAndPermissionSeeder::adminPermissions() as $permName) {
            expect($admin->hasPermissionTo($permName))->toBeTrue();
        }
    });

    it('has all client permissions for role inheritance', function () {
        $this->seed(RoleAndPermissionSeeder::class);

        $admin = Role::findByName('admin', 'web');

        foreach (RoleAndPermissionSeeder::clientPermissions() as $permName) {
            expect($admin->hasPermissionTo($permName))->toBeTrue();
        }
    });

    it('does not have cook-specific permissions', function () {
        $this->seed(RoleAndPermissionSeeder::class);

        $admin = Role::findByName('admin', 'web');

        foreach (RoleAndPermissionSeeder::cookPermissions() as $permName) {
            expect($admin->hasPermissionTo($permName))->toBeFalse();
        }
    });
});

describe('Cook Role Permissions', function () {
    it('has cook and client permissions', function () {
        $this->seed(RoleAndPermissionSeeder::class);

        $cook = Role::findByName('cook', 'web');
        $expectedCount = count(RoleAndPermissionSeeder::cookPermissions())
            + count(RoleAndPermissionSeeder::clientPermissions());

        expect($cook->permissions->count())->toBe($expectedCount);
    });

    it('has all cook-specific permissions', function () {
        $this->seed(RoleAndPermissionSeeder::class);

        $cook = Role::findByName('cook', 'web');

        foreach (RoleAndPermissionSeeder::cookPermissions() as $permName) {
            expect($cook->hasPermissionTo($permName))->toBeTrue();
        }
    });

    it('has all client permissions for role inheritance', function () {
        $this->seed(RoleAndPermissionSeeder::class);

        $cook = Role::findByName('cook', 'web');

        foreach (RoleAndPermissionSeeder::clientPermissions() as $permName) {
            expect($cook->hasPermissionTo($permName))->toBeTrue();
        }
    });

    it('does not have admin-specific permissions', function () {
        $this->seed(RoleAndPermissionSeeder::class);

        $cook = Role::findByName('cook', 'web');

        foreach (RoleAndPermissionSeeder::adminPermissions() as $permName) {
            expect($cook->hasPermissionTo($permName))->toBeFalse();
        }
    });
});

describe('Manager Role Permissions', function () {
    it('has zero permissions by default', function () {
        $this->seed(RoleAndPermissionSeeder::class);

        $manager = Role::findByName('manager', 'web');

        expect($manager->permissions->count())->toBe(0);
    });
});

describe('Client Role Permissions', function () {
    it('has only client permissions', function () {
        $this->seed(RoleAndPermissionSeeder::class);

        $client = Role::findByName('client', 'web');

        expect($client->permissions->count())->toBe(count(RoleAndPermissionSeeder::clientPermissions()));
    });

    it('has all client-specific permissions', function () {
        $this->seed(RoleAndPermissionSeeder::class);

        $client = Role::findByName('client', 'web');

        foreach (RoleAndPermissionSeeder::clientPermissions() as $permName) {
            expect($client->hasPermissionTo($permName))->toBeTrue();
        }
    });

    it('does not have admin permissions', function () {
        $this->seed(RoleAndPermissionSeeder::class);

        $client = Role::findByName('client', 'web');

        foreach (RoleAndPermissionSeeder::adminPermissions() as $permName) {
            expect($client->hasPermissionTo($permName))->toBeFalse();
        }
    });

    it('does not have cook permissions', function () {
        $this->seed(RoleAndPermissionSeeder::class);

        $client = Role::findByName('client', 'web');

        foreach (RoleAndPermissionSeeder::cookPermissions() as $permName) {
            expect($client->hasPermissionTo($permName))->toBeFalse();
        }
    });
});

describe('Seeder Idempotency', function () {
    it('does not create duplicate roles when run twice', function () {
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(RoleAndPermissionSeeder::class);

        expect(Role::count())->toBe(5);
    });

    it('does not create duplicate permissions when run twice', function () {
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(RoleAndPermissionSeeder::class);

        $expectedCount = count(RoleAndPermissionSeeder::allPermissions());

        expect(Permission::count())->toBe($expectedCount);
    });

    it('maintains correct role-permission assignments after re-run', function () {
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(RoleAndPermissionSeeder::class);

        $superAdmin = Role::findByName('super-admin', 'web');
        $manager = Role::findByName('manager', 'web');

        expect($superAdmin->permissions->count())->toBe(Permission::count());
        expect($manager->permissions->count())->toBe(0);
    });
});

describe('Role Inheritance — Every Role Has Client Capabilities', function () {
    it('super-admin includes all client permissions', function () {
        $this->seed(RoleAndPermissionSeeder::class);

        $superAdmin = Role::findByName('super-admin', 'web');

        foreach (RoleAndPermissionSeeder::clientPermissions() as $permName) {
            expect($superAdmin->hasPermissionTo($permName))->toBeTrue();
        }
    });

    it('admin includes all client permissions', function () {
        $this->seed(RoleAndPermissionSeeder::class);

        $admin = Role::findByName('admin', 'web');

        foreach (RoleAndPermissionSeeder::clientPermissions() as $permName) {
            expect($admin->hasPermissionTo($permName))->toBeTrue();
        }
    });

    it('cook includes all client permissions', function () {
        $this->seed(RoleAndPermissionSeeder::class);

        $cook = Role::findByName('cook', 'web');

        foreach (RoleAndPermissionSeeder::clientPermissions() as $permName) {
            expect($cook->hasPermissionTo($permName))->toBeTrue();
        }
    });
});
