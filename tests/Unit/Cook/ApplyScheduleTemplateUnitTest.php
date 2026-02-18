<?php

/**
 * F-105: Schedule Template Application to Days -- Unit Tests
 *
 * Tests for ScheduleTemplateController (showApply/apply),
 * ScheduleTemplateService (applyTemplateToDays, getDaysWithExistingSchedules),
 * ApplyScheduleTemplateRequest, route configuration, blade view,
 * and translation strings.
 *
 * BR-153: Copies template values (not a live link)
 * BR-154: At least one day must be selected
 * BR-155: Confirmation dialog warns about overwriting
 * BR-156: Overwrites all interval values on existing entries
 * BR-157: Sets template_id reference for tracking
 * BR-158: Availability set to true
 * BR-159: Multiple entries per day replaced with one
 * BR-160: Logged via Spatie Activitylog
 * BR-161: Permission-gated
 */

use App\Http\Controllers\Cook\ScheduleTemplateController;
use App\Http\Requests\Cook\ApplyScheduleTemplateRequest;
use App\Models\CookSchedule;
use App\Models\ScheduleTemplate;
use App\Models\Tenant;
use App\Services\ScheduleTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;

$projectRoot = dirname(__DIR__, 3);

uses(Tests\TestCase::class, RefreshDatabase::class);

// =============================================
// Service: applyTemplateToDays()
// =============================================

it('applies template to empty days creating new entries', function () {
    test()->seedRolesAndPermissions();
    $tenant = Tenant::factory()->create();
    $template = ScheduleTemplate::factory()->create(['tenant_id' => $tenant->id]);

    $service = app(ScheduleTemplateService::class);
    $result = $service->applyTemplateToDays($template, $tenant, ['monday', 'tuesday', 'wednesday']);

    expect($result['success'])->toBeTrue()
        ->and($result['days_applied'])->toBe(3)
        ->and($result['days_created'])->toBe(3)
        ->and($result['days_overwritten'])->toBe(0);

    // Verify entries were created
    $entries = CookSchedule::query()->forTenant($tenant->id)->get();
    expect($entries)->toHaveCount(3);

    foreach (['monday', 'tuesday', 'wednesday'] as $day) {
        $entry = $entries->firstWhere('day_of_week', $day);
        expect($entry)->not->toBeNull()
            ->and($entry->template_id)->toBe($template->id)
            ->and($entry->is_available)->toBeTrue()
            ->and(substr($entry->order_start_time, 0, 5))->toBe(substr($template->order_start_time, 0, 5))
            ->and(substr($entry->order_end_time, 0, 5))->toBe(substr($template->order_end_time, 0, 5))
            ->and($entry->delivery_enabled)->toBe($template->delivery_enabled)
            ->and($entry->pickup_enabled)->toBe($template->pickup_enabled)
            ->and($entry->position)->toBe(1);
    }
});

it('overwrites existing schedule entries when applying template', function () {
    test()->seedRolesAndPermissions();
    $tenant = Tenant::factory()->create();
    $template = ScheduleTemplate::factory()->create([
        'tenant_id' => $tenant->id,
        'order_start_time' => '06:00',
        'order_start_day_offset' => 0,
        'order_end_time' => '10:00',
        'order_end_day_offset' => 0,
        'delivery_enabled' => true,
        'delivery_start_time' => '11:00',
        'delivery_end_time' => '14:00',
        'pickup_enabled' => false,
        'pickup_start_time' => null,
        'pickup_end_time' => null,
    ]);

    // Create existing schedule for Monday
    CookSchedule::factory()->create([
        'tenant_id' => $tenant->id,
        'day_of_week' => 'monday',
        'order_start_time' => '08:00',
        'order_end_time' => '12:00',
        'is_available' => false,
        'position' => 1,
    ]);

    $service = app(ScheduleTemplateService::class);
    $result = $service->applyTemplateToDays($template, $tenant, ['monday']);

    expect($result['success'])->toBeTrue()
        ->and($result['days_applied'])->toBe(1)
        ->and($result['days_created'])->toBe(0)
        ->and($result['days_overwritten'])->toBe(1);

    $entry = CookSchedule::query()->forTenant($tenant->id)->forDay('monday')->first();
    expect(substr($entry->order_start_time, 0, 5))->toBe('06:00')
        ->and(substr($entry->order_end_time, 0, 5))->toBe('10:00')
        ->and($entry->delivery_enabled)->toBeTrue()
        ->and($entry->pickup_enabled)->toBeFalse()
        ->and($entry->is_available)->toBeTrue()
        ->and($entry->template_id)->toBe($template->id);
});

it('removes extra entries when day has multiple and template is applied', function () {
    test()->seedRolesAndPermissions();
    $tenant = Tenant::factory()->create();
    $template = ScheduleTemplate::factory()->create(['tenant_id' => $tenant->id]);

    // Create 3 entries for Monday (BR-159)
    CookSchedule::factory()->create([
        'tenant_id' => $tenant->id,
        'day_of_week' => 'monday',
        'position' => 1,
    ]);
    CookSchedule::factory()->create([
        'tenant_id' => $tenant->id,
        'day_of_week' => 'monday',
        'position' => 2,
    ]);
    CookSchedule::factory()->create([
        'tenant_id' => $tenant->id,
        'day_of_week' => 'monday',
        'position' => 3,
    ]);

    $service = app(ScheduleTemplateService::class);
    $result = $service->applyTemplateToDays($template, $tenant, ['monday']);

    expect($result['success'])->toBeTrue()
        ->and($result['days_overwritten'])->toBe(1);

    // Only 1 entry should remain
    $entries = CookSchedule::query()->forTenant($tenant->id)->forDay('monday')->get();
    expect($entries)->toHaveCount(1)
        ->and($entries->first()->template_id)->toBe($template->id);
});

it('returns error when no days are selected', function () {
    test()->seedRolesAndPermissions();
    $tenant = Tenant::factory()->create();
    $template = ScheduleTemplate::factory()->create(['tenant_id' => $tenant->id]);

    $service = app(ScheduleTemplateService::class);
    $result = $service->applyTemplateToDays($template, $tenant, []);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('Select at least one day');
});

it('returns error when invalid day is provided', function () {
    test()->seedRolesAndPermissions();
    $tenant = Tenant::factory()->create();
    $template = ScheduleTemplate::factory()->create(['tenant_id' => $tenant->id]);

    $service = app(ScheduleTemplateService::class);
    $result = $service->applyTemplateToDays($template, $tenant, ['invalidday']);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('Invalid day selected');
});

it('sets template_id on all applied entries for tracking', function () {
    test()->seedRolesAndPermissions();
    $tenant = Tenant::factory()->create();
    $template = ScheduleTemplate::factory()->create(['tenant_id' => $tenant->id]);

    $service = app(ScheduleTemplateService::class);
    $service->applyTemplateToDays($template, $tenant, ['monday', 'friday', 'sunday']);

    $entries = CookSchedule::query()
        ->forTenant($tenant->id)
        ->whereNotNull('template_id')
        ->get();

    expect($entries)->toHaveCount(3)
        ->and($entries->every(fn ($e) => $e->template_id === $template->id))->toBeTrue();
});

it('sets availability to true for all applied entries', function () {
    test()->seedRolesAndPermissions();
    $tenant = Tenant::factory()->create();
    $template = ScheduleTemplate::factory()->create(['tenant_id' => $tenant->id]);

    // Create unavailable entry
    CookSchedule::factory()->create([
        'tenant_id' => $tenant->id,
        'day_of_week' => 'monday',
        'is_available' => false,
        'position' => 1,
    ]);

    $service = app(ScheduleTemplateService::class);
    $service->applyTemplateToDays($template, $tenant, ['monday']);

    $entry = CookSchedule::query()->forTenant($tenant->id)->forDay('monday')->first();
    expect($entry->is_available)->toBeTrue();
});

it('copies template label as schedule label', function () {
    test()->seedRolesAndPermissions();
    $tenant = Tenant::factory()->create();
    $template = ScheduleTemplate::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Lunch Service',
    ]);

    $service = app(ScheduleTemplateService::class);
    $service->applyTemplateToDays($template, $tenant, ['monday']);

    $entry = CookSchedule::query()->forTenant($tenant->id)->forDay('monday')->first();
    expect($entry->label)->toBe('Lunch Service');
});

it('handles mix of new and existing days correctly', function () {
    test()->seedRolesAndPermissions();
    $tenant = Tenant::factory()->create();
    $template = ScheduleTemplate::factory()->create(['tenant_id' => $tenant->id]);

    // Monday has existing, Tuesday does not
    CookSchedule::factory()->create([
        'tenant_id' => $tenant->id,
        'day_of_week' => 'monday',
        'position' => 1,
    ]);

    $service = app(ScheduleTemplateService::class);
    $result = $service->applyTemplateToDays($template, $tenant, ['monday', 'tuesday']);

    expect($result['success'])->toBeTrue()
        ->and($result['days_applied'])->toBe(2)
        ->and($result['days_created'])->toBe(1)
        ->and($result['days_overwritten'])->toBe(1);
});

it('copies delivery disabled from template', function () {
    test()->seedRolesAndPermissions();
    $tenant = Tenant::factory()->create();
    $template = ScheduleTemplate::factory()->pickupOnly()->create(['tenant_id' => $tenant->id]);

    $service = app(ScheduleTemplateService::class);
    $service->applyTemplateToDays($template, $tenant, ['monday']);

    $entry = CookSchedule::query()->forTenant($tenant->id)->forDay('monday')->first();
    expect($entry->delivery_enabled)->toBeFalse()
        ->and($entry->delivery_start_time)->toBeNull()
        ->and($entry->delivery_end_time)->toBeNull()
        ->and($entry->pickup_enabled)->toBeTrue();
});

// =============================================
// Service: getDaysWithExistingSchedules()
// =============================================

it('returns days that have existing schedules', function () {
    test()->seedRolesAndPermissions();
    $tenant = Tenant::factory()->create();

    CookSchedule::factory()->create([
        'tenant_id' => $tenant->id,
        'day_of_week' => 'monday',
        'position' => 1,
    ]);
    CookSchedule::factory()->create([
        'tenant_id' => $tenant->id,
        'day_of_week' => 'friday',
        'position' => 1,
    ]);

    $service = app(ScheduleTemplateService::class);
    $days = $service->getDaysWithExistingSchedules($tenant);

    expect($days)->toContain('monday')
        ->and($days)->toContain('friday')
        ->and($days)->toHaveCount(2);
});

it('returns empty array when no schedules exist', function () {
    test()->seedRolesAndPermissions();
    $tenant = Tenant::factory()->create();

    $service = app(ScheduleTemplateService::class);
    $days = $service->getDaysWithExistingSchedules($tenant);

    expect($days)->toBeEmpty();
});

it('does not duplicate days when multiple entries exist for same day', function () {
    test()->seedRolesAndPermissions();
    $tenant = Tenant::factory()->create();

    CookSchedule::factory()->create([
        'tenant_id' => $tenant->id,
        'day_of_week' => 'monday',
        'position' => 1,
    ]);
    CookSchedule::factory()->create([
        'tenant_id' => $tenant->id,
        'day_of_week' => 'monday',
        'position' => 2,
    ]);

    $service = app(ScheduleTemplateService::class);
    $days = $service->getDaysWithExistingSchedules($tenant);

    expect($days)->toContain('monday')
        ->and($days)->toHaveCount(1);
});

// =============================================
// Controller: showApply
// =============================================

it('shows apply form to authorized user', function () {
    $cook = test()->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create();
    $template = ScheduleTemplate::factory()->create(['tenant_id' => $tenant->id]);
    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $response = test()->actingAs($cook)
        ->get("https://{$tenant->slug}.{$mainDomain}/dashboard/schedule/templates/{$template->id}/apply");

    $response->assertStatus(200);
    $response->assertSeeText('Apply Template');
});

it('returns 403 for users without manage-schedules permission', function () {
    $cook = test()->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create();
    $template = ScheduleTemplate::factory()->create(['tenant_id' => $tenant->id]);
    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $clientUser = test()->createUserWithRole('client');

    $response = test()->actingAs($clientUser)
        ->get("https://{$tenant->slug}.{$mainDomain}/dashboard/schedule/templates/{$template->id}/apply");

    $response->assertStatus(403);
});

it('returns 404 for template from different tenant', function () {
    $cook = test()->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create();
    $otherTenant = Tenant::factory()->create();
    $template = ScheduleTemplate::factory()->create(['tenant_id' => $otherTenant->id]);
    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $response = test()->actingAs($cook)
        ->get("https://{$tenant->slug}.{$mainDomain}/dashboard/schedule/templates/{$template->id}/apply");

    $response->assertStatus(404);
});

it('shows warning badges on days with existing schedules', function () {
    $cook = test()->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create();
    $template = ScheduleTemplate::factory()->create(['tenant_id' => $tenant->id]);
    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    CookSchedule::factory()->create([
        'tenant_id' => $tenant->id,
        'day_of_week' => 'monday',
        'position' => 1,
    ]);

    $response = test()->actingAs($cook)
        ->get("https://{$tenant->slug}.{$mainDomain}/dashboard/schedule/templates/{$template->id}/apply");

    $response->assertStatus(200);
    $response->assertSee('Existing');
});

// =============================================
// Controller: apply (POST)
// =============================================

it('applies template to selected days via POST', function () {
    $cook = test()->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create();
    $template = ScheduleTemplate::factory()->create(['tenant_id' => $tenant->id]);
    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $response = test()->actingAs($cook)
        ->post("https://{$tenant->slug}.{$mainDomain}/dashboard/schedule/templates/{$template->id}/apply", [
            'days' => ['monday', 'tuesday'],
        ]);

    $response->assertRedirect();

    $entries = CookSchedule::query()->forTenant($tenant->id)->get();
    expect($entries)->toHaveCount(2);
});

it('rejects apply with no days via POST', function () {
    $cook = test()->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create();
    $template = ScheduleTemplate::factory()->create(['tenant_id' => $tenant->id]);
    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $response = test()->actingAs($cook)
        ->post("https://{$tenant->slug}.{$mainDomain}/dashboard/schedule/templates/{$template->id}/apply", [
            'days' => [],
        ]);

    $response->assertSessionHasErrors('days');
});

it('rejects apply without permission via POST', function () {
    $cook = test()->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create();
    $template = ScheduleTemplate::factory()->create(['tenant_id' => $tenant->id]);
    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $clientUser = test()->createUserWithRole('client');

    $response = test()->actingAs($clientUser)
        ->post("https://{$tenant->slug}.{$mainDomain}/dashboard/schedule/templates/{$template->id}/apply", [
            'days' => ['monday'],
        ]);

    $response->assertStatus(403);
});

it('logs template application via activity log', function () {
    $cook = test()->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create();
    $template = ScheduleTemplate::factory()->create(['tenant_id' => $tenant->id]);
    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    test()->actingAs($cook)
        ->post("https://{$tenant->slug}.{$mainDomain}/dashboard/schedule/templates/{$template->id}/apply", [
            'days' => ['monday', 'tuesday'],
        ]);

    $log = \Spatie\Activitylog\Models\Activity::query()
        ->where('log_name', 'schedule_templates')
        ->where('description', 'Schedule template applied to days')
        ->latest()
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->properties['action'])->toBe('template_applied')
        ->and($log->properties['days_applied'])->toContain('monday')
        ->and($log->properties['days_applied'])->toContain('tuesday')
        ->and($log->properties['days_created'])->toBe(2);
});

// =============================================
// Route Configuration
// =============================================

it('has GET route for showApply', function () {
    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($r) => $r->getName() === 'cook.schedule-templates.show-apply');

    expect($route)->not->toBeNull()
        ->and($route->methods())->toContain('GET');
});

it('has POST route for apply', function () {
    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($r) => $r->getName() === 'cook.schedule-templates.apply');

    expect($route)->not->toBeNull()
        ->and($route->methods())->toContain('POST');
});

// =============================================
// Form Request: ApplyScheduleTemplateRequest
// =============================================

it('has required rules for days field', function () {
    $formRequest = new ApplyScheduleTemplateRequest;
    $rules = $formRequest->rules();

    expect($rules)->toHaveKey('days')
        ->and($rules['days'])->toContain('required')
        ->and($rules['days'])->toContain('array');
});

it('validates days contain only valid day names', function () {
    $formRequest = new ApplyScheduleTemplateRequest;
    $rules = $formRequest->rules();

    expect($rules)->toHaveKey('days.*')
        ->and($rules['days.*'])->toContain('string');
});

it('authorizes only users with manage-schedules permission', function () {
    $user = test()->createUserWithRole('cook');

    $request = ApplyScheduleTemplateRequest::create('/test', 'POST');
    $request->setUserResolver(fn () => $user);

    expect($request->authorize())->toBeTrue();
});

it('denies users without manage-schedules permission', function () {
    $user = test()->createUserWithRole('client');

    $request = ApplyScheduleTemplateRequest::create('/test', 'POST');
    $request->setUserResolver(fn () => $user);

    expect($request->authorize())->toBeFalse();
});

// =============================================
// Blade View Existence
// =============================================

it('has the apply blade view', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/cook/schedule/templates/apply.blade.php';
    expect(file_exists($viewPath))->toBeTrue();
});

it('apply view extends cook-dashboard layout', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/cook/schedule/templates/apply.blade.php';
    $content = file_get_contents($viewPath);

    expect($content)->toContain("@extends('layouts.cook-dashboard')");
});

it('apply view has day checkboxes for all seven days', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/cook/schedule/templates/apply.blade.php';
    $content = file_get_contents($viewPath);

    expect($content)->toContain('$daysOfWeek');
    expect($content)->toContain('toggleDay');
});

it('apply view has overwrite confirmation modal', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/cook/schedule/templates/apply.blade.php';
    $content = file_get_contents($viewPath);

    expect($content)->toContain('showConfirmModal')
        ->and($content)->toContain('Overwrite')
        ->and($content)->toContain('cancelConfirm');
});

it('apply view has warning badges for existing schedules', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/cook/schedule/templates/apply.blade.php';
    $content = file_get_contents($viewPath);

    expect($content)->toContain('daysWithSchedules')
        ->and($content)->toContain('Existing');
});

it('apply view uses x-message for validation errors', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/cook/schedule/templates/apply.blade.php';
    $content = file_get_contents($viewPath);

    expect($content)->toContain('x-message="days"');
});

it('apply view uses $action for Gale submission', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/cook/schedule/templates/apply.blade.php';
    $content = file_get_contents($viewPath);

    expect($content)->toContain('$action(');
});

it('apply view uses $fetching() for loading state', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/cook/schedule/templates/apply.blade.php';
    $content = file_get_contents($viewPath);

    expect($content)->toContain('$fetching()');
});

// =============================================
// Translation Strings
// =============================================

it('has English translations for F-105 strings', function () use ($projectRoot) {
    $enJson = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);

    expect($enJson)->toHaveKey('Apply Template')
        ->and($enJson)->toHaveKey('Select Days')
        ->and($enJson)->toHaveKey('Select at least one day.')
        ->and($enJson)->toHaveKey('Overwrite Existing Schedules?')
        ->and($enJson)->toHaveKey('Overwrite & Apply')
        ->and($enJson)->toHaveKey('Template applied to :count day.|Template applied to :count days.');
});

it('has French translations for F-105 strings', function () use ($projectRoot) {
    $frJson = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);

    expect($frJson)->toHaveKey('Apply Template')
        ->and($frJson)->toHaveKey('Select Days')
        ->and($frJson)->toHaveKey('Select at least one day.')
        ->and($frJson)->toHaveKey('Overwrite Existing Schedules?')
        ->and($frJson)->toHaveKey('Overwrite & Apply')
        ->and($frJson)->toHaveKey('Template applied to :count day.|Template applied to :count days.');
});

// =============================================
// Controller method existence
// =============================================

it('has showApply method on controller', function () {
    expect(method_exists(ScheduleTemplateController::class, 'showApply'))->toBeTrue();
});

it('has apply method on controller', function () {
    expect(method_exists(ScheduleTemplateController::class, 'apply'))->toBeTrue();
});

// =============================================
// Service method existence
// =============================================

it('has applyTemplateToDays method on service', function () {
    expect(method_exists(ScheduleTemplateService::class, 'applyTemplateToDays'))->toBeTrue();
});

it('has getDaysWithExistingSchedules method on service', function () {
    expect(method_exists(ScheduleTemplateService::class, 'getDaysWithExistingSchedules'))->toBeTrue();
});

// =============================================
// Edge Cases
// =============================================

it('can re-apply same template to same days', function () {
    test()->seedRolesAndPermissions();
    $tenant = Tenant::factory()->create();
    $template = ScheduleTemplate::factory()->create(['tenant_id' => $tenant->id]);

    $service = app(ScheduleTemplateService::class);

    // First application
    $result1 = $service->applyTemplateToDays($template, $tenant, ['monday']);
    expect($result1['success'])->toBeTrue()
        ->and($result1['days_created'])->toBe(1);

    // Second application (re-apply)
    $result2 = $service->applyTemplateToDays($template, $tenant, ['monday']);
    expect($result2['success'])->toBeTrue()
        ->and($result2['days_overwritten'])->toBe(1)
        ->and($result2['days_created'])->toBe(0);

    // Still only 1 entry
    $entries = CookSchedule::query()->forTenant($tenant->id)->forDay('monday')->get();
    expect($entries)->toHaveCount(1);
});

it('applies template to single day correctly', function () {
    test()->seedRolesAndPermissions();
    $tenant = Tenant::factory()->create();
    $template = ScheduleTemplate::factory()->create(['tenant_id' => $tenant->id]);

    $service = app(ScheduleTemplateService::class);
    $result = $service->applyTemplateToDays($template, $tenant, ['saturday']);

    expect($result['success'])->toBeTrue()
        ->and($result['days_applied'])->toBe(1);

    $entry = CookSchedule::query()->forTenant($tenant->id)->forDay('saturday')->first();
    expect($entry)->not->toBeNull()
        ->and($entry->template_id)->toBe($template->id);
});

it('applies to all seven days when selected', function () {
    test()->seedRolesAndPermissions();
    $tenant = Tenant::factory()->create();
    $template = ScheduleTemplate::factory()->create(['tenant_id' => $tenant->id]);

    $service = app(ScheduleTemplateService::class);
    $result = $service->applyTemplateToDays($template, $tenant, CookSchedule::DAYS_OF_WEEK);

    expect($result['success'])->toBeTrue()
        ->and($result['days_applied'])->toBe(7);

    $entries = CookSchedule::query()->forTenant($tenant->id)->get();
    expect($entries)->toHaveCount(7);
});
