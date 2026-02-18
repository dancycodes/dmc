<?php

use App\Models\CookSchedule;
use App\Models\Meal;
use App\Models\MealSchedule;
use App\Models\Tenant;
use App\Services\MealScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(MealScheduleService::class);
    $this->tenant = Tenant::factory()->create();
    $this->meal = Meal::factory()->for($this->tenant)->create();
});

/*
|--------------------------------------------------------------------------
| BR-162/BR-163: Custom Schedule Detection
|--------------------------------------------------------------------------
*/

describe('hasCustomSchedule (BR-162/BR-163)', function () {
    it('returns false when meal has no schedule entries', function () {
        expect($this->service->hasCustomSchedule($this->meal))->toBeFalse();
    });

    it('returns true when meal has schedule entries', function () {
        MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->create();

        expect($this->service->hasCustomSchedule($this->meal))->toBeTrue();
    });

    it('returns false for a different meal with no entries', function () {
        $otherMeal = Meal::factory()->for($this->tenant)->create();

        MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->create();

        expect($this->service->hasCustomSchedule($otherMeal))->toBeFalse();
    });
});

/*
|--------------------------------------------------------------------------
| Meal model relationship (BR-162/BR-163)
|--------------------------------------------------------------------------
*/

describe('Meal::hasCustomSchedule()', function () {
    it('returns false when no MealSchedule entries exist', function () {
        expect($this->meal->hasCustomSchedule())->toBeFalse();
    });

    it('returns true when MealSchedule entries exist', function () {
        MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->create();

        expect($this->meal->hasCustomSchedule())->toBeTrue();
    });

    it('loads schedules relationship correctly', function () {
        MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->count(3)
            ->create();

        expect($this->meal->schedules)->toHaveCount(3);
    });
});

/*
|--------------------------------------------------------------------------
| BR-166: Schedule Entry Creation (Same rules as cook schedule)
|--------------------------------------------------------------------------
*/

describe('createScheduleEntry (BR-166)', function () {
    it('creates a schedule entry for a meal', function () {
        $result = $this->service->createScheduleEntry(
            $this->tenant,
            $this->meal,
            'monday',
            true,
            'Lunch',
        );

        expect($result['success'])->toBeTrue();
        expect($result['schedule'])->toBeInstanceOf(MealSchedule::class);
        expect($result['schedule']->meal_id)->toBe($this->meal->id);
        expect($result['schedule']->tenant_id)->toBe($this->tenant->id);
        expect($result['schedule']->day_of_week)->toBe('monday');
        expect($result['schedule']->is_available)->toBeTrue();
        expect($result['schedule']->label)->toBe('Lunch');
        expect($result['schedule']->position)->toBe(1);
    });

    it('creates an unavailable entry', function () {
        $result = $this->service->createScheduleEntry(
            $this->tenant,
            $this->meal,
            'tuesday',
            false,
        );

        expect($result['success'])->toBeTrue();
        expect($result['schedule']->is_available)->toBeFalse();
    });

    it('assigns correct positions sequentially', function () {
        $result1 = $this->service->createScheduleEntry(
            $this->tenant, $this->meal, 'monday', true,
        );
        $result2 = $this->service->createScheduleEntry(
            $this->tenant, $this->meal, 'monday', true,
        );
        $result3 = $this->service->createScheduleEntry(
            $this->tenant, $this->meal, 'monday', true,
        );

        expect($result1['schedule']->position)->toBe(1);
        expect($result2['schedule']->position)->toBe(2);
        expect($result3['schedule']->position)->toBe(3);
    });

    it('enforces maximum entries per day limit', function () {
        // Create MAX_ENTRIES_PER_DAY entries
        for ($i = 0; $i < MealSchedule::MAX_ENTRIES_PER_DAY; $i++) {
            $this->service->createScheduleEntry(
                $this->tenant, $this->meal, 'monday', true,
            );
        }

        // Next one should fail
        $result = $this->service->createScheduleEntry(
            $this->tenant, $this->meal, 'monday', true,
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('Maximum');
    });

    it('allows entries on different days even if one day is at limit', function () {
        for ($i = 0; $i < MealSchedule::MAX_ENTRIES_PER_DAY; $i++) {
            $this->service->createScheduleEntry(
                $this->tenant, $this->meal, 'monday', true,
            );
        }

        $result = $this->service->createScheduleEntry(
            $this->tenant, $this->meal, 'tuesday', true,
        );

        expect($result['success'])->toBeTrue();
    });

    it('trims and handles empty label', function () {
        $result = $this->service->createScheduleEntry(
            $this->tenant, $this->meal, 'monday', true, '   ',
        );

        expect($result['success'])->toBeTrue();
        expect($result['schedule']->label)->toBeNull();
    });
});

/*
|--------------------------------------------------------------------------
| BR-166: Order Interval Update
|--------------------------------------------------------------------------
*/

describe('updateOrderInterval (BR-166)', function () {
    it('updates order interval for an available schedule entry', function () {
        $schedule = MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->forDay('monday')
            ->create(['is_available' => true]);

        $result = $this->service->updateOrderInterval(
            $schedule, '18:00', 1, '08:00', 0,
        );

        expect($result['success'])->toBeTrue();
        expect($result['schedule']->order_start_time)->toBe('18:00:00');
        expect($result['schedule']->order_start_day_offset)->toBe(1);
        expect($result['schedule']->order_end_time)->toBe('08:00:00');
        expect($result['schedule']->order_end_day_offset)->toBe(0);
    });

    it('rejects when entry is unavailable', function () {
        $schedule = MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->unavailable()
            ->create();

        $result = $this->service->updateOrderInterval(
            $schedule, '06:00', 0, '10:00', 0,
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('available');
    });

    it('rejects when end is before start chronologically', function () {
        $schedule = MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->forDay('monday')
            ->create(['is_available' => true]);

        $result = $this->service->updateOrderInterval(
            $schedule, '14:00', 0, '08:00', 0,
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('after the start');
    });

    it('rejects invalid start day offset', function () {
        $schedule = MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->forDay('monday')
            ->create(['is_available' => true]);

        $result = $this->service->updateOrderInterval(
            $schedule, '06:00', 8, '10:00', 0,
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('days before');
    });

    it('rejects invalid end day offset', function () {
        $schedule = MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->forDay('monday')
            ->create(['is_available' => true]);

        $result = $this->service->updateOrderInterval(
            $schedule, '06:00', 0, '10:00', 2,
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('offset');
    });
});

/*
|--------------------------------------------------------------------------
| BR-166: Delivery/Pickup Interval Update
|--------------------------------------------------------------------------
*/

describe('updateDeliveryPickupInterval (BR-166)', function () {
    it('updates delivery and pickup intervals', function () {
        $schedule = MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->forDay('monday')
            ->withOrderInterval('18:00', 1, '08:00', 0)
            ->create(['is_available' => true]);

        $result = $this->service->updateDeliveryPickupInterval(
            $schedule,
            true, '11:00', '14:00',
            true, '10:30', '15:00',
        );

        expect($result['success'])->toBeTrue();
        expect($result['schedule']->delivery_enabled)->toBeTrue();
        expect($result['schedule']->delivery_start_time)->toBe('11:00:00');
        expect($result['schedule']->delivery_end_time)->toBe('14:00:00');
        expect($result['schedule']->pickup_enabled)->toBeTrue();
        expect($result['schedule']->pickup_start_time)->toBe('10:30:00');
        expect($result['schedule']->pickup_end_time)->toBe('15:00:00');
    });

    it('rejects when entry is unavailable', function () {
        $schedule = MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->unavailable()
            ->create();

        $result = $this->service->updateDeliveryPickupInterval(
            $schedule,
            true, '11:00', '14:00',
            false, null, null,
        );

        expect($result['success'])->toBeFalse();
    });

    it('rejects when order interval is not configured', function () {
        $schedule = MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->forDay('monday')
            ->create(['is_available' => true]);

        $result = $this->service->updateDeliveryPickupInterval(
            $schedule,
            true, '11:00', '14:00',
            false, null, null,
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('order interval');
    });

    it('rejects when neither delivery nor pickup is enabled', function () {
        $schedule = MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->forDay('monday')
            ->withOrderInterval()
            ->create(['is_available' => true]);

        $result = $this->service->updateDeliveryPickupInterval(
            $schedule,
            false, null, null,
            false, null, null,
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('At least one');
    });

    it('rejects when delivery end is before start', function () {
        $schedule = MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->forDay('monday')
            ->withOrderInterval('18:00', 1, '08:00', 0)
            ->create(['is_available' => true]);

        $result = $this->service->updateDeliveryPickupInterval(
            $schedule,
            true, '14:00', '11:00',
            false, null, null,
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('after the start');
    });

    it('clears delivery times when delivery disabled', function () {
        $schedule = MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->forDay('monday')
            ->withOrderInterval('18:00', 1, '08:00', 0)
            ->create([
                'is_available' => true,
                'delivery_enabled' => true,
                'delivery_start_time' => '11:00',
                'delivery_end_time' => '14:00',
            ]);

        $result = $this->service->updateDeliveryPickupInterval(
            $schedule,
            false, null, null,
            true, '10:30', '15:00',
        );

        expect($result['success'])->toBeTrue();
        expect($result['schedule']->delivery_enabled)->toBeFalse();
        expect($result['schedule']->delivery_start_time)->toBeNull();
        expect($result['schedule']->delivery_end_time)->toBeNull();
    });
});

/*
|--------------------------------------------------------------------------
| BR-167: Revert to Default Schedule
|--------------------------------------------------------------------------
*/

describe('revertToDefaultSchedule (BR-167)', function () {
    it('deletes all meal schedule entries', function () {
        MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->count(5)
            ->create();

        $result = $this->service->revertToDefaultSchedule($this->meal);

        expect($result['success'])->toBeTrue();
        expect($result['deleted_count'])->toBe(5);
        expect(MealSchedule::query()->forMeal($this->meal->id)->count())->toBe(0);
    });

    it('only deletes entries for the specific meal', function () {
        $otherMeal = Meal::factory()->for($this->tenant)->create();

        MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->count(3)
            ->create();

        MealSchedule::factory()
            ->for($this->tenant)
            ->for($otherMeal)
            ->count(2)
            ->create();

        $this->service->revertToDefaultSchedule($this->meal);

        expect(MealSchedule::query()->forMeal($this->meal->id)->count())->toBe(0);
        expect(MealSchedule::query()->forMeal($otherMeal->id)->count())->toBe(2);
    });

    it('returns zero count when no entries exist', function () {
        $result = $this->service->revertToDefaultSchedule($this->meal);

        expect($result['success'])->toBeTrue();
        expect($result['deleted_count'])->toBe(0);
    });

    it('meal no longer has custom schedule after revert', function () {
        MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->count(3)
            ->create();

        expect($this->service->hasCustomSchedule($this->meal))->toBeTrue();

        $this->service->revertToDefaultSchedule($this->meal);

        expect($this->service->hasCustomSchedule($this->meal))->toBeFalse();
    });
});

/*
|--------------------------------------------------------------------------
| BR-169: Schedule Data Retrieval (Tenant + Meal Scoped)
|--------------------------------------------------------------------------
*/

describe('getSchedulesByDay (BR-169)', function () {
    it('returns grouped schedules by day', function () {
        MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->forDay('monday')
            ->create();

        MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->forDay('wednesday')
            ->create();

        $result = $this->service->getSchedulesByDay($this->meal);

        expect($result)->toHaveKeys(MealSchedule::DAYS_OF_WEEK);
        expect($result['monday'])->toHaveCount(1);
        expect($result['wednesday'])->toHaveCount(1);
        expect($result['tuesday'])->toHaveCount(0);
    });

    it('does not include entries from other meals', function () {
        $otherMeal = Meal::factory()->for($this->tenant)->create();

        MealSchedule::factory()
            ->for($this->tenant)
            ->for($otherMeal)
            ->forDay('monday')
            ->create();

        $result = $this->service->getSchedulesByDay($this->meal);

        expect($result['monday'])->toHaveCount(0);
    });
});

describe('getScheduleSummary', function () {
    it('returns correct summary counts', function () {
        MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->forDay('monday')
            ->count(2)
            ->create(['is_available' => true]);

        MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->forDay('tuesday')
            ->unavailable()
            ->create();

        $summary = $this->service->getScheduleSummary($this->meal);

        expect($summary['total'])->toBe(3);
        expect($summary['available'])->toBe(2);
        expect($summary['unavailable'])->toBe(1);
        expect($summary['days_covered'])->toBe(2);
    });
});

/*
|--------------------------------------------------------------------------
| MealSchedule Model Tests
|--------------------------------------------------------------------------
*/

describe('MealSchedule model', function () {
    it('has correct constants matching CookSchedule', function () {
        expect(MealSchedule::DAYS_OF_WEEK)->toBe(CookSchedule::DAYS_OF_WEEK);
        expect(MealSchedule::DAY_LABELS)->toBe(CookSchedule::DAY_LABELS);
        expect(MealSchedule::MAX_ENTRIES_PER_DAY)->toBe(CookSchedule::MAX_ENTRIES_PER_DAY);
    });

    it('belongs to a tenant', function () {
        $schedule = MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->create();

        expect($schedule->tenant->id)->toBe($this->tenant->id);
    });

    it('belongs to a meal', function () {
        $schedule = MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->create();

        expect($schedule->meal->id)->toBe($this->meal->id);
    });

    it('detects order interval presence', function () {
        $withInterval = MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->withOrderInterval()
            ->create();

        $withoutInterval = MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->create();

        expect($withInterval->hasOrderInterval())->toBeTrue();
        expect($withoutInterval->hasOrderInterval())->toBeFalse();
    });

    it('detects delivery interval presence', function () {
        $schedule = MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->withOrderInterval()
            ->create([
                'delivery_enabled' => true,
                'delivery_start_time' => '11:00',
                'delivery_end_time' => '14:00',
            ]);

        expect($schedule->hasDeliveryInterval())->toBeTrue();
    });

    it('detects pickup interval presence', function () {
        $schedule = MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->withOrderInterval()
            ->create([
                'pickup_enabled' => true,
                'pickup_start_time' => '10:30',
                'pickup_end_time' => '15:00',
            ]);

        expect($schedule->hasPickupInterval())->toBeTrue();
    });

    it('generates display label from position when no label set', function () {
        $schedule = MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->create(['label' => null, 'position' => 2]);

        expect($schedule->display_label)->toContain('2');
    });

    it('uses custom label when set', function () {
        $schedule = MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->labeled('Dinner Service')
            ->create();

        expect($schedule->display_label)->toBe('Dinner Service');
    });

    it('provides order interval summary', function () {
        $schedule = MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->withOrderInterval('18:00', 1, '08:00', 0)
            ->create();

        expect($schedule->order_interval_summary)->not->toBeNull();
    });

    it('calculates order end time in minutes', function () {
        $schedule = MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->withOrderInterval('06:00', 0, '10:00', 0)
            ->create();

        expect($schedule->getOrderEndTimeInMinutes())->toBe(600);
    });

    it('returns zero for order end minutes when day offset > 0', function () {
        $schedule = MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->withOrderInterval('18:00', 1, '08:00', 1)
            ->create();

        expect($schedule->getOrderEndTimeInMinutes())->toBe(0);
    });

    it('scopes by day correctly', function () {
        MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->forDay('monday')
            ->count(2)
            ->create();

        MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->forDay('friday')
            ->create();

        expect(MealSchedule::query()->forMeal($this->meal->id)->forDay('monday')->count())->toBe(2);
        expect(MealSchedule::query()->forMeal($this->meal->id)->forDay('friday')->count())->toBe(1);
    });

    it('scopes available entries', function () {
        MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->count(2)
            ->create(['is_available' => true]);

        MealSchedule::factory()
            ->for($this->tenant)
            ->for($this->meal)
            ->unavailable()
            ->create();

        expect(MealSchedule::query()->forMeal($this->meal->id)->available()->count())->toBe(2);
    });
});
