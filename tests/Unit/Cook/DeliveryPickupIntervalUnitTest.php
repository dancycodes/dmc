<?php

/**
 * F-100: Delivery/Pickup Time Interval Configuration -- Unit Tests
 *
 * Tests for CookScheduleController (updateDeliveryPickupInterval),
 * UpdateDeliveryPickupIntervalRequest, CookScheduleService
 * (updateDeliveryPickupInterval), CookSchedule model helpers,
 * CookScheduleFactory states, route, blade view, and translations.
 *
 * BR-116: Delivery/pickup intervals on the open day (day offset 0)
 * BR-117: Delivery start >= order interval end time
 * BR-118: Pickup start >= order interval end time
 * BR-119: Delivery end > delivery start
 * BR-120: Pickup end > pickup start
 * BR-121: At least one must be enabled
 * BR-122: Time format is 24-hour (HH:MM)
 * BR-123: Delivery and pickup windows are independent
 * BR-124: Order interval must be configured before delivery/pickup
 * BR-126: Interval configuration logged via Spatie Activitylog
 */

use App\Http\Controllers\Cook\CookScheduleController;
use App\Http\Requests\Cook\UpdateDeliveryPickupIntervalRequest;
use App\Models\CookSchedule;
use App\Models\Tenant;
use App\Services\CookScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;

$projectRoot = dirname(__DIR__, 3);

/* Database integration tests require full app context */
uses(Tests\TestCase::class, RefreshDatabase::class);

// ============================================================
// Test group: CookScheduleController — updateDeliveryPickupInterval
// ============================================================
describe('CookScheduleController updateDeliveryPickupInterval', function () {
    it('has an updateDeliveryPickupInterval method', function () {
        $reflection = new ReflectionMethod(CookScheduleController::class, 'updateDeliveryPickupInterval');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('updateDeliveryPickupInterval accepts Request, CookSchedule, and CookScheduleService', function () {
        $reflection = new ReflectionMethod(CookScheduleController::class, 'updateDeliveryPickupInterval');
        $params = $reflection->getParameters();
        expect($params)->toHaveCount(3);
        expect($params[0]->getName())->toBe('request');
        expect($params[1]->getName())->toBe('cookSchedule');
        expect($params[2]->getName())->toBe('scheduleService');
    });
});

// ============================================================
// Test group: UpdateDeliveryPickupIntervalRequest
// ============================================================
describe('UpdateDeliveryPickupIntervalRequest', function () {
    it('exists as a class', function () {
        expect(class_exists(UpdateDeliveryPickupIntervalRequest::class))->toBeTrue();
    });

    it('has the correct validation rules', function () {
        $request = new UpdateDeliveryPickupIntervalRequest;
        $rules = $request->rules();

        expect($rules)->toHaveKey('delivery_enabled');
        expect($rules)->toHaveKey('delivery_start_time');
        expect($rules)->toHaveKey('delivery_end_time');
        expect($rules)->toHaveKey('pickup_enabled');
        expect($rules)->toHaveKey('pickup_start_time');
        expect($rules)->toHaveKey('pickup_end_time');
    });

    it('delivery_start_time uses date_format:H:i validation', function () {
        $request = new UpdateDeliveryPickupIntervalRequest;
        $rules = $request->rules();

        expect($rules['delivery_start_time'])->toContain('date_format:H:i');
    });

    it('pickup_start_time uses date_format:H:i validation', function () {
        $request = new UpdateDeliveryPickupIntervalRequest;
        $rules = $request->rules();

        expect($rules['pickup_start_time'])->toContain('date_format:H:i');
    });

    it('has custom error messages', function () {
        $request = new UpdateDeliveryPickupIntervalRequest;
        $messages = $request->messages();

        expect($messages)->not->toBeEmpty();
        expect($messages)->toHaveKey('delivery_start_time.date_format');
        expect($messages)->toHaveKey('pickup_start_time.date_format');
    });

    it('requires can-manage-schedules permission for authorization', function () {
        $user = createUser();
        $request = UpdateDeliveryPickupIntervalRequest::create('/test', 'PUT');
        $request->setUserResolver(fn () => $user);

        expect($request->authorize())->toBeFalse();
    });
});

// ============================================================
// Test group: CookSchedule Model — delivery/pickup helpers
// ============================================================
describe('CookSchedule Model delivery/pickup helpers', function () {
    it('has delivery/pickup fields in fillable', function () {
        $schedule = new CookSchedule;
        $fillable = $schedule->getFillable();

        expect($fillable)->toContain('delivery_enabled');
        expect($fillable)->toContain('delivery_start_time');
        expect($fillable)->toContain('delivery_end_time');
        expect($fillable)->toContain('pickup_enabled');
        expect($fillable)->toContain('pickup_start_time');
        expect($fillable)->toContain('pickup_end_time');
    });

    it('casts delivery_enabled and pickup_enabled to boolean', function () {
        $schedule = new CookSchedule;
        $casts = $schedule->getCasts();

        expect($casts['delivery_enabled'])->toBe('boolean');
        expect($casts['pickup_enabled'])->toBe('boolean');
    });

    it('hasDeliveryInterval returns true when delivery is enabled with times', function () {
        $schedule = new CookSchedule;
        $schedule->delivery_enabled = true;
        $schedule->delivery_start_time = '11:00';
        $schedule->delivery_end_time = '14:00';

        expect($schedule->hasDeliveryInterval())->toBeTrue();
    });

    it('hasDeliveryInterval returns false when delivery is disabled', function () {
        $schedule = new CookSchedule;
        $schedule->delivery_enabled = false;
        $schedule->delivery_start_time = '11:00';
        $schedule->delivery_end_time = '14:00';

        expect($schedule->hasDeliveryInterval())->toBeFalse();
    });

    it('hasDeliveryInterval returns false when times are null', function () {
        $schedule = new CookSchedule;
        $schedule->delivery_enabled = true;
        $schedule->delivery_start_time = null;
        $schedule->delivery_end_time = null;

        expect($schedule->hasDeliveryInterval())->toBeFalse();
    });

    it('hasPickupInterval returns true when pickup is enabled with times', function () {
        $schedule = new CookSchedule;
        $schedule->pickup_enabled = true;
        $schedule->pickup_start_time = '10:30';
        $schedule->pickup_end_time = '15:00';

        expect($schedule->hasPickupInterval())->toBeTrue();
    });

    it('hasPickupInterval returns false when pickup is disabled', function () {
        $schedule = new CookSchedule;
        $schedule->pickup_enabled = false;
        $schedule->pickup_start_time = '10:30';
        $schedule->pickup_end_time = '15:00';

        expect($schedule->hasPickupInterval())->toBeFalse();
    });

    it('hasPickupInterval returns false when times are null', function () {
        $schedule = new CookSchedule;
        $schedule->pickup_enabled = true;
        $schedule->pickup_start_time = null;
        $schedule->pickup_end_time = null;

        expect($schedule->hasPickupInterval())->toBeFalse();
    });

    it('delivery_interval_summary returns formatted summary', function () {
        $schedule = new CookSchedule;
        $schedule->delivery_enabled = true;
        $schedule->delivery_start_time = '11:00';
        $schedule->delivery_end_time = '14:00';

        $summary = $schedule->delivery_interval_summary;
        expect($summary)->toContain('11:00 AM');
        expect($summary)->toContain('2:00 PM');
    });

    it('delivery_interval_summary returns null when no delivery interval', function () {
        $schedule = new CookSchedule;
        $schedule->delivery_enabled = false;

        expect($schedule->delivery_interval_summary)->toBeNull();
    });

    it('pickup_interval_summary returns formatted summary', function () {
        $schedule = new CookSchedule;
        $schedule->pickup_enabled = true;
        $schedule->pickup_start_time = '10:30';
        $schedule->pickup_end_time = '15:00';

        $summary = $schedule->pickup_interval_summary;
        expect($summary)->toContain('10:30 AM');
        expect($summary)->toContain('3:00 PM');
    });

    it('pickup_interval_summary returns null when no pickup interval', function () {
        $schedule = new CookSchedule;
        $schedule->pickup_enabled = false;

        expect($schedule->pickup_interval_summary)->toBeNull();
    });

    it('getOrderEndTimeInMinutes returns null when no order interval', function () {
        $schedule = new CookSchedule;
        $schedule->order_start_time = null;
        $schedule->order_end_time = null;

        expect($schedule->getOrderEndTimeInMinutes())->toBeNull();
    });

    it('getOrderEndTimeInMinutes returns 0 when order ends before open day', function () {
        $schedule = new CookSchedule;
        $schedule->order_start_time = '18:00';
        $schedule->order_start_day_offset = 1;
        $schedule->order_end_time = '20:00';
        $schedule->order_end_day_offset = 1;

        expect($schedule->getOrderEndTimeInMinutes())->toBe(0);
    });

    it('getOrderEndTimeInMinutes returns correct minutes for same day', function () {
        $schedule = new CookSchedule;
        $schedule->order_start_time = '06:00';
        $schedule->order_start_day_offset = 0;
        $schedule->order_end_time = '10:00';
        $schedule->order_end_day_offset = 0;

        expect($schedule->getOrderEndTimeInMinutes())->toBe(600); // 10 * 60
    });

    it('getOrderEndTimeInMinutes handles 08:30 correctly', function () {
        $schedule = new CookSchedule;
        $schedule->order_start_time = '06:00';
        $schedule->order_start_day_offset = 0;
        $schedule->order_end_time = '08:30';
        $schedule->order_end_day_offset = 0;

        expect($schedule->getOrderEndTimeInMinutes())->toBe(510); // 8 * 60 + 30
    });
});

// ============================================================
// Test group: CookScheduleService — updateDeliveryPickupInterval
// ============================================================
describe('CookScheduleService updateDeliveryPickupInterval', function () {
    beforeEach(function () {
        $this->seedRolesAndPermissions();
        $this->service = new CookScheduleService;
    });

    it('saves delivery only interval successfully', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->withSameDayInterval('06:00', '10:00')
            ->create();

        $result = $this->service->updateDeliveryPickupInterval(
            $schedule,
            true,
            '11:00',
            '14:00',
            false,
            null,
            null,
        );

        expect($result['success'])->toBeTrue();
        expect($result['schedule']->delivery_enabled)->toBeTrue();
        expect(substr($result['schedule']->delivery_start_time, 0, 5))->toBe('11:00');
        expect(substr($result['schedule']->delivery_end_time, 0, 5))->toBe('14:00');
        expect($result['schedule']->pickup_enabled)->toBeFalse();
    });

    it('saves pickup only interval successfully', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('tuesday')
            ->withSameDayInterval('06:00', '10:00')
            ->create();

        $result = $this->service->updateDeliveryPickupInterval(
            $schedule,
            false,
            null,
            null,
            true,
            '10:30',
            '15:00',
        );

        expect($result['success'])->toBeTrue();
        expect($result['schedule']->delivery_enabled)->toBeFalse();
        expect($result['schedule']->pickup_enabled)->toBeTrue();
        expect(substr($result['schedule']->pickup_start_time, 0, 5))->toBe('10:30');
        expect(substr($result['schedule']->pickup_end_time, 0, 5))->toBe('15:00');
    });

    it('saves both delivery and pickup intervals', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('wednesday')
            ->withSameDayInterval('06:00', '10:00')
            ->create();

        $result = $this->service->updateDeliveryPickupInterval(
            $schedule,
            true,
            '11:00',
            '14:00',
            true,
            '10:30',
            '15:00',
        );

        expect($result['success'])->toBeTrue();
        expect($result['schedule']->delivery_enabled)->toBeTrue();
        expect($result['schedule']->pickup_enabled)->toBeTrue();
    });

    it('fails when unavailable entry', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->unavailable()
            ->create();

        $result = $this->service->updateDeliveryPickupInterval(
            $schedule,
            true,
            '11:00',
            '14:00',
            false,
            null,
            null,
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('available');
    });

    it('fails when no order interval configured (BR-124)', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->create(); // No order interval

        $result = $this->service->updateDeliveryPickupInterval(
            $schedule,
            true,
            '11:00',
            '14:00',
            false,
            null,
            null,
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('order interval');
    });

    it('fails when both delivery and pickup disabled (BR-121)', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->withSameDayInterval('06:00', '10:00')
            ->create();

        $result = $this->service->updateDeliveryPickupInterval(
            $schedule,
            false,
            null,
            null,
            false,
            null,
            null,
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('At least one');
    });

    it('fails when delivery end before start (BR-119)', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->withSameDayInterval('06:00', '10:00')
            ->create();

        $result = $this->service->updateDeliveryPickupInterval(
            $schedule,
            true,
            '14:00',
            '11:00', // End before start
            false,
            null,
            null,
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('after the start time');
    });

    it('fails when pickup end before start (BR-120)', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->withSameDayInterval('06:00', '10:00')
            ->create();

        $result = $this->service->updateDeliveryPickupInterval(
            $schedule,
            false,
            null,
            null,
            true,
            '15:00',
            '10:00', // End before start
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('after the start time');
    });

    it('fails when delivery start before order interval end (BR-117)', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->withSameDayInterval('06:00', '10:00')
            ->create();

        $result = $this->service->updateDeliveryPickupInterval(
            $schedule,
            true,
            '09:00', // Before order end 10:00
            '14:00',
            false,
            null,
            null,
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('order interval end time');
    });

    it('fails when pickup start before order interval end (BR-118)', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->withSameDayInterval('06:00', '10:00')
            ->create();

        $result = $this->service->updateDeliveryPickupInterval(
            $schedule,
            false,
            null,
            null,
            true,
            '09:00', // Before order end 10:00
            '14:00',
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('order interval end time');
    });

    it('succeeds when delivery starts exactly at order interval end', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->withSameDayInterval('06:00', '10:00')
            ->create();

        $result = $this->service->updateDeliveryPickupInterval(
            $schedule,
            true,
            '10:00', // Exactly at order end
            '14:00',
            false,
            null,
            null,
        );

        expect($result['success'])->toBeTrue();
    });

    it('succeeds when pickup starts exactly at order interval end', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->withSameDayInterval('06:00', '10:00')
            ->create();

        $result = $this->service->updateDeliveryPickupInterval(
            $schedule,
            false,
            null,
            null,
            true,
            '10:00', // Exactly at order end
            '15:00',
        );

        expect($result['success'])->toBeTrue();
    });

    it('allows any time when order ends before open day (offset > 0)', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->withOrderInterval('18:00', 1, '20:00', 1) // Ends day before
            ->create();

        $result = $this->service->updateDeliveryPickupInterval(
            $schedule,
            true,
            '06:00', // Very early — valid since order ends previous day
            '14:00',
            false,
            null,
            null,
        );

        expect($result['success'])->toBeTrue();
    });

    it('clears times when disabling delivery', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->withSameDayInterval('06:00', '10:00')
            ->withDeliveryInterval('11:00', '14:00')
            ->create();

        $result = $this->service->updateDeliveryPickupInterval(
            $schedule,
            false,
            null,
            null,
            true,
            '10:30',
            '15:00',
        );

        expect($result['success'])->toBeTrue();
        expect($result['schedule']->delivery_enabled)->toBeFalse();
        expect($result['schedule']->delivery_start_time)->toBeNull();
        expect($result['schedule']->delivery_end_time)->toBeNull();
    });

    it('clears times when disabling pickup', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->withSameDayInterval('06:00', '10:00')
            ->withPickupInterval('10:30', '15:00')
            ->create();

        $result = $this->service->updateDeliveryPickupInterval(
            $schedule,
            true,
            '11:00',
            '14:00',
            false,
            null,
            null,
        );

        expect($result['success'])->toBeTrue();
        expect($result['schedule']->pickup_enabled)->toBeFalse();
        expect($result['schedule']->pickup_start_time)->toBeNull();
        expect($result['schedule']->pickup_end_time)->toBeNull();
    });

    it('fails when delivery enabled but times missing', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->withSameDayInterval('06:00', '10:00')
            ->create();

        $result = $this->service->updateDeliveryPickupInterval(
            $schedule,
            true,
            null, // Missing start time
            null, // Missing end time
            false,
            null,
            null,
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('required');
    });

    it('fails when pickup enabled but times missing', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->withSameDayInterval('06:00', '10:00')
            ->create();

        $result = $this->service->updateDeliveryPickupInterval(
            $schedule,
            false,
            null,
            null,
            true,
            null, // Missing start time
            null, // Missing end time
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('required');
    });
});

// ============================================================
// Test group: CookScheduleFactory — delivery/pickup states
// ============================================================
describe('CookScheduleFactory delivery/pickup states', function () {
    it('withDeliveryInterval creates schedule with delivery interval', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->withDeliveryInterval('11:00', '14:00')
            ->create();

        expect($schedule->delivery_enabled)->toBeTrue();
        expect(substr($schedule->delivery_start_time, 0, 5))->toBe('11:00');
        expect(substr($schedule->delivery_end_time, 0, 5))->toBe('14:00');
    });

    it('withPickupInterval creates schedule with pickup interval', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('tuesday')
            ->withPickupInterval('10:30', '15:00')
            ->create();

        expect($schedule->pickup_enabled)->toBeTrue();
        expect(substr($schedule->pickup_start_time, 0, 5))->toBe('10:30');
        expect(substr($schedule->pickup_end_time, 0, 5))->toBe('15:00');
    });

    it('withBothIntervals creates schedule with both intervals', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('wednesday')
            ->withBothIntervals()
            ->create();

        expect($schedule->delivery_enabled)->toBeTrue();
        expect($schedule->pickup_enabled)->toBeTrue();
    });
});

// ============================================================
// Test group: Route configuration
// ============================================================
describe('Route configuration', function () use ($projectRoot) {
    it('delivery-pickup-interval route is registered', function () use ($projectRoot) {
        $routeFileContent = file_get_contents($projectRoot.'/routes/web.php');

        expect($routeFileContent)->toContain('delivery-pickup-interval');
        expect($routeFileContent)->toContain('updateDeliveryPickupInterval');
    });

    it('uses PUT method for delivery-pickup-interval', function () use ($projectRoot) {
        $routeFileContent = file_get_contents($projectRoot.'/routes/web.php');

        expect($routeFileContent)->toContain("Route::put('/schedule/{cookSchedule}/delivery-pickup-interval'");
    });
});

// ============================================================
// Test group: Blade view
// ============================================================
describe('Blade view delivery/pickup sections', function () use ($projectRoot) {
    it('contains delivery/pickup form elements', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/index.blade.php');

        expect($viewContent)->toContain('delivery_enabled');
        expect($viewContent)->toContain('delivery_start_time');
        expect($viewContent)->toContain('delivery_end_time');
        expect($viewContent)->toContain('pickup_enabled');
        expect($viewContent)->toContain('pickup_start_time');
        expect($viewContent)->toContain('pickup_end_time');
    });

    it('contains x-sync for delivery/pickup state keys', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/index.blade.php');

        expect($viewContent)->toContain("'delivery_enabled'");
        expect($viewContent)->toContain("'delivery_start_time'");
        expect($viewContent)->toContain("'delivery_end_time'");
        expect($viewContent)->toContain("'pickup_enabled'");
        expect($viewContent)->toContain("'pickup_start_time'");
        expect($viewContent)->toContain("'pickup_end_time'");
    });

    it('contains delivery/pickup interval form URL', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/index.blade.php');

        expect($viewContent)->toContain('delivery-pickup-interval');
    });

    it('has toggle switches for delivery and pickup', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/index.blade.php');

        expect($viewContent)->toContain('role="switch"');
        expect($viewContent)->toContain("delivery_enabled === 'true'");
        expect($viewContent)->toContain("pickup_enabled === 'true'");
    });

    it('shows BR-121 warning when both disabled', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/index.blade.php');

        expect($viewContent)->toContain("delivery_enabled === 'false' && pickup_enabled === 'false'");
    });

    it('has delivery and pickup preview text', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/index.blade.php');

        expect($viewContent)->toContain('getDeliveryPreview()');
        expect($viewContent)->toContain('getPickupPreview()');
    });

    it('uses gale view response (not bare view)', function () use ($projectRoot) {
        $controllerContent = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/CookScheduleController.php');

        expect($controllerContent)->toContain("gale()->view('cook.schedule.index'");
        expect($controllerContent)->not->toContain("return view('cook.schedule.index'");
    });

    it('uses __() translation helper for user-facing strings', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/index.blade.php');

        expect($viewContent)->toContain("__('Delivery')");
        expect($viewContent)->toContain("__('Pickup')");
        expect($viewContent)->toContain("__('Enabled')");
        expect($viewContent)->toContain("__('Disabled')");
        expect($viewContent)->toContain("__('Save Intervals')");
    });

    it('uses static Blade for loops for time select options', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/index.blade.php');

        // Should use @for loops (not Alpine x-for) for time select options
        expect($viewContent)->toContain('@for($h = 0; $h < 24; $h++)');
        expect($viewContent)->not->toContain('x-for="time in');
    });

    it('shows order interval hint when not configured', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/index.blade.php');

        expect($viewContent)->toContain('Set order interval first');
    });

    it('has delivery and pickup interval summary display', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/index.blade.php');

        expect($viewContent)->toContain('delivery_interval_summary');
        expect($viewContent)->toContain('pickup_interval_summary');
    });

    it('has delivery/pickup badges in entry row', function () use ($projectRoot) {
        $viewContent = file_get_contents($projectRoot.'/resources/views/cook/schedule/index.blade.php');

        expect($viewContent)->toContain('hasDeliveryInterval()');
        expect($viewContent)->toContain('hasPickupInterval()');
    });
});

// ============================================================
// Test group: Migration
// ============================================================
describe('Migration columns exist', function () {
    it('cook_schedules table has delivery columns', function () {
        expect(\Schema::hasColumn('cook_schedules', 'delivery_enabled'))->toBeTrue();
        expect(\Schema::hasColumn('cook_schedules', 'delivery_start_time'))->toBeTrue();
        expect(\Schema::hasColumn('cook_schedules', 'delivery_end_time'))->toBeTrue();
    });

    it('cook_schedules table has pickup columns', function () {
        expect(\Schema::hasColumn('cook_schedules', 'pickup_enabled'))->toBeTrue();
        expect(\Schema::hasColumn('cook_schedules', 'pickup_start_time'))->toBeTrue();
        expect(\Schema::hasColumn('cook_schedules', 'pickup_end_time'))->toBeTrue();
    });
});

// ============================================================
// Test group: Activity logging
// ============================================================
describe('Activity logging', function () {
    it('logs delivery/pickup interval configuration', function () {
        $this->seedRolesAndPermissions();
        $tenant = Tenant::factory()->create();
        $user = createUser();
        $user->assignRole('cook');

        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->withSameDayInterval('06:00', '10:00')
            ->create();

        $service = new CookScheduleService;

        $service->updateDeliveryPickupInterval(
            $schedule,
            true,
            '11:00',
            '14:00',
            false,
            null,
            null,
        );

        // The model's LogsActivityTrait logs the update automatically
        $latestLog = \Spatie\Activitylog\Models\Activity::query()
            ->where('subject_type', CookSchedule::class)
            ->where('subject_id', $schedule->id)
            ->latest()
            ->first();

        expect($latestLog)->not->toBeNull();
    });
});

// ============================================================
// Test group: HTTP integration
// ============================================================
describe('HTTP integration', function () {
    it('denies access without can-manage-schedules permission via controller check', function () {
        $this->seedRolesAndPermissions();

        // Verify that the controller explicitly checks for can-manage-schedules
        $controllerContent = file_get_contents(app_path('Http/Controllers/Cook/CookScheduleController.php'));
        expect($controllerContent)->toContain("can('can-manage-schedules')");
        expect($controllerContent)->toContain('abort(403)');
    });

    it('returns 404 for schedule from different tenant', function () {
        $this->seedRolesAndPermissions();
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        $user = createUser();
        $user->assignRole('cook');

        $schedule = CookSchedule::factory()
            ->for($tenant2) // Different tenant
            ->forDay('monday')
            ->withSameDayInterval('06:00', '10:00')
            ->create();

        $response = $this->actingAs($user)
            ->put(
                "https://{$tenant1->slug}.".config('app.main_domain')."/dashboard/schedule/{$schedule->id}/delivery-pickup-interval",
                [
                    'delivery_enabled' => 'true',
                    'delivery_start_time' => '11:00',
                    'delivery_end_time' => '14:00',
                    'pickup_enabled' => 'false',
                ],
            );

        expect($response->status())->toBe(404);
    });
});
