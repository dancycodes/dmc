<?php

/**
 * F-107: Schedule Validation Rules -- Feature Tests
 *
 * Tests schedule validation rules through the service layer with full
 * database integration, verifying that ScheduleValidationService and
 * CookScheduleService correctly enforce business rules (BR-172 through BR-186)
 * when processing schedule operations against real database records.
 *
 * These are feature tests (not unit tests) because they exercise the full
 * service pipeline with actual database operations, tenant scoping,
 * and integration between CookScheduleService and ScheduleValidationService.
 */

use App\Models\CookSchedule;
use App\Models\Tenant;
use App\Services\CookScheduleService;
use App\Services\ScheduleValidationService;

// ============================================================
// Test group: Service layer DI integration
// ============================================================
describe('Service container integration', function () {
    it('resolves CookScheduleService with injected ScheduleValidationService', function () {
        $service = app(CookScheduleService::class);
        expect($service)->toBeInstanceOf(CookScheduleService::class);
    });

    it('resolves ScheduleValidationService as a standalone service', function () {
        $service = app(ScheduleValidationService::class);
        expect($service)->toBeInstanceOf(ScheduleValidationService::class);
    });
});

// ============================================================
// Test group: Order interval update with full validation (BR-173, BR-179, BR-180, BR-181)
// ============================================================
describe('Order interval update via CookScheduleService', function () {
    it('saves valid order interval to database', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'is_available' => true,
        ]);

        $service = app(CookScheduleService::class);
        $result = $service->updateOrderInterval($schedule, '18:00', 1, '08:00', 0);

        expect($result['success'])->toBeTrue();

        $fresh = $schedule->fresh();
        expect($fresh->order_start_time)->not->toBeNull();
        expect($fresh->order_start_day_offset)->toBe(1);
        expect($fresh->order_end_day_offset)->toBe(0);
    });

    it('rejects chronologically invalid interval (BR-173)', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'is_available' => true,
        ]);

        $service = app(CookScheduleService::class);
        $result = $service->updateOrderInterval($schedule, '14:00', 0, '08:00', 0);

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toBeString();
    });

    it('rejects start day offset exceeding max (BR-180)', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'is_available' => true,
        ]);

        $service = app(CookScheduleService::class);
        $result = $service->updateOrderInterval($schedule, '18:00', 8, '08:00', 0);

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('days');
    });

    it('rejects end day offset exceeding max (BR-181)', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'is_available' => true,
        ]);

        $service = app(CookScheduleService::class);
        $result = $service->updateOrderInterval($schedule, '18:00', 1, '08:00', 2);

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('offset');
    });

    it('rejects overlapping order windows on the same day (BR-179)', function () {
        $tenant = Tenant::factory()->create();

        // First entry with order interval: 18:00 day-before to 08:00 same-day
        CookSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'day_of_week' => 'monday',
            'is_available' => true,
            'order_start_time' => '18:00',
            'order_start_day_offset' => 1,
            'order_end_time' => '08:00',
            'order_end_day_offset' => 0,
            'position' => 1,
        ]);

        // Second entry on same day, no interval yet
        $schedule2 = CookSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'day_of_week' => 'monday',
            'is_available' => true,
            'position' => 2,
        ]);

        $service = app(CookScheduleService::class);

        // Overlapping: 20:00 day-before to 07:00 same-day
        $result = $service->updateOrderInterval($schedule2, '20:00', 1, '07:00', 0);

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('overlaps');
    });

    it('allows adjacent non-overlapping order windows (BR-179 edge case)', function () {
        $tenant = Tenant::factory()->create();

        // First entry: 18:00 day-before to 08:00 same-day
        CookSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'day_of_week' => 'monday',
            'is_available' => true,
            'order_start_time' => '18:00',
            'order_start_day_offset' => 1,
            'order_end_time' => '08:00',
            'order_end_day_offset' => 0,
            'position' => 1,
        ]);

        // Second entry on same day
        $schedule2 = CookSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'day_of_week' => 'monday',
            'is_available' => true,
            'position' => 2,
        ]);

        $service = app(CookScheduleService::class);

        // Adjacent: starts exactly when first ends
        $result = $service->updateOrderInterval($schedule2, '08:00', 0, '12:00', 0);

        expect($result['success'])->toBeTrue();
    });

    it('does not report overlap when editing the same entry', function () {
        $tenant = Tenant::factory()->create();

        // Schedule with existing interval
        $schedule = CookSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'day_of_week' => 'monday',
            'is_available' => true,
            'order_start_time' => '18:00',
            'order_start_day_offset' => 1,
            'order_end_time' => '08:00',
            'order_end_day_offset' => 0,
            'position' => 1,
        ]);

        $service = app(CookScheduleService::class);

        // Slightly shifting own interval should not detect self-overlap
        $result = $service->updateOrderInterval($schedule, '17:00', 1, '09:00', 0);

        expect($result['success'])->toBeTrue();
    });

    it('only checks overlaps within the same tenant', function () {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        // Tenant 1 entry
        CookSchedule::factory()->create([
            'tenant_id' => $tenant1->id,
            'day_of_week' => 'monday',
            'is_available' => true,
            'order_start_time' => '18:00',
            'order_start_day_offset' => 1,
            'order_end_time' => '08:00',
            'order_end_day_offset' => 0,
            'position' => 1,
        ]);

        // Tenant 2 entry with same day
        $schedule2 = CookSchedule::factory()->create([
            'tenant_id' => $tenant2->id,
            'day_of_week' => 'monday',
            'is_available' => true,
            'position' => 1,
        ]);

        $service = app(CookScheduleService::class);

        // Same window but different tenant -- should succeed
        $result = $service->updateOrderInterval($schedule2, '18:00', 1, '08:00', 0);

        expect($result['success'])->toBeTrue();
    });

    it('rejects interval update on unavailable schedule entry (BR-112)', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()->unavailable()->create([
            'tenant_id' => $tenant->id,
        ]);

        $service = app(CookScheduleService::class);
        $result = $service->updateOrderInterval($schedule, '18:00', 1, '08:00', 0);

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('available');
    });
});

// ============================================================
// Test group: Delivery/pickup update with overlap validation
// ============================================================
describe('Delivery/pickup update via CookScheduleService', function () {
    it('saves valid delivery interval to database', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()->withOrderInterval()->create([
            'tenant_id' => $tenant->id,
        ]);

        $service = app(CookScheduleService::class);
        $result = $service->updateDeliveryPickupInterval(
            $schedule, true, '10:00', '14:00', false, null, null,
        );

        expect($result['success'])->toBeTrue();
        expect($result['schedule']->delivery_enabled)->toBeTrue();
    });

    it('rejects when neither delivery nor pickup is enabled (BR-183)', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()->withOrderInterval()->create([
            'tenant_id' => $tenant->id,
        ]);

        $service = app(CookScheduleService::class);
        $result = $service->updateDeliveryPickupInterval(
            $schedule, false, null, null, false, null, null,
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('enabled');
    });

    it('rejects overlapping delivery windows across entries (BR-179)', function () {
        $tenant = Tenant::factory()->create();

        // First entry with delivery 10:00-14:00
        CookSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'day_of_week' => 'tuesday',
            'is_available' => true,
            'order_start_time' => '06:00',
            'order_start_day_offset' => 0,
            'order_end_time' => '09:00',
            'order_end_day_offset' => 0,
            'delivery_enabled' => true,
            'delivery_start_time' => '10:00',
            'delivery_end_time' => '14:00',
            'position' => 1,
        ]);

        // Second entry on same day with order interval
        $schedule2 = CookSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'day_of_week' => 'tuesday',
            'is_available' => true,
            'order_start_time' => '06:00',
            'order_start_day_offset' => 0,
            'order_end_time' => '08:00',
            'order_end_day_offset' => 0,
            'position' => 2,
        ]);

        $service = app(CookScheduleService::class);

        // Overlapping delivery: 12:00-16:00 vs existing 10:00-14:00
        $result = $service->updateDeliveryPickupInterval(
            $schedule2, true, '12:00', '16:00', false, null, null,
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('overlaps');
    });

    it('rejects delivery start before order end (BR-174)', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'is_available' => true,
            'order_start_time' => '06:00',
            'order_start_day_offset' => 0,
            'order_end_time' => '12:00',
            'order_end_day_offset' => 0,
        ]);

        $service = app(CookScheduleService::class);

        // Delivery starts at 10:00, but order closes at 12:00
        $result = $service->updateDeliveryPickupInterval(
            $schedule, true, '10:00', '14:00', false, null, null,
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('after');
    });

    it('rejects pickup start before order end (BR-175)', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'is_available' => true,
            'order_start_time' => '06:00',
            'order_start_day_offset' => 0,
            'order_end_time' => '12:00',
            'order_end_day_offset' => 0,
        ]);

        $service = app(CookScheduleService::class);

        // Pickup starts at 10:00, but order closes at 12:00
        $result = $service->updateDeliveryPickupInterval(
            $schedule, false, null, null, true, '10:00', '14:00',
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('after');
    });

    it('rejects delivery end before delivery start (BR-176)', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()->withOrderInterval('06:00', 0, '08:00', 0)->create([
            'tenant_id' => $tenant->id,
        ]);

        $service = app(CookScheduleService::class);

        $result = $service->updateDeliveryPickupInterval(
            $schedule, true, '14:00', '10:00', false, null, null,
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('after');
    });

    it('rejects pickup end before pickup start (BR-177)', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()->withOrderInterval('06:00', 0, '08:00', 0)->create([
            'tenant_id' => $tenant->id,
        ]);

        $service = app(CookScheduleService::class);

        $result = $service->updateDeliveryPickupInterval(
            $schedule, false, null, null, true, '14:00', '10:00',
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('after');
    });
});

// ============================================================
// Test group: ScheduleValidationService database queries
// ============================================================
describe('ScheduleValidationService database queries', function () {
    it('checks for available schedules via hasAvailableSchedules (BR-182)', function () {
        $tenant = Tenant::factory()->create();

        // No schedules yet
        $service = app(ScheduleValidationService::class);
        expect($service->hasAvailableSchedules($tenant->id))->toBeFalse();

        // Add unavailable entry
        CookSchedule::factory()->unavailable()->create(['tenant_id' => $tenant->id]);
        expect($service->hasAvailableSchedules($tenant->id))->toBeFalse();

        // Add available entry
        CookSchedule::factory()->create(['tenant_id' => $tenant->id]);
        expect($service->hasAvailableSchedules($tenant->id))->toBeTrue();
    });

    it('runs checkForOverlaps against real database records', function () {
        $tenant = Tenant::factory()->create();
        $service = app(ScheduleValidationService::class);

        // No entries yet -- no overlaps
        $result = $service->checkForOverlaps(
            $tenant->id, 'monday', '08:00', 0, '12:00', 0, null, null, null, null,
        );
        expect($result['overlapping'])->toBeFalse();

        // Create an entry
        CookSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'day_of_week' => 'monday',
            'order_start_time' => '08:00',
            'order_start_day_offset' => 0,
            'order_end_time' => '12:00',
            'order_end_day_offset' => 0,
        ]);

        // Same window should now overlap
        $result = $service->checkForOverlaps(
            $tenant->id, 'monday', '09:00', 0, '11:00', 0, null, null, null, null,
        );
        expect($result['overlapping'])->toBeTrue();
        expect($result['type'])->toBe('order');
    });

    it('scopes overlap checks to same tenant', function () {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        $service = app(ScheduleValidationService::class);

        // Create entry for tenant1
        CookSchedule::factory()->create([
            'tenant_id' => $tenant1->id,
            'day_of_week' => 'monday',
            'order_start_time' => '08:00',
            'order_start_day_offset' => 0,
            'order_end_time' => '12:00',
            'order_end_day_offset' => 0,
        ]);

        // Check same window but for tenant2 -- should not overlap
        $result = $service->checkForOverlaps(
            $tenant2->id, 'monday', '08:00', 0, '12:00', 0, null, null, null, null,
        );
        expect($result['overlapping'])->toBeFalse();
    });
});
