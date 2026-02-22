<?php

use App\Models\Tenant;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);
});

describe('Cook Performance Access Control (BR-428)', function () {
    it('allows admin to access cook performance page', function () {
        $admin = createUser('admin');

        $response = $this->actingAs($admin)->get('/vault-entry/analytics/performance');

        $response->assertStatus(200);
    });

    it('allows super-admin to access cook performance page', function () {
        $superAdmin = createUser('super-admin');

        $response = $this->actingAs($superAdmin)->get('/vault-entry/analytics/performance');

        $response->assertStatus(200);
    });

    it('denies access to cook role', function () {
        ['cook' => $cook] = createTenantWithCook();

        $response = $this->actingAs($cook)->get('/vault-entry/analytics/performance');

        $response->assertStatus(403);
    });

    it('denies access to client role', function () {
        $client = createUser('client');

        $response = $this->actingAs($client)->get('/vault-entry/analytics/performance');

        $response->assertStatus(403);
    });

    it('redirects guests to login', function () {
        $response = $this->get('/vault-entry/analytics/performance');

        $response->assertRedirect('/login');
    });
});

describe('Cook Performance Table Rendering', function () {
    it('renders the page with cook performance heading', function () {
        $admin = createUser('admin');

        $response = $this->actingAs($admin)->get('/vault-entry/analytics/performance');

        $response->assertStatus(200);
        $response->assertSee(__('Cook Performance Metrics'));
    });

    it('shows cooks with their names in the table', function () {
        $admin = createUser('admin');

        // Create a cook user and link them to a tenant via cook_id
        $cook = createUser('cook', ['name' => 'Chef Alice']);
        \App\Models\Tenant::factory()->create([
            'cook_id' => $cook->id,
            'name_en' => 'Test Kitchen',
            'name_fr' => 'Test Kitchen FR',
        ]);

        $response = $this->actingAs($admin)->get('/vault-entry/analytics/performance');

        $response->assertStatus(200);
        $response->assertSee('Chef Alice');
    });

    it('shows empty state when no cooks exist', function () {
        $admin = createUser('admin');

        // Delete all tenants to get clean empty state
        Tenant::query()->update(['cook_id' => null]);

        $response = $this->actingAs($admin)->get('/vault-entry/analytics/performance');

        $response->assertStatus(200);
        $response->assertSee(__('No cooks found'));
    });
});

describe('Cook Performance Filters (BR-431, BR-432)', function () {
    it('accepts search query parameter', function () {
        $admin = createUser('admin');

        $response = $this->actingAs($admin)
            ->get('/vault-entry/analytics/performance?search=alice');

        $response->assertStatus(200);
    });

    it('accepts status filter parameter', function () {
        $admin = createUser('admin');

        $response = $this->actingAs($admin)
            ->get('/vault-entry/analytics/performance?status=active');

        $response->assertStatus(200);
    });

    it('accepts sort and direction parameters', function () {
        $admin = createUser('admin');

        $response = $this->actingAs($admin)
            ->get('/vault-entry/analytics/performance?sort=total_orders&direction=asc');

        $response->assertStatus(200);
    });

    it('accepts period parameter', function () {
        $admin = createUser('admin');

        foreach (['this_month', 'last_3_months', 'this_year', 'all_time'] as $period) {
            $response = $this->actingAs($admin)
                ->get("/vault-entry/analytics/performance?period={$period}");

            $response->assertStatus(200);
        }
    });

    it('accepts custom date range', function () {
        $admin = createUser('admin');

        $response = $this->actingAs($admin)
            ->get('/vault-entry/analytics/performance?period=custom&custom_start=2025-01-01&custom_end=2025-03-31');

        $response->assertStatus(200);
    });

    it('rejects invalid sort column', function () {
        $admin = createUser('admin');

        $response = $this->actingAs($admin)
            ->get('/vault-entry/analytics/performance?sort=invalid_column');

        $response->assertStatus(302);
    });
});

describe('Cook Performance Gale Fragment (BR-437)', function () {
    it('returns fragment on Gale navigate request', function () {
        $admin = createUser('admin');

        $response = $this->actingAs($admin)
            ->withHeaders(['Gale-Request' => '1', 'Gale-Navigate' => 'cook-performance'])
            ->get('/vault-entry/analytics/performance?sort=complaint_count&direction=desc');

        $response->assertStatus(200);
    });
});
