<?php

use App\Models\Order;
use App\Services\CookOrderAnalyticsService;
use Carbon\Carbon;

/**
 * F-201: Cook Order Analytics — Unit tests for CookOrderAnalyticsService.
 */
it('resolves this_month date range correctly', function () {
    $service = new CookOrderAnalyticsService;
    $range = $service->resolveDateRange('this_month');

    expect($range['start']->isStartOfMonth())->toBeTrue()
        ->and($range['end']->toDateString())->toBe(Carbon::now()->endOfMonth()->toDateString());
});

it('resolves today date range correctly', function () {
    $service = new CookOrderAnalyticsService;
    $range = $service->resolveDateRange('today');

    expect($range['start']->toDateString())->toBe(Carbon::now()->toDateString())
        ->and($range['end']->toDateString())->toBe(Carbon::now()->toDateString());
});

it('resolves custom date range correctly', function () {
    $service = new CookOrderAnalyticsService;
    $range = $service->resolveDateRange('custom', '2025-01-01', '2025-01-31');

    expect($range['start']->toDateString())->toBe('2025-01-01')
        ->and($range['end']->toDateString())->toBe('2025-01-31');
});

it('falls back to this_month for unknown period key', function () {
    $service = new CookOrderAnalyticsService;
    $range = $service->resolveDateRange('unknown_period');

    expect($range['start']->isStartOfMonth())->toBeTrue();
});

it('resolves daily granularity for ranges up to 31 days', function () {
    $service = new CookOrderAnalyticsService;

    $start = Carbon::now()->startOfMonth();
    $end = Carbon::now()->endOfMonth();

    expect($service->resolveGranularity($start, $end))->toBe('daily');
});

it('resolves weekly granularity for ranges up to 183 days', function () {
    $service = new CookOrderAnalyticsService;

    $start = Carbon::now()->subMonths(3);
    $end = Carbon::now();

    expect($service->resolveGranularity($start, $end))->toBe('weekly');
});

it('resolves monthly granularity for ranges over 183 days', function () {
    $service = new CookOrderAnalyticsService;

    $start = Carbon::now()->subYear();
    $end = Carbon::now();

    expect($service->resolveGranularity($start, $end))->toBe('monthly');
});

it('formats XAF amounts correctly', function () {
    expect(CookOrderAnalyticsService::formatXAF(0))->toBe('0 XAF')
        ->and(CookOrderAnalyticsService::formatXAF(4200))->toBe('4,200 XAF')
        ->and(CookOrderAnalyticsService::formatXAF(1000000))->toBe('1,000,000 XAF');
});

it('has the correct PERIODS constant keys', function () {
    $keys = array_keys(CookOrderAnalyticsService::PERIODS);

    expect($keys)->toContain('today')
        ->toContain('this_week')
        ->toContain('this_month')
        ->toContain('last_3_months')
        ->toContain('last_6_months')
        ->toContain('this_year')
        ->toContain('custom');
});

it('has the correct COMPLETED_STATUSES for average order value', function () {
    expect(CookOrderAnalyticsService::COMPLETED_STATUSES)->toContain(Order::STATUS_COMPLETED)
        ->toContain(Order::STATUS_DELIVERED)
        ->toContain(Order::STATUS_PICKED_UP);
});

it('has 7 day labels matching days of the week', function () {
    expect(CookOrderAnalyticsService::DAY_LABELS)->toHaveCount(7)
        ->and(CookOrderAnalyticsService::DAY_LABELS[0])->toBe('Sun')
        ->and(CookOrderAnalyticsService::DAY_LABELS[6])->toBe('Sat');
});
