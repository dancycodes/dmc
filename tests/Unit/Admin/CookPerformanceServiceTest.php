<?php

use App\Services\CookPerformanceService;
use Carbon\Carbon;

$projectRoot = dirname(__DIR__, 3);

test('service class exists', function () use ($projectRoot): void {
    expect(file_exists($projectRoot.'/app/Services/CookPerformanceService.php'))->toBeTrue();
});

test('PERIODS constant contains expected keys', function (): void {
    expect(CookPerformanceService::PERIODS)->toHaveKey('this_month')
        ->toHaveKey('last_3_months')
        ->toHaveKey('last_6_months')
        ->toHaveKey('this_year')
        ->toHaveKey('last_year')
        ->toHaveKey('all_time')
        ->toHaveKey('custom');
});

test('SORT_COLUMNS contains all required columns', function (): void {
    $expected = ['cook_name', 'region', 'total_orders', 'total_revenue', 'avg_rating', 'complaint_count', 'avg_response_hours'];

    foreach ($expected as $col) {
        expect(in_array($col, CookPerformanceService::SORT_COLUMNS, true))->toBeTrue(
            "Expected sort column '{$col}' to be in SORT_COLUMNS"
        );
    }
});

test('PER_PAGE is 25', function (): void {
    expect(CookPerformanceService::PER_PAGE)->toBe(25);
});

test('resolveDateRange returns null start and end for all_time', function (): void {
    $service = new CookPerformanceService;
    $range = $service->resolveDateRange('all_time');

    expect($range['start'])->toBeNull();
    expect($range['end'])->toBeNull();
});

test('resolveDateRange returns Carbon dates for this_month', function (): void {
    $service = new CookPerformanceService;
    $range = $service->resolveDateRange('this_month');

    expect($range['start'])->toBeInstanceOf(Carbon::class);
    expect($range['end'])->toBeInstanceOf(Carbon::class);
    expect($range['start']->day)->toBe(1);
});

test('resolveDateRange returns custom range', function (): void {
    $service = new CookPerformanceService;
    $range = $service->resolveDateRange('custom', '2025-01-01', '2025-03-31');

    expect($range['start']->format('Y-m-d'))->toBe('2025-01-01');
    expect($range['end']->format('Y-m-d'))->toBe('2025-03-31');
});

test('resolveDateRange defaults unknown period to all_time', function (): void {
    $service = new CookPerformanceService;
    $range = $service->resolveDateRange('unknown_period');

    expect($range['start'])->toBeNull();
    expect($range['end'])->toBeNull();
});

test('formatXAF formats integer as XAF currency string', function (): void {
    expect(CookPerformanceService::formatXAF(1000))->toBe('1,000 XAF');
    expect(CookPerformanceService::formatXAF(250000))->toBe('250,000 XAF');
    expect(CookPerformanceService::formatXAF(0))->toBe('0 XAF');
});

test('formatResponseTime returns N/A for null', function (): void {
    expect(CookPerformanceService::formatResponseTime(null))->toBe('N/A');
});

test('formatResponseTime returns 30 mins for 0.5 hours', function (): void {
    expect(CookPerformanceService::formatResponseTime(0.5))->toBe('30 mins');
});

test('formatResponseTime returns 1 min for very small value', function (): void {
    expect(CookPerformanceService::formatResponseTime(0.01))->toBe('1 min');
});

test('formatResponseTime returns 3 hrs for 3.0 hours', function (): void {
    expect(CookPerformanceService::formatResponseTime(3.0))->toBe('3 hrs');
});

test('formatResponseTime returns 1 hr for 1.0 hours', function (): void {
    expect(CookPerformanceService::formatResponseTime(1.0))->toBe('1 hr');
});

test('formatResponseTime returns 2 days for 48 hours', function (): void {
    expect(CookPerformanceService::formatResponseTime(48.0))->toBe('2 days');
});

test('formatResponseTime returns 1 day for 24 hours', function (): void {
    expect(CookPerformanceService::formatResponseTime(24.0))->toBe('1 day');
});

test('ratingColorClass returns success class for high rating', function (): void {
    expect(CookPerformanceService::ratingColorClass(4.5))->toContain('text-success');
    expect(CookPerformanceService::ratingColorClass(4.0))->toContain('text-success');
});

test('ratingColorClass returns warning class for medium rating', function (): void {
    expect(CookPerformanceService::ratingColorClass(3.5))->toContain('text-warning');
    expect(CookPerformanceService::ratingColorClass(3.0))->toContain('text-warning');
});

test('ratingColorClass returns danger class for low rating', function (): void {
    expect(CookPerformanceService::ratingColorClass(2.5))->toContain('text-danger');
    expect(CookPerformanceService::ratingColorClass(1.0))->toContain('text-danger');
});

test('ratingColorClass returns muted class for null rating', function (): void {
    expect(CookPerformanceService::ratingColorClass(null))->toContain('text-on-surface/50');
});

test('complaintColorClass returns success class for zero complaints', function (): void {
    expect(CookPerformanceService::complaintColorClass(0))->toContain('text-success');
});

test('complaintColorClass returns warning class for 1 to 4 complaints', function (): void {
    expect(CookPerformanceService::complaintColorClass(1))->toContain('text-warning');
    expect(CookPerformanceService::complaintColorClass(4))->toContain('text-warning');
});

test('complaintColorClass returns danger class for 5 or more complaints', function (): void {
    expect(CookPerformanceService::complaintColorClass(5))->toContain('text-danger');
    expect(CookPerformanceService::complaintColorClass(15))->toContain('text-danger');
});
