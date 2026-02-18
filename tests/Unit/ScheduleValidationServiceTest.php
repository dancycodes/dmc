<?php

use App\Models\CookSchedule;
use App\Models\Tenant;
use App\Services\ScheduleValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(ScheduleValidationService::class);
});

/*
|--------------------------------------------------------------------------
| BR-172: Time Format Validation
|--------------------------------------------------------------------------
*/

describe('isValidTimeFormat (BR-172)', function () {
    it('accepts valid 24-hour time formats', function (string $time) {
        expect($this->service->isValidTimeFormat($time))->toBeTrue();
    })->with([
        '00:00',
        '08:30',
        '12:00',
        '15:45',
        '23:59',
    ]);

    it('rejects invalid time formats', function (string $time) {
        expect($this->service->isValidTimeFormat($time))->toBeFalse();
    })->with([
        '25:00',
        '12:60',
        '24:00',
        '8:30',
        '1:5',
        'abc',
        '12:00:00',
        '',
        '12-00',
        '12:0a',
    ]);
});

/*
|--------------------------------------------------------------------------
| BR-173: Order Interval Chronological Ordering
|--------------------------------------------------------------------------
*/

describe('isOrderIntervalValid (BR-173)', function () {
    it('validates start is before end on same day', function () {
        expect($this->service->isOrderIntervalValid('08:00', 0, '12:00', 0))->toBeTrue();
    });

    it('rejects when start equals end on same day', function () {
        expect($this->service->isOrderIntervalValid('12:00', 0, '12:00', 0))->toBeFalse();
    });

    it('rejects when start is after end on same day', function () {
        expect($this->service->isOrderIntervalValid('14:00', 0, '10:00', 0))->toBeFalse();
    });

    it('validates start day before is before end same day', function () {
        // Day before 18:00 to same day 08:00
        expect($this->service->isOrderIntervalValid('18:00', 1, '08:00', 0))->toBeTrue();
    });

    it('validates 2 days before to day before', function () {
        // 2 days before 12:00 to day before 18:00
        expect($this->service->isOrderIntervalValid('12:00', 2, '18:00', 1))->toBeTrue();
    });

    it('rejects when day before start is after same day end with higher offset', function () {
        // If offset makes it effectively after
        expect($this->service->isOrderIntervalValid('08:00', 0, '18:00', 1))->toBeFalse();
    });
});

/*
|--------------------------------------------------------------------------
| BR-174: Order End Before Delivery Start
|--------------------------------------------------------------------------
*/

describe('validateOrderEndBeforeDeliveryStart (BR-174)', function () {
    it('passes when delivery starts at order end', function () {
        $result = $this->service->validateOrderEndBeforeDeliveryStart('10:00', 0, '10:00');
        expect($result['valid'])->toBeTrue();
    });

    it('passes when delivery starts after order end', function () {
        $result = $this->service->validateOrderEndBeforeDeliveryStart('10:00', 0, '11:00');
        expect($result['valid'])->toBeTrue();
    });

    it('fails when delivery starts before order end', function () {
        $result = $this->service->validateOrderEndBeforeDeliveryStart('10:00', 0, '09:00');
        expect($result['valid'])->toBeFalse();
        expect($result['message'])->toContain('order window closes');
    });

    it('passes when order ends on day before (offset > 0)', function () {
        // Order ends day before — any time on open day is valid
        $result = $this->service->validateOrderEndBeforeDeliveryStart('18:00', 1, '06:00');
        expect($result['valid'])->toBeTrue();
    });
});

/*
|--------------------------------------------------------------------------
| BR-175: Order End Before Pickup Start
|--------------------------------------------------------------------------
*/

describe('validateOrderEndBeforePickupStart (BR-175)', function () {
    it('passes when pickup starts at order end', function () {
        $result = $this->service->validateOrderEndBeforePickupStart('10:00', 0, '10:00');
        expect($result['valid'])->toBeTrue();
    });

    it('fails when pickup starts before order end', function () {
        $result = $this->service->validateOrderEndBeforePickupStart('10:00', 0, '09:30');
        expect($result['valid'])->toBeFalse();
        expect($result['message'])->toContain('order window closes');
    });
});

/*
|--------------------------------------------------------------------------
| BR-176 / BR-177: Delivery/Pickup Interval Validity
|--------------------------------------------------------------------------
*/

describe('isDeliveryIntervalValid (BR-176)', function () {
    it('passes when end is after start', function () {
        expect($this->service->isDeliveryIntervalValid('10:00', '14:00'))->toBeTrue();
    });

    it('fails when end equals start', function () {
        expect($this->service->isDeliveryIntervalValid('10:00', '10:00'))->toBeFalse();
    });

    it('fails when end is before start', function () {
        expect($this->service->isDeliveryIntervalValid('14:00', '10:00'))->toBeFalse();
    });
});

describe('isPickupIntervalValid (BR-177)', function () {
    it('passes when end is after start', function () {
        expect($this->service->isPickupIntervalValid('11:00', '15:00'))->toBeTrue();
    });

    it('fails when end is before start', function () {
        expect($this->service->isPickupIntervalValid('15:00', '11:00'))->toBeFalse();
    });
});

/*
|--------------------------------------------------------------------------
| BR-179: Overlapping Schedule Entries
|--------------------------------------------------------------------------
*/

describe('checkForOverlaps (BR-179)', function () {
    it('detects overlapping order windows on the same day', function () {
        $tenant = Tenant::factory()->create();

        // Existing schedule: order from 06:00 same day to 10:00 same day
        CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->withSameDayInterval('06:00', '10:00')
            ->create();

        // New schedule tries: order from 08:00 same day to 12:00 same day (overlaps)
        $result = $this->service->checkForOverlaps(
            $tenant->id,
            'monday',
            '08:00', 0, '12:00', 0,
            null, null, null, null,
        );

        expect($result['overlapping'])->toBeTrue();
        expect($result['type'])->toBe('order');
    });

    it('allows adjacent order windows (not overlapping)', function () {
        $tenant = Tenant::factory()->create();

        // Existing: 06:00 - 10:00
        CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->withSameDayInterval('06:00', '10:00')
            ->create();

        // New: 10:00 - 14:00 (adjacent, should NOT overlap)
        $result = $this->service->checkForOverlaps(
            $tenant->id,
            'monday',
            '10:00', 0, '14:00', 0,
            null, null, null, null,
        );

        expect($result['overlapping'])->toBeFalse();
    });

    it('detects overlapping delivery windows', function () {
        $tenant = Tenant::factory()->create();

        // Existing schedule with delivery 11:00-14:00
        CookSchedule::factory()
            ->for($tenant)
            ->forDay('tuesday')
            ->withOrderInterval()
            ->withDeliveryInterval('11:00', '14:00')
            ->create();

        // New delivery 13:00-16:00 (overlaps)
        $result = $this->service->checkForOverlaps(
            $tenant->id,
            'tuesday',
            null, null, null, null,
            '13:00', '16:00',
            null, null,
        );

        expect($result['overlapping'])->toBeTrue();
        expect($result['type'])->toBe('delivery');
    });

    it('detects overlapping pickup windows', function () {
        $tenant = Tenant::factory()->create();

        // Existing schedule with pickup 10:30-15:00
        CookSchedule::factory()
            ->for($tenant)
            ->forDay('wednesday')
            ->withOrderInterval()
            ->withPickupInterval('10:30', '15:00')
            ->create();

        // New pickup 14:00-17:00 (overlaps)
        $result = $this->service->checkForOverlaps(
            $tenant->id,
            'wednesday',
            null, null, null, null,
            null, null,
            '14:00', '17:00',
        );

        expect($result['overlapping'])->toBeTrue();
        expect($result['type'])->toBe('pickup');
    });

    it('excludes the entry being edited from overlap checks', function () {
        $tenant = Tenant::factory()->create();

        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->withSameDayInterval('06:00', '10:00')
            ->create();

        // Editing the same entry — should not conflict with itself
        $result = $this->service->checkForOverlaps(
            $tenant->id,
            'monday',
            '06:00', 0, '12:00', 0,
            null, null, null, null,
            $schedule->id,
        );

        expect($result['overlapping'])->toBeFalse();
    });

    it('does not flag overlaps across different days', function () {
        $tenant = Tenant::factory()->create();

        // Existing on Monday
        CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->withSameDayInterval('06:00', '10:00')
            ->create();

        // New on Tuesday — same times, different day
        $result = $this->service->checkForOverlaps(
            $tenant->id,
            'tuesday',
            '06:00', 0, '10:00', 0,
            null, null, null, null,
        );

        expect($result['overlapping'])->toBeFalse();
    });

    it('does not flag overlaps across different tenants', function () {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        // Existing on tenant1
        CookSchedule::factory()
            ->for($tenant1)
            ->forDay('monday')
            ->withSameDayInterval('06:00', '10:00')
            ->create();

        // New on tenant2 — same day, same times
        $result = $this->service->checkForOverlaps(
            $tenant2->id,
            'monday',
            '06:00', 0, '10:00', 0,
            null, null, null, null,
        );

        expect($result['overlapping'])->toBeFalse();
    });
});

/*
|--------------------------------------------------------------------------
| BR-180 / BR-181: Day Offset Limits
|--------------------------------------------------------------------------
*/

describe('isStartDayOffsetValid (BR-180)', function () {
    it('accepts offsets from 0 to 7', function (int $offset) {
        expect($this->service->isStartDayOffsetValid($offset))->toBeTrue();
    })->with([0, 1, 3, 5, 7]);

    it('rejects offsets above 7', function () {
        expect($this->service->isStartDayOffsetValid(8))->toBeFalse();
    });

    it('rejects negative offsets', function () {
        expect($this->service->isStartDayOffsetValid(-1))->toBeFalse();
    });
});

describe('isEndDayOffsetValid (BR-181)', function () {
    it('accepts 0 and 1', function (int $offset) {
        expect($this->service->isEndDayOffsetValid($offset))->toBeTrue();
    })->with([0, 1]);

    it('rejects offsets above 1', function () {
        expect($this->service->isEndDayOffsetValid(2))->toBeFalse();
    });
});

/*
|--------------------------------------------------------------------------
| BR-182: At Least One Available Schedule
|--------------------------------------------------------------------------
*/

describe('hasAvailableSchedules (BR-182)', function () {
    it('returns true when available schedules exist', function () {
        $tenant = Tenant::factory()->create();
        CookSchedule::factory()
            ->for($tenant)
            ->create(['is_available' => true]);

        expect($this->service->hasAvailableSchedules($tenant->id))->toBeTrue();
    });

    it('returns false when no available schedules exist', function () {
        $tenant = Tenant::factory()->create();
        CookSchedule::factory()
            ->for($tenant)
            ->unavailable()
            ->create();

        expect($this->service->hasAvailableSchedules($tenant->id))->toBeFalse();
    });

    it('returns false when no schedules exist at all', function () {
        $tenant = Tenant::factory()->create();

        expect($this->service->hasAvailableSchedules($tenant->id))->toBeFalse();
    });
});

/*
|--------------------------------------------------------------------------
| BR-183: At Least One of Delivery/Pickup
|--------------------------------------------------------------------------
*/

describe('hasDeliveryOrPickup (BR-183)', function () {
    it('returns true when delivery is enabled', function () {
        expect($this->service->hasDeliveryOrPickup(true, false))->toBeTrue();
    });

    it('returns true when pickup is enabled', function () {
        expect($this->service->hasDeliveryOrPickup(false, true))->toBeTrue();
    });

    it('returns true when both are enabled', function () {
        expect($this->service->hasDeliveryOrPickup(true, true))->toBeTrue();
    });

    it('returns false when neither is enabled', function () {
        expect($this->service->hasDeliveryOrPickup(false, false))->toBeFalse();
    });
});

/*
|--------------------------------------------------------------------------
| Utility Methods
|--------------------------------------------------------------------------
*/

describe('resolveToAbsoluteMinutes', function () {
    it('calculates same day correctly', function () {
        // 08:00 same day = 480
        expect($this->service->resolveToAbsoluteMinutes('08:00', 0))->toBe(480);
    });

    it('calculates day before correctly', function () {
        // 18:00 day before = 1080 - 1440 = -360
        expect($this->service->resolveToAbsoluteMinutes('18:00', 1))->toBe(-360);
    });

    it('calculates 2 days before correctly', function () {
        // 12:00 two days before = 720 - 2880 = -2160
        expect($this->service->resolveToAbsoluteMinutes('12:00', 2))->toBe(-2160);
    });

    it('calculates midnight same day as zero', function () {
        expect($this->service->resolveToAbsoluteMinutes('00:00', 0))->toBe(0);
    });
});

describe('timeToMinutes', function () {
    it('converts midnight to 0', function () {
        expect($this->service->timeToMinutes('00:00'))->toBe(0);
    });

    it('converts noon to 720', function () {
        expect($this->service->timeToMinutes('12:00'))->toBe(720);
    });

    it('converts 23:59 to 1439', function () {
        expect($this->service->timeToMinutes('23:59'))->toBe(1439);
    });
});

describe('windowsOverlap', function () {
    it('detects overlap when windows partially overlap', function () {
        expect($this->service->windowsOverlap(100, 300, 200, 400))->toBeTrue();
    });

    it('detects overlap when one window contains the other', function () {
        expect($this->service->windowsOverlap(100, 500, 200, 400))->toBeTrue();
    });

    it('does not flag adjacent windows as overlapping', function () {
        // [100, 200) and [200, 300) — adjacent
        expect($this->service->windowsOverlap(100, 200, 200, 300))->toBeFalse();
    });

    it('does not flag non-overlapping windows', function () {
        expect($this->service->windowsOverlap(100, 200, 300, 400))->toBeFalse();
    });
});

describe('getOrderEndMinutesOnOpenDay', function () {
    it('returns time in minutes when order ends on open day', function () {
        // 10:00 same day = 600
        expect($this->service->getOrderEndMinutesOnOpenDay('10:00', 0))->toBe(600);
    });

    it('returns 0 when order ends before open day', function () {
        // Offset > 0 means order ends before open day, so any time on open day is valid
        expect($this->service->getOrderEndMinutesOnOpenDay('18:00', 1))->toBe(0);
    });
});

/*
|--------------------------------------------------------------------------
| Comprehensive Validation: validateOrderIntervalUpdate
|--------------------------------------------------------------------------
*/

describe('validateOrderIntervalUpdate', function () {
    it('passes with valid order interval', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->create();

        $result = $this->service->validateOrderIntervalUpdate(
            $schedule, '18:00', 1, '08:00', 0,
        );

        expect($result['valid'])->toBeTrue();
        expect($result['errors'])->toBeEmpty();
    });

    it('fails with invalid start day offset', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->create();

        $result = $this->service->validateOrderIntervalUpdate(
            $schedule, '18:00', 8, '08:00', 0,
        );

        expect($result['valid'])->toBeFalse();
        expect($result['errors'])->toHaveKey('order_start_day_offset');
    });

    it('fails with invalid end day offset', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->create();

        $result = $this->service->validateOrderIntervalUpdate(
            $schedule, '18:00', 1, '08:00', 2,
        );

        expect($result['valid'])->toBeFalse();
        expect($result['errors'])->toHaveKey('order_end_day_offset');
    });

    it('fails when start is after end chronologically', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->create();

        $result = $this->service->validateOrderIntervalUpdate(
            $schedule, '14:00', 0, '08:00', 0,
        );

        expect($result['valid'])->toBeFalse();
        expect($result['errors'])->toHaveKey('order_start_time');
    });

    it('fails when order window overlaps existing entry', function () {
        $tenant = Tenant::factory()->create();

        // Existing schedule
        CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->withSameDayInterval('06:00', '10:00')
            ->create();

        // New schedule on the same day
        $newSchedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->atPosition(2)
            ->create();

        $result = $this->service->validateOrderIntervalUpdate(
            $newSchedule, '08:00', 0, '12:00', 0,
        );

        expect($result['valid'])->toBeFalse();
        expect($result['errors'])->toHaveKey('order_start_time');
    });
});

/*
|--------------------------------------------------------------------------
| Comprehensive Validation: validateDeliveryPickupUpdate
|--------------------------------------------------------------------------
*/

describe('validateDeliveryPickupUpdate', function () {
    it('passes with valid delivery interval', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->withOrderInterval('18:00', 1, '08:00', 0)
            ->create();

        $result = $this->service->validateDeliveryPickupUpdate(
            $schedule,
            true, '11:00', '14:00',
            false, null, null,
        );

        expect($result['valid'])->toBeTrue();
    });

    it('fails when neither delivery nor pickup is enabled', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->withOrderInterval()
            ->create();

        $result = $this->service->validateDeliveryPickupUpdate(
            $schedule,
            false, null, null,
            false, null, null,
        );

        expect($result['valid'])->toBeFalse();
        expect($result['errors'])->toHaveKey('delivery_enabled');
    });

    it('fails when delivery end is before delivery start', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->withOrderInterval('18:00', 1, '08:00', 0)
            ->create();

        $result = $this->service->validateDeliveryPickupUpdate(
            $schedule,
            true, '14:00', '11:00',
            false, null, null,
        );

        expect($result['valid'])->toBeFalse();
        expect($result['errors'])->toHaveKey('delivery_end_time');
    });

    it('fails when delivery starts before order window closes', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->withSameDayInterval('06:00', '10:00')
            ->create();

        $result = $this->service->validateDeliveryPickupUpdate(
            $schedule,
            true, '09:00', '14:00',
            false, null, null,
        );

        expect($result['valid'])->toBeFalse();
        expect($result['errors'])->toHaveKey('delivery_start_time');
    });

    it('fails when pickup starts before order window closes', function () {
        $tenant = Tenant::factory()->create();
        $schedule = CookSchedule::factory()
            ->for($tenant)
            ->forDay('monday')
            ->withSameDayInterval('06:00', '10:00')
            ->create();

        $result = $this->service->validateDeliveryPickupUpdate(
            $schedule,
            false, null, null,
            true, '09:00', '14:00',
        );

        expect($result['valid'])->toBeFalse();
        expect($result['errors'])->toHaveKey('pickup_start_time');
    });
});

describe('formatTimeForDisplay', function () {
    it('formats morning time correctly', function () {
        expect($this->service->formatTimeForDisplay('08:30'))->toBe('8:30 AM');
    });

    it('formats afternoon time correctly', function () {
        expect($this->service->formatTimeForDisplay('14:00'))->toBe('2:00 PM');
    });

    it('formats midnight correctly', function () {
        expect($this->service->formatTimeForDisplay('00:00'))->toBe('12:00 AM');
    });

    it('formats noon correctly', function () {
        expect($this->service->formatTimeForDisplay('12:00'))->toBe('12:00 PM');
    });
});
