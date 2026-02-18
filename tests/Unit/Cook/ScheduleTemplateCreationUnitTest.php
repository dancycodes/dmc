<?php

/**
 * F-101: Create Schedule Template -- Unit Tests
 *
 * Tests for ScheduleTemplateController, StoreScheduleTemplateRequest,
 * ScheduleTemplateService, ScheduleTemplate model, blade view,
 * route configuration, and translation strings.
 *
 * BR-127: Unique name within tenant
 * BR-128: Name required, max 100 chars
 * BR-129: Order interval required
 * BR-130: At least one of delivery/pickup required
 * BR-131: Time interval validations from F-099/F-100 apply
 * BR-132: Tenant-scoped
 * BR-133: Only users with can-manage-schedules permission
 * BR-134: Template creation logged via Spatie Activitylog
 * BR-135: Templates are independent entities
 */

use App\Http\Controllers\Cook\ScheduleTemplateController;
use App\Http\Requests\Cook\StoreScheduleTemplateRequest;
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
    it('has a create method', function () {
        $reflection = new ReflectionMethod(ScheduleTemplateController::class, 'create');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has a store method', function () {
        $reflection = new ReflectionMethod(ScheduleTemplateController::class, 'store');
        expect($reflection->isPublic())->toBeTrue();
    });
});

// ============================================================
// Test group: StoreScheduleTemplateRequest
// ============================================================
describe('StoreScheduleTemplateRequest', function () {
    it('has proper validation rules', function () {
        $request = new StoreScheduleTemplateRequest;
        $rules = $request->rules();

        expect($rules)->toHaveKeys([
            'name',
            'order_start_time',
            'order_start_day_offset',
            'order_end_time',
            'order_end_day_offset',
            'delivery_enabled',
            'delivery_start_time',
            'delivery_end_time',
            'pickup_enabled',
            'pickup_start_time',
            'pickup_end_time',
        ]);
    });

    it('requires name field', function () {
        $request = new StoreScheduleTemplateRequest;
        $rules = $request->rules();

        expect($rules['name'])->toContain('required');
    });

    it('limits name to 100 characters', function () {
        $request = new StoreScheduleTemplateRequest;
        $rules = $request->rules();

        expect($rules['name'])->toContain('max:100');
    });

    it('requires order start time', function () {
        $request = new StoreScheduleTemplateRequest;
        $rules = $request->rules();

        expect($rules['order_start_time'])->toContain('required');
    });

    it('requires order end time', function () {
        $request = new StoreScheduleTemplateRequest;
        $rules = $request->rules();

        expect($rules['order_end_time'])->toContain('required');
    });

    it('validates time format with ValidTimeFormat rule', function () {
        $request = new StoreScheduleTemplateRequest;
        $rules = $request->rules();

        $hasStartRule = collect($rules['order_start_time'])
            ->contains(fn ($rule) => $rule instanceof \App\Rules\ValidTimeFormat);
        $hasEndRule = collect($rules['order_end_time'])
            ->contains(fn ($rule) => $rule instanceof \App\Rules\ValidTimeFormat);
        expect($hasStartRule)->toBeTrue();
        expect($hasEndRule)->toBeTrue();
    });

    it('limits start day offset to CookSchedule MAX_START_DAY_OFFSET', function () {
        $request = new StoreScheduleTemplateRequest;
        $rules = $request->rules();

        expect($rules['order_start_day_offset'])->toContain('max:'.CookSchedule::MAX_START_DAY_OFFSET);
    });

    it('limits end day offset to CookSchedule MAX_END_DAY_OFFSET', function () {
        $request = new StoreScheduleTemplateRequest;
        $rules = $request->rules();

        expect($rules['order_end_day_offset'])->toContain('max:'.CookSchedule::MAX_END_DAY_OFFSET);
    });

    it('has custom error messages', function () {
        $request = new StoreScheduleTemplateRequest;
        $messages = $request->messages();

        expect($messages)->toHaveKeys([
            'name.required',
            'name.max',
            'order_start_time.required',
            'order_end_time.required',
        ]);
    });

    it('authorizes only users with can-manage-schedules permission', function () {
        $user = test()->createUserWithRole('cook');

        $request = StoreScheduleTemplateRequest::create('/test', 'POST');
        $request->setUserResolver(fn () => $user);

        expect($request->authorize())->toBeTrue();
    });

    it('denies users without can-manage-schedules permission', function () {
        $user = test()->createUserWithRole('client');

        $request = StoreScheduleTemplateRequest::create('/test', 'POST');
        $request->setUserResolver(fn () => $user);

        expect($request->authorize())->toBeFalse();
    });
});

// ============================================================
// Test group: ScheduleTemplate model
// ============================================================
describe('ScheduleTemplate model', function () {
    it('has correct fillable fields', function () {
        $template = new ScheduleTemplate;

        expect($template->getFillable())->toContain('tenant_id')
            ->toContain('name')
            ->toContain('order_start_time')
            ->toContain('order_start_day_offset')
            ->toContain('order_end_time')
            ->toContain('order_end_day_offset')
            ->toContain('delivery_enabled')
            ->toContain('delivery_start_time')
            ->toContain('delivery_end_time')
            ->toContain('pickup_enabled')
            ->toContain('pickup_start_time')
            ->toContain('pickup_end_time');
    });

    it('casts delivery_enabled to boolean', function () {
        $template = ScheduleTemplate::factory()->create();

        expect($template->delivery_enabled)->toBeBool();
    });

    it('casts pickup_enabled to boolean', function () {
        $template = ScheduleTemplate::factory()->create();

        expect($template->pickup_enabled)->toBeBool();
    });

    it('casts order_start_day_offset to integer', function () {
        $template = ScheduleTemplate::factory()->create();

        expect($template->order_start_day_offset)->toBeInt();
    });

    it('casts order_end_day_offset to integer', function () {
        $template = ScheduleTemplate::factory()->create();

        expect($template->order_end_day_offset)->toBeInt();
    });

    it('belongs to a tenant', function () {
        $template = ScheduleTemplate::factory()->create();

        expect($template->tenant)->toBeInstanceOf(Tenant::class);
    });

    it('uses schedule_templates table', function () {
        $template = new ScheduleTemplate;

        expect($template->getTable())->toBe('schedule_templates');
    });

    it('has forTenant scope', function () {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();

        ScheduleTemplate::factory()->withName('Template A')->create(['tenant_id' => $tenant->id]);
        ScheduleTemplate::factory()->withName('Template B')->create(['tenant_id' => $otherTenant->id]);

        $results = ScheduleTemplate::query()->forTenant($tenant->id)->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->name)->toBe('Template A');
    });

    it('generates order interval summary', function () {
        $template = ScheduleTemplate::factory()->create([
            'order_start_time' => '18:00',
            'order_start_day_offset' => 1,
            'order_end_time' => '08:00',
            'order_end_day_offset' => 0,
        ]);

        $summary = $template->order_interval_summary;

        expect($summary)->toContain('6:00 PM');
        expect($summary)->toContain('8:00 AM');
    });

    it('generates delivery interval summary when enabled', function () {
        $template = ScheduleTemplate::factory()->create([
            'delivery_enabled' => true,
            'delivery_start_time' => '11:00',
            'delivery_end_time' => '14:00',
        ]);

        expect($template->delivery_interval_summary)->toContain('11:00 AM');
        expect($template->delivery_interval_summary)->toContain('2:00 PM');
    });

    it('returns null delivery summary when disabled', function () {
        $template = ScheduleTemplate::factory()->deliveryOnly()->create([
            'delivery_enabled' => false,
        ]);

        expect($template->delivery_interval_summary)->toBeNull();
    });

    it('generates pickup interval summary when enabled', function () {
        $template = ScheduleTemplate::factory()->create([
            'pickup_enabled' => true,
            'pickup_start_time' => '10:30',
            'pickup_end_time' => '15:00',
        ]);

        expect($template->pickup_interval_summary)->toContain('10:30 AM');
        expect($template->pickup_interval_summary)->toContain('3:00 PM');
    });

    it('returns null pickup summary when disabled', function () {
        $template = ScheduleTemplate::factory()->pickupOnly()->create([
            'pickup_enabled' => false,
        ]);

        expect($template->pickup_interval_summary)->toBeNull();
    });

    it('calculates order end time in minutes for same day', function () {
        $template = ScheduleTemplate::factory()->create([
            'order_end_time' => '10:00',
            'order_end_day_offset' => 0,
        ]);

        expect($template->getOrderEndTimeInMinutes())->toBe(600);
    });

    it('returns 0 for order end time when offset > 0', function () {
        $template = ScheduleTemplate::factory()->create([
            'order_end_time' => '10:00',
            'order_end_day_offset' => 1,
        ]);

        expect($template->getOrderEndTimeInMinutes())->toBe(0);
    });
});

// ============================================================
// Test group: ScheduleTemplateService
// ============================================================
describe('ScheduleTemplateService', function () {
    it('creates a template successfully', function () {
        $tenant = Tenant::factory()->create();

        $service = app(ScheduleTemplateService::class);
        $result = $service->createTemplate(
            $tenant,
            'Lunch Service',
            '18:00', 1, '08:00', 0,
            true, '11:00', '14:00',
            true, '10:30', '15:00',
        );

        expect($result['success'])->toBeTrue();
        expect($result['template'])->toBeInstanceOf(ScheduleTemplate::class);
        expect($result['template']->name)->toBe('Lunch Service');
        expect($result['template']->tenant_id)->toBe($tenant->id);
    });

    it('trims template name before saving', function () {
        $tenant = Tenant::factory()->create();

        $service = app(ScheduleTemplateService::class);
        $result = $service->createTemplate(
            $tenant,
            '  Lunch Service  ',
            '18:00', 1, '08:00', 0,
            true, '11:00', '14:00',
            true, '10:30', '15:00',
        );

        expect($result['success'])->toBeTrue();
        expect($result['template']->name)->toBe('Lunch Service');
    });

    it('rejects duplicate template name within same tenant', function () {
        $tenant = Tenant::factory()->create();

        $service = app(ScheduleTemplateService::class);

        // Create first template
        $service->createTemplate(
            $tenant,
            'Lunch Service',
            '18:00', 1, '08:00', 0,
            true, '11:00', '14:00',
            true, '10:30', '15:00',
        );

        // Try to create duplicate
        $result = $service->createTemplate(
            $tenant,
            'Lunch Service',
            '06:00', 0, '10:00', 0,
            true, '11:00', '14:00',
            true, '10:30', '15:00',
        );

        expect($result['success'])->toBeFalse();
        expect($result['field'])->toBe('name');
    });

    it('rejects duplicate name case-insensitively', function () {
        $tenant = Tenant::factory()->create();

        $service = app(ScheduleTemplateService::class);

        $service->createTemplate(
            $tenant,
            'Lunch Service',
            '18:00', 1, '08:00', 0,
            true, '11:00', '14:00',
            true, '10:30', '15:00',
        );

        $result = $service->createTemplate(
            $tenant,
            'lunch service',
            '06:00', 0, '10:00', 0,
            true, '11:00', '14:00',
            true, '10:30', '15:00',
        );

        expect($result['success'])->toBeFalse();
    });

    it('allows same template name in different tenants', function () {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        $service = app(ScheduleTemplateService::class);

        $result1 = $service->createTemplate(
            $tenant1,
            'Lunch Service',
            '18:00', 1, '08:00', 0,
            true, '11:00', '14:00',
            true, '10:30', '15:00',
        );

        $result2 = $service->createTemplate(
            $tenant2,
            'Lunch Service',
            '18:00', 1, '08:00', 0,
            true, '11:00', '14:00',
            true, '10:30', '15:00',
        );

        expect($result1['success'])->toBeTrue();
        expect($result2['success'])->toBeTrue();
    });

    it('rejects when both delivery and pickup are disabled', function () {
        $tenant = Tenant::factory()->create();

        $service = app(ScheduleTemplateService::class);
        $result = $service->createTemplate(
            $tenant,
            'No Service',
            '18:00', 1, '08:00', 0,
            false, null, null,
            false, null, null,
        );

        expect($result['success'])->toBeFalse();
        expect($result['field'])->toBe('delivery_enabled');
    });

    it('rejects invalid chronological order interval', function () {
        $tenant = Tenant::factory()->create();

        $service = app(ScheduleTemplateService::class);
        // Start AFTER end on same day
        $result = $service->createTemplate(
            $tenant,
            'Bad Order',
            '14:00', 0, '08:00', 0,
            true, '15:00', '18:00',
            false, null, null,
        );

        expect($result['success'])->toBeFalse();
        expect($result['field'])->toBe('order_start_time');
    });

    it('rejects delivery start before order end', function () {
        $tenant = Tenant::factory()->create();

        $service = app(ScheduleTemplateService::class);
        $result = $service->createTemplate(
            $tenant,
            'Early Delivery',
            '06:00', 0, '10:00', 0,
            true, '09:00', '14:00', // delivery start 09:00 < order end 10:00
            false, null, null,
        );

        expect($result['success'])->toBeFalse();
        expect($result['field'])->toBe('delivery_start_time');
    });

    it('rejects pickup start before order end', function () {
        $tenant = Tenant::factory()->create();

        $service = app(ScheduleTemplateService::class);
        $result = $service->createTemplate(
            $tenant,
            'Early Pickup',
            '06:00', 0, '10:00', 0,
            false, null, null,
            true, '09:00', '14:00', // pickup start 09:00 < order end 10:00
        );

        expect($result['success'])->toBeFalse();
        expect($result['field'])->toBe('pickup_start_time');
    });

    it('rejects delivery end before delivery start', function () {
        $tenant = Tenant::factory()->create();

        $service = app(ScheduleTemplateService::class);
        $result = $service->createTemplate(
            $tenant,
            'Bad Delivery',
            '06:00', 0, '10:00', 0,
            true, '14:00', '11:00', // end before start
            false, null, null,
        );

        expect($result['success'])->toBeFalse();
        expect($result['field'])->toBe('delivery_end_time');
    });

    it('rejects pickup end before pickup start', function () {
        $tenant = Tenant::factory()->create();

        $service = app(ScheduleTemplateService::class);
        $result = $service->createTemplate(
            $tenant,
            'Bad Pickup',
            '06:00', 0, '10:00', 0,
            false, null, null,
            true, '14:00', '11:00', // end before start
        );

        expect($result['success'])->toBeFalse();
        expect($result['field'])->toBe('pickup_end_time');
    });

    it('requires delivery times when delivery enabled', function () {
        $tenant = Tenant::factory()->create();

        $service = app(ScheduleTemplateService::class);
        $result = $service->createTemplate(
            $tenant,
            'Missing Times',
            '18:00', 1, '08:00', 0,
            true, null, null, // enabled but no times
            false, null, null,
        );

        expect($result['success'])->toBeFalse();
        expect($result['field'])->toBe('delivery_start_time');
    });

    it('requires pickup times when pickup enabled', function () {
        $tenant = Tenant::factory()->create();

        $service = app(ScheduleTemplateService::class);
        $result = $service->createTemplate(
            $tenant,
            'Missing Pickup Times',
            '18:00', 1, '08:00', 0,
            false, null, null,
            true, null, null, // enabled but no times
        );

        expect($result['success'])->toBeFalse();
        expect($result['field'])->toBe('pickup_start_time');
    });

    it('clears delivery times when delivery disabled', function () {
        $tenant = Tenant::factory()->create();

        $service = app(ScheduleTemplateService::class);
        $result = $service->createTemplate(
            $tenant,
            'Pickup Only',
            '18:00', 1, '08:00', 0,
            false, null, null,
            true, '10:30', '15:00',
        );

        expect($result['success'])->toBeTrue();
        expect($result['template']->delivery_enabled)->toBeFalse();
        expect($result['template']->delivery_start_time)->toBeNull();
        expect($result['template']->delivery_end_time)->toBeNull();
    });

    it('retrieves templates for a tenant ordered by name', function () {
        $tenant = Tenant::factory()->create();

        ScheduleTemplate::factory()->withName('Dinner Service')->create(['tenant_id' => $tenant->id]);
        ScheduleTemplate::factory()->withName('Breakfast Service')->create(['tenant_id' => $tenant->id]);
        ScheduleTemplate::factory()->withName('Lunch Service')->create(['tenant_id' => $tenant->id]);

        $service = app(ScheduleTemplateService::class);
        $templates = $service->getTemplatesForTenant($tenant);

        expect($templates)->toHaveCount(3);
        expect($templates->first()->name)->toBe('Breakfast Service');
        expect($templates->last()->name)->toBe('Lunch Service');
    });

    it('returns correct template count', function () {
        $tenant = Tenant::factory()->create();

        ScheduleTemplate::factory()->withName('Template A')->create(['tenant_id' => $tenant->id]);
        ScheduleTemplate::factory()->withName('Template B')->create(['tenant_id' => $tenant->id]);

        $service = app(ScheduleTemplateService::class);

        expect($service->getTemplateCount($tenant))->toBe(2);
    });

    it('creates delivery-only template', function () {
        $tenant = Tenant::factory()->create();

        $service = app(ScheduleTemplateService::class);
        $result = $service->createTemplate(
            $tenant,
            'Delivery Only',
            '18:00', 1, '08:00', 0,
            true, '11:00', '14:00',
            false, null, null,
        );

        expect($result['success'])->toBeTrue();
        expect($result['template']->delivery_enabled)->toBeTrue();
        expect($result['template']->pickup_enabled)->toBeFalse();
    });

    it('creates pickup-only template', function () {
        $tenant = Tenant::factory()->create();

        $service = app(ScheduleTemplateService::class);
        $result = $service->createTemplate(
            $tenant,
            'Pickup Only',
            '18:00', 1, '08:00', 0,
            false, null, null,
            true, '10:30', '15:00',
        );

        expect($result['success'])->toBeTrue();
        expect($result['template']->delivery_enabled)->toBeFalse();
        expect($result['template']->pickup_enabled)->toBeTrue();
    });
});

// ============================================================
// Test group: ScheduleTemplate factory
// ============================================================
describe('ScheduleTemplateFactory', function () {
    it('creates a template with default values', function () {
        $template = ScheduleTemplate::factory()->create();

        expect($template->name)->not->toBeEmpty();
        expect($template->order_start_time)->not->toBeNull();
        expect($template->order_end_time)->not->toBeNull();
        expect($template->delivery_enabled)->toBeBool();
        expect($template->pickup_enabled)->toBeBool();
    });

    it('has deliveryOnly state', function () {
        $template = ScheduleTemplate::factory()->deliveryOnly()->create();

        expect($template->delivery_enabled)->toBeTrue();
        expect($template->pickup_enabled)->toBeFalse();
        expect($template->pickup_start_time)->toBeNull();
    });

    it('has pickupOnly state', function () {
        $template = ScheduleTemplate::factory()->pickupOnly()->create();

        expect($template->delivery_enabled)->toBeFalse();
        expect($template->pickup_enabled)->toBeTrue();
        expect($template->delivery_start_time)->toBeNull();
    });

    it('has sameDayOrders state', function () {
        $template = ScheduleTemplate::factory()->sameDayOrders()->create();

        expect($template->order_start_day_offset)->toBe(0);
        expect($template->order_end_day_offset)->toBe(0);
    });

    it('has withName state', function () {
        $template = ScheduleTemplate::factory()->withName('Custom Name')->create();

        expect($template->name)->toBe('Custom Name');
    });
});

// ============================================================
// Test group: Route configuration
// ============================================================
describe('Route configuration', function () {
    it('has schedule template create route', function () {
        $route = app('router')->getRoutes()->getByName('cook.schedule-templates.create');

        expect($route)->not->toBeNull();
        expect($route->methods())->toContain('GET');
        expect($route->uri())->toContain('schedule/templates/create');
    });

    it('has schedule template store route', function () {
        $route = app('router')->getRoutes()->getByName('cook.schedule-templates.store');

        expect($route)->not->toBeNull();
        expect($route->methods())->toContain('POST');
        expect($route->uri())->toContain('schedule/templates');
    });
});

// ============================================================
// Test group: Blade view
// ============================================================
describe('Blade view', function () use ($projectRoot) {
    it('exists at cook/schedule/templates/create.blade.php', function () use ($projectRoot) {
        expect(file_exists($projectRoot.'/resources/views/cook/schedule/templates/create.blade.php'))->toBeTrue();
    });

    it('extends cook-dashboard layout', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/create.blade.php');

        expect($content)->toContain("@extends('layouts.cook-dashboard')");
    });

    it('uses x-data with Alpine state', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/create.blade.php');

        expect($content)->toContain('x-data=');
        expect($content)->toContain('x-sync=');
    });

    it('uses gale $action for form submission', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/create.blade.php');

        expect($content)->toContain('$action(');
    });

    it('uses x-message for validation errors', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/create.blade.php');

        expect($content)->toContain('x-message="name"');
        expect($content)->toContain('x-message="order_start_time"');
        expect($content)->toContain('x-message="delivery_enabled"');
    });

    it('uses $fetching() for loading states', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/create.blade.php');

        expect($content)->toContain('$fetching()');
    });

    it('uses __() for all user-facing strings', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/create.blade.php');

        expect($content)->toContain("__('Template Name')");
        expect($content)->toContain("__('Create Template')");
        expect($content)->toContain("__('Order Interval')");
    });

    it('has delivery toggle switch', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/create.blade.php');

        expect($content)->toContain('delivery_enabled');
        expect($content)->toContain('role="switch"');
    });

    it('has pickup toggle switch', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/create.blade.php');

        expect($content)->toContain('pickup_enabled');
    });

    it('has template summary preview', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/create.blade.php');

        expect($content)->toContain("__('Template Summary')");
        expect($content)->toContain('getOrderPreview()');
    });

    it('has both delivery and pickup disabled warning', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/create.blade.php');

        expect($content)->toContain("delivery_enabled === 'false' && pickup_enabled === 'false'");
    });

    it('has breadcrumb navigation', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/schedule/templates/create.blade.php');

        expect($content)->toContain("__('Dashboard')");
        expect($content)->toContain("__('Schedule')");
        expect($content)->toContain("__('Templates')");
    });
});

// ============================================================
// Test group: Translation strings
// ============================================================
describe('Translation strings', function () use ($projectRoot) {
    it('has English translations for F-101 strings', function () use ($projectRoot) {
        $en = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);

        expect($en)->toHaveKey('Schedule Templates');
        expect($en)->toHaveKey('Create Template');
        expect($en)->toHaveKey('Template Name');
        expect($en)->toHaveKey('Template name is required.');
        expect($en)->toHaveKey('A template with this name already exists.');
        expect($en)->toHaveKey('Schedule template created successfully.');
        expect($en)->toHaveKey('Existing Templates');
    });

    it('has French translations for F-101 strings', function () use ($projectRoot) {
        $fr = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);

        expect($fr)->toHaveKey('Schedule Templates');
        expect($fr)->toHaveKey('Create Template');
        expect($fr)->toHaveKey('Template Name');
        expect($fr)->toHaveKey('Template name is required.');
        expect($fr)->toHaveKey('A template with this name already exists.');
        expect($fr)->toHaveKey('Schedule template created successfully.');
        expect($fr)->toHaveKey('Existing Templates');
    });
});

// ============================================================
// Test group: Controller access control
// ============================================================
describe('Controller access control', function () {
    it('returns 403 for users without can-manage-schedules permission on create', function () {
        $tenant = Tenant::factory()->create();
        $user = test()->createUserWithRole('client');

        $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

        $this->actingAs($user)
            ->get("https://{$tenant->slug}.{$mainDomain}/dashboard/schedule/templates/create")
            ->assertForbidden();
    });

    it('allows cook to access create form', function () {
        $cook = test()->createUserWithRole('cook');
        $tenant = Tenant::factory()->withCook($cook->id)->create();

        $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

        $this->actingAs($cook)
            ->get("https://{$tenant->slug}.{$mainDomain}/dashboard/schedule/templates/create")
            ->assertSuccessful();
    });
});

// ============================================================
// Test group: Activity logging
// ============================================================
describe('Activity logging', function () {
    it('uses LogsActivityTrait', function () {
        $traits = class_uses_recursive(ScheduleTemplate::class);

        expect($traits)->toContain(\App\Traits\LogsActivityTrait::class);
    });
});

// ============================================================
// Test group: Migration
// ============================================================
describe('Migration', function () {
    it('created schedule_templates table', function () {
        expect(\Illuminate\Support\Facades\Schema::hasTable('schedule_templates'))->toBeTrue();
    });

    it('has required columns', function () {
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('schedule_templates');

        expect($columns)->toContain('id');
        expect($columns)->toContain('tenant_id');
        expect($columns)->toContain('name');
        expect($columns)->toContain('order_start_time');
        expect($columns)->toContain('order_start_day_offset');
        expect($columns)->toContain('order_end_time');
        expect($columns)->toContain('order_end_day_offset');
        expect($columns)->toContain('delivery_enabled');
        expect($columns)->toContain('delivery_start_time');
        expect($columns)->toContain('delivery_end_time');
        expect($columns)->toContain('pickup_enabled');
        expect($columns)->toContain('pickup_start_time');
        expect($columns)->toContain('pickup_end_time');
        expect($columns)->toContain('created_at');
        expect($columns)->toContain('updated_at');
    });

    it('enforces unique name per tenant', function () {
        $tenant = Tenant::factory()->create();

        ScheduleTemplate::factory()->withName('Lunch Service')->create(['tenant_id' => $tenant->id]);

        expect(function () use ($tenant) {
            ScheduleTemplate::factory()->withName('Lunch Service')->create(['tenant_id' => $tenant->id]);
        })->toThrow(\Illuminate\Database\QueryException::class);
    });
});
