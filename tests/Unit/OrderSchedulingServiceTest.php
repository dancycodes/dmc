<?php

use App\Models\CookSchedule;
use App\Models\MealSchedule;
use App\Models\Tenant;
use App\Services\OrderSchedulingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * F-148: Unit tests for OrderSchedulingService
 *
 * Tests date availability computation, date validation, cart item checks,
 * and next-available-slot logic against business rules BR-335 through BR-342.
 */
beforeEach(function () {
    $this->service = new OrderSchedulingService;
    $this->tenant = Tenant::factory()->create();

    // Freeze time so tests are deterministic (a known Sunday)
    Carbon::setTestNow(Carbon::parse('2026-02-22 10:00:00', 'Africa/Douala')); // Sunday
});

afterEach(function () {
    Carbon::setTestNow(); // Reset time
});

describe('getAvailableDates', function () {
    it('returns 14 dates starting from tomorrow (BR-338, BR-339)', function () {
        $dates = $this->service->getAvailableDates($this->tenant->id);

        expect($dates)->toHaveCount(14);

        $firstKey = array_key_first($dates);
        expect($firstKey)->toBe('2026-02-23'); // tomorrow

        $lastKey = array_key_last($dates);
        expect($lastKey)->toBe('2026-03-08'); // +14 days
    });

    it('marks dates as available when cook has schedule for that day (BR-336)', function () {
        // Cook is available on monday
        CookSchedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'day_of_week' => 'monday',
            'is_available' => true,
        ]);

        $dates = $this->service->getAvailableDates($this->tenant->id);

        // Feb 23 2026 is a Monday
        expect($dates['2026-02-23']['available'])->toBeTrue();
    });

    it('marks dates as unavailable when cook has no schedule for that day (BR-337)', function () {
        // Only wednesday available — no other days
        CookSchedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'day_of_week' => 'wednesday',
            'is_available' => true,
        ]);

        $dates = $this->service->getAvailableDates($this->tenant->id);

        // Feb 23 is Monday — not available
        expect($dates['2026-02-23']['available'])->toBeFalse();
    });

    it('marks dates as unavailable when cook has schedule set to unavailable (BR-337)', function () {
        CookSchedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'day_of_week' => 'monday',
            'is_available' => false,
        ]);

        $dates = $this->service->getAvailableDates($this->tenant->id);

        expect($dates['2026-02-23']['available'])->toBeFalse();
    });

    it('returns correct day labels', function () {
        $dates = $this->service->getAvailableDates($this->tenant->id);

        expect($dates['2026-02-23']['day_of_week'])->toBe('monday');
        expect($dates['2026-02-23']['display_date'])->toBe('Mon, Feb 23');
    });
});

describe('hasAvailableDates', function () {
    it('returns true when at least one date is available', function () {
        CookSchedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'day_of_week' => 'monday',
            'is_available' => true,
        ]);

        expect($this->service->hasAvailableDates($this->tenant->id))->toBeTrue();
    });

    it('returns false when no dates are available', function () {
        expect($this->service->hasAvailableDates($this->tenant->id))->toBeFalse();
    });
});

describe('validateScheduledDate', function () {
    it('rejects today (BR-339)', function () {
        $result = $this->service->validateScheduledDate($this->tenant->id, '2026-02-22');

        expect($result['valid'])->toBeFalse();
        expect($result['error'])->not()->toBeNull();
    });

    it('rejects past dates (BR-339)', function () {
        $result = $this->service->validateScheduledDate($this->tenant->id, '2026-02-21');

        expect($result['valid'])->toBeFalse();
    });

    it('rejects dates beyond 14 days (BR-338)', function () {
        $result = $this->service->validateScheduledDate($this->tenant->id, '2026-03-09');

        expect($result['valid'])->toBeFalse();
    });

    it('rejects dates with no available cook schedule (BR-337)', function () {
        // No schedule created — all days unavailable
        $result = $this->service->validateScheduledDate($this->tenant->id, '2026-02-23');

        expect($result['valid'])->toBeFalse();
    });

    it('accepts a valid future date within 14 days with available schedule (BR-336)', function () {
        CookSchedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'day_of_week' => 'monday',
            'is_available' => true,
        ]);

        $result = $this->service->validateScheduledDate($this->tenant->id, '2026-02-23');

        expect($result['valid'])->toBeTrue();
        expect($result['error'])->toBeNull();
    });

    it('rejects invalid date format', function () {
        $result = $this->service->validateScheduledDate($this->tenant->id, 'not-a-date');

        expect($result['valid'])->toBeFalse();
    });
});

describe('getUnavailableCartItems', function () {
    it('returns empty for empty cart', function () {
        $result = $this->service->getUnavailableCartItems($this->tenant->id, '2026-02-23', []);

        expect($result)->toBeEmpty();
    });

    it('returns empty when meals have no schedule overrides (follow cook schedule)', function () {
        // Meal with no MealSchedule overrides — follows cook default schedule
        $cartItems = [
            ['meal_id' => 999, 'component_id' => 1, 'quantity' => 1],
        ];

        $result = $this->service->getUnavailableCartItems($this->tenant->id, '2026-02-23', $cartItems);

        expect($result)->toBeEmpty();
    });

    it('returns unavailable meals when override marks day as unavailable (BR-341, BR-342)', function () {
        $meal = \App\Models\Meal::factory()->create(['tenant_id' => $this->tenant->id]);

        MealSchedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'meal_id' => $meal->id,
            'day_of_week' => 'monday',
            'is_available' => false,
        ]);

        $cartItems = [
            ['meal_id' => $meal->id, 'component_id' => 1, 'quantity' => 1],
        ];

        $result = $this->service->getUnavailableCartItems($this->tenant->id, '2026-02-23', $cartItems);

        expect($result)->toHaveCount(1);
        expect($result[0]['meal_id'])->toBe($meal->id);
        expect($result[0]['reason'])->not()->toBeEmpty();
    });

    it('returns no unavailable meals when override marks day as available', function () {
        $meal = \App\Models\Meal::factory()->create(['tenant_id' => $this->tenant->id]);

        MealSchedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'meal_id' => $meal->id,
            'day_of_week' => 'monday',
            'is_available' => true,
        ]);

        $cartItems = [
            ['meal_id' => $meal->id, 'component_id' => 1, 'quantity' => 1],
        ];

        $result = $this->service->getUnavailableCartItems($this->tenant->id, '2026-02-23', $cartItems);

        expect($result)->toBeEmpty();
    });
});

describe('getNextAvailableSlot', function () {
    it('returns next available slot when one exists (BR-335)', function () {
        CookSchedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'day_of_week' => 'monday',
            'is_available' => true,
        ]);

        $slot = $this->service->getNextAvailableSlot($this->tenant->id);

        expect($slot['date'])->toBe('2026-02-23');
        expect($slot['text'])->toContain('Next available');
        expect($slot['day_label'])->not()->toBeNull();
    });

    it('returns no-slot message when no dates available', function () {
        $slot = $this->service->getNextAvailableSlot($this->tenant->id);

        expect($slot['date'])->toBeNull();
        expect($slot['text'])->toContain('No available');
    });
});

describe('formatScheduledDate', function () {
    it('formats a Y-m-d date to human-readable string', function () {
        $formatted = $this->service->formatScheduledDate('2026-02-23');

        expect($formatted)->toBe('Monday, February 23, 2026');
    });

    it('returns the input string if date cannot be parsed', function () {
        $formatted = $this->service->formatScheduledDate('invalid');

        expect($formatted)->toBe('invalid');
    });
});
