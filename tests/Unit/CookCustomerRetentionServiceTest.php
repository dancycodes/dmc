<?php

use App\Models\Order;
use App\Services\CookCustomerRetentionService;
use Carbon\Carbon;

/**
 * F-202: Cook Customer Retention Analytics — Unit tests for CookCustomerRetentionService.
 */
it('resolves this_month date range correctly', function () {
    $service = new CookCustomerRetentionService;
    $range = $service->resolveDateRange('this_month');

    expect($range['start']->isStartOfMonth())->toBeTrue()
        ->and($range['end']->toDateString())->toBe(Carbon::now()->endOfMonth()->toDateString());
});

it('resolves last_3_months date range correctly', function () {
    $service = new CookCustomerRetentionService;
    $range = $service->resolveDateRange('last_3_months');

    $expected = Carbon::now()->subMonths(3)->startOfDay()->toDateString();

    expect($range['start']->toDateString())->toBe($expected)
        ->and($range['end']->toDateString())->toBe(Carbon::now()->toDateString());
});

it('resolves last_6_months date range correctly', function () {
    $service = new CookCustomerRetentionService;
    $range = $service->resolveDateRange('last_6_months');

    $expected = Carbon::now()->subMonths(6)->startOfDay()->toDateString();

    expect($range['start']->toDateString())->toBe($expected)
        ->and($range['end']->toDateString())->toBe(Carbon::now()->toDateString());
});

it('resolves this_year date range correctly', function () {
    $service = new CookCustomerRetentionService;
    $range = $service->resolveDateRange('this_year');

    expect($range['start']->isStartOfYear())->toBeTrue()
        ->and($range['end']->toDateString())->toBe(Carbon::now()->endOfYear()->toDateString());
});

it('resolves custom date range correctly', function () {
    $service = new CookCustomerRetentionService;
    $range = $service->resolveDateRange('custom', '2025-03-01', '2025-03-31');

    expect($range['start']->toDateString())->toBe('2025-03-01')
        ->and($range['end']->toDateString())->toBe('2025-03-31');
});

it('falls back to this_month for unknown period key', function () {
    $service = new CookCustomerRetentionService;
    $range = $service->resolveDateRange('unknown_period');

    expect($range['start']->isStartOfMonth())->toBeTrue();
});

it('formats XAF amounts correctly', function () {
    expect(CookCustomerRetentionService::formatXAF(0))->toBe('0 XAF')
        ->and(CookCustomerRetentionService::formatXAF(5000))->toBe('5,000 XAF')
        ->and(CookCustomerRetentionService::formatXAF(1000000))->toBe('1,000,000 XAF');
});

it('has the correct PERIODS constant keys', function () {
    $keys = array_keys(CookCustomerRetentionService::PERIODS);

    expect($keys)->toContain('this_month')
        ->toContain('last_3_months')
        ->toContain('last_6_months')
        ->toContain('this_year')
        ->toContain('custom');
});

it('has the correct SORT_OPTIONS keys', function () {
    $keys = array_keys(CookCustomerRetentionService::SORT_OPTIONS);

    expect($keys)->toContain('total_spend')
        ->toContain('order_count');
});

it('has the correct COMPLETED_STATUSES', function () {
    expect(CookCustomerRetentionService::COMPLETED_STATUSES)
        ->toContain(Order::STATUS_COMPLETED)
        ->toContain(Order::STATUS_DELIVERED)
        ->toContain(Order::STATUS_PICKED_UP);
});

it('has exactly 3 CLV distribution buckets', function () {
    expect(CookCustomerRetentionService::CLV_BUCKETS)->toHaveCount(3);
});

it('CLV buckets have correct min/max boundaries', function () {
    $buckets = CookCustomerRetentionService::CLV_BUCKETS;

    // First bucket: 0–5,000
    expect($buckets[0]['min'])->toBe(0)
        ->and($buckets[0]['max'])->toBe(5000);

    // Second bucket: 5,001–20,000
    expect($buckets[1]['min'])->toBe(5001)
        ->and($buckets[1]['max'])->toBe(20000);

    // Third bucket: 20,001+
    expect($buckets[2]['min'])->toBe(20001)
        ->and($buckets[2]['max'])->toBeNull();
});

it('CLV buckets have non-empty labels', function () {
    foreach (CookCustomerRetentionService::CLV_BUCKETS as $bucket) {
        expect($bucket['label'])->not->toBeEmpty();
    }
});
