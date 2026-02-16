<?php

use App\Models\Tenant;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);
});

describe('Tenant List Access Control', function () {
    it('allows admins to access the tenant list', function () {
        $admin = createUser('admin');

        $response = $this->actingAs($admin)->get('/vault-entry/tenants');

        $response->assertStatus(200);
    });

    it('allows super-admins to access the tenant list', function () {
        $admin = createUser('super-admin');

        $response = $this->actingAs($admin)->get('/vault-entry/tenants');

        $response->assertStatus(200);
    });

    it('denies access to regular users', function () {
        $user = createUser('client');

        $response = $this->actingAs($user)->get('/vault-entry/tenants');

        $response->assertStatus(403);
    });

    it('redirects guests to login', function () {
        $response = $this->get('/vault-entry/tenants');

        $response->assertRedirect('/login');
    });
});

describe('Tenant List Display (BR-064, BR-067)', function () {
    it('displays tenants sorted by newest first by default', function () {
        $admin = createUser('admin');

        $olderTenant = Tenant::factory()->withSlug('older-tenant', 'Older Tenant')->create([
            'created_at' => now()->subDays(5),
        ]);
        $newerTenant = Tenant::factory()->withSlug('newer-tenant', 'Newer Tenant')->create([
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/vault-entry/tenants');

        $response->assertStatus(200);
        $response->assertSeeInOrder(['Newer Tenant', 'Older Tenant']);
    });

    it('paginates with 15 items per page', function () {
        $admin = createUser('admin');

        // Create 20 tenants with unique slugs
        for ($i = 1; $i <= 20; $i++) {
            Tenant::factory()->withSlug("tenant-{$i}", "Tenant {$i}")->create();
        }

        $response = $this->actingAs($admin)->get('/vault-entry/tenants');

        $response->assertStatus(200);
        // Should see pagination (page 2 link)
        $response->assertSee('page=2');
    });

    it('shows summary cards with correct counts', function () {
        $admin = createUser('admin');

        Tenant::factory()->withSlug('active-one', 'Active One')->create(['is_active' => true]);
        Tenant::factory()->withSlug('active-two', 'Active Two')->create(['is_active' => true]);
        Tenant::factory()->withSlug('inactive-one', 'Inactive One')->inactive()->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants');

        $response->assertStatus(200);
        // Check that the view data contains correct counts
        $response->assertViewHas('totalCount', 3);
        $response->assertViewHas('activeCount', 2);
        $response->assertViewHas('inactiveCount', 1);
    });

    it('shows empty state when no tenants exist', function () {
        $admin = createUser('admin');

        $response = $this->actingAs($admin)->get('/vault-entry/tenants');

        $response->assertStatus(200);
        $response->assertSee('No tenants yet. Create your first tenant.');
    });

    it('displays tenant name, subdomain, status, and created date', function () {
        $admin = createUser('admin');

        Tenant::factory()->withSlug('chef-amara', 'Chef Amara Kitchen')->create([
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get('/vault-entry/tenants');

        $response->assertStatus(200);
        $response->assertSee('Chef Amara Kitchen');
        $response->assertSee('chef-amara');
        $response->assertSee('Active');
    });

    it('shows custom domain when set', function () {
        $admin = createUser('admin');

        Tenant::factory()->withSlug('latifa', 'Latifa Kitchen')
            ->withCustomDomain('latifa.cm')
            ->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants');

        $response->assertStatus(200);
        $response->assertSee('latifa.cm');
    });

    it('shows unassigned text for tenants without cook', function () {
        $admin = createUser('admin');

        Tenant::factory()->withSlug('no-cook', 'No Cook Tenant')->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants');

        $response->assertStatus(200);
        $response->assertSee('Unassigned');
    });
});

describe('Tenant List Search (BR-065)', function () {
    it('filters tenants by name_en', function () {
        $admin = createUser('admin');

        Tenant::factory()->withSlug('amara-kitchen', "Chef Amara's Kitchen")->create();
        Tenant::factory()->withSlug('powel-foods', "Chef Powel's Foods")->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants?search=amara');

        $response->assertStatus(200);
        $response->assertSee('Chef Amara');
        $response->assertDontSee('Chef Powel');
    });

    it('filters tenants by subdomain', function () {
        $admin = createUser('admin');

        Tenant::factory()->withSlug('latifa-eats', 'Latifa Eats')->create();
        Tenant::factory()->withSlug('mama-ngono', 'Mama Ngono')->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants?search=latifa');

        $response->assertStatus(200);
        $response->assertSee('Latifa Eats');
        $response->assertDontSee('Mama Ngono');
    });

    it('filters tenants by custom domain', function () {
        $admin = createUser('admin');

        Tenant::factory()->withSlug('with-domain', 'With Domain')
            ->withCustomDomain('chefamara.cm')
            ->create();
        Tenant::factory()->withSlug('no-domain', 'No Domain')->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants?search=chefamara');

        $response->assertStatus(200);
        $response->assertSee('With Domain');
        $response->assertDontSee('No Domain');
    });

    it('shows no results message when search matches nothing', function () {
        $admin = createUser('admin');

        Tenant::factory()->withSlug('existing-tenant', 'Existing Tenant')->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants?search=nonexistent');

        $response->assertStatus(200);
        $response->assertSee('No tenants match your search.');
    });

    it('search is case-insensitive', function () {
        $admin = createUser('admin');

        Tenant::factory()->withSlug('amara-kitchen', "Chef Amara's Kitchen")->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants?search=AMARA');

        $response->assertStatus(200);
        $response->assertSee('Chef Amara');
    });
});

describe('Tenant List Status Filter (BR-066)', function () {
    it('filters by active status', function () {
        $admin = createUser('admin');

        Tenant::factory()->withSlug('active-t', 'Active Tenant')->create(['is_active' => true]);
        Tenant::factory()->withSlug('inactive-t', 'Inactive Tenant')->inactive()->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants?status=active');

        $response->assertStatus(200);
        $response->assertSee('Active Tenant');
        $response->assertDontSee('Inactive Tenant');
    });

    it('filters by inactive status', function () {
        $admin = createUser('admin');

        Tenant::factory()->withSlug('active-t2', 'Active Tenant')->create(['is_active' => true]);
        Tenant::factory()->withSlug('inactive-t2', 'Inactive Tenant')->inactive()->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants?status=inactive');

        $response->assertStatus(200);
        $response->assertDontSee('Active Tenant');
        $response->assertSee('Inactive Tenant');
    });

    it('shows all tenants when no status filter applied', function () {
        $admin = createUser('admin');

        Tenant::factory()->withSlug('active-t3', 'Active Tenant Three')->create(['is_active' => true]);
        Tenant::factory()->withSlug('inactive-t3', 'Inactive Tenant Three')->inactive()->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants');

        $response->assertStatus(200);
        $response->assertSee('Active Tenant Three');
        $response->assertSee('Inactive Tenant Three');
    });
});

describe('Tenant List Sorting (BR-068)', function () {
    it('sorts by name ascending', function () {
        $admin = createUser('admin');

        Tenant::factory()->withSlug('z-tenant', 'Zara Kitchen')->create();
        Tenant::factory()->withSlug('a-tenant', 'Amara Kitchen')->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants?sort=name&direction=asc');

        $response->assertStatus(200);
        $response->assertSeeInOrder(['Amara Kitchen', 'Zara Kitchen']);
    });

    it('sorts by name descending', function () {
        $admin = createUser('admin');

        Tenant::factory()->withSlug('z-tenant2', 'Zara Kitchen Two')->create();
        Tenant::factory()->withSlug('a-tenant2', 'Amara Kitchen Two')->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants?sort=name&direction=desc');

        $response->assertStatus(200);
        $response->assertSeeInOrder(['Zara Kitchen Two', 'Amara Kitchen Two']);
    });

    it('sorts by created_at ascending', function () {
        $admin = createUser('admin');

        $older = Tenant::factory()->withSlug('older-sort', 'Older Sort')->create([
            'created_at' => now()->subDays(5),
        ]);
        $newer = Tenant::factory()->withSlug('newer-sort', 'Newer Sort')->create([
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/vault-entry/tenants?sort=created_at&direction=asc');

        $response->assertStatus(200);
        $response->assertSeeInOrder(['Older Sort', 'Newer Sort']);
    });

    it('ignores invalid sort columns', function () {
        $admin = createUser('admin');

        Tenant::factory()->withSlug('safe-tenant', 'Safe Tenant')->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants?sort=DROP_TABLE&direction=asc');

        $response->assertStatus(200);
        $response->assertSee('Safe Tenant');
    });
});

describe('Tenant List Combined Filters', function () {
    it('combines search and status filter', function () {
        $admin = createUser('admin');

        Tenant::factory()->withSlug('active-amara', 'Active Amara')->create(['is_active' => true]);
        Tenant::factory()->withSlug('inactive-amara', 'Inactive Amara')->inactive()->create();
        Tenant::factory()->withSlug('active-powel', 'Active Powel')->create(['is_active' => true]);

        $response = $this->actingAs($admin)->get('/vault-entry/tenants?search=amara&status=active');

        $response->assertStatus(200);
        $response->assertSee('Active Amara');
        $response->assertDontSee('Inactive Amara');
        $response->assertDontSee('Active Powel');
    });

    it('preserves query parameters in pagination links', function () {
        $admin = createUser('admin');

        for ($i = 1; $i <= 20; $i++) {
            Tenant::factory()->withSlug("search-t-{$i}", "Searchable Tenant {$i}")->create();
        }

        $response = $this->actingAs($admin)->get('/vault-entry/tenants?search=Searchable&sort=name&direction=asc');

        $response->assertStatus(200);
        $response->assertSee('search=Searchable');
    });
});

describe('Tenant List Navigation', function () {
    it('contains links to tenant detail pages', function () {
        $admin = createUser('admin');

        Tenant::factory()->withSlug('clickable-tenant', 'Clickable Tenant')->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants');

        $response->assertStatus(200);
        $response->assertSee('/vault-entry/tenants/clickable-tenant');
    });

    it('contains create tenant button', function () {
        $admin = createUser('admin');

        $response = $this->actingAs($admin)->get('/vault-entry/tenants');

        $response->assertStatus(200);
        $response->assertSee('/vault-entry/tenants/create');
        $response->assertSee('Create Tenant');
    });
});
