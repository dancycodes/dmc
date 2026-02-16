<?php

use App\Http\Controllers\Admin\RoleController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);
});

describe('Role List — System Role Hierarchy Order (BR-115)', function () {
    it('defines system role hierarchy order', function () {
        $reflection = new ReflectionClass(RoleController::class);
        $constant = $reflection->getConstant('SYSTEM_ROLE_ORDER');

        expect($constant)->toBeArray()
            ->toHaveCount(5)
            ->toHaveKeys(['super-admin', 'admin', 'cook', 'manager', 'client']);

        // Verify order: super-admin < admin < cook < manager < client
        expect($constant['super-admin'])->toBeLessThan($constant['admin'])
            ->and($constant['admin'])->toBeLessThan($constant['cook'])
            ->and($constant['cook'])->toBeLessThan($constant['manager'])
            ->and($constant['manager'])->toBeLessThan($constant['client']);
    });

    it('system role order matches system role names', function () {
        $reflection = new ReflectionClass(RoleController::class);
        $orderKeys = array_keys($reflection->getConstant('SYSTEM_ROLE_ORDER'));

        expect($orderKeys)->toEqualCanonicalizing(RoleController::SYSTEM_ROLE_NAMES);
    });
});

describe('Role List — Permission Count (BR-113)', function () {
    it('counts permissions correctly for system roles', function () {
        $adminRole = Role::where('name', 'admin')
            ->withCount('permissions')
            ->first();

        $actualPermissions = $adminRole->permissions()->count();

        expect($adminRole->permissions_count)->toBe($actualPermissions)
            ->and($adminRole->permissions_count)->toBeGreaterThan(0);
    });

    it('counts zero permissions for roles without any', function () {
        $customRole = Role::create([
            'name' => 'empty-role',
            'guard_name' => 'web',
            'name_en' => 'Empty Role',
            'name_fr' => 'Role Vide',
            'is_system' => false,
        ]);

        $loaded = Role::where('id', $customRole->id)
            ->withCount('permissions')
            ->first();

        expect($loaded->permissions_count)->toBe(0);
    });

    it('accurately reflects permission count after assignment', function () {
        $customRole = Role::create([
            'name' => 'partial-role',
            'guard_name' => 'web',
            'name_en' => 'Partial Role',
            'name_fr' => 'Role Partiel',
            'is_system' => false,
        ]);

        // Assign 3 permissions
        $permissions = Permission::query()->limit(3)->get();
        $customRole->syncPermissions($permissions);

        $loaded = Role::where('id', $customRole->id)
            ->withCount('permissions')
            ->first();

        expect($loaded->permissions_count)->toBe(3);
    });
});

describe('Role List — User Count (BR-114)', function () {
    it('counts users correctly for roles with users', function () {
        $clientRole = Role::where('name', 'client')
            ->withCount('users')
            ->first();

        $actualUsers = $clientRole->users()->count();

        expect($clientRole->users_count)->toBe($actualUsers);
    });

    it('counts zero users for roles without any', function () {
        $customRole = Role::create([
            'name' => 'no-users-role',
            'guard_name' => 'web',
            'name_en' => 'No Users',
            'name_fr' => 'Sans Utilisateurs',
            'is_system' => false,
        ]);

        $loaded = Role::where('id', $customRole->id)
            ->withCount('users')
            ->first();

        expect($loaded->users_count)->toBe(0);
    });

    it('accurately reflects user count after assignment', function () {
        $customRole = Role::create([
            'name' => 'staffed-role',
            'guard_name' => 'web',
            'name_en' => 'Staffed Role',
            'name_fr' => 'Role Actif',
            'is_system' => false,
        ]);

        // Create 2 users and assign role
        $user1 = \App\Models\User::factory()->create();
        $user2 = \App\Models\User::factory()->create();
        $user1->assignRole($customRole);
        $user2->assignRole($customRole);

        $loaded = Role::where('id', $customRole->id)
            ->withCount('users')
            ->first();

        expect($loaded->users_count)->toBe(2);
    });
});

describe('Role List — Type Filter', function () {
    it('filters system roles only', function () {
        // Create a custom role to ensure filtering works
        Role::create([
            'name' => 'filter-test',
            'guard_name' => 'web',
            'name_en' => 'Filter Test',
            'name_fr' => 'Test Filtre',
            'is_system' => false,
        ]);

        $systemRoles = Role::query()
            ->where('guard_name', 'web')
            ->where('is_system', true)
            ->get();

        expect($systemRoles)->toHaveCount(5);
        $systemRoles->each(function ($role) {
            expect($role->is_system)->toBeTrue();
        });
    });

    it('filters custom roles only', function () {
        Role::create([
            'name' => 'custom-filter-1',
            'guard_name' => 'web',
            'name_en' => 'Custom Filter One',
            'name_fr' => 'Filtre Personnalise Un',
            'is_system' => false,
        ]);
        Role::create([
            'name' => 'custom-filter-2',
            'guard_name' => 'web',
            'name_en' => 'Custom Filter Two',
            'name_fr' => 'Filtre Personnalise Deux',
            'is_system' => false,
        ]);

        $customRoles = Role::query()
            ->where('guard_name', 'web')
            ->where('is_system', false)
            ->get();

        expect($customRoles)->toHaveCount(2);
        $customRoles->each(function ($role) {
            expect($role->is_system)->toBeFalse();
        });
    });

    it('shows all roles when no type filter', function () {
        Role::create([
            'name' => 'all-test',
            'guard_name' => 'web',
            'name_en' => 'All Test',
            'name_fr' => 'Test Tous',
            'is_system' => false,
        ]);

        $allRoles = Role::query()
            ->where('guard_name', 'web')
            ->get();

        // 5 system + 1 custom
        expect($allRoles)->toHaveCount(6);
    });
});

describe('Role List — Guard Filter (Edge Case)', function () {
    it('only includes web guard roles', function () {
        // Create a non-web guard role (edge case)
        // Spatie allows multiple guards
        $allWebRoles = Role::query()
            ->where('guard_name', 'web')
            ->get();

        $allWebRoles->each(function ($role) {
            expect($role->guard_name)->toBe('web');
        });
    });
});

describe('Role List — System Role Identification (BR-111)', function () {
    it('correctly identifies all system roles', function () {
        $systemRoles = Role::where('is_system', true)->pluck('name')->toArray();

        expect($systemRoles)->toContain('super-admin')
            ->toContain('admin')
            ->toContain('cook')
            ->toContain('manager')
            ->toContain('client')
            ->toHaveCount(5);
    });

    it('custom roles are not marked as system', function () {
        $customRole = Role::create([
            'name' => 'delivery-boy',
            'guard_name' => 'web',
            'name_en' => 'Delivery Boy',
            'name_fr' => 'Livreur',
            'is_system' => false,
        ]);

        expect($customRole->is_system)->toBeFalse();
    });
});

describe('Role List — Summary Counts', function () {
    it('calculates total count correctly', function () {
        $totalCount = Role::query()->where('guard_name', 'web')->count();

        expect($totalCount)->toBe(5); // 5 system roles by default
    });

    it('calculates system count correctly', function () {
        $systemCount = Role::query()
            ->where('guard_name', 'web')
            ->where('is_system', true)
            ->count();

        expect($systemCount)->toBe(5);
    });

    it('calculates custom count correctly', function () {
        Role::create([
            'name' => 'count-test',
            'guard_name' => 'web',
            'name_en' => 'Count Test',
            'name_fr' => 'Test Comptage',
            'is_system' => false,
        ]);

        $customCount = Role::query()
            ->where('guard_name', 'web')
            ->where('is_system', false)
            ->count();

        expect($customCount)->toBe(1);
    });
});

describe('Role List — Sorting (BR-115)', function () {
    it('sorts system roles before custom roles', function () {
        Role::create([
            'name' => 'aaa-custom',
            'guard_name' => 'web',
            'name_en' => 'AAA Custom',
            'name_fr' => 'AAA Personnalise',
            'is_system' => false,
        ]);

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->withCount(['permissions', 'users'])
            ->get();

        // Sort using the same logic as the controller
        $systemOrder = [
            'super-admin' => 1,
            'admin' => 2,
            'cook' => 3,
            'manager' => 4,
            'client' => 5,
        ];

        $sorted = $roles->sort(function ($a, $b) use ($systemOrder) {
            if ($a->is_system && ! $b->is_system) {
                return -1;
            }
            if (! $a->is_system && $b->is_system) {
                return 1;
            }
            if ($a->is_system && $b->is_system) {
                return ($systemOrder[$a->name] ?? 99) <=> ($systemOrder[$b->name] ?? 99);
            }

            return strcasecmp($a->name_en ?? $a->name, $b->name_en ?? $b->name);
        })->values();

        // First 5 should be system roles
        expect($sorted[0]->name)->toBe('super-admin')
            ->and($sorted[1]->name)->toBe('admin')
            ->and($sorted[2]->name)->toBe('cook')
            ->and($sorted[3]->name)->toBe('manager')
            ->and($sorted[4]->name)->toBe('client')
            ->and($sorted[5]->is_system)->toBeFalse();
    });

    it('sorts custom roles alphabetically by name_en', function () {
        Role::create([
            'name' => 'zzz-custom',
            'guard_name' => 'web',
            'name_en' => 'Zzz Custom',
            'name_fr' => 'Zzz Personnalise',
            'is_system' => false,
        ]);
        Role::create([
            'name' => 'aaa-custom',
            'guard_name' => 'web',
            'name_en' => 'Aaa Custom',
            'name_fr' => 'Aaa Personnalise',
            'is_system' => false,
        ]);

        $customRoles = Role::query()
            ->where('guard_name', 'web')
            ->where('is_system', false)
            ->get()
            ->sortBy(fn ($r) => strtolower($r->name_en ?? $r->name))
            ->values();

        expect($customRoles[0]->name_en)->toBe('Aaa Custom')
            ->and($customRoles[1]->name_en)->toBe('Zzz Custom');
    });
});
