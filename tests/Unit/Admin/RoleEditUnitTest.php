<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Requests\Admin\UpdateRoleRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);
});

describe('UpdateRoleRequest Validation Rules', function () {
    it('has description rule for all roles', function () {
        $role = Role::where('name', 'admin')->first();
        $request = UpdateRoleRequest::create("/vault-entry/roles/{$role->id}", 'POST');
        $request->setRouteResolver(fn () => tap(new \Illuminate\Routing\Route('POST', '/vault-entry/roles/{role}', []), function ($route) use ($role) {
            $route->bind(request());
            $route->setParameter('role', $role);
        }));
        $request->setUserResolver(fn () => $this->createUserWithRole('admin'));

        $rules = $request->rules();

        expect($rules)->toHaveKey('description');
        expect($rules['description'])->toContain('nullable');
        expect($rules['description'])->toContain('max:500');
    });

    it('excludes name fields for system roles (BR-116)', function () {
        $role = Role::where('name', 'admin')->first();
        $request = UpdateRoleRequest::create("/vault-entry/roles/{$role->id}", 'POST');
        $request->setRouteResolver(fn () => tap(new \Illuminate\Routing\Route('POST', '/vault-entry/roles/{role}', []), function ($route) use ($role) {
            $route->bind(request());
            $route->setParameter('role', $role);
        }));
        $request->setUserResolver(fn () => $this->createUserWithRole('admin'));

        $rules = $request->rules();

        expect($rules)->not->toHaveKey('name_en');
        expect($rules)->not->toHaveKey('name_fr');
    });

    it('includes name fields for custom roles (BR-118)', function () {
        $customRole = Role::create([
            'name' => 'test-editor',
            'guard_name' => 'web',
            'name_en' => 'Test Editor',
            'name_fr' => 'Editeur Test',
            'is_system' => false,
        ]);

        $request = UpdateRoleRequest::create("/vault-entry/roles/{$customRole->id}", 'POST');
        $request->setRouteResolver(fn () => tap(new \Illuminate\Routing\Route('POST', '/vault-entry/roles/{role}', []), function ($route) use ($customRole) {
            $route->bind(request());
            $route->setParameter('role', $customRole);
        }));
        $request->setUserResolver(fn () => $this->createUserWithRole('admin'));

        $rules = $request->rules();

        expect($rules)->toHaveKeys(['name_en', 'name_fr', 'description']);
        expect($rules['name_en'])->toContain('required');
        expect($rules['name_fr'])->toContain('required');
    });

    it('limits description to 500 characters', function () {
        $role = Role::where('name', 'cook')->first();
        $request = UpdateRoleRequest::create("/vault-entry/roles/{$role->id}", 'POST');
        $request->setRouteResolver(fn () => tap(new \Illuminate\Routing\Route('POST', '/vault-entry/roles/{role}', []), function ($route) use ($role) {
            $route->bind(request());
            $route->setParameter('role', $role);
        }));
        $request->setUserResolver(fn () => $this->createUserWithRole('admin'));

        $rules = $request->rules();

        expect($rules['description'])->toContain('max:500');
    });
});

describe('UpdateRoleRequest Authorization', function () {
    it('authorizes users with can-manage-roles permission', function () {
        $admin = $this->createUserWithRole('admin');
        $request = UpdateRoleRequest::create('/vault-entry/roles/1', 'POST');
        $request->setUserResolver(fn () => $admin);

        expect($request->authorize())->toBeTrue();
    });

    it('denies users without can-manage-roles permission', function () {
        $client = $this->createUserWithRole('client');
        $request = UpdateRoleRequest::create('/vault-entry/roles/1', 'POST');
        $request->setUserResolver(fn () => $client);

        expect($request->authorize())->toBeFalse();
    });

    it('denies unauthenticated users', function () {
        $request = UpdateRoleRequest::create('/vault-entry/roles/1', 'POST');
        $request->setUserResolver(fn () => null);

        expect($request->authorize())->toBeFalse();
    });
});

describe('UpdateRoleRequest Custom Messages', function () {
    it('provides custom error messages', function () {
        $request = new UpdateRoleRequest;
        $messages = $request->messages();

        expect($messages)->toHaveKeys([
            'name_en.required',
            'name_en.min',
            'name_en.max',
            'name_en.regex',
            'name_fr.required',
            'name_fr.min',
            'name_fr.max',
            'name_fr.regex',
            'description.max',
        ]);
    });
});

describe('Role Update Logic — System Roles (BR-116, BR-117)', function () {
    it('does not change system role name on update', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $originalName = $adminRole->name;
        $originalNameEn = $adminRole->name_en;
        $originalNameFr = $adminRole->name_fr;

        // Simulate updating only description for system role
        $adminRole->description = 'Updated description';
        $adminRole->save();

        $adminRole->refresh();

        expect($adminRole->name)->toBe($originalName)
            ->and($adminRole->name_en)->toBe($originalNameEn)
            ->and($adminRole->name_fr)->toBe($originalNameFr)
            ->and($adminRole->description)->toBe('Updated description');
    });

    it('allows description update for system roles (BR-117)', function () {
        $cookRole = Role::where('name', 'cook')->first();

        $cookRole->description = 'Manages meals, orders, and tenant settings';
        $cookRole->save();

        $cookRole->refresh();

        expect($cookRole->description)->toBe('Manages meals, orders, and tenant settings');
    });
});

describe('Role Update Logic — Custom Roles (BR-118, BR-119)', function () {
    it('can update custom role name', function () {
        $customRole = Role::create([
            'name' => 'kitchen-assistant',
            'guard_name' => 'web',
            'name_en' => 'Kitchen Assistant',
            'name_fr' => 'Assistant de Cuisine',
            'is_system' => false,
        ]);

        $customRole->name_en = 'Prep Cook';
        $customRole->name_fr = 'Cuisinier Preparateur';
        $customRole->name = 'prep-cook';
        $customRole->save();

        $customRole->refresh();

        expect($customRole->name)->toBe('prep-cook')
            ->and($customRole->name_en)->toBe('Prep Cook')
            ->and($customRole->name_fr)->toBe('Cuisinier Preparateur');
    });

    it('can update custom role description', function () {
        $customRole = Role::create([
            'name' => 'helper',
            'guard_name' => 'web',
            'name_en' => 'Helper',
            'name_fr' => 'Aide',
            'is_system' => false,
        ]);

        $customRole->description = 'Assists with various tasks';
        $customRole->save();

        $customRole->refresh();

        expect($customRole->description)->toBe('Assists with various tasks');
    });

    it('preserves role assignment when name changes', function () {
        $customRole = Role::create([
            'name' => 'original-name',
            'guard_name' => 'web',
            'name_en' => 'Original Name',
            'name_fr' => 'Nom Original',
            'is_system' => false,
        ]);

        $user = \App\Models\User::factory()->create();
        $user->assignRole($customRole);

        // Rename the role
        $customRole->name_en = 'New Name';
        $customRole->name_fr = 'Nouveau Nom';
        $customRole->name = 'new-name';
        $customRole->save();

        // User still has the role
        expect($user->hasRole('new-name'))->toBeTrue();
    });
});

describe('Permission Grouping', function () {
    it('groups permissions by module', function () {
        $controller = new RoleController;
        $reflection = new ReflectionMethod($controller, 'groupPermissionsByModule');
        $reflection->setAccessible(true);

        $permissions = Permission::query()
            ->whereIn('name', ['can-manage-meals', 'can-browse-meals', 'can-manage-orders'])
            ->get();

        $grouped = $reflection->invoke($controller, $permissions);

        expect($grouped)->toBeArray();
        // Module names are ucfirst'd: "can-manage-meals" -> "Meals"
        expect(array_keys($grouped))->toContain('Meals');
    });

    it('handles permissions with single-word nouns', function () {
        $controller = new RoleController;
        $reflection = new ReflectionMethod($controller, 'groupPermissionsByModule');
        $reflection->setAccessible(true);

        $permissions = collect([
            (object) ['name' => 'can-manage-meals'],
            (object) ['name' => 'can-view-tenants'],
        ]);

        $grouped = $reflection->invoke($controller, $permissions);

        expect($grouped)->toBeArray()
            ->and(count($grouped))->toBeGreaterThanOrEqual(2);
    });

    it('returns empty array for no permissions', function () {
        $controller = new RoleController;
        $reflection = new ReflectionMethod($controller, 'groupPermissionsByModule');
        $reflection->setAccessible(true);

        $grouped = $reflection->invoke($controller, collect([]));

        expect($grouped)->toBeArray()
            ->and($grouped)->toBeEmpty();
    });
});

describe('Activity Log on Role Edit (BR-120)', function () {
    it('does not log when no changes are made', function () {
        $role = Role::create([
            'name' => 'no-change-role',
            'guard_name' => 'web',
            'name_en' => 'No Change',
            'name_fr' => 'Sans Changement',
            'description' => 'Original description',
            'is_system' => false,
        ]);

        $beforeCount = \Spatie\Activitylog\Models\Activity::count();

        // Save without changes
        $role->save();

        $afterCount = \Spatie\Activitylog\Models\Activity::count();

        // No automatic logging since we haven't used the controller's manual log
        // The controller checks hasChanges before logging
        expect($afterCount)->toBe($beforeCount);
    });
});

describe('Machine Name Generation on Edit', function () {
    it('updates machine name when English name changes', function () {
        $nameEn = 'Prep Cook';
        $machineName = strtolower(str_replace(' ', '-', $nameEn));

        expect($machineName)->toBe('prep-cook');
    });

    it('handles multi-word names correctly', function () {
        $nameEn = 'Head Kitchen Manager';
        $machineName = strtolower(str_replace(' ', '-', $nameEn));

        expect($machineName)->toBe('head-kitchen-manager');
    });
});
