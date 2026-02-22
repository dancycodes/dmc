<?php

/**
 * Unit tests for AdminRevenueAnalyticsService.
 *
 * F-205: Admin Platform Revenue Analytics
 */

use App\Services\AdminRevenueAnalyticsService;
use Carbon\Carbon;

$projectRoot = dirname(__DIR__, 3);

test('service class exists', function () use ($projectRoot): void {
    expect(file_exists($projectRoot.'/app/Services/AdminRevenueAnalyticsService.php'))->toBeTrue();
});

test('PERIODS constant contains required period keys', function (): void {
    $periods = AdminRevenueAnalyticsService::PERIODS;

    expect($periods)->toHaveKey('this_month');
    expect($periods)->toHaveKey('last_3_months');
    expect($periods)->toHaveKey('last_6_months');
    expect($periods)->toHaveKey('this_year');
    expect($periods)->toHaveKey('last_year');
    expect($periods)->toHaveKey('custom');
});

test('COMPLETED_STATUSES constant is non-empty', function (): void {
    expect(AdminRevenueAnalyticsService::COMPLETED_STATUSES)->not->toBeEmpty();
});

test('formatXAF formats integer amounts correctly', function (): void {
    expect(AdminRevenueAnalyticsService::formatXAF(0))->toBe('0 XAF');
    expect(AdminRevenueAnalyticsService::formatXAF(1000))->toBe('1,000 XAF');
    expect(AdminRevenueAnalyticsService::formatXAF(45000000))->toBe('45,000,000 XAF');
    expect(AdminRevenueAnalyticsService::formatXAF(4500000))->toBe('4,500,000 XAF');
});

test('resolveDateRange returns correct range for this_month', function (): void {
    $service = new AdminRevenueAnalyticsService;
    Carbon::setTestNow(Carbon::parse('2025-06-15'));

    $range = $service->resolveDateRange('this_month');

    expect($range['start']->format('Y-m-d'))->toBe('2025-06-01');
    expect($range['end']->format('Y-m-d'))->toBe('2025-06-30');

    Carbon::setTestNow();
});

test('resolveDateRange returns correct range for this_year', function (): void {
    $service = new AdminRevenueAnalyticsService;
    Carbon::setTestNow(Carbon::parse('2025-06-15'));

    $range = $service->resolveDateRange('this_year');

    expect($range['start']->format('Y-m-d'))->toBe('2025-01-01');
    expect($range['end']->format('Y-m-d'))->toBe('2025-12-31');

    Carbon::setTestNow();
});

test('resolveDateRange returns correct range for last_year', function (): void {
    $service = new AdminRevenueAnalyticsService;
    Carbon::setTestNow(Carbon::parse('2025-06-15'));

    $range = $service->resolveDateRange('last_year');

    expect($range['start']->format('Y-m-d'))->toBe('2024-01-01');
    expect($range['end']->format('Y-m-d'))->toBe('2024-12-31');

    Carbon::setTestNow();
});

test('resolveDateRange returns correct range for custom period', function (): void {
    $service = new AdminRevenueAnalyticsService;

    $range = $service->resolveDateRange('custom', '2025-03-01', '2025-05-31');

    expect($range['start']->format('Y-m-d'))->toBe('2025-03-01');
    expect($range['end']->format('Y-m-d'))->toBe('2025-05-31');
});

test('resolveDateRange falls back to this_month for unknown period', function (): void {
    $service = new AdminRevenueAnalyticsService;
    Carbon::setTestNow(Carbon::parse('2025-06-15'));

    $range = $service->resolveDateRange('invalid_period');

    expect($range['start']->format('Y-m-d'))->toBe('2025-06-01');
    expect($range['end']->format('Y-m-d'))->toBe('2025-06-30');

    Carbon::setTestNow();
});

test('resolvePreviousDateRange returns previous month for this_month', function (): void {
    $service = new AdminRevenueAnalyticsService;
    Carbon::setTestNow(Carbon::parse('2025-06-15'));

    $range = $service->resolveDateRange('this_month');
    $prevRange = $service->resolvePreviousDateRange('this_month', $range);

    expect($prevRange['start']->format('Y-m-d'))->toBe('2025-05-01');
    expect($prevRange['end']->format('Y-m-d'))->toBe('2025-05-31');

    Carbon::setTestNow();
});

test('resolvePreviousDateRange returns previous year for this_year', function (): void {
    $service = new AdminRevenueAnalyticsService;
    Carbon::setTestNow(Carbon::parse('2025-06-15'));

    $range = $service->resolveDateRange('this_year');
    $prevRange = $service->resolvePreviousDateRange('this_year', $range);

    expect($prevRange['start']->format('Y-m-d'))->toBe('2024-01-01');
    expect($prevRange['end']->format('Y-m-d'))->toBe('2024-12-31');

    Carbon::setTestNow();
});

test('calculatePercentageChange returns correct positive change', function (): void {
    $service = new AdminRevenueAnalyticsService;

    expect($service->calculatePercentageChange(150, 100))->toBe(50.0);
    expect($service->calculatePercentageChange(200, 100))->toBe(100.0);
});

test('calculatePercentageChange returns correct negative change', function (): void {
    $service = new AdminRevenueAnalyticsService;

    expect($service->calculatePercentageChange(50, 100))->toBe(-50.0);
});

test('calculatePercentageChange handles zero previous correctly', function (): void {
    $service = new AdminRevenueAnalyticsService;

    expect($service->calculatePercentageChange(100, 0))->toBe(100.0);
    expect($service->calculatePercentageChange(0, 0))->toBeNull();
});

test('resolveGranularity returns daily for 31 days or fewer', function (): void {
    $service = new AdminRevenueAnalyticsService;

    $start = Carbon::parse('2025-06-01');
    $end = Carbon::parse('2025-06-30');

    expect($service->resolveGranularity($start, $end))->toBe('daily');
});

test('resolveGranularity returns monthly for more than 31 days', function (): void {
    $service = new AdminRevenueAnalyticsService;

    $start = Carbon::parse('2025-01-01');
    $end = Carbon::parse('2025-12-31');

    expect($service->resolveGranularity($start, $end))->toBe('monthly');
});
