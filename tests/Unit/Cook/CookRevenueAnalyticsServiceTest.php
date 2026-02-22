<?php

use App\Models\Order;
use App\Services\CookRevenueAnalyticsService;
use Carbon\Carbon;

/**
 * F-200: Cook Revenue Analytics — Unit Tests
 *
 * Tests for CookRevenueAnalyticsService pure logic:
 * - Date range resolution (BR-372)
 * - Previous period resolution (BR-375)
 * - Granularity auto-adjustment (BR-373)
 * - XAF formatting (BR-376)
 * - Percentage change calculation
 */
beforeEach(function () {
    $this->service = new CookRevenueAnalyticsService;
});

// ---------------------------------------------------------------------------
// Date range resolution (BR-372)
// ---------------------------------------------------------------------------

describe('resolveDateRange', function () {
    it('returns start-of-day to end-of-day for today', function () {
        $range = $this->service->resolveDateRange('today');

        expect($range['start']->isStartOfDay())->toBeTrue();
        expect($range['end']->isEndOfDay())->toBeTrue();
        expect($range['start']->isSameDay(Carbon::today()))->toBeTrue();
    });

    it('returns start-of-week to end-of-week for this_week', function () {
        $range = $this->service->resolveDateRange('this_week');

        expect($range['start']->dayOfWeek)->toBe(Carbon::MONDAY);
        expect($range['end']->dayOfWeek)->toBe(Carbon::SUNDAY);
    });

    it('returns start-of-month to end-of-month for this_month', function () {
        $range = $this->service->resolveDateRange('this_month');

        expect($range['start']->day)->toBe(1);
        expect($range['end']->isSameDay(Carbon::now()->endOfMonth()))->toBeTrue();
    });

    it('returns approx 3 months back for last_3_months', function () {
        $range = $this->service->resolveDateRange('last_3_months');

        $expectedStart = Carbon::now()->subMonths(3)->startOfDay();
        expect($range['start']->isSameDay($expectedStart))->toBeTrue();
    });

    it('returns approx 6 months back for last_6_months', function () {
        $range = $this->service->resolveDateRange('last_6_months');

        $expectedStart = Carbon::now()->subMonths(6)->startOfDay();
        expect($range['start']->isSameDay($expectedStart))->toBeTrue();
    });

    it('returns start-of-year to end-of-year for this_year', function () {
        $range = $this->service->resolveDateRange('this_year');

        expect($range['start']->month)->toBe(1);
        expect($range['start']->day)->toBe(1);
        expect($range['end']->month)->toBe(12);
        expect($range['end']->day)->toBe(31);
    });

    it('returns custom dates parsed to start/end of day', function () {
        $range = $this->service->resolveDateRange('custom', '2025-01-15', '2025-02-15');

        expect($range['start']->format('Y-m-d'))->toBe('2025-01-15');
        expect($range['end']->format('Y-m-d'))->toBe('2025-02-15');
        expect($range['start']->isStartOfDay())->toBeTrue();
        expect($range['end']->isEndOfDay())->toBeTrue();
    });

    it('falls back to this_month for unknown period key', function () {
        $range = $this->service->resolveDateRange('bogus_period');

        expect($range['start']->day)->toBe(1);
        expect($range['end']->isSameDay(Carbon::now()->endOfMonth()))->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// Previous period resolution (BR-375)
// ---------------------------------------------------------------------------

describe('resolvePreviousDateRange', function () {
    it('shifts today one day back', function () {
        $current = $this->service->resolveDateRange('today');
        $previous = $this->service->resolvePreviousDateRange('today', $current);

        expect($previous['start']->isSameDay($current['start']->copy()->subDay()))->toBeTrue();
    });

    it('shifts this_week one week back', function () {
        $current = $this->service->resolveDateRange('this_week');
        $previous = $this->service->resolvePreviousDateRange('this_week', $current);

        expect($previous['start']->isSameDay($current['start']->copy()->subWeek()))->toBeTrue();
    });

    it('shifts this_month to previous calendar month', function () {
        $current = $this->service->resolveDateRange('this_month');
        $previous = $this->service->resolvePreviousDateRange('this_month', $current);

        $expectedStart = Carbon::now()->subMonth()->startOfMonth();
        expect($previous['start']->isSameDay($expectedStart))->toBeTrue();
    });

    it('shifts custom by same span backwards', function () {
        $current = $this->service->resolveDateRange('custom', '2025-03-01', '2025-03-31');
        $previous = $this->service->resolvePreviousDateRange('custom', $current);

        // span = 30 days, so previous = 2025-01-30 to 2025-02-28
        expect($previous['end']->isSameDay($current['start']->copy()->subDay()))->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// Granularity auto-adjustment (BR-373)
// ---------------------------------------------------------------------------

describe('resolveGranularity', function () {
    it('returns daily for ranges <= 31 days', function () {
        $start = Carbon::now()->subDays(14);
        $end = Carbon::now();

        expect($this->service->resolveGranularity($start, $end))->toBe('daily');
    });

    it('returns daily for ranges at the 31-day boundary', function () {
        // 30-day span — clearly within the daily threshold
        $start = Carbon::now()->subDays(30);
        $end = Carbon::now();

        expect($this->service->resolveGranularity($start, $end))->toBe('daily');
    });

    it('returns weekly for ranges 32–183 days', function () {
        $start = Carbon::now()->subMonths(3);
        $end = Carbon::now();

        expect($this->service->resolveGranularity($start, $end))->toBe('weekly');
    });

    it('returns monthly for ranges > 183 days', function () {
        $start = Carbon::now()->subMonths(7);
        $end = Carbon::now();

        expect($this->service->resolveGranularity($start, $end))->toBe('monthly');
    });
});

// ---------------------------------------------------------------------------
// XAF formatting (BR-376)
// ---------------------------------------------------------------------------

describe('formatXAF', function () {
    it('formats zero correctly', function () {
        expect(CookRevenueAnalyticsService::formatXAF(0))->toBe('0 XAF');
    });

    it('formats thousands with comma separator', function () {
        expect(CookRevenueAnalyticsService::formatXAF(1000))->toBe('1,000 XAF');
    });

    it('formats large amounts', function () {
        expect(CookRevenueAnalyticsService::formatXAF(2450000))->toBe('2,450,000 XAF');
    });

    it('has no decimal places', function () {
        // Integer amounts only — XAF does not use decimals
        expect(CookRevenueAnalyticsService::formatXAF(12500))->toBe('12,500 XAF');
        expect(str_contains(CookRevenueAnalyticsService::formatXAF(12500), '.'))->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// Percentage change calculation
// ---------------------------------------------------------------------------

describe('calculatePercentageChange', function () {
    it('returns positive percentage when current > previous', function () {
        $result = $this->service->calculatePercentageChange(120, 100);

        expect($result)->toBe(20.0);
    });

    it('returns negative percentage when current < previous', function () {
        $result = $this->service->calculatePercentageChange(80, 100);

        expect($result)->toBe(-20.0);
    });

    it('returns 100.0 when previous is zero and current is positive', function () {
        $result = $this->service->calculatePercentageChange(100, 0);

        expect($result)->toBe(100.0);
    });

    it('returns null when both are zero', function () {
        $result = $this->service->calculatePercentageChange(0, 0);

        expect($result)->toBeNull();
    });

    it('rounds to 1 decimal place', function () {
        $result = $this->service->calculatePercentageChange(115, 100);

        expect($result)->toBe(15.0);
    });
});

// ---------------------------------------------------------------------------
// COMPLETED_STATUSES constant
// ---------------------------------------------------------------------------

describe('COMPLETED_STATUSES', function () {
    it('includes completed, delivered, and picked_up statuses', function () {
        expect(CookRevenueAnalyticsService::COMPLETED_STATUSES)->toContain(Order::STATUS_COMPLETED);
        expect(CookRevenueAnalyticsService::COMPLETED_STATUSES)->toContain(Order::STATUS_DELIVERED);
        expect(CookRevenueAnalyticsService::COMPLETED_STATUSES)->toContain(Order::STATUS_PICKED_UP);
    });

    it('does not include cancelled or refunded statuses', function () {
        expect(CookRevenueAnalyticsService::COMPLETED_STATUSES)->not->toContain(Order::STATUS_CANCELLED);
        expect(CookRevenueAnalyticsService::COMPLETED_STATUSES)->not->toContain(Order::STATUS_REFUNDED);
    });
});

// ---------------------------------------------------------------------------
// PERIODS constant (BR-372)
// ---------------------------------------------------------------------------

describe('PERIODS', function () {
    it('contains all required 7 period keys', function () {
        $periods = array_keys(CookRevenueAnalyticsService::PERIODS);

        expect($periods)->toContain('today');
        expect($periods)->toContain('this_week');
        expect($periods)->toContain('this_month');
        expect($periods)->toContain('last_3_months');
        expect($periods)->toContain('last_6_months');
        expect($periods)->toContain('this_year');
        expect($periods)->toContain('custom');
    });
});
