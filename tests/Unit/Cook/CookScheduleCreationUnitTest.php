<?php

/**
 * F-098: Cook Day Schedule Creation -- Unit Tests
 *
 * Tests for CookScheduleController, StoreCookScheduleRequest, CookScheduleService,
 * CookSchedule model, schedule blade view, route configuration, and translation strings.
 *
 * BR-098: Each schedule entry belongs to a single day of the week (Monday-Sunday)
 * BR-099: Entry has an availability flag: available or unavailable
 * BR-100: Multiple entries per day up to configurable maximum (default 3)
 * BR-101: Unavailable entries cannot have time intervals configured
 * BR-102: Schedule entries are tenant-scoped
 * BR-103: Only users with can-manage-schedules permission
 * BR-104: Schedule creation logged via Spatie Activitylog
 * BR-105: Each schedule entry must have a unique label or position within its day
 */

use App\Http\Controllers\Cook\CookScheduleController;
use App\Http\Requests\Cook\StoreCookScheduleRequest;
use App\Models\CookSchedule;
use App\Models\Tenant;
use App\Services\CookScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;

$projectRoot = dirname(__DIR__, 3);

/* Database integration tests require full app context */
uses(Tests\TestCase::class, RefreshDatabase::class);

// ============================================================
// Test group: CookScheduleController methods
// ============================================================
describe('CookScheduleController', function () {
    it('has an index method', function () {
        $reflection = new ReflectionMethod(CookScheduleController::class, 'index');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has a store method', function () {
        $reflection = new ReflectionMethod(CookScheduleController::class, 'store');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('index method accepts Request and CookScheduleService parameters', function () {
        $reflection = new ReflectionMethod(CookScheduleController::class, 'index');
        $params = $reflection->getParameters();
        expect($params)->toHaveCount(2);
        expect($params[0]->getName())->toBe('request');
        expect($params[1]->getName())->toBe('scheduleService');
    });

    it('store method accepts Request and CookScheduleService parameters', function () {
        $reflection = new ReflectionMethod(CookScheduleController::class, 'store');
        $params = $reflection->getParameters();
        expect($params)->toHaveCount(2);
        expect($params[0]->getName())->toBe('request');
        expect($params[1]->getName())->toBe('scheduleService');
    });
});

// ============================================================
// Test group: CookSchedule Model
// ============================================================
describe('CookSchedule Model', function () {
    it('has the correct table name', function () {
        $schedule = new CookSchedule;
        expect($schedule->getTable())->toBe('cook_schedules');
    });

    it('has correct fillable attributes', function () {
        $schedule = new CookSchedule;
        expect($schedule->getFillable())->toBe([
            'tenant_id',
            'day_of_week',
            'is_available',
            'label',
            'position',
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

    it('casts is_available to boolean', function () {
        $schedule = new CookSchedule;
        $casts = $schedule->getCasts();
        expect($casts['is_available'])->toBe('boolean');
    });

    it('casts position to integer', function () {
        $schedule = new CookSchedule;
        $casts = $schedule->getCasts();
        expect($casts['position'])->toBe('integer');
    });

    it('defines all seven days of the week', function () {
        expect(CookSchedule::DAYS_OF_WEEK)->toBe([
            'monday',
            'tuesday',
            'wednesday',
            'thursday',
            'friday',
            'saturday',
            'sunday',
        ]);
    });

    it('defines day labels for all seven days', function () {
        expect(CookSchedule::DAY_LABELS)->toHaveCount(7);
        expect(CookSchedule::DAY_LABELS['monday'])->toBe('Monday');
        expect(CookSchedule::DAY_LABELS['sunday'])->toBe('Sunday');
    });

    it('has MAX_ENTRIES_PER_DAY constant set to 3', function () {
        expect(CookSchedule::MAX_ENTRIES_PER_DAY)->toBe(3);
    });

    it('belongs to a tenant', function () {
        $schedule = new CookSchedule;
        $relation = $schedule->tenant();
        expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    });

    it('returns the day label as translated attribute', function () {
        $schedule = new CookSchedule(['day_of_week' => 'monday']);
        expect($schedule->day_label)->toBe(__('Monday'));
    });

    it('returns "Slot N" as display label when label is empty', function () {
        $schedule = new CookSchedule(['label' => null, 'position' => 2]);
        expect($schedule->display_label)->toBe(__('Slot').' 2');
    });

    it('returns the label as display label when label is set', function () {
        $schedule = new CookSchedule(['label' => 'Lunch', 'position' => 1]);
        expect($schedule->display_label)->toBe('Lunch');
    });

    it('creates a schedule entry via factory', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()->forDay('monday')->create([
            'tenant_id' => $tenant->id,
        ]);

        expect($schedule)->toBeInstanceOf(CookSchedule::class);
        expect($schedule->tenant_id)->toBe($tenant->id);
        expect($schedule->day_of_week)->toBe('monday');
        expect($schedule->is_available)->toBeTrue();
    });

    it('can be created as unavailable via factory', function () {
        $schedule = CookSchedule::factory()->unavailable()->create();
        expect($schedule->is_available)->toBeFalse();
    });

    it('scopes by tenant', function () {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        CookSchedule::factory()->forDay('monday')->create(['tenant_id' => $tenant1->id]);
        CookSchedule::factory()->forDay('tuesday')->create(['tenant_id' => $tenant1->id]);
        CookSchedule::factory()->forDay('monday')->create(['tenant_id' => $tenant2->id]);

        $result = CookSchedule::forTenant($tenant1->id)->get();
        expect($result)->toHaveCount(2);
    });

    it('scopes by day', function () {
        $tenant = Tenant::factory()->create();

        CookSchedule::factory()->forDay('monday')->create(['tenant_id' => $tenant->id, 'position' => 1]);
        CookSchedule::factory()->forDay('monday')->create(['tenant_id' => $tenant->id, 'position' => 2]);
        CookSchedule::factory()->forDay('tuesday')->create(['tenant_id' => $tenant->id]);

        $result = CookSchedule::forTenant($tenant->id)->forDay('monday')->get();
        expect($result)->toHaveCount(2);
    });

    it('scopes by available', function () {
        $tenant = Tenant::factory()->create();

        CookSchedule::factory()->forDay('monday')->create(['tenant_id' => $tenant->id]);
        CookSchedule::factory()->forDay('tuesday')->unavailable()->create(['tenant_id' => $tenant->id]);

        $result = CookSchedule::forTenant($tenant->id)->available()->get();
        expect($result)->toHaveCount(1);
    });
});

// ============================================================
// Test group: CookScheduleService
// ============================================================
describe('CookScheduleService', function () {
    it('returns schedules grouped by day', function () {
        $tenant = Tenant::factory()->create();
        CookSchedule::factory()->forDay('monday')->create(['tenant_id' => $tenant->id, 'position' => 1]);
        CookSchedule::factory()->forDay('wednesday')->create(['tenant_id' => $tenant->id, 'position' => 1]);

        $service = new CookScheduleService;
        $grouped = $service->getSchedulesByDay($tenant);

        expect($grouped)->toHaveCount(7);
        expect($grouped['monday'])->toHaveCount(1);
        expect($grouped['wednesday'])->toHaveCount(1);
        expect($grouped['tuesday'])->toHaveCount(0);
    });

    it('counts entries for a specific day', function () {
        $tenant = Tenant::factory()->create();
        CookSchedule::factory()->forDay('monday')->create(['tenant_id' => $tenant->id, 'position' => 1]);
        CookSchedule::factory()->forDay('monday')->create(['tenant_id' => $tenant->id, 'position' => 2]);

        $service = new CookScheduleService;
        expect($service->countEntriesForDay($tenant, 'monday'))->toBe(2);
        expect($service->countEntriesForDay($tenant, 'tuesday'))->toBe(0);
    });

    it('detects when day is at limit', function () {
        $tenant = Tenant::factory()->create();
        for ($i = 1; $i <= CookSchedule::MAX_ENTRIES_PER_DAY; $i++) {
            CookSchedule::factory()->forDay('monday')->atPosition($i)->create(['tenant_id' => $tenant->id]);
        }

        $service = new CookScheduleService;
        expect($service->isDayAtLimit($tenant, 'monday'))->toBeTrue();
        expect($service->isDayAtLimit($tenant, 'tuesday'))->toBeFalse();
    });

    it('calculates the next position for a day', function () {
        $tenant = Tenant::factory()->create();
        CookSchedule::factory()->forDay('monday')->atPosition(1)->create(['tenant_id' => $tenant->id]);

        $service = new CookScheduleService;
        expect($service->getNextPosition($tenant, 'monday'))->toBe(2);
        expect($service->getNextPosition($tenant, 'tuesday'))->toBe(1);
    });

    it('creates a schedule entry successfully', function () {
        $tenant = Tenant::factory()->create();
        $service = new CookScheduleService;

        $result = $service->createScheduleEntry($tenant, 'monday', true, 'Lunch');

        expect($result['success'])->toBeTrue();
        expect($result['schedule'])->toBeInstanceOf(CookSchedule::class);
        expect($result['schedule']->day_of_week)->toBe('monday');
        expect($result['schedule']->is_available)->toBeTrue();
        expect($result['schedule']->label)->toBe('Lunch');
        expect($result['schedule']->position)->toBe(1);
    });

    it('auto-assigns position for multiple entries on same day', function () {
        $tenant = Tenant::factory()->create();
        $service = new CookScheduleService;

        $result1 = $service->createScheduleEntry($tenant, 'monday', true, 'Lunch');
        $result2 = $service->createScheduleEntry($tenant, 'monday', true, 'Dinner');

        expect($result1['schedule']->position)->toBe(1);
        expect($result2['schedule']->position)->toBe(2);
    });

    it('rejects when per-day limit is reached', function () {
        $tenant = Tenant::factory()->create();
        $service = new CookScheduleService;

        for ($i = 0; $i < CookSchedule::MAX_ENTRIES_PER_DAY; $i++) {
            $service->createScheduleEntry($tenant, 'monday', true, "Slot $i");
        }

        $result = $service->createScheduleEntry($tenant, 'monday', true, 'Extra');
        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain((string) CookSchedule::MAX_ENTRIES_PER_DAY);
    });

    it('trims and nullifies empty labels', function () {
        $tenant = Tenant::factory()->create();
        $service = new CookScheduleService;

        $result = $service->createScheduleEntry($tenant, 'monday', true, '   ');
        expect($result['schedule']->label)->toBeNull();
    });

    it('creates unavailable entries', function () {
        $tenant = Tenant::factory()->create();
        $service = new CookScheduleService;

        $result = $service->createScheduleEntry($tenant, 'sunday', false);

        expect($result['success'])->toBeTrue();
        expect($result['schedule']->is_available)->toBeFalse();
    });

    it('detects if tenant has any schedules', function () {
        $tenant = Tenant::factory()->create();
        $service = new CookScheduleService;

        expect($service->hasAnySchedules($tenant))->toBeFalse();

        CookSchedule::factory()->forDay('monday')->create(['tenant_id' => $tenant->id]);
        expect($service->hasAnySchedules($tenant))->toBeTrue();
    });

    it('returns schedule summary', function () {
        $tenant = Tenant::factory()->create();
        CookSchedule::factory()->forDay('monday')->create(['tenant_id' => $tenant->id, 'position' => 1]);
        CookSchedule::factory()->forDay('monday')->create(['tenant_id' => $tenant->id, 'position' => 2]);
        CookSchedule::factory()->forDay('tuesday')->unavailable()->create(['tenant_id' => $tenant->id]);

        $service = new CookScheduleService;
        $summary = $service->getScheduleSummary($tenant);

        expect($summary['total'])->toBe(3);
        expect($summary['available'])->toBe(2);
        expect($summary['unavailable'])->toBe(1);
        expect($summary['days_covered'])->toBe(2);
    });
});

// ============================================================
// Test group: StoreCookScheduleRequest
// ============================================================
describe('StoreCookScheduleRequest', function () {
    it('has validation rules for day_of_week, is_available, and label', function () {
        $request = new StoreCookScheduleRequest;
        $rules = $request->rules();

        expect($rules)->toHaveKey('day_of_week');
        expect($rules)->toHaveKey('is_available');
        expect($rules)->toHaveKey('label');
    });

    it('requires day_of_week', function () {
        $request = new StoreCookScheduleRequest;
        $rules = $request->rules();

        expect($rules['day_of_week'])->toContain('required');
    });

    it('validates day_of_week against valid days', function () {
        $request = new StoreCookScheduleRequest;
        $rules = $request->rules();

        // The Rule::in validation is present
        $hasInRule = false;
        foreach ($rules['day_of_week'] as $rule) {
            if ($rule instanceof \Illuminate\Validation\Rules\In) {
                $hasInRule = true;
            }
        }
        expect($hasInRule)->toBeTrue();
    });

    it('requires is_available', function () {
        $request = new StoreCookScheduleRequest;
        $rules = $request->rules();

        expect($rules['is_available'])->toContain('required');
    });

    it('allows nullable label with max 100 chars', function () {
        $request = new StoreCookScheduleRequest;
        $rules = $request->rules();

        expect($rules['label'])->toContain('nullable');
        expect($rules['label'])->toContain('max:100');
    });

    it('has custom error messages', function () {
        $request = new StoreCookScheduleRequest;
        $messages = $request->messages();

        expect($messages)->toHaveKey('day_of_week.required');
        expect($messages)->toHaveKey('day_of_week.in');
        expect($messages)->toHaveKey('is_available.required');
        expect($messages)->toHaveKey('label.max');
    });
});

// ============================================================
// Test group: Tenant relationship
// ============================================================
describe('Tenant cookSchedules relationship', function () {
    it('has a cookSchedules hasMany relationship', function () {
        $tenant = new Tenant;
        $relation = $tenant->cookSchedules();
        expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    });

    it('returns cook schedules for a tenant', function () {
        $tenant = Tenant::factory()->create();
        CookSchedule::factory()->forDay('monday')->create(['tenant_id' => $tenant->id]);
        CookSchedule::factory()->forDay('tuesday')->create(['tenant_id' => $tenant->id]);

        expect($tenant->cookSchedules)->toHaveCount(2);
    });
});

// ============================================================
// Test group: Route configuration
// ============================================================
describe('Schedule routes', function () use ($projectRoot) {
    it('has the cook schedule index route', function () use ($projectRoot) {
        $routeContent = file_get_contents($projectRoot.'/routes/web.php');
        expect($routeContent)->toContain("Route::get('/schedule'");
        expect($routeContent)->toContain('CookScheduleController::class');
        expect($routeContent)->toContain("'cook.schedule.index'");
    });

    it('has the cook schedule store route', function () use ($projectRoot) {
        $routeContent = file_get_contents($projectRoot.'/routes/web.php');
        expect($routeContent)->toContain("Route::post('/schedule'");
        expect($routeContent)->toContain("'cook.schedule.store'");
    });
});

// ============================================================
// Test group: View file existence
// ============================================================
describe('Schedule view files', function () use ($projectRoot) {
    it('has the schedule index blade view', function () use ($projectRoot) {
        expect(file_exists($projectRoot.'/resources/views/cook/schedule/index.blade.php'))->toBeTrue();
    });

    it('extends the cook dashboard layout', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/schedule/index.blade.php');
        expect($content)->toContain("@extends('layouts.cook-dashboard')");
    });

    it('uses gale x-data and x-sync directives', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/schedule/index.blade.php');
        expect($content)->toContain('x-data=');
        expect($content)->toContain('x-sync=');
    });

    it('has __() translation helpers for user-facing strings', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/schedule/index.blade.php');
        expect($content)->toContain("__('Schedule')");
        expect($content)->toContain("__('Add Schedule')");
        expect($content)->toContain("__('Day of the Week')");
        expect($content)->toContain("__('Available')");
        expect($content)->toContain("__('Unavailable')");
    });

    it('contains empty state message', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/schedule/index.blade.php');
        expect($content)->toContain("__('No schedule entries yet')");
    });

    it('uses $action for form submission', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/schedule/index.blade.php');
        expect($content)->toContain('$action(');
    });

    it('shows $fetching() loading state', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/schedule/index.blade.php');
        expect($content)->toContain('$fetching()');
    });
});

// ============================================================
// Test group: Translation strings
// ============================================================
describe('Translation strings', function () use ($projectRoot) {
    it('has English translation strings for schedule', function () use ($projectRoot) {
        $enJson = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);
        expect($enJson)->toHaveKey('Schedule');
        expect($enJson)->toHaveKey('Add Schedule');
        expect($enJson)->toHaveKey('Schedule entry created successfully.');
        expect($enJson)->toHaveKey('Maximum of :max schedule entries per day has been reached.');
        expect($enJson)->toHaveKey('Please select a day of the week.');
    });

    it('has French translation strings for schedule', function () use ($projectRoot) {
        $frJson = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);
        expect($frJson)->toHaveKey('Schedule');
        expect($frJson)->toHaveKey('Add Schedule');
        expect($frJson)->toHaveKey('Schedule entry created successfully.');
        expect($frJson['Schedule'])->toBe('Horaire');
    });
});

// ============================================================
// Test group: CookSchedule Factory
// ============================================================
describe('CookScheduleFactory', function () {
    it('creates a valid schedule with default state', function () {
        $schedule = CookSchedule::factory()->create();
        expect($schedule)->toBeInstanceOf(CookSchedule::class);
        expect($schedule->day_of_week)->toBeIn(CookSchedule::DAYS_OF_WEEK);
        expect($schedule->is_available)->toBeTrue();
        expect($schedule->position)->toBe(1);
    });

    it('creates schedule for specific day', function () {
        $schedule = CookSchedule::factory()->forDay('friday')->create();
        expect($schedule->day_of_week)->toBe('friday');
    });

    it('creates unavailable schedule', function () {
        $schedule = CookSchedule::factory()->unavailable()->create();
        expect($schedule->is_available)->toBeFalse();
    });

    it('creates schedule at specific position', function () {
        $schedule = CookSchedule::factory()->atPosition(3)->create();
        expect($schedule->position)->toBe(3);
    });

    it('creates schedule with specific label', function () {
        $schedule = CookSchedule::factory()->withLabel('Dinner Special')->create();
        expect($schedule->label)->toBe('Dinner Special');
    });

    it('creates schedule without label', function () {
        $schedule = CookSchedule::factory()->withoutLabel()->create();
        expect($schedule->label)->toBeNull();
    });
});

// ============================================================
// Test group: Database schema
// ============================================================
describe('cook_schedules table', function () {
    it('exists in the database', function () {
        expect(\Schema::hasTable('cook_schedules'))->toBeTrue();
    });

    it('has all expected columns', function () {
        $columns = \Schema::getColumnListing('cook_schedules');
        expect($columns)->toContain('id');
        expect($columns)->toContain('tenant_id');
        expect($columns)->toContain('day_of_week');
        expect($columns)->toContain('is_available');
        expect($columns)->toContain('label');
        expect($columns)->toContain('position');
        expect($columns)->toContain('created_at');
        expect($columns)->toContain('updated_at');
    });
});

// ============================================================
// Test group: BR-104 Activity logging
// ============================================================
describe('Activity logging', function () {
    it('uses LogsActivityTrait', function () {
        $traits = class_uses_recursive(CookSchedule::class);
        expect($traits)->toContain(\App\Traits\LogsActivityTrait::class);
    });
});

// ============================================================
// Test group: BR-102 Tenant scope isolation
// ============================================================
describe('Tenant scope isolation', function () {
    it('isolates schedules between tenants', function () {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        CookSchedule::factory()->forDay('monday')->create(['tenant_id' => $tenant1->id]);
        CookSchedule::factory()->forDay('tuesday')->create(['tenant_id' => $tenant1->id]);
        CookSchedule::factory()->forDay('monday')->create(['tenant_id' => $tenant2->id]);

        $service = new CookScheduleService;
        $tenant1Schedules = $service->getSchedulesByDay($tenant1);
        $tenant2Schedules = $service->getSchedulesByDay($tenant2);

        $tenant1Total = collect($tenant1Schedules)->flatten()->count();
        $tenant2Total = collect($tenant2Schedules)->flatten()->count();

        expect($tenant1Total)->toBe(2);
        expect($tenant2Total)->toBe(1);
    });
});
