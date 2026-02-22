<?php

use App\Services\AdminGrowthMetricsService;
use Carbon\Carbon;

$projectRoot = dirname(__DIR__, 3);

/**
 * Unit tests for AdminGrowthMetricsService — F-207 Admin Growth Metrics.
 *
 * Tests cover pure logic methods that do not require the Laravel app container.
 */
describe('AdminGrowthMetricsService', function () {

    describe('resolveDateRange', function () {
        it('returns correct range for last_3_months', function () {
            $service = new AdminGrowthMetricsService;
            $range = $service->resolveDateRange('last_3_months');

            expect($range['start'])->toBeInstanceOf(Carbon::class);
            expect($range['end'])->toBeInstanceOf(Carbon::class);
            expect($range['start']->diffInMonths($range['end']))->toBeBetween(2, 4);
        });

        it('returns correct range for last_6_months', function () {
            $service = new AdminGrowthMetricsService;
            $range = $service->resolveDateRange('last_6_months');

            expect($range['start']->diffInMonths($range['end']))->toBeBetween(5, 7);
        });

        it('returns correct range for this_year', function () {
            $service = new AdminGrowthMetricsService;
            $range = $service->resolveDateRange('this_year');

            expect($range['start']->year)->toBe(Carbon::now()->year);
            expect($range['end']->year)->toBe(Carbon::now()->year);
        });

        it('returns correct range for last_year', function () {
            $service = new AdminGrowthMetricsService;
            $range = $service->resolveDateRange('last_year');

            $lastYear = Carbon::now()->subYear()->year;
            expect($range['start']->year)->toBe($lastYear);
            expect($range['end']->year)->toBe($lastYear);
        });

        it('returns all_time range starting from 2020', function () {
            $service = new AdminGrowthMetricsService;
            $range = $service->resolveDateRange('all_time');

            expect($range['start']->year)->toBe(2020);
        });

        it('falls back to last_6_months for unknown period', function () {
            $service = new AdminGrowthMetricsService;
            $range = $service->resolveDateRange('unknown_period');

            // Default is last_6_months
            expect($range['start']->diffInMonths($range['end']))->toBeBetween(5, 7);
        });
    });

    describe('resolvePreviousDateRange', function () {
        it('shifts back 3 months for last_3_months', function () {
            $service = new AdminGrowthMetricsService;
            $current = $service->resolveDateRange('last_3_months');
            $prev = $service->resolvePreviousDateRange('last_3_months', $current);

            expect($prev['start'])->toBeInstanceOf(Carbon::class);
            expect($prev['end'])->toBeInstanceOf(Carbon::class);
            // Previous end should be just before current start
            expect($prev['end']->lt($current['start']))->toBeTrue();
        });

        it('shifts back 6 months for last_6_months', function () {
            $service = new AdminGrowthMetricsService;
            $current = $service->resolveDateRange('last_6_months');
            $prev = $service->resolvePreviousDateRange('last_6_months', $current);

            expect($prev['end']->lt($current['start']))->toBeTrue();
        });
    });

    describe('calculatePercentageChange', function () {
        it('returns null when both are zero', function () {
            $service = new AdminGrowthMetricsService;
            expect($service->calculatePercentageChange(0, 0))->toBeNull();
        });

        it('returns 100.0 when previous is zero and current is positive', function () {
            $service = new AdminGrowthMetricsService;
            expect($service->calculatePercentageChange(10, 0))->toBe(100.0);
        });

        it('returns null when previous is zero and current is also zero', function () {
            $service = new AdminGrowthMetricsService;
            expect($service->calculatePercentageChange(0, 0))->toBeNull();
        });

        it('calculates positive growth correctly', function () {
            $service = new AdminGrowthMetricsService;
            // 50% growth
            expect($service->calculatePercentageChange(150, 100))->toBe(50.0);
        });

        it('calculates negative growth correctly', function () {
            $service = new AdminGrowthMetricsService;
            // -25% growth
            expect($service->calculatePercentageChange(75, 100))->toBe(-25.0);
        });

        it('rounds to 1 decimal place', function () {
            $service = new AdminGrowthMetricsService;
            // 33.333... → 33.3
            expect($service->calculatePercentageChange(100, 75))->toBe(33.3);
        });
    });

    describe('PERIODS constant', function () {
        it('contains all 5 required period keys per BR-445', function () {
            $periods = AdminGrowthMetricsService::PERIODS;
            expect($periods)->toHaveKey('last_3_months');
            expect($periods)->toHaveKey('last_6_months');
            expect($periods)->toHaveKey('this_year');
            expect($periods)->toHaveKey('last_year');
            expect($periods)->toHaveKey('all_time');
        });
    });

    describe('milestone thresholds', function () {
        it('defines user milestones per BR-447', function () {
            expect(AdminGrowthMetricsService::USER_MILESTONES)->toBe([100, 500, 1000, 5000, 10000]);
        });

        it('defines cook milestones per BR-447', function () {
            expect(AdminGrowthMetricsService::COOK_MILESTONES)->toBe([10, 50, 100]);
        });

        it('defines order milestones per BR-447', function () {
            expect(AdminGrowthMetricsService::ORDER_MILESTONES)->toBe([1000, 10000, 100000]);
        });
    });
});
