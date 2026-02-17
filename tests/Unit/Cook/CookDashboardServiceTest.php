<?php

use App\Services\CookDashboardService;

/*
|--------------------------------------------------------------------------
| F-077: Cook Dashboard Home — CookDashboardService Unit Tests
|--------------------------------------------------------------------------
|
| Pure unit tests for CookDashboardService (no database required).
| DB-dependent tests are in the feature test file.
|
*/

// ---- XAF Formatting (BR-172) ----

it('formats zero amount correctly', function () {
    expect(CookDashboardService::formatXAF(0))->toBe('0 XAF');
});

it('formats small amounts correctly', function () {
    expect(CookDashboardService::formatXAF(500))->toBe('500 XAF');
});

it('formats amounts with comma thousand separators', function () {
    expect(CookDashboardService::formatXAF(45000))->toBe('45,000 XAF');
});

it('formats large amounts correctly', function () {
    expect(CookDashboardService::formatXAF(1250000))->toBe('1,250,000 XAF');
});

it('formats single digit amounts correctly', function () {
    expect(CookDashboardService::formatXAF(1))->toBe('1 XAF');
});

it('formats hundred thousands correctly', function () {
    expect(CookDashboardService::formatXAF(150000))->toBe('150,000 XAF');
});

it('formats millions correctly', function () {
    expect(CookDashboardService::formatXAF(10500000))->toBe('10,500,000 XAF');
});
