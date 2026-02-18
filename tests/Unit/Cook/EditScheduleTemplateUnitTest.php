<?php

/**
 * F-103: Edit Schedule Template -- Unit Tests
 *
 * Tests for ScheduleTemplateController edit/update methods,
 * UpdateScheduleTemplateRequest, ScheduleTemplateService updateTemplate,
 * blade view, route configuration, and translation strings.
 *
 * BR-140: All validation rules from F-099 and F-100 apply to template edits
 * BR-141: Template name must remain unique within the tenant
 * BR-142: Editing a template does NOT propagate changes to day schedules
 * BR-143: To update day schedules with new template values, re-apply via F-105
 * BR-144: Template edits are logged via Spatie Activitylog
 * BR-145: Only users with can-manage-schedules permission can edit
 */

use App\Http\Controllers\Cook\ScheduleTemplateController;
use App\Http\Requests\Cook\UpdateScheduleTemplateRequest;
use App\Models\CookSchedule;
use App\Models\ScheduleTemplate;
use App\Models\Tenant;
use App\Services\ScheduleTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;

$projectRoot = dirname(__DIR__, 3);

uses(Tests\TestCase::class, RefreshDatabase::class);

// ============================================================
// Test group: ScheduleTemplateController edit/update methods
// ============================================================
describe('ScheduleTemplateController edit/update', function () {
    it('has an edit method', function () {
        $reflection = new ReflectionMethod(ScheduleTemplateController::class, 'edit');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has an update method', function () {
        $reflection = new ReflectionMethod(ScheduleTemplateController::class, 'update');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('edit method accepts ScheduleTemplate parameter', function () {
        $reflection = new ReflectionMethod(ScheduleTemplateController::class, 'edit');
        $params = $reflection->getParameters();

        $paramNames = array_map(fn ($p) => $p->getName(), $params);
        expect($paramNames)->toContain('scheduleTemplate');
    });

    it('update method accepts ScheduleTemplate parameter', function () {
        $reflection = new ReflectionMethod(ScheduleTemplateController::class, 'update');
        $params = $reflection->getParameters();

        $paramNames = array_map(fn ($p) => $p->getName(), $params);
        expect($paramNames)->toContain('scheduleTemplate');
    });
});

// ============================================================
// Test group: UpdateScheduleTemplateRequest
// ============================================================
describe('UpdateScheduleTemplateRequest', function () {
    it('has proper validation rules matching store request', function () {
        $request = new UpdateScheduleTemplateRequest;
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
        $request = new UpdateScheduleTemplateRequest;
        $rules = $request->rules();

        expect($rules['name'])->toContain('required');
    });

    it('limits name to 100 characters', function () {
        $request = new UpdateScheduleTemplateRequest;
        $rules = $request->rules();

        expect($rules['name'])->toContain('max:100');
    });

    it('requires order start time', function () {
        $request = new UpdateScheduleTemplateRequest;
        $rules = $request->rules();

        expect($rules['order_start_time'])->toContain('required');
    });

    it('requires order end time', function () {
        $request = new UpdateScheduleTemplateRequest;
        $rules = $request->rules();

        expect($rules['order_end_time'])->toContain('required');
    });

    it('validates time format with ValidTimeFormat rule', function () {
        $request = new UpdateScheduleTemplateRequest;
        $rules = $request->rules();

        $hasStartRule = collect($rules['order_start_time'])
            ->contains(fn ($rule) => $rule instanceof \App\Rules\ValidTimeFormat);
        $hasEndRule = collect($rules['order_end_time'])
            ->contains(fn ($rule) => $rule instanceof \App\Rules\ValidTimeFormat);
        expect($hasStartRule)->toBeTrue();
        expect($hasEndRule)->toBeTrue();
    });

    it('limits start day offset to CookSchedule MAX_START_DAY_OFFSET', function () {
        $request = new UpdateScheduleTemplateRequest;
        $rules = $request->rules();

        expect($rules['order_start_day_offset'])->toContain('max:'.CookSchedule::MAX_START_DAY_OFFSET);
    });

    it('limits end day offset to CookSchedule MAX_END_DAY_OFFSET', function () {
        $request = new UpdateScheduleTemplateRequest;
        $rules = $request->rules();

        expect($rules['order_end_day_offset'])->toContain('max:'.CookSchedule::MAX_END_DAY_OFFSET);
    });

    it('has custom error messages', function () {
        $request = new UpdateScheduleTemplateRequest;
        $messages = $request->messages();

        expect($messages)->toHaveKeys([
            'name.required',
            'name.max',
            'order_start_time.required',
            'order_end_time.required',
            'delivery_enabled.required',
            'pickup_enabled.required',
        ]);
    });

    it('authorizes users with can-manage-schedules permission', function () {
        $user = test()->createUserWithRole('cook');

        $request = UpdateScheduleTemplateRequest::create('/test', 'PUT');
        $request->setUserResolver(fn () => $user);

        expect($request->authorize())->toBeTrue();
    });

    it('denies users without can-manage-schedules permission', function () {
        $user = test()->createUserWithRole('client');

        $request = UpdateScheduleTemplateRequest::create('/test', 'PUT');
        $request->setUserResolver(fn () => $user);

        expect($request->authorize())->toBeFalse();
    });
});

// ============================================================
// Test group: ScheduleTemplateService updateTemplate
// ============================================================
describe('ScheduleTemplateService updateTemplate', function () {
    it('updates template name successfully', function () {
        $template = ScheduleTemplate::factory()->create(['name' => 'Lunch Service']);
        $service = app(ScheduleTemplateService::class);

        $result = $service->updateTemplate(
            $template,
            'Weekday Lunch',
            $template->order_start_time,
            $template->order_start_day_offset,
            $template->order_end_time,
            $template->order_end_day_offset,
            $template->delivery_enabled,
            $template->delivery_start_time,
            $template->delivery_end_time,
            $template->pickup_enabled,
            $template->pickup_start_time,
            $template->pickup_end_time,
        );

        expect($result['success'])->toBeTrue();
        expect($result['template']->name)->toBe('Weekday Lunch');
    });

    it('rejects duplicate name within same tenant', function () {
        $tenant = Tenant::factory()->create();
        ScheduleTemplate::factory()->withName('Dinner Service')->create(['tenant_id' => $tenant->id]);
        $template = ScheduleTemplate::factory()->withName('Lunch Service')->create(['tenant_id' => $tenant->id]);

        $service = app(ScheduleTemplateService::class);

        $result = $service->updateTemplate(
            $template,
            'Dinner Service',
            $template->order_start_time,
            $template->order_start_day_offset,
            $template->order_end_time,
            $template->order_end_day_offset,
            $template->delivery_enabled,
            $template->delivery_start_time,
            $template->delivery_end_time,
            $template->pickup_enabled,
            $template->pickup_start_time,
            $template->pickup_end_time,
        );

        expect($result['success'])->toBeFalse();
        expect($result['field'])->toBe('name');
    });

    it('allows same name if it is the current template name (no-op)', function () {
        $template = ScheduleTemplate::factory()->create(['name' => 'Lunch Service']);
        $service = app(ScheduleTemplateService::class);

        $result = $service->updateTemplate(
            $template,
            'Lunch Service',
            $template->order_start_time,
            $template->order_start_day_offset,
            $template->order_end_time,
            $template->order_end_day_offset,
            $template->delivery_enabled,
            $template->delivery_start_time,
            $template->delivery_end_time,
            $template->pickup_enabled,
            $template->pickup_start_time,
            $template->pickup_end_time,
        );

        expect($result['success'])->toBeTrue();
    });

    it('allows same name from different tenant', function () {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        ScheduleTemplate::factory()->withName('Lunch Service')->create(['tenant_id' => $tenant1->id]);
        $template = ScheduleTemplate::factory()->withName('Dinner Service')->create(['tenant_id' => $tenant2->id]);

        $service = app(ScheduleTemplateService::class);

        $result = $service->updateTemplate(
            $template,
            'Lunch Service',
            $template->order_start_time,
            $template->order_start_day_offset,
            $template->order_end_time,
            $template->order_end_day_offset,
            $template->delivery_enabled,
            $template->delivery_start_time,
            $template->delivery_end_time,
            $template->pickup_enabled,
            $template->pickup_start_time,
            $template->pickup_end_time,
        );

        expect($result['success'])->toBeTrue();
    });

    it('rejects duplicate name case-insensitively', function () {
        $tenant = Tenant::factory()->create();
        ScheduleTemplate::factory()->withName('Dinner Service')->create(['tenant_id' => $tenant->id]);
        $template = ScheduleTemplate::factory()->withName('Lunch Service')->create(['tenant_id' => $tenant->id]);

        $service = app(ScheduleTemplateService::class);

        $result = $service->updateTemplate(
            $template,
            'dinner service',
            $template->order_start_time,
            $template->order_start_day_offset,
            $template->order_end_time,
            $template->order_end_day_offset,
            $template->delivery_enabled,
            $template->delivery_start_time,
            $template->delivery_end_time,
            $template->pickup_enabled,
            $template->pickup_start_time,
            $template->pickup_end_time,
        );

        expect($result['success'])->toBeFalse();
    });

    it('rejects invalid order interval chronology', function () {
        $template = ScheduleTemplate::factory()->create();
        $service = app(ScheduleTemplateService::class);

        $result = $service->updateTemplate(
            $template,
            $template->name,
            '10:00',
            0,
            '08:00',
            0,
            $template->delivery_enabled,
            $template->delivery_start_time,
            $template->delivery_end_time,
            $template->pickup_enabled,
            $template->pickup_start_time,
            $template->pickup_end_time,
        );

        expect($result['success'])->toBeFalse();
        expect($result['field'])->toBe('order_start_time');
    });

    it('rejects when both delivery and pickup are disabled', function () {
        $template = ScheduleTemplate::factory()->create();
        $service = app(ScheduleTemplateService::class);

        $result = $service->updateTemplate(
            $template,
            $template->name,
            $template->order_start_time,
            $template->order_start_day_offset,
            $template->order_end_time,
            $template->order_end_day_offset,
            false,
            null,
            null,
            false,
            null,
            null,
        );

        expect($result['success'])->toBeFalse();
        expect($result['field'])->toBe('delivery_enabled');
    });

    it('can enable pickup on delivery-only template', function () {
        $template = ScheduleTemplate::factory()->deliveryOnly()->create();
        $service = app(ScheduleTemplateService::class);

        $result = $service->updateTemplate(
            $template,
            $template->name,
            $template->order_start_time,
            $template->order_start_day_offset,
            $template->order_end_time,
            $template->order_end_day_offset,
            true,
            $template->delivery_start_time,
            $template->delivery_end_time,
            true,
            '10:30',
            '15:00',
        );

        expect($result['success'])->toBeTrue();
        expect($result['template']->pickup_enabled)->toBeTrue();
        expect($result['template']->delivery_enabled)->toBeTrue();
    });

    it('can disable delivery and keep pickup only', function () {
        $template = ScheduleTemplate::factory()->create();
        $service = app(ScheduleTemplateService::class);

        $result = $service->updateTemplate(
            $template,
            $template->name,
            $template->order_start_time,
            $template->order_start_day_offset,
            $template->order_end_time,
            $template->order_end_day_offset,
            false,
            null,
            null,
            true,
            '10:30',
            '15:00',
        );

        expect($result['success'])->toBeTrue();
        expect($result['template']->delivery_enabled)->toBeFalse();
        expect($result['template']->delivery_start_time)->toBeNull();
        expect($result['template']->delivery_end_time)->toBeNull();
        expect($result['template']->pickup_enabled)->toBeTrue();
    });

    it('updates order interval times', function () {
        $template = ScheduleTemplate::factory()->create();
        $service = app(ScheduleTemplateService::class);

        $result = $service->updateTemplate(
            $template,
            $template->name,
            '19:00',
            1,
            '09:00',
            0,
            $template->delivery_enabled,
            $template->delivery_start_time,
            $template->delivery_end_time,
            $template->pickup_enabled,
            $template->pickup_start_time,
            $template->pickup_end_time,
        );

        expect($result['success'])->toBeTrue();
        expect($result['template']->order_start_time)->toBe('19:00');
        expect($result['template']->order_end_time)->toBe('09:00');
    });

    it('validates delivery start after order end', function () {
        $template = ScheduleTemplate::factory()->sameDayOrders()->create();
        $service = app(ScheduleTemplateService::class);

        $result = $service->updateTemplate(
            $template,
            $template->name,
            '06:00',
            0,
            '10:00',
            0,
            true,
            '09:00',
            '14:00',
            $template->pickup_enabled,
            $template->pickup_start_time,
            $template->pickup_end_time,
        );

        expect($result['success'])->toBeFalse();
        expect($result['field'])->toBe('delivery_start_time');
    });

    it('validates pickup start after order end', function () {
        $template = ScheduleTemplate::factory()->sameDayOrders()->create();
        $service = app(ScheduleTemplateService::class);

        $result = $service->updateTemplate(
            $template,
            $template->name,
            '06:00',
            0,
            '10:00',
            0,
            $template->delivery_enabled,
            $template->delivery_start_time,
            $template->delivery_end_time,
            true,
            '09:00',
            '14:00',
        );

        expect($result['success'])->toBeFalse();
        expect($result['field'])->toBe('pickup_start_time');
    });

    it('validates delivery end after delivery start', function () {
        $template = ScheduleTemplate::factory()->create();
        $service = app(ScheduleTemplateService::class);

        $result = $service->updateTemplate(
            $template,
            $template->name,
            $template->order_start_time,
            $template->order_start_day_offset,
            $template->order_end_time,
            $template->order_end_day_offset,
            true,
            '14:00',
            '11:00',
            $template->pickup_enabled,
            $template->pickup_start_time,
            $template->pickup_end_time,
        );

        expect($result['success'])->toBeFalse();
        expect($result['field'])->toBe('delivery_end_time');
    });

    it('requires delivery times when delivery is enabled', function () {
        $template = ScheduleTemplate::factory()->create();
        $service = app(ScheduleTemplateService::class);

        $result = $service->updateTemplate(
            $template,
            $template->name,
            $template->order_start_time,
            $template->order_start_day_offset,
            $template->order_end_time,
            $template->order_end_day_offset,
            true,
            null,
            null,
            $template->pickup_enabled,
            $template->pickup_start_time,
            $template->pickup_end_time,
        );

        expect($result['success'])->toBeFalse();
        expect($result['field'])->toBe('delivery_start_time');
    });

    it('trims the template name', function () {
        $template = ScheduleTemplate::factory()->create();
        $service = app(ScheduleTemplateService::class);

        $result = $service->updateTemplate(
            $template,
            '  Lunch Service  ',
            $template->order_start_time,
            $template->order_start_day_offset,
            $template->order_end_time,
            $template->order_end_day_offset,
            $template->delivery_enabled,
            $template->delivery_start_time,
            $template->delivery_end_time,
            $template->pickup_enabled,
            $template->pickup_start_time,
            $template->pickup_end_time,
        );

        expect($result['success'])->toBeTrue();
        expect($result['template']->name)->toBe('Lunch Service');
    });
});

// ============================================================
// Test group: BR-142 — Day schedules NOT propagated
// ============================================================
describe('BR-142 non-propagation', function () {
    it('does not update day schedules when template is edited', function () {
        $tenant = Tenant::factory()->create();
        $template = ScheduleTemplate::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Original Template',
            'order_start_time' => '18:00',
        ]);

        // Simulate a day schedule referencing this template
        $schedule = CookSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'template_id' => $template->id,
            'order_start_time' => '18:00',
            'order_start_day_offset' => 1,
            'order_end_time' => '08:00',
            'order_end_day_offset' => 0,
        ]);

        $service = app(ScheduleTemplateService::class);

        // Update the template order start time
        $result = $service->updateTemplate(
            $template,
            'Updated Template',
            '19:00',
            1,
            '08:00',
            0,
            $template->delivery_enabled,
            $template->delivery_start_time,
            $template->delivery_end_time,
            $template->pickup_enabled,
            $template->pickup_start_time,
            $template->pickup_end_time,
        );

        expect($result['success'])->toBeTrue();
        expect($result['template']->order_start_time)->toBe('19:00');

        // Day schedule should still have original value
        $schedule->refresh();
        expect($schedule->order_start_time)->toContain('18:00');
    });
});

// ============================================================
// Test group: ScheduleTemplateService findTemplateForTenant
// ============================================================
describe('ScheduleTemplateService findTemplateForTenant', function () {
    it('finds a template belonging to the tenant', function () {
        $tenant = Tenant::factory()->create();
        $template = ScheduleTemplate::factory()->create(['tenant_id' => $tenant->id]);

        $service = app(ScheduleTemplateService::class);
        $found = $service->findTemplateForTenant($tenant, $template->id);

        expect($found)->not->toBeNull();
        expect($found->id)->toBe($template->id);
    });

    it('returns null for template from different tenant', function () {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        $template = ScheduleTemplate::factory()->create(['tenant_id' => $tenant1->id]);

        $service = app(ScheduleTemplateService::class);
        $found = $service->findTemplateForTenant($tenant2, $template->id);

        expect($found)->toBeNull();
    });
});

// ============================================================
// Test group: Route configuration
// ============================================================
describe('Route configuration', function () {
    it('has edit route defined', function () {
        $route = route('cook.schedule-templates.edit', ['scheduleTemplate' => 1]);

        expect($route)->toContain('/dashboard/schedule/templates/1/edit');
    });

    it('has update route defined', function () {
        $route = route('cook.schedule-templates.update', ['scheduleTemplate' => 1]);

        expect($route)->toContain('/dashboard/schedule/templates/1');
    });
});

// ============================================================
// Test group: View file
// ============================================================
describe('Edit view', function () {
    it('exists at the expected path', function () {
        $root = dirname(__DIR__, 3);
        expect(file_exists($root.'/resources/views/cook/schedule/templates/edit.blade.php'))->toBeTrue();
    });

    it('extends cook dashboard layout', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/edit.blade.php');

        expect($content)->toContain("@extends('layouts.cook-dashboard')");
    });

    it('includes non-propagation info banner', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/edit.blade.php');

        expect($content)->toContain('Changes will not affect schedules already using this template');
    });

    it('has save and cancel buttons', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/edit.blade.php');

        expect($content)->toContain("__('Save Changes')");
        expect($content)->toContain("__('Cancel')");
    });

    it('uses PUT method for form submission', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/edit.blade.php');

        expect($content)->toContain("method: 'PUT'");
    });

    it('pre-populates form with template data', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/edit.blade.php');

        expect($content)->toContain('$template->name');
        expect($content)->toContain('$template->order_start_time');
        expect($content)->toContain('$template->delivery_enabled');
        expect($content)->toContain('$template->pickup_enabled');
    });

    it('includes order interval section', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/edit.blade.php');

        expect($content)->toContain("__('Order Interval')");
        expect($content)->toContain('order_start_time');
        expect($content)->toContain('order_end_time');
    });

    it('includes delivery section with toggle', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/edit.blade.php');

        expect($content)->toContain("__('Delivery')");
        expect($content)->toContain('delivery_enabled');
    });

    it('includes pickup section with toggle', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/edit.blade.php');

        expect($content)->toContain("__('Pickup')");
        expect($content)->toContain('pickup_enabled');
    });

    it('includes template summary preview', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/edit.blade.php');

        expect($content)->toContain("__('Template Summary')");
    });

    it('uses x-sync for state management', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/edit.blade.php');

        expect($content)->toContain('x-sync=');
    });

    it('uses fetching state for submit button', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/edit.blade.php');

        expect($content)->toContain('$fetching()');
    });

    it('uses x-message for validation errors', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/edit.blade.php');

        expect($content)->toContain('x-message="name"');
        expect($content)->toContain('x-message="order_start_time"');
    });

    it('has breadcrumb with templates link', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/edit.blade.php');

        expect($content)->toContain('/dashboard/schedule/templates');
        expect($content)->toContain("__('Templates')");
    });

    it('uses __() for all user-facing strings', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/edit.blade.php');

        expect($content)->toContain("__('Edit Template')");
        expect($content)->toContain("__('Back to Templates')");
        expect($content)->toContain("__('Modify the schedule template settings.')");
    });

    it('shows warning when both delivery and pickup disabled', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/edit.blade.php');

        expect($content)->toContain("__('At least one of delivery or pickup must be enabled.')");
    });
});

// ============================================================
// Test group: Translation strings
// ============================================================
describe('Translation strings', function () {
    it('has required English translations', function () {
        $root = dirname(__DIR__, 3);
        $translations = json_decode(file_get_contents($root.'/lang/en.json'), true);

        expect($translations)->toHaveKey('Edit Template');
        expect($translations)->toHaveKey('Back to Templates');
        expect($translations)->toHaveKey('Modify the schedule template settings.');
        expect($translations)->toHaveKey('Schedule template updated successfully.');
        expect($translations)->toHaveKey('Changes will not affect schedules already using this template. Re-apply to update them.');
    });

    it('has required French translations', function () {
        $root = dirname(__DIR__, 3);
        $translations = json_decode(file_get_contents($root.'/lang/fr.json'), true);

        expect($translations)->toHaveKey('Edit Template');
        expect($translations)->toHaveKey('Back to Templates');
        expect($translations)->toHaveKey('Modify the schedule template settings.');
        expect($translations)->toHaveKey('Schedule template updated successfully.');
        expect($translations)->toHaveKey('Changes will not affect schedules already using this template. Re-apply to update them.');
    });
});
