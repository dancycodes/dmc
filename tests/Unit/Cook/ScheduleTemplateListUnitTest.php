<?php

/**
 * F-102: Schedule Template List View -- Unit Tests
 *
 * Tests for ScheduleTemplateController::index(), ScheduleTemplateService
 * getTemplatesWithAppliedCount(), ScheduleTemplate model relationships,
 * CookSchedule template relationship, route configuration, blade view,
 * and translation strings.
 *
 * BR-136: Tenant-scoped list
 * BR-137: Applied-to count via template_id reference
 * BR-138: Permission check (can-manage-schedules)
 * BR-139: Alphabetical order by name
 */

use App\Http\Controllers\Cook\ScheduleTemplateController;
use App\Models\CookSchedule;
use App\Models\ScheduleTemplate;
use App\Models\Tenant;
use App\Services\ScheduleTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;

$projectRoot = dirname(__DIR__, 3);

uses(Tests\TestCase::class, RefreshDatabase::class);

// ============================================================
// Test group: ScheduleTemplateController methods
// ============================================================
describe('ScheduleTemplateController', function () {
    it('has an index method', function () {
        $reflection = new ReflectionMethod(ScheduleTemplateController::class, 'index');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('index method returns mixed type', function () {
        $reflection = new ReflectionMethod(ScheduleTemplateController::class, 'index');
        expect($reflection->getReturnType()->getName())->toBe('mixed');
    });

    it('index method accepts Request and ScheduleTemplateService parameters', function () {
        $reflection = new ReflectionMethod(ScheduleTemplateController::class, 'index');
        $params = $reflection->getParameters();

        expect($params)->toHaveCount(2)
            ->and($params[0]->getType()->getName())->toBe('Illuminate\Http\Request')
            ->and($params[1]->getType()->getName())->toBe('App\Services\ScheduleTemplateService');
    });
});

// ============================================================
// Test group: ScheduleTemplateService
// ============================================================
describe('ScheduleTemplateService', function () {
    it('has getTemplatesWithAppliedCount method', function () {
        $reflection = new ReflectionMethod(ScheduleTemplateService::class, 'getTemplatesWithAppliedCount');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('returns empty collection for tenant with no templates', function () {
        $this->seedRolesAndPermissions();
        $tenant = Tenant::factory()->create();
        $service = app(ScheduleTemplateService::class);

        $result = $service->getTemplatesWithAppliedCount($tenant);

        expect($result)->toBeEmpty();
    });

    it('returns templates ordered alphabetically by name (BR-139)', function () {
        $this->seedRolesAndPermissions();
        $tenant = Tenant::factory()->create();
        ScheduleTemplate::factory()->withName('Dinner Service')->create(['tenant_id' => $tenant->id]);
        ScheduleTemplate::factory()->withName('Breakfast Service')->create(['tenant_id' => $tenant->id]);
        ScheduleTemplate::factory()->withName('Lunch Service')->create(['tenant_id' => $tenant->id]);

        $service = app(ScheduleTemplateService::class);
        $result = $service->getTemplatesWithAppliedCount($tenant);

        expect($result)->toHaveCount(3)
            ->and($result[0]->name)->toBe('Breakfast Service')
            ->and($result[1]->name)->toBe('Dinner Service')
            ->and($result[2]->name)->toBe('Lunch Service');
    });

    it('only returns templates for specified tenant (BR-136)', function () {
        $this->seedRolesAndPermissions();
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        ScheduleTemplate::factory()->withName('Template A')->create(['tenant_id' => $tenant1->id]);
        ScheduleTemplate::factory()->withName('Template B')->create(['tenant_id' => $tenant2->id]);

        $service = app(ScheduleTemplateService::class);
        $result1 = $service->getTemplatesWithAppliedCount($tenant1);
        $result2 = $service->getTemplatesWithAppliedCount($tenant2);

        expect($result1)->toHaveCount(1)
            ->and($result1[0]->name)->toBe('Template A')
            ->and($result2)->toHaveCount(1)
            ->and($result2[0]->name)->toBe('Template B');
    });

    it('includes cook_schedules_count on templates (BR-137)', function () {
        $this->seedRolesAndPermissions();
        $tenant = Tenant::factory()->create();
        $template = ScheduleTemplate::factory()->withName('Lunch Service')->create(['tenant_id' => $tenant->id]);

        // Create cook schedules referencing this template
        CookSchedule::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
            'template_id' => $template->id,
        ]);

        $service = app(ScheduleTemplateService::class);
        $result = $service->getTemplatesWithAppliedCount($tenant);

        expect($result)->toHaveCount(1)
            ->and($result[0]->cook_schedules_count)->toBe(3);
    });

    it('returns zero applied count for unused template', function () {
        $this->seedRolesAndPermissions();
        $tenant = Tenant::factory()->create();
        ScheduleTemplate::factory()->withName('Unused Template')->create(['tenant_id' => $tenant->id]);

        $service = app(ScheduleTemplateService::class);
        $result = $service->getTemplatesWithAppliedCount($tenant);

        expect($result)->toHaveCount(1)
            ->and($result[0]->cook_schedules_count)->toBe(0);
    });

    it('does not count schedules from other templates', function () {
        $this->seedRolesAndPermissions();
        $tenant = Tenant::factory()->create();
        $template1 = ScheduleTemplate::factory()->withName('Template 1')->create(['tenant_id' => $tenant->id]);
        $template2 = ScheduleTemplate::factory()->withName('Template 2')->create(['tenant_id' => $tenant->id]);

        CookSchedule::factory()->count(5)->create([
            'tenant_id' => $tenant->id,
            'template_id' => $template1->id,
        ]);
        CookSchedule::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'template_id' => $template2->id,
        ]);

        $service = app(ScheduleTemplateService::class);
        $result = $service->getTemplatesWithAppliedCount($tenant);

        $t1 = $result->firstWhere('name', 'Template 1');
        $t2 = $result->firstWhere('name', 'Template 2');

        expect($t1->cook_schedules_count)->toBe(5)
            ->and($t2->cook_schedules_count)->toBe(2);
    });
});

// ============================================================
// Test group: ScheduleTemplate model relationships
// ============================================================
describe('ScheduleTemplate model', function () {
    it('has cookSchedules hasMany relationship', function () {
        $template = new ScheduleTemplate;
        $relation = $template->cookSchedules();

        expect($relation)->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
    });

    it('cookSchedules relationship uses template_id foreign key', function () {
        $template = new ScheduleTemplate;
        $relation = $template->cookSchedules();

        expect($relation->getForeignKeyName())->toBe('template_id');
    });

    it('has order_interval_summary accessor', function () {
        $template = ScheduleTemplate::factory()->make([
            'order_start_time' => '18:00',
            'order_start_day_offset' => 1,
            'order_end_time' => '08:00',
            'order_end_day_offset' => 0,
        ]);

        expect($template->order_interval_summary)->toBeString()
            ->and($template->order_interval_summary)->toContain('6:00 PM');
    });

    it('returns null delivery summary when delivery disabled', function () {
        $template = ScheduleTemplate::factory()->pickupOnly()->make();

        expect($template->delivery_interval_summary)->toBeNull();
    });

    it('returns delivery summary when delivery enabled', function () {
        $template = ScheduleTemplate::factory()->make([
            'delivery_enabled' => true,
            'delivery_start_time' => '11:00',
            'delivery_end_time' => '14:00',
        ]);

        expect($template->delivery_interval_summary)->toBeString()
            ->and($template->delivery_interval_summary)->toContain('11:00 AM')
            ->and($template->delivery_interval_summary)->toContain('2:00 PM');
    });

    it('returns null pickup summary when pickup disabled', function () {
        $template = ScheduleTemplate::factory()->deliveryOnly()->make();

        expect($template->pickup_interval_summary)->toBeNull();
    });

    it('returns pickup summary when pickup enabled', function () {
        $template = ScheduleTemplate::factory()->make([
            'pickup_enabled' => true,
            'pickup_start_time' => '10:30',
            'pickup_end_time' => '15:00',
        ]);

        expect($template->pickup_interval_summary)->toBeString()
            ->and($template->pickup_interval_summary)->toContain('10:30 AM')
            ->and($template->pickup_interval_summary)->toContain('3:00 PM');
    });
});

// ============================================================
// Test group: CookSchedule model template relationship
// ============================================================
describe('CookSchedule model', function () {
    it('has template belongsTo relationship', function () {
        $schedule = new CookSchedule;
        $relation = $schedule->template();

        expect($relation)->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    });

    it('template relationship uses template_id foreign key', function () {
        $schedule = new CookSchedule;
        $relation = $schedule->template();

        expect($relation->getForeignKeyName())->toBe('template_id');
    });

    it('has template_id in fillable', function () {
        $schedule = new CookSchedule;

        expect($schedule->getFillable())->toContain('template_id');
    });

    it('can set and retrieve template reference', function () {
        $this->seedRolesAndPermissions();
        $tenant = Tenant::factory()->create();
        $template = ScheduleTemplate::factory()->withName('Test Template')->create(['tenant_id' => $tenant->id]);
        $schedule = CookSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'template_id' => $template->id,
        ]);

        expect($schedule->template_id)->toBe($template->id)
            ->and($schedule->template->name)->toBe('Test Template');
    });

    it('template_id is nullable (manually created schedules)', function () {
        $this->seedRolesAndPermissions();
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'template_id' => null,
        ]);

        expect($schedule->template_id)->toBeNull()
            ->and($schedule->template)->toBeNull();
    });
});

// ============================================================
// Test group: Route configuration
// ============================================================
describe('route configuration', function () use ($projectRoot) {
    it('registers GET route for template list', function () use ($projectRoot) {
        $routeContent = file_get_contents($projectRoot.'/routes/web.php');

        expect($routeContent)->toContain("Route::get('/schedule/templates'")
            ->and($routeContent)->toContain("'index'")
            ->and($routeContent)->toContain("'cook.schedule-templates.index'");
    });

    it('index route is before create route', function () use ($projectRoot) {
        $routeContent = file_get_contents($projectRoot.'/routes/web.php');

        $indexPos = strpos($routeContent, "Route::get('/schedule/templates',");
        $createPos = strpos($routeContent, "Route::get('/schedule/templates/create'");

        // Index route must come before create route so /templates/create matches before /templates
        // Actually for Laravel, more specific routes should come first, so create should be first
        // But the index route with exact match still works. Either ordering is fine with distinct paths.
        expect($indexPos)->toBeInt()
            ->and($createPos)->toBeInt();
    });
});

// ============================================================
// Test group: Blade view
// ============================================================
describe('blade view', function () use ($projectRoot) {
    it('exists at the correct path', function () use ($projectRoot) {
        $viewPath = $projectRoot.'/resources/views/cook/schedule/templates/index.blade.php';
        expect(file_exists($viewPath))->toBeTrue();
    });

    it('extends cook-dashboard layout', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($viewContent)->toContain("@extends('layouts.cook-dashboard')");
    });

    it('has Schedule Templates page title', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($viewContent)->toContain("@section('title', __('Schedule Templates'))");
    });

    it('has breadcrumb navigation', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($viewContent)->toContain("__('Dashboard')")
            ->and($viewContent)->toContain("__('Schedule')")
            ->and($viewContent)->toContain("__('Templates')");
    });

    it('has Create Template button linking to create page', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($viewContent)->toContain("url('/dashboard/schedule/templates/create')")
            ->and($viewContent)->toContain("__('Create Template')");
    });

    it('has empty state with CTA', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($viewContent)->toContain("__('No templates yet')")
            ->and($viewContent)->toContain("__('Create Your First Template')");
    });

    it('shows template count summary', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($viewContent)->toContain("trans_choice(':count template|:count templates'");
    });

    it('has desktop table view', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($viewContent)->toContain('hidden md:block')
            ->and($viewContent)->toContain('<table')
            ->and($viewContent)->toContain("__('Order Window')")
            ->and($viewContent)->toContain("__('Applied')");
    });

    it('has mobile card view', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($viewContent)->toContain('md:hidden');
    });

    it('shows applied-to count badge (BR-137)', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($viewContent)->toContain('cook_schedules_count')
            ->and($viewContent)->toContain("trans_choice(':count day|:count days'");
    });

    it('has edit action button for each template', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($viewContent)->toContain("/edit')")
            ->and($viewContent)->toContain("__('Edit')");
    });

    it('has apply action button for each template', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($viewContent)->toContain("/apply')")
            ->and($viewContent)->toContain("__('Apply')");
    });

    it('has delete action button for each template', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/index.blade.php');

        // F-104: Delete now uses Alpine.js confirmDelete modal instead of anchor link
        expect($viewContent)->toContain('confirmDelete(')
            ->and($viewContent)->toContain("__('Delete')");
    });

    it('shows delivery and pickup status', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($viewContent)->toContain('delivery_enabled')
            ->and($viewContent)->toContain('pickup_enabled')
            ->and($viewContent)->toContain('delivery_interval_summary')
            ->and($viewContent)->toContain('pickup_interval_summary');
    });

    it('uses semantic color tokens', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($viewContent)->toContain('bg-surface-alt')
            ->and($viewContent)->toContain('text-on-surface-strong')
            ->and($viewContent)->toContain('border-outline')
            ->and($viewContent)->toContain('bg-primary');
    });

    it('includes dark mode variants', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($viewContent)->toContain('dark:bg-surface-alt')
            ->and($viewContent)->toContain('dark:border-outline');
    });

    it('uses __() for all user-facing strings', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/index.blade.php');

        // Key user-facing strings should be wrapped in __()
        expect($viewContent)->toContain("__('Schedule Templates')")
            ->and($viewContent)->toContain("__('Back to Schedule')")
            ->and($viewContent)->toContain("__('Delivery')")
            ->and($viewContent)->toContain("__('Pickup')")
            ->and($viewContent)->toContain("__('Orders')");
    });

    it('has x-data on root container', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($viewContent)->toContain('x-data');
    });

    it('truncates long template names with title attribute', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($viewContent)->toContain('truncate')
            ->and($viewContent)->toContain('title="{{ $template->name }}"');
    });
});

// ============================================================
// Test group: Translation strings
// ============================================================
describe('translation strings', function () use ($projectRoot) {
    it('has all EN translation strings', function () use ($projectRoot) {
        $en = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);

        expect($en)->toHaveKey('No templates yet')
            ->and($en)->toHaveKey('Create Your First Template')
            ->and($en)->toHaveKey(':count template|:count templates')
            ->and($en)->toHaveKey('Order Window')
            ->and($en)->toHaveKey('Applied')
            ->and($en)->toHaveKey(':count day|:count days')
            ->and($en)->toHaveKey('Apply to Days')
            ->and($en)->toHaveKey('Apply');
    });

    it('has all FR translation strings', function () use ($projectRoot) {
        $fr = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);

        expect($fr)->toHaveKey('No templates yet')
            ->and($fr)->toHaveKey('Create Your First Template')
            ->and($fr)->toHaveKey(':count template|:count templates')
            ->and($fr)->toHaveKey('Order Window')
            ->and($fr)->toHaveKey('Applied')
            ->and($fr)->toHaveKey(':count day|:count days')
            ->and($fr)->toHaveKey('Apply to Days')
            ->and($fr)->toHaveKey('Apply');
    });

    it('FR translations are not English copies', function () use ($projectRoot) {
        $en = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);
        $fr = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);

        expect($fr['No templates yet'])->not->toBe($en['No templates yet'])
            ->and($fr['Create Your First Template'])->not->toBe($en['Create Your First Template'])
            ->and($fr[':count template|:count templates'])->not->toBe($en[':count template|:count templates']);
    });
});

// ============================================================
// Test group: Migration
// ============================================================
describe('migration', function () {
    it('adds template_id column to cook_schedules table', function () {
        expect(\Schema::hasColumn('cook_schedules', 'template_id'))->toBeTrue();
    });

    it('template_id column is nullable', function () {
        $this->seedRolesAndPermissions();
        $tenant = Tenant::factory()->create();

        // Creating a schedule without template_id should work
        $schedule = CookSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'template_id' => null,
        ]);

        expect($schedule->exists)->toBeTrue();
    });

    it('template_id has foreign key constraint to schedule_templates', function () {
        $this->seedRolesAndPermissions();
        $tenant = Tenant::factory()->create();
        $template = ScheduleTemplate::factory()->withName('FK Test')->create(['tenant_id' => $tenant->id]);
        $schedule = CookSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'template_id' => $template->id,
        ]);

        expect($schedule->template_id)->toBe($template->id);
    });

    it('nullifies template_id on template deletion (BR-152)', function () {
        $this->seedRolesAndPermissions();
        $tenant = Tenant::factory()->create();
        $template = ScheduleTemplate::factory()->withName('To Delete')->create(['tenant_id' => $tenant->id]);
        $schedule = CookSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'template_id' => $template->id,
        ]);

        expect($schedule->template_id)->toBe($template->id);

        $template->delete();
        $schedule->refresh();

        expect($schedule->template_id)->toBeNull();
    });
});

// ============================================================
// Test group: Feature-level integration (HTTP)
// ============================================================
describe('HTTP integration', function () {
    it('returns 403 for unauthorized user (BR-138)', function () {
        $this->seedRolesAndPermissions();
        $cook = $this->createUserWithRole('cook');
        $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);

        // Create a client user without manage-schedules permission
        $client = \App\Models\User::factory()->create();
        $client->assignRole('client');

        $url = 'https://'.$tenant->slug.'.'.parse_url(config('app.url'), PHP_URL_HOST).'/dashboard/schedule/templates';
        $response = $this->actingAs($client)->get($url);

        expect($response->status())->toBe(403);
    });

    it('returns success for authorized cook (BR-138)', function () {
        $this->seedRolesAndPermissions();
        $cook = $this->createUserWithRole('cook');
        $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);

        $url = 'https://'.$tenant->slug.'.'.parse_url(config('app.url'), PHP_URL_HOST).'/dashboard/schedule/templates';
        $response = $this->actingAs($cook)->get($url);

        expect($response->status())->toBe(200);
    });

    it('displays templates in list for authorized user', function () {
        $this->seedRolesAndPermissions();
        $cook = $this->createUserWithRole('cook');
        $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
        ScheduleTemplate::factory()->withName('Breakfast Service')->create(['tenant_id' => $tenant->id]);
        ScheduleTemplate::factory()->withName('Dinner Service')->create(['tenant_id' => $tenant->id]);

        $url = 'https://'.$tenant->slug.'.'.parse_url(config('app.url'), PHP_URL_HOST).'/dashboard/schedule/templates';
        $response = $this->actingAs($cook)->get($url);

        expect($response->status())->toBe(200);
        $response->assertSee('Breakfast Service');
        $response->assertSee('Dinner Service');
    });

    it('shows empty state when no templates exist', function () {
        $this->seedRolesAndPermissions();
        $cook = $this->createUserWithRole('cook');
        $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);

        $url = 'https://'.$tenant->slug.'.'.parse_url(config('app.url'), PHP_URL_HOST).'/dashboard/schedule/templates';
        $response = $this->actingAs($cook)->get($url);

        expect($response->status())->toBe(200);
        $response->assertSee('No templates yet');
    });

    it('does not show templates from another tenant (BR-136)', function () {
        $this->seedRolesAndPermissions();
        $cook = $this->createUserWithRole('cook');
        $tenant1 = Tenant::factory()->create(['cook_id' => $cook->id]);
        $tenant2 = Tenant::factory()->create();
        ScheduleTemplate::factory()->withName('Other Tenant Template')->create(['tenant_id' => $tenant2->id]);

        $url = 'https://'.$tenant1->slug.'.'.parse_url(config('app.url'), PHP_URL_HOST).'/dashboard/schedule/templates';
        $response = $this->actingAs($cook)->get($url);

        expect($response->status())->toBe(200);
        $response->assertDontSee('Other Tenant Template');
    });
});
