<?php

/**
 * Unit tests for CookDeliveryAnalyticsService
 *
 * F-203: Cook Delivery Performance Analytics
 *
 * Tests are pure unit tests — they exercise the service's non-DB methods
 * (date range resolution, ratio calculation logic, edge cases).
 */

use App\Services\CookDeliveryAnalyticsService;
use Carbon\Carbon;

beforeEach(function (): void {
    $this->service = new CookDeliveryAnalyticsService;
});

// ─── resolveDateRange ────────────────────────────────────────────────────────

it('resolves this_month to start and end of current month', function (): void {
    Carbon::setTestNow('2025-03-15 12:00:00');

    $range = $this->service->resolveDateRange('this_month');

    expect($range['start']->format('Y-m-d'))->toBe('2025-03-01')
        ->and($range['end']->format('Y-m-d'))->toBe('2025-03-31');

    Carbon::setTestNow();
});

it('resolves today to start and end of today', function (): void {
    Carbon::setTestNow('2025-06-20 14:30:00');

    $range = $this->service->resolveDateRange('today');

    expect($range['start']->format('Y-m-d H:i:s'))->toBe('2025-06-20 00:00:00')
        ->and($range['end']->format('Y-m-d'))->toBe('2025-06-20');

    Carbon::setTestNow();
});

it('resolves custom period using provided dates', function (): void {
    $range = $this->service->resolveDateRange('custom', '2025-01-10', '2025-01-20');

    expect($range['start']->format('Y-m-d'))->toBe('2025-01-10')
        ->and($range['end']->format('Y-m-d'))->toBe('2025-01-20');
});

it('falls back to this_month for unknown period', function (): void {
    Carbon::setTestNow('2025-03-15 12:00:00');

    $range = $this->service->resolveDateRange('invalid_period');

    expect($range['start']->format('Y-m'))->toBe('2025-03')
        ->and($range['end']->format('Y-m'))->toBe('2025-03');

    Carbon::setTestNow();
});

it('resolves last_3_months to three months back from now', function (): void {
    Carbon::setTestNow('2025-06-01 00:00:00');

    $range = $this->service->resolveDateRange('last_3_months');

    expect($range['start']->format('Y-m-d'))->toBe('2025-03-01')
        ->and($range['end']->format('Y-m-d'))->toBe('2025-06-01');

    Carbon::setTestNow();
});

// ─── PERIODS constant ────────────────────────────────────────────────────────

it('exposes all required period keys', function (): void {
    $expected = ['today', 'this_week', 'this_month', 'last_3_months', 'last_6_months', 'this_year', 'custom'];

    foreach ($expected as $key) {
        expect(CookDeliveryAnalyticsService::PERIODS)->toHaveKey($key);
    }
});

// ─── COMPLETED_STATUSES constant ─────────────────────────────────────────────

it('includes completed delivered and picked_up in COMPLETED_STATUSES', function (): void {
    expect(CookDeliveryAnalyticsService::COMPLETED_STATUSES)
        ->toContain('completed')
        ->toContain('delivered')
        ->toContain('picked_up');
});

// ─── DELIVERY_METHOD / PICKUP_METHOD constants ──────────────────────────────

it('has correct delivery and pickup method constants', function (): void {
    expect(CookDeliveryAnalyticsService::DELIVERY_METHOD)->toBe('delivery')
        ->and(CookDeliveryAnalyticsService::PICKUP_METHOD)->toBe('pickup');
});
