<?php

use App\Models\Tenant;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);
});

describe('Tenant Creation Form Access', function () {
    it('allows admins to access the creation form', function () {
        $admin = createUser('admin');

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/create');

        $response->assertStatus(200);
    });

    it('allows super-admins to access the creation form', function () {
        $admin = createUser('super-admin');

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/create');

        $response->assertStatus(200);
    });

    it('denies access to regular users', function () {
        $user = createUser('client');

        $response = $this->actingAs($user)->get('/vault-entry/tenants/create');

        $response->assertStatus(403);
    });

    it('redirects guests to login', function () {
        $response = $this->get('/vault-entry/tenants/create');

        $response->assertRedirect('/login');
    });
});

describe('Tenant Creation Submission (BR-056 to BR-063)', function () {
    it('creates a tenant with valid data', function () {
        $admin = createUser('admin');

        $response = $this->actingAs($admin)->post('/vault-entry/tenants', [
            'name_en' => "Chef Amara's Kitchen",
            'name_fr' => 'La Cuisine de Chef Amara',
            'subdomain' => 'chef-amara',
            'custom_domain' => '',
            'description_en' => 'Delicious Cameroonian cuisine made with love.',
            'description_fr' => 'Cuisine camerounaise delicieuse faite avec amour.',
            'is_active' => true,
        ]);

        $response->assertRedirect('/vault-entry/tenants');

        $this->assertDatabaseHas('tenants', [
            'slug' => 'chef-amara',
            'name_en' => "Chef Amara's Kitchen",
            'name_fr' => 'La Cuisine de Chef Amara',
            'is_active' => true,
        ]);
    });

    it('creates tenant with custom domain (BR-058)', function () {
        $admin = createUser('admin');

        $this->actingAs($admin)->post('/vault-entry/tenants', [
            'name_en' => 'Chef Domain',
            'name_fr' => 'Chef Domaine',
            'subdomain' => 'chef-domain',
            'custom_domain' => 'chefamara.cm',
            'description_en' => 'A great restaurant.',
            'description_fr' => 'Un super restaurant.',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('tenants', [
            'slug' => 'chef-domain',
            'custom_domain' => 'chefamara.cm',
        ]);
    });

    it('rejects duplicate subdomain (BR-056)', function () {
        $admin = createUser('admin');

        Tenant::factory()->create(['slug' => 'chef-amara']);

        $response = $this->actingAs($admin)->post('/vault-entry/tenants', [
            'name_en' => 'Another Chef',
            'name_fr' => 'Un autre Chef',
            'subdomain' => 'chef-amara',
            'description_en' => 'Description en.',
            'description_fr' => 'Description fr.',
        ]);

        $response->assertSessionHasErrors('subdomain');
    });

    it('rejects invalid subdomain format (BR-057)', function () {
        $admin = createUser('admin');

        $response = $this->actingAs($admin)->post('/vault-entry/tenants', [
            'name_en' => 'Test',
            'name_fr' => 'Test',
            'subdomain' => "Chef's!",
            'description_en' => 'Desc en.',
            'description_fr' => 'Desc fr.',
        ]);

        $response->assertSessionHasErrors('subdomain');
    });

    it('rejects subdomain with consecutive hyphens', function () {
        $admin = createUser('admin');

        $response = $this->actingAs($admin)->post('/vault-entry/tenants', [
            'name_en' => 'Test',
            'name_fr' => 'Test',
            'subdomain' => 'chef--amara',
            'description_en' => 'Desc en.',
            'description_fr' => 'Desc fr.',
        ]);

        $response->assertSessionHasErrors('subdomain');
    });

    it('rejects reserved subdomains (BR-063)', function () {
        $admin = createUser('admin');

        $response = $this->actingAs($admin)->post('/vault-entry/tenants', [
            'name_en' => 'Test WWW',
            'name_fr' => 'Test WWW',
            'subdomain' => 'www',
            'description_en' => 'Desc en.',
            'description_fr' => 'Desc fr.',
        ]);

        $response->assertSessionHasErrors('subdomain');
    });

    it('rejects subdomain shorter than 3 characters (BR-057)', function () {
        $admin = createUser('admin');

        $response = $this->actingAs($admin)->post('/vault-entry/tenants', [
            'name_en' => 'Test',
            'name_fr' => 'Test',
            'subdomain' => 'ab',
            'description_en' => 'Desc en.',
            'description_fr' => 'Desc fr.',
        ]);

        $response->assertSessionHasErrors('subdomain');
    });

    it('requires both name translations (BR-060)', function () {
        $admin = createUser('admin');

        // Missing name_fr
        $response = $this->actingAs($admin)->post('/vault-entry/tenants', [
            'name_en' => 'English Name',
            'name_fr' => '',
            'subdomain' => 'test-name',
            'description_en' => 'Desc en.',
            'description_fr' => 'Desc fr.',
        ]);

        $response->assertSessionHasErrors('name_fr');

        // Missing name_en
        $response = $this->actingAs($admin)->post('/vault-entry/tenants', [
            'name_en' => '',
            'name_fr' => 'Nom Francais',
            'subdomain' => 'test-name2',
            'description_en' => 'Desc en.',
            'description_fr' => 'Desc fr.',
        ]);

        $response->assertSessionHasErrors('name_en');
    });

    it('requires both description translations (BR-060)', function () {
        $admin = createUser('admin');

        $response = $this->actingAs($admin)->post('/vault-entry/tenants', [
            'name_en' => 'Test',
            'name_fr' => 'Test',
            'subdomain' => 'test-desc',
            'description_en' => '',
            'description_fr' => 'Description fr.',
        ]);

        $response->assertSessionHasErrors('description_en');
    });

    it('rejects custom domain that conflicts with platform domain (BR-059)', function () {
        $admin = createUser('admin');
        $mainDomain = \App\Services\TenantService::mainDomain();

        $response = $this->actingAs($admin)->post('/vault-entry/tenants', [
            'name_en' => 'Test',
            'name_fr' => 'Test',
            'subdomain' => 'test-platform',
            'custom_domain' => $mainDomain,
            'description_en' => 'Desc en.',
            'description_fr' => 'Desc fr.',
        ]);

        $response->assertSessionHasErrors('custom_domain');
    });

    it('rejects duplicate custom domain (BR-059)', function () {
        $admin = createUser('admin');

        Tenant::factory()->create([
            'slug' => 'existing-tenant',
            'custom_domain' => 'existing.cm',
        ]);

        $response = $this->actingAs($admin)->post('/vault-entry/tenants', [
            'name_en' => 'Test',
            'name_fr' => 'Test',
            'subdomain' => 'new-tenant',
            'custom_domain' => 'existing.cm',
            'description_en' => 'Desc en.',
            'description_fr' => 'Desc fr.',
        ]);

        $response->assertSessionHasErrors('custom_domain');
    });

    it('defaults status to active (BR-061)', function () {
        $admin = createUser('admin');

        $this->actingAs($admin)->post('/vault-entry/tenants', [
            'name_en' => 'Default Active',
            'name_fr' => 'Actif par Defaut',
            'subdomain' => 'default-active',
            'description_en' => 'Desc en.',
            'description_fr' => 'Desc fr.',
        ]);

        $this->assertDatabaseHas('tenants', [
            'slug' => 'default-active',
            'is_active' => true,
        ]);
    });

    it('allows creating inactive tenant (BR-061)', function () {
        $admin = createUser('admin');

        $this->actingAs($admin)->post('/vault-entry/tenants', [
            'name_en' => 'Inactive Tenant',
            'name_fr' => 'Locataire Inactif',
            'subdomain' => 'inactive-tenant',
            'description_en' => 'Desc en.',
            'description_fr' => 'Desc fr.',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('tenants', [
            'slug' => 'inactive-tenant',
            'is_active' => false,
        ]);
    });

    it('logs creation in activity log (BR-062)', function () {
        $admin = createUser('admin');

        $this->actingAs($admin)->post('/vault-entry/tenants', [
            'name_en' => 'Logged Tenant',
            'name_fr' => 'Locataire Journalise',
            'subdomain' => 'logged-tenant',
            'description_en' => 'Desc en.',
            'description_fr' => 'Desc fr.',
        ]);

        $tenant = Tenant::where('slug', 'logged-tenant')->first();

        $activity = Activity::query()
            ->where('subject_type', Tenant::class)
            ->where('subject_id', $tenant->id)
            ->where('description', 'created')
            ->first();

        expect($activity)->not->toBeNull()
            ->and($activity->causer_id)->toBe($admin->id)
            ->and($activity->properties['slug'])->toBe('logged-tenant');
    });

    it('rejects name exceeding 255 characters', function () {
        $admin = createUser('admin');

        $response = $this->actingAs($admin)->post('/vault-entry/tenants', [
            'name_en' => str_repeat('A', 256),
            'name_fr' => 'Test',
            'subdomain' => 'long-name',
            'description_en' => 'Desc en.',
            'description_fr' => 'Desc fr.',
        ]);

        $response->assertSessionHasErrors('name_en');
    });

    it('sanitizes HTML in descriptions', function () {
        $admin = createUser('admin');

        $this->actingAs($admin)->post('/vault-entry/tenants', [
            'name_en' => 'Sanitized Tenant',
            'name_fr' => 'Locataire Assaini',
            'subdomain' => 'sanitized-tenant',
            'description_en' => '<script>alert("xss")</script>Clean text',
            'description_fr' => '<b>Texte</b> propre',
        ]);

        $tenant = Tenant::where('slug', 'sanitized-tenant')->first();
        expect($tenant->description_en)->toBe('alert("xss")Clean text')
            ->and($tenant->description_fr)->toBe('Texte propre');
    });

    it('normalizes subdomain to lowercase', function () {
        $admin = createUser('admin');

        $this->actingAs($admin)->post('/vault-entry/tenants', [
            'name_en' => 'Upper Test',
            'name_fr' => 'Test Majuscules',
            'subdomain' => 'CHEF-TEST',
            'description_en' => 'Desc en.',
            'description_fr' => 'Desc fr.',
        ]);

        $this->assertDatabaseHas('tenants', [
            'slug' => 'chef-test',
        ]);
    });
});

describe('Tenant List (Stub)', function () {
    it('allows admins to access the tenant list', function () {
        $admin = createUser('admin');

        $response = $this->actingAs($admin)->get('/vault-entry/tenants');

        $response->assertStatus(200);
    });

    it('shows empty state when no tenants exist', function () {
        $admin = createUser('admin');

        $response = $this->actingAs($admin)->get('/vault-entry/tenants');

        $response->assertSee(__('No tenants yet'));
    });

    it('shows tenants after creation', function () {
        $admin = createUser('admin');

        Tenant::factory()->create([
            'name_en' => 'Visible Tenant',
            'name_fr' => 'Locataire Visible',
            'slug' => 'visible-tenant',
        ]);

        $response = $this->actingAs($admin)->get('/vault-entry/tenants');

        $response->assertSee('Visible Tenant');
    });
});
