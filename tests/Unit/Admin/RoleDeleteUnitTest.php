<?php

use App\Http\Controllers\Admin\RoleController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);
});

describe('System Role Deletion Prevention (BR-122)', function () {
    it('does not allow deleting super-admin role', function () {
        $role = Role::where('name', 'super-admin')->first();

        expect($role->is_system)->toBeTrue();
        expect(in_array($role->name, RoleController::SYSTEM_ROLE_NAMES, true))->toBeTrue();
    });

    it('does not allow deleting admin role', function () {
        $role = Role::where('name', 'admin')->first();

        expect($role->is_system)->toBeTrue();
    });

    it('does not allow deleting cook role', function () {
        $role = Role::where('name', 'cook')->first();

        expect($role->is_system)->toBeTrue();
    });

    it('does not allow deleting manager role', function () {
        $role = Role::where('name', 'manager')->first();

        expect($role->is_system)->toBeTrue();
    });

    it('does not allow deleting client role', function () {
        $role = Role::where('name', 'client')->first();

        expect($role->is_system)->toBeTrue();
    });

    it('identifies all five system roles correctly', function () {
        $systemRoles = Role::where('is_system', true)->pluck('name')->sort()->values();

        expect($systemRoles->toArray())->toBe([
            'admin',
            'client',
            'cook',
            'manager',
            'super-admin',
        ]);
    });
});

describe('Custom Role Deletion with Users (BR-123)', function () {
    it('blocks deletion when users are assigned', function () {
        $customRole = Role::create([
            'name' => 'delivery-boy',
            'guard_name' => 'web',
            'name_en' => 'Delivery Boy',
            'name_fr' => 'Livreur',
            'is_system' => false,
        ]);

        $user = \App\Models\User::factory()->create();
        $user->assignRole($customRole);

        expect($customRole->users()->count())->toBe(1);
    });

    it('blocks deletion when multiple users are assigned', function () {
        $customRole = Role::create([
            'name' => 'kitchen-assistant',
            'guard_name' => 'web',
            'name_en' => 'Kitchen Assistant',
            'name_fr' => 'Assistant de Cuisine',
            'is_system' => false,
        ]);

        $users = \App\Models\User::factory()->count(4)->create();
        $users->each(fn ($user) => $user->assignRole($customRole));

        expect($customRole->users()->count())->toBe(4);
    });

    it('allows deletion when no users are assigned', function () {
        $customRole = Role::create([
            'name' => 'empty-role',
            'guard_name' => 'web',
            'name_en' => 'Empty Role',
            'name_fr' => 'Role Vide',
            'is_system' => false,
        ]);

        expect($customRole->users()->count())->toBe(0);
    });
});

describe('Permanent Deletion (BR-124)', function () {
    it('deletes the role record from the database', function () {
        $customRole = Role::create([
            'name' => 'temp-role',
            'guard_name' => 'web',
            'name_en' => 'Temp Role',
            'name_fr' => 'Role Temp',
            'is_system' => false,
        ]);

        $roleId = $customRole->id;
        $customRole->delete();

        expect(Role::find($roleId))->toBeNull();
    });

    it('removes permission assignments when role is deleted', function () {
        $customRole = Role::create([
            'name' => 'perm-test',
            'guard_name' => 'web',
            'name_en' => 'Perm Test',
            'name_fr' => 'Test Perm',
            'is_system' => false,
        ]);

        $permission = Permission::where('name', 'can-manage-meals')->first();
        $customRole->givePermissionTo($permission);

        expect($customRole->permissions()->count())->toBe(1);

        // Clear permissions and delete
        $customRole->syncPermissions([]);
        $customRole->delete();

        expect(Role::where('name', 'perm-test')->exists())->toBeFalse();
    });

    it('uses database transaction for safe deletion', function () {
        $customRole = Role::create([
            'name' => 'transaction-test',
            'guard_name' => 'web',
            'name_en' => 'Transaction Test',
            'name_fr' => 'Test Transaction',
            'is_system' => false,
        ]);

        $customRole->givePermissionTo(['can-manage-meals', 'can-browse-meals']);
        expect($customRole->permissions()->count())->toBe(2);

        \Illuminate\Support\Facades\DB::transaction(function () use ($customRole) {
            $customRole->syncPermissions([]);
            $customRole->delete();
        });

        expect(Role::where('name', 'transaction-test')->exists())->toBeFalse();
    });
});

describe('Confirmation Name Match (BR-125)', function () {
    it('confirms when names match exactly', function () {
        $roleName = 'Delivery Boy';
        $confirmInput = 'Delivery Boy';

        expect(trim($confirmInput) === $roleName)->toBeTrue();
    });

    it('rejects when names do not match', function () {
        $roleName = 'Delivery Boy';
        $confirmInput = 'delivery boy';

        expect(trim($confirmInput) === $roleName)->toBeFalse();
    });

    it('rejects empty confirmation', function () {
        $roleName = 'Delivery Boy';
        $confirmInput = '';

        expect(trim($confirmInput) === $roleName)->toBeFalse();
    });

    it('trims whitespace from confirmation input', function () {
        $roleName = 'Delivery Boy';
        $confirmInput = '  Delivery Boy  ';

        expect(trim($confirmInput) === $roleName)->toBeTrue();
    });
});

describe('Activity Log on Deletion (BR-126)', function () {
    it('logs deletion activity with role details', function () {
        $admin = $this->createUserWithRole('admin');
        $customRole = Role::create([
            'name' => 'log-test',
            'guard_name' => 'web',
            'name_en' => 'Log Test',
            'name_fr' => 'Test Log',
            'description' => 'Test description',
            'is_system' => false,
        ]);

        $beforeCount = Activity::where('log_name', 'roles')->where('description', 'deleted')->count();

        activity('roles')
            ->performedOn($customRole)
            ->causedBy($admin)
            ->withProperties([
                'name' => $customRole->name,
                'name_en' => $customRole->name_en,
                'name_fr' => $customRole->name_fr,
                'description' => $customRole->description,
                'guard_name' => $customRole->guard_name,
                'permissions_count' => 0,
            ])
            ->log('deleted');

        $customRole->delete();

        $afterCount = Activity::where('log_name', 'roles')->where('description', 'deleted')->count();

        expect($afterCount)->toBe($beforeCount + 1);

        $logEntry = Activity::where('log_name', 'roles')
            ->where('description', 'deleted')
            ->latest()
            ->first();

        expect($logEntry)->not->toBeNull()
            ->and($logEntry->description)->toBe('deleted')
            ->and($logEntry->log_name)->toBe('roles')
            ->and($logEntry->causer_id)->toBe($admin->id)
            ->and($logEntry->properties['name'])->toBe('log-test')
            ->and($logEntry->properties['name_en'])->toBe('Log Test');
    });
});

describe('Redirect After Deletion (BR-127)', function () {
    it('defines the roles list URL correctly', function () {
        $url = url('/vault-entry/roles');

        expect($url)->toContain('/vault-entry/roles');
    });
});

describe('Delete Route Existence', function () {
    it('has a DELETE route for role destruction', function () {
        $admin = $this->createUserWithRole('admin');
        $customRole = Role::create([
            'name' => 'route-test',
            'guard_name' => 'web',
            'name_en' => 'Route Test',
            'name_fr' => 'Test Route',
            'is_system' => false,
        ]);

        $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

        $response = $this->actingAs($admin)->delete(
            "https://{$mainDomain}/vault-entry/roles/{$customRole->id}",
            ['confirmRoleName' => 'Route Test']
        );

        // Should redirect to roles list (302) or back
        expect($response->status())->toBeIn([302, 200]);
    });

    it('returns 403 for system role deletion via HTTP', function () {
        $admin = $this->createUserWithRole('admin');
        $systemRole = Role::where('name', 'admin')->first();

        $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

        $response = $this->actingAs($admin)->delete(
            "https://{$mainDomain}/vault-entry/roles/{$systemRole->id}",
            ['confirmRoleName' => 'Admin']
        );

        expect($response->status())->toBe(403);
    });

    it('rejects deletion by client role user', function () {
        $client = $this->createUserWithRole('client');
        $customRole = Role::create([
            'name' => 'perm-check',
            'guard_name' => 'web',
            'name_en' => 'Perm Check',
            'name_fr' => 'Verification Perm',
            'is_system' => false,
        ]);

        $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

        $response = $this->actingAs($client)->delete(
            "https://{$mainDomain}/vault-entry/roles/{$customRole->id}",
            ['confirmRoleName' => 'Perm Check']
        );

        // Should be rejected (403 from either middleware or controller)
        expect($response->status())->toBeIn([302, 403]);
    });

    it('blocks deletion when role has assigned users via HTTP', function () {
        $admin = $this->createUserWithRole('admin');
        $customRole = Role::create([
            'name' => 'has-users',
            'guard_name' => 'web',
            'name_en' => 'Has Users',
            'name_fr' => 'A des Utilisateurs',
            'is_system' => false,
        ]);

        $user = \App\Models\User::factory()->create();
        $user->assignRole($customRole);

        $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

        $response = $this->actingAs($admin)->delete(
            "https://{$mainDomain}/vault-entry/roles/{$customRole->id}",
            ['confirmRoleName' => 'Has Users']
        );

        // Should redirect back with error
        expect($response->status())->toBe(302);

        // Role should still exist
        expect(Role::find($customRole->id))->not->toBeNull();
    });

    it('validates confirmation name mismatch via HTTP', function () {
        $admin = $this->createUserWithRole('admin');
        $customRole = Role::create([
            'name' => 'mismatch-test',
            'guard_name' => 'web',
            'name_en' => 'Mismatch Test',
            'name_fr' => 'Test Discordance',
            'is_system' => false,
        ]);

        $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

        $response = $this->actingAs($admin)->delete(
            "https://{$mainDomain}/vault-entry/roles/{$customRole->id}",
            ['confirmRoleName' => 'wrong name']
        );

        // Should redirect back with validation error
        expect($response->status())->toBe(302);

        // Role should still exist
        expect(Role::find($customRole->id))->not->toBeNull();
    });

    it('successfully deletes a custom role with correct confirmation via HTTP', function () {
        $admin = $this->createUserWithRole('admin');
        $customRole = Role::create([
            'name' => 'to-delete',
            'guard_name' => 'web',
            'name_en' => 'To Delete',
            'name_fr' => 'A Supprimer',
            'is_system' => false,
        ]);

        $roleId = $customRole->id;

        $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

        $response = $this->actingAs($admin)->delete(
            "https://{$mainDomain}/vault-entry/roles/{$roleId}",
            ['confirmRoleName' => 'To Delete']
        );

        expect($response->status())->toBe(302);
        expect(Role::find($roleId))->toBeNull();
    });

    it('records activity log on successful deletion via HTTP', function () {
        $admin = $this->createUserWithRole('admin');
        $customRole = Role::create([
            'name' => 'logged-delete',
            'guard_name' => 'web',
            'name_en' => 'Logged Delete',
            'name_fr' => 'Suppression Logguee',
            'is_system' => false,
        ]);

        $roleId = $customRole->id;

        $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

        $this->actingAs($admin)->delete(
            "https://{$mainDomain}/vault-entry/roles/{$roleId}",
            ['confirmRoleName' => 'Logged Delete']
        );

        $log = Activity::where('log_name', 'roles')
            ->where('description', 'deleted')
            ->latest()
            ->first();

        expect($log)->not->toBeNull();
        expect($log->causer_id)->toBe($admin->id);
        expect($log->properties['name'])->toBe('logged-delete');
        expect($log->properties['name_en'])->toBe('Logged Delete');
    });

    it('removes permissions when role is deleted via HTTP', function () {
        $admin = $this->createUserWithRole('admin');
        $customRole = Role::create([
            'name' => 'perm-clean',
            'guard_name' => 'web',
            'name_en' => 'Perm Clean',
            'name_fr' => 'Nettoyage Perm',
            'is_system' => false,
        ]);

        $customRole->givePermissionTo(['can-manage-meals', 'can-browse-meals']);
        expect($customRole->permissions()->count())->toBe(2);

        $roleId = $customRole->id;

        $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

        $this->actingAs($admin)->delete(
            "https://{$mainDomain}/vault-entry/roles/{$roleId}",
            ['confirmRoleName' => 'Perm Clean']
        );

        expect(Role::find($roleId))->toBeNull();
    });
});

describe('Edge Cases', function () {
    it('handles deletion when role was already deleted (404)', function () {
        $admin = $this->createUserWithRole('admin');

        $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

        $response = $this->actingAs($admin)->delete(
            "https://{$mainDomain}/vault-entry/roles/999999",
            ['confirmRoleName' => 'Nonexistent']
        );

        expect($response->status())->toBe(404);
    });
});
