<?php

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('Tenant Search Scope (BR-065)', function () {
    it('returns all tenants when search term is null', function () {
        Tenant::factory()->withSlug('tenant-a', 'Tenant A')->create();
        Tenant::factory()->withSlug('tenant-b', 'Tenant B')->create();

        $results = Tenant::query()->search(null)->get();

        expect($results)->toHaveCount(2);
    });

    it('returns all tenants when search term is empty', function () {
        Tenant::factory()->withSlug('tenant-c', 'Tenant C')->create();
        Tenant::factory()->withSlug('tenant-d', 'Tenant D')->create();

        $results = Tenant::query()->search('')->get();

        expect($results)->toHaveCount(2);
    });

    it('searches by name_en', function () {
        Tenant::factory()->withSlug('amara', 'Amara Kitchen')->create();
        Tenant::factory()->withSlug('powel', 'Powel Foods')->create();

        $results = Tenant::query()->search('amara')->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->slug)->toBe('amara');
    });

    it('searches by name_fr', function () {
        Tenant::factory()->withSlug('latifa-fr', 'Latifa Kitchen')->create([
            'name_fr' => 'Cuisine de Latifa',
        ]);
        Tenant::factory()->withSlug('other-fr', 'Other Kitchen')->create([
            'name_fr' => 'Autre Cuisine',
        ]);

        $results = Tenant::query()->search('Latifa')->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->slug)->toBe('latifa-fr');
    });

    it('searches by subdomain (slug)', function () {
        Tenant::factory()->withSlug('chef-amara', 'Chef Amara')->create();
        Tenant::factory()->withSlug('chef-powel', 'Chef Powel')->create();

        $results = Tenant::query()->search('chef-amara')->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->slug)->toBe('chef-amara');
    });

    it('searches by custom domain', function () {
        Tenant::factory()->withSlug('with-domain-unit', 'With Domain')
            ->withCustomDomain('latifa.cm')
            ->create();
        Tenant::factory()->withSlug('without-domain-unit', 'Without Domain')->create();

        $results = Tenant::query()->search('latifa.cm')->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->slug)->toBe('with-domain-unit');
    });

    it('is case insensitive', function () {
        Tenant::factory()->withSlug('case-test', 'Chef AMARA KITCHEN')->create();

        $results = Tenant::query()->search('amara')->get();

        expect($results)->toHaveCount(1);
    });

    it('returns empty for no matches', function () {
        Tenant::factory()->withSlug('no-match', 'Some Kitchen')->create();

        $results = Tenant::query()->search('nonexistent')->get();

        expect($results)->toHaveCount(0);
    });

    it('handles special characters in search safely', function () {
        Tenant::factory()->withSlug('special-char', "Chef O'Brien Kitchen")->create();

        $results = Tenant::query()->search("O'Brien")->get();

        expect($results)->toHaveCount(1);
    });
});

describe('Tenant Status Scope (BR-066)', function () {
    it('returns only active tenants when status is active', function () {
        Tenant::factory()->withSlug('active-scope', 'Active Scope')->create(['is_active' => true]);
        Tenant::factory()->withSlug('inactive-scope', 'Inactive Scope')->inactive()->create();

        $results = Tenant::query()->status('active')->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->is_active)->toBeTrue();
    });

    it('returns only inactive tenants when status is inactive', function () {
        Tenant::factory()->withSlug('active-scope2', 'Active Scope Two')->create(['is_active' => true]);
        Tenant::factory()->withSlug('inactive-scope2', 'Inactive Scope Two')->inactive()->create();

        $results = Tenant::query()->status('inactive')->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->is_active)->toBeFalse();
    });

    it('returns all tenants when status is null', function () {
        Tenant::factory()->withSlug('all-a', 'All A')->create(['is_active' => true]);
        Tenant::factory()->withSlug('all-b', 'All B')->inactive()->create();

        $results = Tenant::query()->status(null)->get();

        expect($results)->toHaveCount(2);
    });

    it('returns all tenants when status is empty string', function () {
        Tenant::factory()->withSlug('all-c', 'All C')->create(['is_active' => true]);
        Tenant::factory()->withSlug('all-d', 'All D')->inactive()->create();

        $results = Tenant::query()->status('')->get();

        expect($results)->toHaveCount(2);
    });

    it('returns all tenants for unrecognized status values', function () {
        Tenant::factory()->withSlug('all-e', 'All E')->create(['is_active' => true]);
        Tenant::factory()->withSlug('all-f', 'All F')->inactive()->create();

        $results = Tenant::query()->status('bogus')->get();

        expect($results)->toHaveCount(2);
    });
});

describe('Tenant Search and Status Combined', function () {
    it('can chain search and status scopes', function () {
        Tenant::factory()->withSlug('active-amara-u', 'Active Amara Unit')->create(['is_active' => true]);
        Tenant::factory()->withSlug('inactive-amara-u', 'Inactive Amara Unit')->inactive()->create();
        Tenant::factory()->withSlug('active-powel-u', 'Active Powel Unit')->create(['is_active' => true]);

        $results = Tenant::query()
            ->search('amara')
            ->status('active')
            ->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->slug)->toBe('active-amara-u');
    });
});

describe('Tenant Model Properties', function () {
    it('has translatable name via HasTranslatable trait', function () {
        $tenant = Tenant::factory()->withSlug('translatable-test', 'English Name')->create([
            'name_fr' => 'Nom Francais',
        ]);

        // Default locale is en
        app()->setLocale('en');
        expect($tenant->name)->toBe('English Name');

        app()->setLocale('fr');
        expect($tenant->name)->toBe('Nom Francais');
    });

    it('casts is_active to boolean', function () {
        $tenant = Tenant::factory()->withSlug('bool-test', 'Bool Test')->create(['is_active' => true]);

        expect($tenant->is_active)->toBeBool();
        expect($tenant->is_active)->toBeTrue();
    });

    it('uses slug as route key name', function () {
        $tenant = new Tenant;

        expect($tenant->getRouteKeyName())->toBe('slug');
    });
});
