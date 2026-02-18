<?php

/**
 * F-099: Order Time Interval Configuration -- Unit Tests
 *
 * Tests for order interval configuration on CookSchedule model,
 * CookScheduleService interval methods, UpdateOrderIntervalRequest,
 * controller methods, route configuration, translation strings,
 * and blade view.
 *
 * BR-106: Order interval start = time + day offset (0-7)
 * BR-107: Order interval end = time + day offset (0-1)
 * BR-108: Start must be chronologically before end
 * BR-109: Time format is 24-hour (HH:MM)
 * BR-110: Start day offset max 7
 * BR-111: End day offset max 1
 * BR-112: Only available entries can have intervals configured
 * BR-115: Interval config logged via Spatie Activitylog
 */

use App\Http\Controllers\Cook\CookScheduleController;
use App\Http\Requests\Cook\UpdateOrderIntervalRequest;
use App\Models\CookSchedule;
use App\Models\Tenant;
use App\Services\CookScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;

$projectRoot = dirname(__DIR__, 3);

uses(Tests\TestCase::class, RefreshDatabase::class);

// ============================================================
// Test group: CookSchedule Model — Order Interval
// ============================================================
describe('CookSchedule Model - Order Interval', function () {
    it('has order interval fields in fillable', function () {
        $schedule = new CookSchedule;
        $fillable = $schedule->getFillable();

        expect($fillable)->toContain('order_start_time');
        expect($fillable)->toContain('order_start_day_offset');
        expect($fillable)->toContain('order_end_time');
        expect($fillable)->toContain('order_end_day_offset');
    });

    it('casts order day offsets to integer', function () {
        $schedule = new CookSchedule;
        $casts = $schedule->getCasts();

        expect($casts['order_start_day_offset'])->toBe('integer');
        expect($casts['order_end_day_offset'])->toBe('integer');
    });

    it('has MAX_START_DAY_OFFSET constant set to 7', function () {
        expect(CookSchedule::MAX_START_DAY_OFFSET)->toBe(7);
    });

    it('has MAX_END_DAY_OFFSET constant set to 1', function () {
        expect(CookSchedule::MAX_END_DAY_OFFSET)->toBe(1);
    });

    it('detects when order interval is configured', function () {
        $schedule = new CookSchedule([
            'order_start_time' => '18:00',
            'order_start_day_offset' => 1,
            'order_end_time' => '08:00',
            'order_end_day_offset' => 0,
        ]);

        expect($schedule->hasOrderInterval())->toBeTrue();
    });

    it('detects when order interval is not configured', function () {
        $schedule = new CookSchedule([
            'order_start_time' => null,
            'order_end_time' => null,
        ]);

        expect($schedule->hasOrderInterval())->toBeFalse();
    });

    it('returns null summary when no interval configured', function () {
        $schedule = new CookSchedule([
            'order_start_time' => null,
            'order_end_time' => null,
        ]);

        expect($schedule->order_interval_summary)->toBeNull();
    });

    it('returns human-readable summary for day-before-to-same-day interval', function () {
        $schedule = new CookSchedule([
            'order_start_time' => '18:00',
            'order_start_day_offset' => 1,
            'order_end_time' => '08:00',
            'order_end_day_offset' => 0,
        ]);

        $summary = $schedule->order_interval_summary;
        expect($summary)->toContain('6:00 PM');
        expect($summary)->toContain('8:00 AM');
    });

    it('returns human-readable summary for same-day interval', function () {
        $schedule = new CookSchedule([
            'order_start_time' => '06:00',
            'order_start_day_offset' => 0,
            'order_end_time' => '10:00',
            'order_end_day_offset' => 0,
        ]);

        $summary = $schedule->order_interval_summary;
        expect($summary)->toContain('6:00 AM');
        expect($summary)->toContain('10:00 AM');
    });

    it('formats day offset 0 as same day', function () {
        expect(CookSchedule::formatDayOffset(0))->toBe(__('same day'));
    });

    it('formats day offset 1 as day before', function () {
        expect(CookSchedule::formatDayOffset(1))->toBe(__('day before'));
    });

    it('formats day offset 2+ as N days before', function () {
        expect(CookSchedule::formatDayOffset(3))->toBe(__(':count days before', ['count' => 3]));
    });

    it('returns start day offset options from 0 to 7', function () {
        $options = CookSchedule::getStartDayOffsetOptions();
        expect($options)->toHaveCount(8);
        expect(array_key_exists(0, $options))->toBeTrue();
        expect(array_key_exists(7, $options))->toBeTrue();
    });

    it('returns end day offset options from 0 to 1', function () {
        $options = CookSchedule::getEndDayOffsetOptions();
        expect($options)->toHaveCount(2);
        expect(array_key_exists(0, $options))->toBeTrue();
        expect(array_key_exists(1, $options))->toBeTrue();
    });
});

// ============================================================
// Test group: CookSchedule Factory — Order Interval
// ============================================================
describe('CookScheduleFactory - Order Interval', function () {
    it('creates schedule with order interval via withOrderInterval', function () {
        $schedule = CookSchedule::factory()
            ->withOrderInterval('18:00', 1, '08:00', 0)
            ->create();

        expect($schedule->order_start_time)->toContain('18:00');
        expect($schedule->order_start_day_offset)->toBe(1);
        expect($schedule->order_end_time)->toContain('08:00');
        expect($schedule->order_end_day_offset)->toBe(0);
        expect($schedule->is_available)->toBeTrue();
    });

    it('creates schedule with same-day interval via withSameDayInterval', function () {
        $schedule = CookSchedule::factory()
            ->withSameDayInterval('06:00', '10:00')
            ->create();

        expect($schedule->order_start_time)->toContain('06:00');
        expect($schedule->order_start_day_offset)->toBe(0);
        expect($schedule->order_end_time)->toContain('10:00');
        expect($schedule->order_end_day_offset)->toBe(0);
    });
});

// ============================================================
// Test group: CookScheduleService — Order Interval
// ============================================================
describe('CookScheduleService - Order Interval', function () {
    it('updates order interval successfully', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()->forDay('monday')->create([
            'tenant_id' => $tenant->id,
        ]);

        $service = app(CookScheduleService::class);
        $result = $service->updateOrderInterval(
            $schedule, '18:00', 1, '08:00', 0
        );

        expect($result['success'])->toBeTrue();
        expect($result['schedule']->order_start_time)->toContain('18:00');
        expect($result['schedule']->order_start_day_offset)->toBe(1);
        expect($result['schedule']->order_end_time)->toContain('08:00');
        expect($result['schedule']->order_end_day_offset)->toBe(0);
    });

    it('rejects interval on unavailable entries (BR-112)', function () {
        $schedule = CookSchedule::factory()->unavailable()->create();

        $service = app(CookScheduleService::class);
        $result = $service->updateOrderInterval(
            $schedule, '06:00', 0, '10:00', 0
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain(__('available'));
    });

    it('rejects start day offset exceeding 7 (BR-110)', function () {
        $schedule = CookSchedule::factory()->create();

        $service = app(CookScheduleService::class);
        $result = $service->updateOrderInterval(
            $schedule, '06:00', 8, '10:00', 0
        );

        expect($result['success'])->toBeFalse();
    });

    it('rejects end day offset exceeding 1 (BR-111)', function () {
        $schedule = CookSchedule::factory()->create();

        $service = app(CookScheduleService::class);
        $result = $service->updateOrderInterval(
            $schedule, '06:00', 0, '10:00', 2
        );

        expect($result['success'])->toBeFalse();
    });

    it('rejects chronologically invalid interval (BR-108) - end before start same day', function () {
        $schedule = CookSchedule::factory()->create();

        $service = app(CookScheduleService::class);
        $result = $service->updateOrderInterval(
            $schedule, '10:00', 0, '08:00', 0
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain(__('after'));
    });

    it('rejects chronologically invalid interval (BR-108) - same time', function () {
        $schedule = CookSchedule::factory()->create();

        $service = app(CookScheduleService::class);
        $result = $service->updateOrderInterval(
            $schedule, '10:00', 0, '10:00', 0
        );

        expect($result['success'])->toBeFalse();
    });

    it('accepts valid day-before-to-same-day interval', function () {
        $schedule = CookSchedule::factory()->create();

        $service = app(CookScheduleService::class);
        $result = $service->updateOrderInterval(
            $schedule, '18:00', 1, '08:00', 0
        );

        expect($result['success'])->toBeTrue();
    });

    it('accepts valid same-day interval', function () {
        $schedule = CookSchedule::factory()->create();

        $service = app(CookScheduleService::class);
        $result = $service->updateOrderInterval(
            $schedule, '06:00', 0, '10:00', 0
        );

        expect($result['success'])->toBeTrue();
    });

    it('accepts valid multi-day-before interval', function () {
        $schedule = CookSchedule::factory()->create();

        $service = app(CookScheduleService::class);
        $result = $service->updateOrderInterval(
            $schedule, '12:00', 2, '18:00', 1
        );

        expect($result['success'])->toBeTrue();
    });

    it('validates midnight crossing correctly (start 11PM day before, end 1AM same day)', function () {
        $schedule = CookSchedule::factory()->create();

        $service = app(CookScheduleService::class);
        $result = $service->updateOrderInterval(
            $schedule, '23:00', 1, '01:00', 0
        );

        expect($result['success'])->toBeTrue();
    });

    it('removes order interval', function () {
        $schedule = CookSchedule::factory()->withOrderInterval()->create();

        $service = app(CookScheduleService::class);
        $result = $service->removeOrderInterval($schedule);

        expect($result['success'])->toBeTrue();
        expect($result['schedule']->order_start_time)->toBeNull();
        expect($result['schedule']->order_end_time)->toBeNull();
    });

    it('validates chronological order correctly via isIntervalChronologicallyValid', function () {
        $service = app(CookScheduleService::class);

        // Same day, start before end
        expect($service->isIntervalChronologicallyValid('06:00', 0, '10:00', 0))->toBeTrue();

        // Same day, end before start
        expect($service->isIntervalChronologicallyValid('10:00', 0, '06:00', 0))->toBeFalse();

        // Day before to same day
        expect($service->isIntervalChronologicallyValid('18:00', 1, '08:00', 0))->toBeTrue();

        // 2 days before to day before
        expect($service->isIntervalChronologicallyValid('12:00', 2, '18:00', 1))->toBeTrue();

        // Same time = not valid (zero duration)
        expect($service->isIntervalChronologicallyValid('08:00', 0, '08:00', 0))->toBeFalse();
    });
});

// ============================================================
// Test group: UpdateOrderIntervalRequest
// ============================================================
describe('UpdateOrderIntervalRequest', function () {
    it('has validation rules for all interval fields', function () {
        $request = new UpdateOrderIntervalRequest;
        $rules = $request->rules();

        expect($rules)->toHaveKey('order_start_time');
        expect($rules)->toHaveKey('order_start_day_offset');
        expect($rules)->toHaveKey('order_end_time');
        expect($rules)->toHaveKey('order_end_day_offset');
    });

    it('requires order_start_time with ValidTimeFormat', function () {
        $request = new UpdateOrderIntervalRequest;
        $rules = $request->rules();

        expect($rules['order_start_time'])->toContain('required');
        $hasValidTimeFormat = collect($rules['order_start_time'])
            ->contains(fn ($rule) => $rule instanceof \App\Rules\ValidTimeFormat);
        expect($hasValidTimeFormat)->toBeTrue();
    });

    it('limits order_start_day_offset to 0-7', function () {
        $request = new UpdateOrderIntervalRequest;
        $rules = $request->rules();

        expect($rules['order_start_day_offset'])->toContain('min:0');
        expect($rules['order_start_day_offset'])->toContain('max:7');
    });

    it('limits order_end_day_offset to 0-1', function () {
        $request = new UpdateOrderIntervalRequest;
        $rules = $request->rules();

        expect($rules['order_end_day_offset'])->toContain('min:0');
        expect($rules['order_end_day_offset'])->toContain('max:1');
    });

    it('has custom error messages', function () {
        $request = new UpdateOrderIntervalRequest;
        $messages = $request->messages();

        expect($messages)->toHaveKey('order_start_time.required');
        expect($messages)->toHaveKey('order_start_day_offset.max');
        expect($messages)->toHaveKey('order_end_time.required');
        expect($messages)->toHaveKey('order_end_day_offset.max');
    });
});

// ============================================================
// Test group: CookScheduleController — Order Interval
// ============================================================
describe('CookScheduleController - updateOrderInterval', function () {
    it('has an updateOrderInterval method', function () {
        $reflection = new ReflectionMethod(CookScheduleController::class, 'updateOrderInterval');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('updateOrderInterval method accepts Request, CookSchedule, and CookScheduleService parameters', function () {
        $reflection = new ReflectionMethod(CookScheduleController::class, 'updateOrderInterval');
        $params = $reflection->getParameters();

        expect($params)->toHaveCount(3);
        expect($params[0]->getName())->toBe('request');
        expect($params[1]->getName())->toBe('cookSchedule');
        expect($params[2]->getName())->toBe('scheduleService');
    });
});

// ============================================================
// Test group: Route configuration
// ============================================================
describe('Order interval route', function () use ($projectRoot) {
    it('has the order interval PUT route', function () use ($projectRoot) {
        $routeContent = file_get_contents($projectRoot.'/routes/web.php');
        expect($routeContent)->toContain("Route::put('/schedule/{cookSchedule}/order-interval'");
        expect($routeContent)->toContain("'cook.schedule.update-order-interval'");
    });
});

// ============================================================
// Test group: View file updates
// ============================================================
describe('Schedule view - Order Interval', function () use ($projectRoot) {
    it('contains order interval configuration section', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/schedule/index.blade.php');
        expect($content)->toContain("__('Order Time Interval')");
    });

    it('contains time interval form elements', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/schedule/index.blade.php');
        expect($content)->toContain("__('Start accepting orders')");
        expect($content)->toContain("__('Stop accepting orders')");
        expect($content)->toContain("__('Save Interval')");
    });

    it('contains interval preview', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/schedule/index.blade.php');
        expect($content)->toContain("__('Preview')");
        expect($content)->toContain('getIntervalPreview()');
    });

    it('contains order interval summary display', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/schedule/index.blade.php');
        expect($content)->toContain('order_interval_summary');
    });

    it('uses x-sync for interval state keys', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/schedule/index.blade.php');
        expect($content)->toContain('order_start_time');
        expect($content)->toContain('order_start_day_offset');
        expect($content)->toContain('order_end_time');
        expect($content)->toContain('order_end_day_offset');
    });

    it('uses $action with PUT method for interval update', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/schedule/index.blade.php');
        expect($content)->toContain("method: 'PUT'");
    });

    it('uses static Blade @for loops for time options', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/schedule/index.blade.php');
        expect($content)->toContain('@for($h = 0;');
    });

    it('shows no intervals text for unavailable entries', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/schedule/index.blade.php');
        expect($content)->toContain("__('No intervals')");
    });

    it('uses $fetching() for loading state', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/schedule/index.blade.php');
        expect($content)->toContain('$fetching()');
    });
});

// ============================================================
// Test group: Translation strings
// ============================================================
describe('Translation strings - Order Interval', function () use ($projectRoot) {
    it('has English translation strings for order interval', function () use ($projectRoot) {
        $enJson = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);
        expect($enJson)->toHaveKey('Order Time Interval');
        expect($enJson)->toHaveKey('Start accepting orders');
        expect($enJson)->toHaveKey('Stop accepting orders');
        expect($enJson)->toHaveKey('Save Interval');
        expect($enJson)->toHaveKey('Order interval configured successfully.');
        expect($enJson)->toHaveKey('same day');
        expect($enJson)->toHaveKey('day before');
    });

    it('has French translation strings for order interval', function () use ($projectRoot) {
        $frJson = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);
        expect($frJson)->toHaveKey('Order Time Interval');
        expect($frJson)->toHaveKey('Start accepting orders');
        expect($frJson)->toHaveKey('Save Interval');
        expect($frJson['Order Time Interval'])->toBe('Intervalle de temps des commandes');
    });
});

// ============================================================
// Test group: Database schema
// ============================================================
describe('cook_schedules table - Order Interval columns', function () {
    it('has order_start_time column', function () {
        $columns = \Schema::getColumnListing('cook_schedules');
        expect($columns)->toContain('order_start_time');
    });

    it('has order_start_day_offset column', function () {
        $columns = \Schema::getColumnListing('cook_schedules');
        expect($columns)->toContain('order_start_day_offset');
    });

    it('has order_end_time column', function () {
        $columns = \Schema::getColumnListing('cook_schedules');
        expect($columns)->toContain('order_end_time');
    });

    it('has order_end_day_offset column', function () {
        $columns = \Schema::getColumnListing('cook_schedules');
        expect($columns)->toContain('order_end_day_offset');
    });
});

// ============================================================
// Test group: Migration file existence
// ============================================================
describe('Order interval migration', function () use ($projectRoot) {
    it('has the migration file', function () use ($projectRoot) {
        $files = glob($projectRoot.'/database/migrations/*add_order_interval_fields_to_cook_schedules_table*');
        expect($files)->not->toBeEmpty();
    });
});
