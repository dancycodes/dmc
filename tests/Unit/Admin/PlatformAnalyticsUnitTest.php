<?php

use App\Services\PlatformAnalyticsService;
use Carbon\Carbon;

/**
 * Unit tests for PlatformAnalyticsService.
 *
 * F-057: Platform Analytics Dashboard
 * Tests date range resolution, previous range calculation, % change,
 * chart granularity, and formatting helpers — no app container required.
 */
$projectRoot = dirname(__DIR__, 3);
require_once $projectRoot.'/vendor/autoload.php';

beforeEach(function (): void {
    $this->service = new PlatformAnalyticsService;
});

// ---------------------------------------------------------------------------
// Date range resolution (BR-140)
// ---------------------------------------------------------------------------

describe('resolveDateRange', function (): void {
    test('today resolves to current day boundaries', function (): void {
        $now = Carbon::now();
        $range = (new PlatformAnalyticsService)->resolveDateRange('today');

        expect($range['start']->format('Y-m-d'))->toBe($now->format('Y-m-d'))
            ->and($range['end']->format('Y-m-d'))->toBe($now->format('Y-m-d'))
            ->and($range['start']->format('H:i:s'))->toBe('00:00:00')
            ->and($range['end']->format('H:i:s'))->toBe('23:59:59');
    });

    test('week resolves to current week boundaries', function (): void {
        $range = (new PlatformAnalyticsService)->resolveDateRange('week');

        expect($range['start']->dayOfWeek)->toBe(Carbon::MONDAY)
            ->and($range['end']->dayOfWeek)->toBe(Carbon::SUNDAY);
    });

    test('month resolves to current month boundaries', function (): void {
        $now = Carbon::now();
        $range = (new PlatformAnalyticsService)->resolveDateRange('month');

        expect($range['start']->day)->toBe(1)
            ->and($range['end']->day)->toBe($now->daysInMonth);
    });

    test('year resolves to January 1 through December 31', function (): void {
        $range = (new PlatformAnalyticsService)->resolveDateRange('year');

        expect($range['start']->format('m-d'))->toBe('01-01')
            ->and($range['end']->format('m-d'))->toBe('12-31');
    });

    test('custom resolves to specified dates', function (): void {
        $range = (new PlatformAnalyticsService)->resolveDateRange('custom', '2025-01-01', '2025-01-31');

        expect($range['start']->format('Y-m-d'))->toBe('2025-01-01')
            ->and($range['end']->format('Y-m-d'))->toBe('2025-01-31');
    });

    test('unknown period defaults to today', function (): void {
        $now = Carbon::now();
        $range = (new PlatformAnalyticsService)->resolveDateRange('unknown_period');

        expect($range['start']->format('Y-m-d'))->toBe($now->format('Y-m-d'));
    });
});

// ---------------------------------------------------------------------------
// Previous date range (comparison period)
// ---------------------------------------------------------------------------

describe('resolvePreviousDateRange', function (): void {
    test('today previous is yesterday', function (): void {
        $service = new PlatformAnalyticsService;
        $current = $service->resolveDateRange('today');
        $prev = $service->resolvePreviousDateRange('today', $current);

        expect($prev['start']->format('Y-m-d'))
            ->toBe(Carbon::yesterday()->format('Y-m-d'));
    });

    test('month previous is last month boundaries', function (): void {
        $service = new PlatformAnalyticsService;
        $current = $service->resolveDateRange('month');
        $prev = $service->resolvePreviousDateRange('month', $current);
        $lastMonth = Carbon::now()->subMonth();

        expect($prev['start']->format('Y-m'))->toBe($lastMonth->format('Y-m'))
            ->and($prev['start']->day)->toBe(1);
    });

    test('year previous is last year', function (): void {
        $service = new PlatformAnalyticsService;
        $current = $service->resolveDateRange('year');
        $prev = $service->resolvePreviousDateRange('year', $current);

        expect($prev['start']->year)->toBe(Carbon::now()->year - 1)
            ->and($prev['start']->format('m-d'))->toBe('01-01');
    });
});

// ---------------------------------------------------------------------------
// Percentage change calculation
// ---------------------------------------------------------------------------

describe('calculatePercentageChange', function (): void {
    test('positive increase is calculated correctly', function (): void {
        $service = new PlatformAnalyticsService;

        expect($service->calculatePercentageChange(150, 100))->toBe(50.0)
            ->and($service->calculatePercentageChange(110, 100))->toBe(10.0);
    });

    test('negative decrease is calculated correctly', function (): void {
        $service = new PlatformAnalyticsService;

        expect($service->calculatePercentageChange(80, 100))->toBe(-20.0);
    });

    test('zero previous with non-zero current returns 100%', function (): void {
        $service = new PlatformAnalyticsService;

        expect($service->calculatePercentageChange(50, 0))->toBe(100.0);
    });

    test('zero previous and zero current returns null', function (): void {
        $service = new PlatformAnalyticsService;

        expect($service->calculatePercentageChange(0, 0))->toBeNull();
    });

    test('result is rounded to 1 decimal place', function (): void {
        $service = new PlatformAnalyticsService;

        expect($service->calculatePercentageChange(133, 100))->toBe(33.0)
            ->and($service->calculatePercentageChange(107, 100))->toBe(7.0);
    });
});

// ---------------------------------------------------------------------------
// Chart granularity (daily ≤ 3 months, weekly > 3 months)
// ---------------------------------------------------------------------------

describe('resolveChartGranularity', function (): void {
    test('1-day range uses daily granularity', function (): void {
        $service = new PlatformAnalyticsService;
        $start = Carbon::today();
        $end = Carbon::today();

        expect($service->resolveChartGranularity($start, $end))->toBe('daily');
    });

    test('3-month range uses daily granularity', function (): void {
        $service = new PlatformAnalyticsService;
        $start = Carbon::today()->subMonths(3);
        $end = Carbon::today();

        expect($service->resolveChartGranularity($start, $end))->toBe('daily');
    });

    test('6-month range uses weekly granularity', function (): void {
        $service = new PlatformAnalyticsService;
        $start = Carbon::today()->subMonths(6);
        $end = Carbon::today();

        expect($service->resolveChartGranularity($start, $end))->toBe('weekly');
    });

    test('1-year range uses weekly granularity', function (): void {
        $service = new PlatformAnalyticsService;
        $start = Carbon::today()->subYear();
        $end = Carbon::today();

        expect($service->resolveChartGranularity($start, $end))->toBe('weekly');
    });
});

// ---------------------------------------------------------------------------
// XAF formatting helper
// ---------------------------------------------------------------------------

describe('formatXAF', function (): void {
    test('formats integers with thousand separators and XAF suffix', function (): void {
        expect(PlatformAnalyticsService::formatXAF(0))->toBe('0 XAF')
            ->and(PlatformAnalyticsService::formatXAF(1000))->toBe('1,000 XAF')
            ->and(PlatformAnalyticsService::formatXAF(320000))->toBe('320,000 XAF')
            ->and(PlatformAnalyticsService::formatXAF(1500000))->toBe('1,500,000 XAF');
    });
});

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

describe('COMPLETED_STATUSES constant', function (): void {
    test('contains completed, delivered, and picked_up statuses', function (): void {
        expect(PlatformAnalyticsService::COMPLETED_STATUSES)
            ->toContain('completed')
            ->toContain('delivered')
            ->toContain('picked_up');
    });
});

describe('PERIODS constant', function (): void {
    test('contains all 5 supported period keys', function (): void {
        expect(PlatformAnalyticsService::PERIODS)
            ->toContain('today')
            ->toContain('week')
            ->toContain('month')
            ->toContain('year')
            ->toContain('custom');
    });
});
