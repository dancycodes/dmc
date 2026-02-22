<?php

use App\Services\FinancialReportsService;
use Carbon\Carbon;

/**
 * Unit tests for FinancialReportsService.
 *
 * F-058: Financial Reports & Export
 * Tests pure logic methods that don't require DB access.
 */
describe('FinancialReportsService', function () {

    beforeEach(function () {
        $this->service = new FinancialReportsService;
    });

    // ─── getDefaultDateRange ─────────────────────────────────────────────────

    test('getDefaultDateRange returns start of current month to end of current month', function () {
        $range = $this->service->getDefaultDateRange();

        expect($range)->toHaveKeys(['start', 'end'])
            ->and($range['start'])->toBeInstanceOf(Carbon::class)
            ->and($range['end'])->toBeInstanceOf(Carbon::class)
            ->and($range['start']->day)->toBe(1)
            ->and($range['start']->hour)->toBe(0)
            ->and($range['end']->hour)->toBe(23)
            ->and($range['start']->month)->toBe(now()->month)
            ->and($range['end']->month)->toBe(now()->month);
    });

    // ─── parseDateRange ───────────────────────────────────────────────────────

    test('parseDateRange returns correct range for valid inputs', function () {
        $range = $this->service->parseDateRange('2026-01-01', '2026-01-31');

        expect($range['start']->format('Y-m-d'))->toBe('2026-01-01')
            ->and($range['end']->format('Y-m-d'))->toBe('2026-01-31')
            ->and($range['start']->hour)->toBe(0)
            ->and($range['end']->hour)->toBe(23);
    });

    test('parseDateRange falls back to current month for null inputs', function () {
        $range = $this->service->parseDateRange(null, null);
        $default = $this->service->getDefaultDateRange();

        expect($range['start']->format('Y-m'))->toBe($default['start']->format('Y-m'));
    });

    test('parseDateRange falls back to current month when start is after end', function () {
        $range = $this->service->parseDateRange('2026-02-28', '2026-02-01');
        $default = $this->service->getDefaultDateRange();

        expect($range['start']->format('Y-m'))->toBe($default['start']->format('Y-m'));
    });

    test('parseDateRange falls back to current month for invalid date strings', function () {
        $range = $this->service->parseDateRange('not-a-date', 'also-not-a-date');
        $default = $this->service->getDefaultDateRange();

        expect($range['start']->format('Y-m'))->toBe($default['start']->format('Y-m'));
    });

    // ─── formatXAF ───────────────────────────────────────────────────────────

    test('formatXAF formats zero correctly', function () {
        expect(FinancialReportsService::formatXAF(0))->toBe('0 XAF');
    });

    test('formatXAF formats small integer correctly', function () {
        expect(FinancialReportsService::formatXAF(500))->toBe('500 XAF');
    });

    test('formatXAF formats large amounts with thousands separator', function () {
        expect(FinancialReportsService::formatXAF(2_500_000))->toBe('2,500,000 XAF');
    });

    test('formatXAF formats exact commission example from spec', function () {
        // Spec scenario: Gross Revenue = 2,500,000 XAF
        expect(FinancialReportsService::formatXAF(2_500_000))->toBe('2,500,000 XAF')
            ->and(FinancialReportsService::formatXAF(250_000))->toBe('250,000 XAF')
            ->and(FinancialReportsService::formatXAF(2_250_000))->toBe('2,250,000 XAF');
    });

    // ─── Constants ───────────────────────────────────────────────────────────

    test('COMPLETED_STATUSES includes expected statuses', function () {
        expect(FinancialReportsService::COMPLETED_STATUSES)
            ->toContain('completed')
            ->toContain('delivered')
            ->toContain('picked_up');
    });

    test('TABS constant includes all four required tabs', function () {
        expect(FinancialReportsService::TABS)
            ->toContain('overview')
            ->toContain('by_cook')
            ->toContain('pending_payouts')
            ->toContain('failed_payments');
    });

    // ─── FinancialReportRequest accessor methods ──────────────────────────────

    test('service is instantiable without DI container', function () {
        $service = new FinancialReportsService;
        expect($service)->toBeInstanceOf(FinancialReportsService::class);
    });

});
