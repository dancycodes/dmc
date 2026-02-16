<?php

use App\Http\Requests\Admin\UpdateTenantRequest;
use App\Models\Tenant;

describe('UpdateTenantRequest Authorization', function () {
    it('denies access without user', function () {
        $request = new UpdateTenantRequest;
        $request->setUserResolver(fn () => null);

        expect($request->authorize())->toBeFalse();
    });
});

describe('UpdateTenantRequest Validation Rules', function () {
    it('has required fields', function () {
        $tenant = new Tenant(['slug' => 'test', 'name_en' => 'Test', 'name_fr' => 'Test FR', 'settings' => []]);

        $request = new UpdateTenantRequest;
        $request->setRouteResolver(fn () => new class($tenant)
        {
            public function __construct(private Tenant $tenant) {}

            public function parameter(string $name)
            {
                return $this->tenant;
            }
        });

        $rules = $request->rules();

        expect($rules)->toHaveKeys(['name_en', 'name_fr', 'subdomain', 'custom_domain', 'description_en', 'description_fr']);
        expect($rules['name_en'])->toContain('required');
        expect($rules['name_fr'])->toContain('required');
        expect($rules['subdomain'])->toContain('required');
        expect($rules['description_en'])->toContain('required');
        expect($rules['description_fr'])->toContain('required');
    });

    it('does not have is_active in rules (status is toggled separately)', function () {
        $tenant = new Tenant(['slug' => 'test', 'name_en' => 'Test', 'name_fr' => 'Test FR', 'settings' => []]);

        $request = new UpdateTenantRequest;
        $request->setRouteResolver(fn () => new class($tenant)
        {
            public function __construct(private Tenant $tenant) {}

            public function parameter(string $name)
            {
                return $this->tenant;
            }
        });

        $rules = $request->rules();

        expect($rules)->not->toHaveKey('is_active');
    });
});

describe('Translation strings exist for F-048', function () {
    it('has all required English translation keys', function () {
        $projectRoot = dirname(__DIR__, 3);
        $enTranslations = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);

        $requiredKeys = [
            'Edit Tenant',
            'Tenant Status',
            'Deactivate Tenant?',
            'Deactivate',
            'Save Changes',
            'Saving...',
            'Tenant ":name" updated successfully.',
            'Tenant ":name" has been :status.',
            'activated',
            'deactivated',
            'This domain is already assigned to another tenant.',
        ];

        foreach ($requiredKeys as $key) {
            expect(array_key_exists($key, $enTranslations))->toBeTrue("Missing EN translation: $key");
        }
    });

    it('has all required French translation keys', function () {
        $projectRoot = dirname(__DIR__, 3);
        $frTranslations = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);

        $requiredKeys = [
            'Tenant Status',
            'Deactivate Tenant?',
            'Deactivate',
            'Tenant ":name" updated successfully.',
            'Tenant ":name" has been :status.',
            'activated',
            'deactivated',
            'This domain is already assigned to another tenant.',
        ];

        foreach ($requiredKeys as $key) {
            expect(array_key_exists($key, $frTranslations))->toBeTrue("Missing FR translation: $key");
        }
    });
});

describe('Tenant Model Status Toggle', function () {
    it('can toggle is_active from true to false', function () {
        $tenant = new Tenant([
            'slug' => 'toggle-test',
            'name_en' => 'Toggle Test',
            'name_fr' => 'Test Toggle',
            'is_active' => true,
            'settings' => [],
        ]);

        expect($tenant->is_active)->toBeTrue();
        $tenant->is_active = false;
        expect($tenant->is_active)->toBeFalse();
    });

    it('can toggle is_active from false to true', function () {
        $tenant = new Tenant([
            'slug' => 'toggle-test-2',
            'name_en' => 'Toggle Test 2',
            'name_fr' => 'Test Toggle 2',
            'is_active' => false,
            'settings' => [],
        ]);

        expect($tenant->is_active)->toBeFalse();
        $tenant->is_active = true;
        expect($tenant->is_active)->toBeTrue();
    });
});

describe('Tenant Edit View File Exists', function () {
    it('edit blade template exists', function () {
        $projectRoot = dirname(__DIR__, 3);
        $editPath = $projectRoot.'/resources/views/admin/tenants/edit.blade.php';

        expect(file_exists($editPath))->toBeTrue();
    });
});

describe('UpdateTenantRequest File Structure', function () {
    it('extends FormRequest', function () {
        $request = new UpdateTenantRequest;

        expect($request)->toBeInstanceOf(\Illuminate\Foundation\Http\FormRequest::class);
    });

    it('has prepareForValidation method', function () {
        $reflection = new ReflectionClass(UpdateTenantRequest::class);

        expect($reflection->hasMethod('prepareForValidation'))->toBeTrue();
    });

    it('has messages method', function () {
        $reflection = new ReflectionClass(UpdateTenantRequest::class);

        expect($reflection->hasMethod('messages'))->toBeTrue();
    });
});
