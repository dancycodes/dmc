<?php

use App\Models\Tenant;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);
});

describe('Tenant Detail Access Control', function () {
    it('allows admins to access the tenant detail page', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('test-kitchen', 'Test Kitchen')->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/'.$tenant->slug);

        $response->assertStatus(200);
    });

    it('allows super-admins to access the tenant detail page', function () {
        $admin = createUser('super-admin');
        $tenant = Tenant::factory()->withSlug('test-kitchen-sa', 'Test Kitchen SA')->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/'.$tenant->slug);

        $response->assertStatus(200);
    });

    it('denies access to regular users', function () {
        $user = createUser('client');
        $tenant = Tenant::factory()->withSlug('test-kitchen-denied', 'Test Kitchen Denied')->create();

        $response = $this->actingAs($user)->get('/vault-entry/tenants/'.$tenant->slug);

        $response->assertStatus(403);
    });

    it('redirects guests to login', function () {
        $tenant = Tenant::factory()->withSlug('test-kitchen-guest', 'Test Kitchen Guest')->create();

        $response = $this->get('/vault-entry/tenants/'.$tenant->slug);

        $response->assertRedirect('/login');
    });

    it('returns 404 for non-existent tenant', function () {
        $admin = createUser('admin');

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/nonexistent-slug');

        $response->assertStatus(404);
    });
});

describe('Tenant Detail Display', function () {
    it('displays tenant name and status badge', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('amara-kitchen', 'Amara Kitchen')->create([
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/'.$tenant->slug);

        $response->assertStatus(200);
        $response->assertSee('Amara Kitchen');
        $response->assertSee(__('Active'));
    });

    it('displays inactive status for inactive tenant', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('closed-kitchen', 'Closed Kitchen')->inactive()->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/'.$tenant->slug);

        $response->assertStatus(200);
        $response->assertSee('Closed Kitchen');
        $response->assertSee(__('Inactive'));
    });

    it('displays tenant names in both languages', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->create([
            'slug' => 'bilingual-kitchen',
            'name_en' => 'Bilingual Kitchen',
            'name_fr' => 'Cuisine Bilingue',
        ]);

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/'.$tenant->slug);

        $response->assertStatus(200);
        $response->assertSee('Bilingual Kitchen');
        $response->assertSee('Cuisine Bilingue');
    });

    it('displays subdomain as clickable link', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('link-test', 'Link Test')->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/'.$tenant->slug);

        $response->assertStatus(200);
        $response->assertSee('link-test.'.\App\Services\TenantService::mainDomain());
    });

    it('displays custom domain when set', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('domain-test', 'Domain Test')->withCustomDomain('custom-food.cm')->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/'.$tenant->slug);

        $response->assertStatus(200);
        $response->assertSee('custom-food.cm');
    });

    it('shows None when no custom domain', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('no-domain', 'No Domain')->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/'.$tenant->slug);

        $response->assertStatus(200);
        $response->assertSee(__('None'));
    });
});

describe('Tenant Detail Metrics (BR-070, BR-071, BR-073)', function () {
    it('displays default commission rate of 10%', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('commission-default', 'Commission Default')->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/'.$tenant->slug);

        $response->assertStatus(200);
        $response->assertSee('10%');
        $response->assertSee(__('Commission Rate'));
    });

    it('displays custom commission rate from settings', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('commission-custom', 'Commission Custom')->create();
        $tenant->setSetting('commission_rate', 15)->save();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/'.$tenant->slug);

        $response->assertStatus(200);
        $response->assertSee('15%');
    });

    it('shows zero orders and revenue for new tenant', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('new-tenant', 'New Tenant')->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/'.$tenant->slug);

        $response->assertStatus(200);
        $response->assertSee(__('Total Orders'));
        $response->assertSee(__('Total Revenue'));
        $response->assertSee(__('Active Meals'));
    });
});

describe('Tenant Detail Cook Section', function () {
    it('shows no cook assigned prompt when no cook is linked', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('no-cook', 'No Cook Kitchen')->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/'.$tenant->slug);

        $response->assertStatus(200);
        $response->assertSee(__('No cook assigned to this tenant.'));
        $response->assertSee(__('Assign Cook'));
    });
});

describe('Tenant Detail Activity History (BR-072)', function () {
    it('displays activity entries scoped to the tenant', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('activity-test', 'Activity Test Kitchen')->create();

        // Create a scoped activity entry
        activity('tenants')
            ->performedOn($tenant)
            ->causedBy($admin)
            ->log('created');

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/'.$tenant->slug);

        $response->assertStatus(200);
        $response->assertSee($admin->name);
    });

    it('shows empty state when no activities exist', function () {
        $admin = createUser('admin');
        // Delete any auto-logged activities for a clean state
        $tenant = Tenant::factory()->withSlug('no-activity', 'No Activity Kitchen')->create();
        Activity::where('subject_type', Tenant::class)->where('subject_id', $tenant->id)->delete();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/'.$tenant->slug);

        $response->assertStatus(200);
        $response->assertSee(__('No activity recorded yet.'));
    });

    it('does not show activity from other tenants', function () {
        $admin = createUser('admin');
        $tenant1 = Tenant::factory()->withSlug('tenant-one', 'Tenant One')->create();
        $tenant2 = Tenant::factory()->withSlug('tenant-two', 'Tenant Two')->create();

        // Log activity for tenant2 only
        activity('tenants')
            ->performedOn($tenant2)
            ->causedBy($admin)
            ->log('status_changed');

        // Clear any auto-logged activity for tenant1
        Activity::where('subject_type', Tenant::class)->where('subject_id', $tenant1->id)->delete();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/'.$tenant1->slug);

        $response->assertStatus(200);
        $response->assertSee(__('No activity recorded yet.'));
    });
});

describe('Tenant Detail Action Links', function () {
    it('contains edit tenant link', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('edit-link-test', 'Edit Link Test')->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/'.$tenant->slug);

        $response->assertStatus(200);
        $response->assertSee('/vault-entry/tenants/'.$tenant->slug.'/edit');
        $response->assertSee(__('Edit Tenant'));
    });

    it('contains visit site link with target blank', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('visit-link', 'Visit Link Test')->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/'.$tenant->slug);

        $response->assertStatus(200);
        $response->assertSee(__('Visit Site'));
        $response->assertSee('target="_blank"', false);
    });

    it('contains configure commission link', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('commission-link', 'Commission Link')->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/'.$tenant->slug);

        $response->assertStatus(200);
        $response->assertSee('/vault-entry/tenants/'.$tenant->slug.'/commission');
        $response->assertSee(__('Configure Commission'));
    });

    it('contains back to tenant list link', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('back-link', 'Back Link Test')->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/'.$tenant->slug);

        $response->assertStatus(200);
        $response->assertSee(__('Back to Tenant List'));
        $response->assertSee('/vault-entry/tenants');
    });
});

describe('Tenant Detail Breadcrumb', function () {
    it('displays correct breadcrumb navigation', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('breadcrumb-test', 'Breadcrumb Kitchen')->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/'.$tenant->slug);

        $response->assertStatus(200);
        $response->assertSee(__('Tenants'));
    });
});

describe('Tenant Detail Description', function () {
    it('displays description when present', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->create([
            'slug' => 'desc-test',
            'name_en' => 'Desc Test',
            'name_fr' => 'Desc Test FR',
            'description_en' => 'This is a test description for the kitchen.',
            'description_fr' => 'Ceci est une description test pour la cuisine.',
        ]);

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/'.$tenant->slug);

        $response->assertStatus(200);
        $response->assertSee(__('Description'));
    });

    it('does not show description section when no description', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('no-desc', 'No Desc Kitchen')->create([
            'description_en' => null,
            'description_fr' => null,
        ]);

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/'.$tenant->slug);

        $response->assertStatus(200);
        // Should not display description heading if no description
        $response->assertDontSee(__('Read more'));
    });
});
