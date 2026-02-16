<?php

use App\Http\Requests\Admin\StoreTenantRequest;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);
});

describe('StoreTenantRequest Validation Rules', function () {
    it('has correct validation rules', function () {
        $request = new StoreTenantRequest;
        $rules = $request->rules();

        expect($rules)->toHaveKeys([
            'name_en',
            'name_fr',
            'subdomain',
            'custom_domain',
            'description_en',
            'description_fr',
            'is_active',
        ]);
    });

    it('requires name_en and name_fr', function () {
        $request = new StoreTenantRequest;
        $rules = $request->rules();

        expect($rules['name_en'])->toContain('required')
            ->and($rules['name_fr'])->toContain('required');
    });

    it('requires subdomain', function () {
        $request = new StoreTenantRequest;
        $rules = $request->rules();

        expect($rules['subdomain'])->toContain('required');
    });

    it('allows nullable custom_domain', function () {
        $request = new StoreTenantRequest;
        $rules = $request->rules();

        expect($rules['custom_domain'])->toContain('nullable');
    });

    it('requires both description translations', function () {
        $request = new StoreTenantRequest;
        $rules = $request->rules();

        expect($rules['description_en'])->toContain('required')
            ->and($rules['description_fr'])->toContain('required');
    });
});

describe('StoreTenantRequest Authorization', function () {
    it('authorizes admin users', function () {
        $admin = createUser('admin');

        $request = StoreTenantRequest::create('/vault-entry/tenants', 'POST');
        $request->setUserResolver(fn () => $admin);

        expect($request->authorize())->toBeTrue();
    });

    it('denies non-admin users', function () {
        $client = createUser('client');

        $request = StoreTenantRequest::create('/vault-entry/tenants', 'POST');
        $request->setUserResolver(fn () => $client);

        expect($request->authorize())->toBeFalse();
    });
});

describe('Tenant Model with Translatable Fields', function () {
    it('has translatable name and description attributes', function () {
        $tenant = new Tenant;

        expect($tenant->getTranslatableAttributes())->toBe(['name', 'description']);
    });

    it('resolves name to locale-specific column', function () {
        $tenant = Tenant::factory()->create([
            'name_en' => 'English Kitchen',
            'name_fr' => 'Cuisine Francaise',
            'slug' => 'locale-test',
        ]);

        // Default locale is 'en'
        expect($tenant->name)->toBe('English Kitchen');

        // Switch to French
        app()->setLocale('fr');
        $tenant->refresh();
        expect($tenant->name)->toBe('Cuisine Francaise');

        // Reset locale
        app()->setLocale('en');
    });

    it('resolves description to locale-specific column', function () {
        $tenant = Tenant::factory()->create([
            'slug' => 'desc-locale',
            'description_en' => 'English description',
            'description_fr' => 'Description francaise',
        ]);

        expect($tenant->description)->toBe('English description');

        app()->setLocale('fr');
        $tenant->refresh();
        expect($tenant->description)->toBe('Description francaise');

        app()->setLocale('en');
    });

    it('has updated fillable array with translatable columns', function () {
        $tenant = new Tenant;

        expect($tenant->getFillable())->toContain('name_en')
            ->toContain('name_fr')
            ->toContain('description_en')
            ->toContain('description_fr');
    });
});

describe('Tenant Factory', function () {
    it('creates tenant with both name columns', function () {
        $tenant = Tenant::factory()->create();

        expect($tenant->name_en)->not->toBeEmpty()
            ->and($tenant->name_fr)->not->toBeEmpty()
            ->and($tenant->slug)->not->toBeEmpty();
    });

    it('withSlug sets both name_en and name_fr', function () {
        $tenant = Tenant::factory()->withSlug('test-slug', 'Test Name')->create();

        expect($tenant->name_en)->toBe('Test Name')
            ->and($tenant->name_fr)->toBe('Test Name')
            ->and($tenant->slug)->toBe('test-slug');
    });
});

describe('Reserved Subdomain Validation', function () {
    it('rejects all reserved subdomains', function () {
        foreach (Tenant::RESERVED_SUBDOMAINS as $reserved) {
            expect(Tenant::isReservedSubdomain($reserved))->toBeTrue(
                "Expected '$reserved' to be reserved"
            );
        }
    });

    it('allows non-reserved subdomains', function () {
        $allowed = ['latifa', 'chef-amara', 'powel', 'my-kitchen'];

        foreach ($allowed as $subdomain) {
            expect(Tenant::isReservedSubdomain($subdomain))->toBeFalse(
                "Expected '$subdomain' to be allowed"
            );
        }
    });
});
