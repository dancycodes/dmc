<?php

/**
 * F-132: Schedule & Availability Display — Unit Tests
 *
 * Tests the schedule display data logic in TenantLandingService.
 * Covers: weekly schedule building, availability badge logic, edge cases.
 */

use App\Models\CookSchedule;
use App\Models\Tenant;
use App\Services\TenantLandingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(TenantLandingService::class);
    $this->tenant = Tenant::factory()->create(['is_active' => true]);
});

// --- BR-186: All 7 days displayed ---

it('returns hasSchedule false when no schedules exist', function () {
    $result = $this->service->getScheduleDisplayData($this->tenant);

    expect($result['hasSchedule'])->toBeFalse()
        ->and($result['days'])->toBeEmpty()
        ->and($result['availabilityBadge']['type'])->toBe('none');
});

it('returns all 7 days when schedules exist', function () {
    CookSchedule::factory()
        ->for($this->tenant)
        ->forDay('monday')
        ->withSameDayInterval('08:00', '11:00')
        ->create();

    $result = $this->service->getScheduleDisplayData($this->tenant);

    expect($result['hasSchedule'])->toBeTrue()
        ->and($result['days'])->toHaveCount(7);

    $dayNames = collect($result['days'])->pluck('day')->toArray();
    expect($dayNames)->toBe(CookSchedule::DAYS_OF_WEEK);
});

it('marks available and unavailable days correctly', function () {
    CookSchedule::factory()
        ->for($this->tenant)
        ->forDay('monday')
        ->withSameDayInterval('08:00', '11:00')
        ->create();

    CookSchedule::factory()
        ->for($this->tenant)
        ->forDay('tuesday')
        ->unavailable()
        ->create();

    $result = $this->service->getScheduleDisplayData($this->tenant);

    $monday = collect($result['days'])->firstWhere('day', 'monday');
    $tuesday = collect($result['days'])->firstWhere('day', 'tuesday');
    $wednesday = collect($result['days'])->firstWhere('day', 'wednesday');

    expect($monday['isAvailable'])->toBeTrue()
        ->and($tuesday['isAvailable'])->toBeFalse()
        ->and($wednesday['isAvailable'])->toBeFalse(); // No schedule = unavailable
});

// --- BR-188: Current day highlighted ---

it('marks today correctly in the schedule', function () {
    Carbon::setTestNow(Carbon::parse('2026-02-19 10:00:00', 'Africa/Douala')); // Thursday

    CookSchedule::factory()
        ->for($this->tenant)
        ->forDay('thursday')
        ->withSameDayInterval('08:00', '14:00')
        ->create();

    $result = $this->service->getScheduleDisplayData($this->tenant);

    $thursday = collect($result['days'])->firstWhere('day', 'thursday');
    $monday = collect($result['days'])->firstWhere('day', 'monday');

    expect($thursday['isToday'])->toBeTrue()
        ->and($monday['isToday'])->toBeFalse();

    Carbon::setTestNow();
});

// --- BR-187/BR-192: Multiple slots per day with labels ---

it('displays multiple slots per day with labels', function () {
    CookSchedule::factory()
        ->for($this->tenant)
        ->forDay('monday')
        ->withLabel('Lunch')
        ->atPosition(1)
        ->withSameDayInterval('08:00', '11:00')
        ->withDeliveryInterval('12:00', '14:00')
        ->create();

    CookSchedule::factory()
        ->for($this->tenant)
        ->forDay('monday')
        ->withLabel('Dinner')
        ->atPosition(2)
        ->withSameDayInterval('14:00', '17:00')
        ->withDeliveryInterval('18:00', '20:00')
        ->create();

    $result = $this->service->getScheduleDisplayData($this->tenant);

    $monday = collect($result['days'])->firstWhere('day', 'monday');

    expect($monday['isAvailable'])->toBeTrue()
        ->and($monday['slots'])->toHaveCount(2)
        ->and($monday['slots'][0]['label'])->toBe('Lunch')
        ->and($monday['slots'][1]['label'])->toBe('Dinner');
});

// --- BR-187: Time windows displayed per slot ---

it('includes order, delivery, and pickup intervals in slots', function () {
    CookSchedule::factory()
        ->for($this->tenant)
        ->forDay('wednesday')
        ->withSameDayInterval('08:00', '11:00')
        ->withDeliveryInterval('12:00', '14:00')
        ->withPickupInterval('11:30', '13:00')
        ->create();

    $result = $this->service->getScheduleDisplayData($this->tenant);

    $wednesday = collect($result['days'])->firstWhere('day', 'wednesday');

    expect($wednesday['slots'][0]['hasOrderInterval'])->toBeTrue()
        ->and($wednesday['slots'][0]['hasDeliveryInterval'])->toBeTrue()
        ->and($wednesday['slots'][0]['hasPickupInterval'])->toBeTrue()
        ->and($wednesday['slots'][0]['orderInterval'])->not->toBeNull()
        ->and($wednesday['slots'][0]['deliveryInterval'])->not->toBeNull()
        ->and($wednesday['slots'][0]['pickupInterval'])->not->toBeNull();
});

it('handles slots with only order interval', function () {
    CookSchedule::factory()
        ->for($this->tenant)
        ->forDay('friday')
        ->withSameDayInterval('06:00', '10:00')
        ->create();

    $result = $this->service->getScheduleDisplayData($this->tenant);

    $friday = collect($result['days'])->firstWhere('day', 'friday');

    expect($friday['slots'][0]['hasOrderInterval'])->toBeTrue()
        ->and($friday['slots'][0]['hasDeliveryInterval'])->toBeFalse()
        ->and($friday['slots'][0]['hasPickupInterval'])->toBeFalse();
});

// --- BR-189: "Available Now" badge ---

it('shows Available Now badge when within order window', function () {
    Carbon::setTestNow(Carbon::parse('2026-02-18 09:00:00', 'Africa/Douala')); // Wednesday

    CookSchedule::factory()
        ->for($this->tenant)
        ->forDay('wednesday')
        ->withSameDayInterval('08:00', '11:00')
        ->create();

    $result = $this->service->getScheduleDisplayData($this->tenant);

    expect($result['availabilityBadge']['type'])->toBe('available')
        ->and($result['availabilityBadge']['color'])->toBe('success')
        ->and($result['availabilityBadge']['label'])->toContain('11:00 AM');

    Carbon::setTestNow();
});

// --- Edge case: "Closing soon" warning ---

it('shows closing soon when order window ends in less than 15 minutes', function () {
    Carbon::setTestNow(Carbon::parse('2026-02-18 10:50:00', 'Africa/Douala')); // Wednesday

    CookSchedule::factory()
        ->for($this->tenant)
        ->forDay('wednesday')
        ->withSameDayInterval('08:00', '11:00')
        ->create();

    $result = $this->service->getScheduleDisplayData($this->tenant);

    expect($result['availabilityBadge']['type'])->toBe('closing_soon')
        ->and($result['availabilityBadge']['color'])->toBe('warning')
        ->and($result['availabilityBadge']['label'])->toContain('11:00 AM');

    Carbon::setTestNow();
});

// --- BR-190: "Next available" badge ---

it('shows Next available badge when outside order windows', function () {
    Carbon::setTestNow(Carbon::parse('2026-02-18 15:00:00', 'Africa/Douala')); // Wednesday

    CookSchedule::factory()
        ->for($this->tenant)
        ->forDay('thursday')
        ->withSameDayInterval('08:00', '11:00')
        ->create();

    $result = $this->service->getScheduleDisplayData($this->tenant);

    expect($result['availabilityBadge']['type'])->toBe('next')
        ->and($result['availabilityBadge']['color'])->toBe('warning')
        ->and($result['availabilityBadge']['label'])->toContain('Thursday');

    Carbon::setTestNow();
});

// --- Edge case: All 7 days unavailable ---

it('shows not accepting orders when all schedules are unavailable', function () {
    foreach (['monday', 'tuesday', 'wednesday'] as $day) {
        CookSchedule::factory()
            ->for($this->tenant)
            ->forDay($day)
            ->unavailable()
            ->create();
    }

    $result = $this->service->getScheduleDisplayData($this->tenant);

    expect($result['availabilityBadge']['type'])->toBe('closed')
        ->and($result['availabilityBadge']['color'])->toBe('danger');
});

// --- Day short labels ---

it('provides short day labels', function () {
    CookSchedule::factory()
        ->for($this->tenant)
        ->forDay('monday')
        ->withSameDayInterval('08:00', '11:00')
        ->create();

    $result = $this->service->getScheduleDisplayData($this->tenant);

    $monday = collect($result['days'])->firstWhere('day', 'monday');

    expect($monday['dayShort'])->toBe('Mon')
        ->and($monday['dayLabel'])->toBe('Monday');
});

// --- BR-193: Timezone note ---

it('includes timezone note', function () {
    CookSchedule::factory()
        ->for($this->tenant)
        ->forDay('monday')
        ->create();

    $result = $this->service->getScheduleDisplayData($this->tenant);

    expect($result['timezoneNote'])->toContain('Africa/Douala');
});

// --- getLandingPageData includes scheduleDisplay ---

it('includes scheduleDisplay in landing page data', function () {
    CookSchedule::factory()
        ->for($this->tenant)
        ->forDay('monday')
        ->withSameDayInterval('08:00', '11:00')
        ->create();

    $data = $this->service->getLandingPageData($this->tenant);

    expect($data)->toHaveKey('scheduleDisplay')
        ->and($data['scheduleDisplay']['hasSchedule'])->toBeTrue()
        ->and($data['scheduleDisplay']['days'])->toHaveCount(7);
});

// --- Edge case: Schedule with day offset ---

it('handles order window with day offset for availability', function () {
    // Wednesday schedule with order window starting Tuesday at 6 PM (1 day before)
    Carbon::setTestNow(Carbon::parse('2026-02-17 19:00:00', 'Africa/Douala')); // Tuesday at 7 PM

    CookSchedule::factory()
        ->for($this->tenant)
        ->forDay('wednesday')
        ->withOrderInterval('18:00', 1, '08:00', 0) // Tue 6PM to Wed 8AM
        ->create();

    $result = $this->service->getScheduleDisplayData($this->tenant);

    expect($result['availabilityBadge']['type'])->toBe('available')
        ->and($result['availabilityBadge']['color'])->toBe('success');

    Carbon::setTestNow();
});

// --- Edge case: No order intervals on available schedules ---

it('shows closed when schedules exist but no order intervals configured', function () {
    CookSchedule::factory()
        ->for($this->tenant)
        ->forDay('monday')
        ->create(); // available but no order interval

    $result = $this->service->getScheduleDisplayData($this->tenant);

    // hasSchedule is true (there are schedule entries), but badge is closed
    // because no order windows are configured
    expect($result['hasSchedule'])->toBeTrue()
        ->and($result['availabilityBadge']['type'])->toBe('closed');
});

// --- Empty slots not shown for unavailable entries ---

it('excludes unavailable entries from day slots', function () {
    CookSchedule::factory()
        ->for($this->tenant)
        ->forDay('monday')
        ->withLabel('Lunch')
        ->atPosition(1)
        ->withSameDayInterval('08:00', '11:00')
        ->create();

    CookSchedule::factory()
        ->for($this->tenant)
        ->forDay('monday')
        ->withLabel('Dinner')
        ->atPosition(2)
        ->unavailable()
        ->create();

    $result = $this->service->getScheduleDisplayData($this->tenant);

    $monday = collect($result['days'])->firstWhere('day', 'monday');

    expect($monday['isAvailable'])->toBeTrue()
        ->and($monday['slots'])->toHaveCount(1)
        ->and($monday['slots'][0]['label'])->toBe('Lunch');
});

// --- Edge case: no schedules = empty message ---

it('returns correct empty schedule message', function () {
    $result = $this->service->getScheduleDisplayData($this->tenant);

    expect($result['availabilityBadge']['label'])->toBe(
        'Schedule not yet available. Contact the cook for ordering information.'
    );
});
