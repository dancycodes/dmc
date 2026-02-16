<?php

use App\Models\Tenant;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);
});

describe('Tenant Edit Access Control', function () {
    it('allows admins to access the edit form', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('edit-access', 'Edit Access Kitchen')->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/'.$tenant->slug.'/edit');

        $response->assertStatus(200);
    });

    it('allows super-admins to access the edit form', function () {
        $admin = createUser('super-admin');
        $tenant = Tenant::factory()->withSlug('edit-sa', 'Edit SA Kitchen')->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/'.$tenant->slug.'/edit');

        $response->assertStatus(200);
    });

    it('denies regular users access to the edit form', function () {
        $user = createUser('client');
        $tenant = Tenant::factory()->withSlug('edit-denied', 'Edit Denied Kitchen')->create();

        $response = $this->actingAs($user)->get('/vault-entry/tenants/'.$tenant->slug.'/edit');

        $response->assertStatus(403);
    });

    it('redirects guests to login', function () {
        $tenant = Tenant::factory()->withSlug('edit-guest', 'Edit Guest Kitchen')->create();

        $response = $this->get('/vault-entry/tenants/'.$tenant->slug.'/edit');

        $response->assertRedirect('/login');
    });

    it('returns 404 for non-existent tenant', function () {
        $admin = createUser('admin');

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/nonexistent/edit');

        $response->assertStatus(404);
    });
});

describe('Tenant Edit Form Display', function () {
    it('displays pre-filled form with current tenant values', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->create([
            'slug' => 'prefilled-test',
            'name_en' => 'Prefilled Kitchen',
            'name_fr' => 'Cuisine Preremplie',
            'description_en' => 'A test kitchen description.',
            'description_fr' => 'Une description de cuisine test.',
            'custom_domain' => 'prefilled.cm',
        ]);

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/'.$tenant->slug.'/edit');

        $response->assertStatus(200);
        $response->assertSee('Prefilled Kitchen');
        $response->assertSee('Cuisine Preremplie');
        $response->assertSee('prefilled.cm');
        $response->assertSee(__('Save Changes'));
        $response->assertSee(__('Cancel'));
    });

    it('shows breadcrumb navigation', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('breadcrumb-edit', 'Breadcrumb Kitchen')->create();

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/'.$tenant->slug.'/edit');

        $response->assertStatus(200);
        $response->assertSee(__('Tenants'));
        $response->assertSee(__('Edit'));
    });

    it('shows status toggle section', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('status-display', 'Status Display Kitchen')->create([
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get('/vault-entry/tenants/'.$tenant->slug.'/edit');

        $response->assertStatus(200);
        $response->assertSee(__('Tenant Status'));
    });
});

describe('Tenant Update (BR-078, BR-079, BR-080)', function () {
    it('updates tenant name and redirects to detail page', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->create([
            'slug' => 'update-name',
            'name_en' => 'Original Name',
            'name_fr' => 'Nom Original',
            'description_en' => 'Description',
            'description_fr' => 'Description FR',
        ]);

        $response = $this->actingAs($admin)->post('/vault-entry/tenants/'.$tenant->slug, [
            'name_en' => 'Updated Kitchen',
            'name_fr' => 'Cuisine Mise a Jour',
            'subdomain' => $tenant->slug,
            'custom_domain' => '',
            'description_en' => 'Description',
            'description_fr' => 'Description FR',
        ]);

        $response->assertRedirect('/vault-entry/tenants/'.$tenant->slug);

        $tenant->refresh();
        expect($tenant->name_en)->toBe('Updated Kitchen');
        expect($tenant->name_fr)->toBe('Cuisine Mise a Jour');
    });

    it('updates subdomain and uses new slug in redirect', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->create([
            'slug' => 'old-subdomain',
            'name_en' => 'Subdomain Test',
            'name_fr' => 'Test Sous-domaine',
            'description_en' => 'Test description',
            'description_fr' => 'Description test',
        ]);

        $response = $this->actingAs($admin)->post('/vault-entry/tenants/'.$tenant->slug, [
            'name_en' => 'Subdomain Test',
            'name_fr' => 'Test Sous-domaine',
            'subdomain' => 'new-subdomain',
            'custom_domain' => '',
            'description_en' => 'Test description',
            'description_fr' => 'Description test',
        ]);

        $tenant->refresh();
        expect($tenant->slug)->toBe('new-subdomain');
        $response->assertRedirect('/vault-entry/tenants/new-subdomain');
    });

    it('allows keeping the same subdomain (BR-079)', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->create([
            'slug' => 'same-subdomain',
            'name_en' => 'Same Subdomain',
            'name_fr' => 'Meme Sous-domaine',
            'description_en' => 'Desc',
            'description_fr' => 'Desc FR',
        ]);

        $response = $this->actingAs($admin)->post('/vault-entry/tenants/'.$tenant->slug, [
            'name_en' => 'Updated Name',
            'name_fr' => 'Nom Modifie',
            'subdomain' => 'same-subdomain',
            'custom_domain' => '',
            'description_en' => 'Desc',
            'description_fr' => 'Desc FR',
        ]);

        $response->assertRedirect('/vault-entry/tenants/same-subdomain');
    });

    it('rejects duplicate subdomain from another tenant', function () {
        $admin = createUser('admin');
        Tenant::factory()->withSlug('taken-subdomain', 'Taken Tenant')->create();
        $tenant = Tenant::factory()->withSlug('my-tenant', 'My Tenant')->create([
            'description_en' => 'Desc',
            'description_fr' => 'Desc FR',
        ]);

        $response = $this->actingAs($admin)->post('/vault-entry/tenants/'.$tenant->slug, [
            'name_en' => 'My Tenant',
            'name_fr' => 'My Tenant FR',
            'subdomain' => 'taken-subdomain',
            'custom_domain' => '',
            'description_en' => 'Desc',
            'description_fr' => 'Desc FR',
        ]);

        $response->assertSessionHasErrors('subdomain');
    });

    it('rejects duplicate custom domain from another tenant', function () {
        $admin = createUser('admin');
        Tenant::factory()->withSlug('domain-owner', 'Domain Owner')->withCustomDomain('taken.cm')->create();
        $tenant = Tenant::factory()->withSlug('domain-seeker', 'Domain Seeker')->create([
            'description_en' => 'Desc',
            'description_fr' => 'Desc FR',
        ]);

        $response = $this->actingAs($admin)->post('/vault-entry/tenants/'.$tenant->slug, [
            'name_en' => 'Domain Seeker',
            'name_fr' => 'Domain Seeker FR',
            'subdomain' => 'domain-seeker',
            'custom_domain' => 'taken.cm',
            'description_en' => 'Desc',
            'description_fr' => 'Desc FR',
        ]);

        $response->assertSessionHasErrors('custom_domain');
    });

    it('allows keeping same custom domain (BR-079)', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('keep-domain', 'Keep Domain')->withCustomDomain('mysite.cm')->create([
            'description_en' => 'Desc',
            'description_fr' => 'Desc FR',
        ]);

        $response = $this->actingAs($admin)->post('/vault-entry/tenants/'.$tenant->slug, [
            'name_en' => 'Keep Domain Updated',
            'name_fr' => 'Keep Domain FR',
            'subdomain' => 'keep-domain',
            'custom_domain' => 'mysite.cm',
            'description_en' => 'Desc',
            'description_fr' => 'Desc FR',
        ]);

        $response->assertRedirect('/vault-entry/tenants/keep-domain');
    });

    it('allows removing custom domain', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('remove-domain', 'Remove Domain')->withCustomDomain('old.cm')->create([
            'description_en' => 'Desc',
            'description_fr' => 'Desc FR',
        ]);

        $response = $this->actingAs($admin)->post('/vault-entry/tenants/'.$tenant->slug, [
            'name_en' => 'Remove Domain',
            'name_fr' => 'Remove Domain FR',
            'subdomain' => 'remove-domain',
            'custom_domain' => '',
            'description_en' => 'Desc',
            'description_fr' => 'Desc FR',
        ]);

        $tenant->refresh();
        expect($tenant->custom_domain)->toBeNull();
    });

    it('creates activity log entry on update (BR-080)', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->create([
            'slug' => 'log-update',
            'name_en' => 'Before Update',
            'name_fr' => 'Avant MAJ',
            'description_en' => 'Desc',
            'description_fr' => 'Desc FR',
        ]);

        // Clear auto-logged creation activity
        Activity::where('subject_type', Tenant::class)->where('subject_id', $tenant->id)->delete();

        $this->actingAs($admin)->post('/vault-entry/tenants/'.$tenant->slug, [
            'name_en' => 'After Update',
            'name_fr' => 'Apres MAJ',
            'subdomain' => 'log-update',
            'custom_domain' => '',
            'description_en' => 'Desc',
            'description_fr' => 'Desc FR',
        ]);

        $activity = Activity::where('subject_type', Tenant::class)
            ->where('subject_id', $tenant->id)
            ->where('description', 'updated')
            ->first();

        expect($activity)->not->toBeNull();
        expect($activity->causer_id)->toBe($admin->id);
        expect($activity->properties['old']['name_en'])->toBe('Before Update');
        expect($activity->properties['attributes']['name_en'])->toBe('After Update');
    });

    it('does not create activity log when no changes made', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->create([
            'slug' => 'no-change',
            'name_en' => 'No Change',
            'name_fr' => 'Pas de Changement',
            'description_en' => 'Desc',
            'description_fr' => 'Desc FR',
            'custom_domain' => null,
        ]);

        Activity::where('subject_type', Tenant::class)->where('subject_id', $tenant->id)->delete();

        $this->actingAs($admin)->post('/vault-entry/tenants/'.$tenant->slug, [
            'name_en' => 'No Change',
            'name_fr' => 'Pas de Changement',
            'subdomain' => 'no-change',
            'custom_domain' => '',
            'description_en' => 'Desc',
            'description_fr' => 'Desc FR',
        ]);

        $activity = Activity::where('subject_type', Tenant::class)
            ->where('subject_id', $tenant->id)
            ->where('description', 'updated')
            ->first();

        expect($activity)->toBeNull();
    });

    it('strips HTML from descriptions', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->create([
            'slug' => 'html-strip',
            'name_en' => 'HTML Strip',
            'name_fr' => 'HTML Strip FR',
            'description_en' => 'Old desc',
            'description_fr' => 'Old desc FR',
        ]);

        $this->actingAs($admin)->post('/vault-entry/tenants/'.$tenant->slug, [
            'name_en' => 'HTML Strip',
            'name_fr' => 'HTML Strip FR',
            'subdomain' => 'html-strip',
            'custom_domain' => '',
            'description_en' => '<script>alert("xss")</script>Clean description',
            'description_fr' => '<b>Bold</b> description FR',
        ]);

        $tenant->refresh();
        expect($tenant->description_en)->toBe('alert("xss")Clean description');
        expect($tenant->description_fr)->toBe('Bold description FR');
    });
});

describe('Tenant Status Toggle (BR-075, BR-076, BR-080, BR-081)', function () {
    it('deactivates an active tenant', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('deactivate-me', 'Deactivate Me')->create([
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post('/vault-entry/tenants/'.$tenant->slug.'/toggle-status');

        $response->assertRedirect('/vault-entry/tenants/'.$tenant->slug);

        $tenant->refresh();
        expect($tenant->is_active)->toBeFalse();
    });

    it('activates an inactive tenant', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('activate-me', 'Activate Me')->inactive()->create();

        $response = $this->actingAs($admin)->post('/vault-entry/tenants/'.$tenant->slug.'/toggle-status');

        $response->assertRedirect('/vault-entry/tenants/'.$tenant->slug);

        $tenant->refresh();
        expect($tenant->is_active)->toBeTrue();
    });

    it('logs deactivation in activity log (BR-080)', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('log-deactivate', 'Log Deactivate')->create([
            'is_active' => true,
        ]);

        Activity::where('subject_type', Tenant::class)->where('subject_id', $tenant->id)->delete();

        $this->actingAs($admin)->post('/vault-entry/tenants/'.$tenant->slug.'/toggle-status');

        $activity = Activity::where('subject_type', Tenant::class)
            ->where('subject_id', $tenant->id)
            ->where('description', 'deactivated')
            ->first();

        expect($activity)->not->toBeNull();
        expect($activity->causer_id)->toBe($admin->id);
        expect($activity->properties['old']['is_active'])->toBeTrue();
        expect($activity->properties['attributes']['is_active'])->toBeFalse();
    });

    it('logs activation in activity log (BR-080)', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('log-activate', 'Log Activate')->inactive()->create();

        Activity::where('subject_type', Tenant::class)->where('subject_id', $tenant->id)->delete();

        $this->actingAs($admin)->post('/vault-entry/tenants/'.$tenant->slug.'/toggle-status');

        $activity = Activity::where('subject_type', Tenant::class)
            ->where('subject_id', $tenant->id)
            ->where('description', 'activated')
            ->first();

        expect($activity)->not->toBeNull();
        expect($activity->properties['attributes']['is_active'])->toBeTrue();
    });

    it('does not delete tenant data on deactivation (BR-076)', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->create([
            'slug' => 'data-preserved',
            'name_en' => 'Data Preserved Kitchen',
            'name_fr' => 'Cuisine Preservee',
            'description_en' => 'Important description',
            'description_fr' => 'Description importante',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post('/vault-entry/tenants/'.$tenant->slug.'/toggle-status');

        $tenant->refresh();
        expect($tenant->is_active)->toBeFalse();
        expect($tenant->name_en)->toBe('Data Preserved Kitchen');
        expect($tenant->description_en)->toBe('Important description');
        expect($tenant->slug)->toBe('data-preserved');
    });

    it('denies toggle to regular users', function () {
        $user = createUser('client');
        $tenant = Tenant::factory()->withSlug('toggle-denied', 'Toggle Denied')->create();

        $response = $this->actingAs($user)->post('/vault-entry/tenants/'.$tenant->slug.'/toggle-status');

        $response->assertStatus(403);
    });

    it('shows success toast after toggle', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('toast-toggle', 'Toast Toggle Kitchen')->create([
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post('/vault-entry/tenants/'.$tenant->slug.'/toggle-status');

        $response->assertSessionHas('toast');
    });
});

describe('Tenant Edit Validation', function () {
    it('requires name_en', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('val-name', 'Validation Name')->create([
            'description_en' => 'Desc',
            'description_fr' => 'Desc FR',
        ]);

        $response = $this->actingAs($admin)->post('/vault-entry/tenants/'.$tenant->slug, [
            'name_en' => '',
            'name_fr' => 'Valid FR',
            'subdomain' => 'val-name',
            'custom_domain' => '',
            'description_en' => 'Desc',
            'description_fr' => 'Desc FR',
        ]);

        $response->assertSessionHasErrors('name_en');
    });

    it('requires description_en and description_fr', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('val-desc', 'Validation Desc')->create([
            'description_en' => 'Desc',
            'description_fr' => 'Desc FR',
        ]);

        $response = $this->actingAs($admin)->post('/vault-entry/tenants/'.$tenant->slug, [
            'name_en' => 'Valid',
            'name_fr' => 'Valid FR',
            'subdomain' => 'val-desc',
            'custom_domain' => '',
            'description_en' => '',
            'description_fr' => '',
        ]);

        $response->assertSessionHasErrors(['description_en', 'description_fr']);
    });

    it('rejects reserved subdomain', function () {
        $admin = createUser('admin');
        $tenant = Tenant::factory()->withSlug('reserved-test', 'Reserved Test')->create([
            'description_en' => 'Desc',
            'description_fr' => 'Desc FR',
        ]);

        $response = $this->actingAs($admin)->post('/vault-entry/tenants/'.$tenant->slug, [
            'name_en' => 'Reserved Test',
            'name_fr' => 'Reserved Test FR',
            'subdomain' => 'admin',
            'custom_domain' => '',
            'description_en' => 'Desc',
            'description_fr' => 'Desc FR',
        ]);

        $response->assertSessionHasErrors('subdomain');
    });
});
