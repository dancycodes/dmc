<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Requests\Admin\StoreRoleRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);
});

describe('StoreRoleRequest Validation Rules', function () {
    it('has correct validation rules', function () {
        $request = new StoreRoleRequest;
        $rules = $request->rules();

        expect($rules)->toHaveKeys([
            'name_en',
            'name_fr',
            'description',
        ]);
    });

    it('requires name_en', function () {
        $request = new StoreRoleRequest;
        $rules = $request->rules();

        expect($rules['name_en'])->toContain('required');
    });

    it('requires name_fr', function () {
        $request = new StoreRoleRequest;
        $rules = $request->rules();

        expect($rules['name_fr'])->toContain('required');
    });

    it('allows nullable description', function () {
        $request = new StoreRoleRequest;
        $rules = $request->rules();

        expect($rules['description'])->toContain('nullable');
    });

    it('limits name_en to 100 characters', function () {
        $request = new StoreRoleRequest;
        $rules = $request->rules();

        expect($rules['name_en'])->toContain('max:100');
    });

    it('limits name_fr to 100 characters', function () {
        $request = new StoreRoleRequest;
        $rules = $request->rules();

        expect($rules['name_fr'])->toContain('max:100');
    });

    it('limits description to 500 characters', function () {
        $request = new StoreRoleRequest;
        $rules = $request->rules();

        expect($rules['description'])->toContain('max:500');
    });
});

describe('StoreRoleRequest Authorization', function () {
    it('authorizes users with can-manage-roles permission', function () {
        $admin = $this->createUserWithRole('admin');
        $request = StoreRoleRequest::create('/vault-entry/roles', 'POST');
        $request->setUserResolver(fn () => $admin);

        expect($request->authorize())->toBeTrue();
    });

    it('denies users without can-manage-roles permission', function () {
        $client = $this->createUserWithRole('client');
        $request = StoreRoleRequest::create('/vault-entry/roles', 'POST');
        $request->setUserResolver(fn () => $client);

        expect($request->authorize())->toBeFalse();
    });

    it('denies unauthenticated users', function () {
        $request = StoreRoleRequest::create('/vault-entry/roles', 'POST');
        $request->setUserResolver(fn () => null);

        expect($request->authorize())->toBeFalse();
    });
});

describe('StoreRoleRequest Custom Messages', function () {
    it('provides custom error messages', function () {
        $request = new StoreRoleRequest;
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

describe('RoleController System Role Names', function () {
    it('defines all system role names', function () {
        $systemNames = RoleController::SYSTEM_ROLE_NAMES;

        expect($systemNames)->toContain('super-admin')
            ->toContain('admin')
            ->toContain('cook')
            ->toContain('manager')
            ->toContain('client')
            ->toHaveCount(5);
    });

    it('system role names match seeded roles', function () {
        $systemNames = RoleController::SYSTEM_ROLE_NAMES;
        $seededRoles = Role::where('is_system', true)->pluck('name')->toArray();

        foreach ($systemNames as $name) {
            expect($seededRoles)->toContain($name);
        }
    });
});

describe('Role Database Structure', function () {
    it('has translatable columns on roles table', function () {
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('roles');

        expect($columns)->toContain('name_en')
            ->toContain('name_fr')
            ->toContain('description')
            ->toContain('is_system');
    });

    it('seeded roles are marked as system', function () {
        $systemRoles = Role::where('is_system', true)->get();

        expect($systemRoles)->toHaveCount(5);
        expect($systemRoles->pluck('name')->toArray())->toContain('super-admin')
            ->toContain('admin')
            ->toContain('cook')
            ->toContain('manager')
            ->toContain('client');
    });

    it('seeded roles have translatable names', function () {
        $adminRole = Role::where('name', 'admin')->first();

        expect($adminRole->name_en)->toBe('Admin')
            ->and($adminRole->name_fr)->toBe('Administrateur');
    });

    it('cook role has correct French translation', function () {
        $cookRole = Role::where('name', 'cook')->first();

        expect($cookRole->name_en)->toBe('Cook')
            ->and($cookRole->name_fr)->toBe('Cuisinier');
    });
});

describe('Machine Name Generation', function () {
    it('generates correct machine name from English name', function () {
        $nameEn = 'Kitchen Assistant';
        $machineName = strtolower(str_replace(' ', '-', $nameEn));

        expect($machineName)->toBe('kitchen-assistant');
    });

    it('generates lowercase machine names', function () {
        $nameEn = 'HEAD Chef';
        $machineName = strtolower(str_replace(' ', '-', $nameEn));

        expect($machineName)->toBe('head-chef');
    });

    it('handles single word names', function () {
        $nameEn = 'Supervisor';
        $machineName = strtolower(str_replace(' ', '-', $nameEn));

        expect($machineName)->toBe('supervisor');
    });
});

describe('Role Creation Logic', function () {
    it('can create a custom role with required fields', function () {
        $role = Role::create([
            'name' => 'kitchen-assistant',
            'guard_name' => 'web',
            'name_en' => 'Kitchen Assistant',
            'name_fr' => 'Assistant de Cuisine',
            'description' => 'Helps with meal preparation',
            'is_system' => false,
        ]);

        expect($role->name)->toBe('kitchen-assistant')
            ->and($role->guard_name)->toBe('web')
            ->and($role->name_en)->toBe('Kitchen Assistant')
            ->and($role->name_fr)->toBe('Assistant de Cuisine')
            ->and($role->description)->toBe('Helps with meal preparation')
            ->and($role->is_system)->toBeFalse();
    });

    it('creates role with zero permissions by default', function () {
        $role = Role::create([
            'name' => 'test-role',
            'guard_name' => 'web',
            'name_en' => 'Test Role',
            'name_fr' => 'Role Test',
            'is_system' => false,
        ]);

        expect($role->permissions)->toHaveCount(0);
    });

    it('allows nullable description', function () {
        $role = Role::create([
            'name' => 'no-desc-role',
            'guard_name' => 'web',
            'name_en' => 'No Description',
            'name_fr' => 'Sans Description',
            'is_system' => false,
        ]);

        expect($role->description)->toBeNull();
    });

    it('enforces unique name column via Spatie', function () {
        Role::create([
            'name' => 'unique-test',
            'guard_name' => 'web',
            'name_en' => 'Unique Test',
            'name_fr' => 'Test Unique',
            'is_system' => false,
        ]);

        expect(fn () => Role::create([
            'name' => 'unique-test',
            'guard_name' => 'web',
            'name_en' => 'Unique Test 2',
            'name_fr' => 'Test Unique 2',
            'is_system' => false,
        ]))->toThrow(\Spatie\Permission\Exceptions\RoleAlreadyExists::class);
    });
});
