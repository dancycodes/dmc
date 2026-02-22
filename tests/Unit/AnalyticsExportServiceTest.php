<?php

use App\Services\AnalyticsExportService;
use Carbon\Carbon;

// ─── Constants ───────────────────────────────────────────────────────────────

it('defines IMMEDIATE_ROW_LIMIT as 5000', function (): void {
    expect(AnalyticsExportService::IMMEDIATE_ROW_LIMIT)->toBe(5000);
});

it('defines UTF8_BOM as the correct 3-byte sequence', function (): void {
    expect(AnalyticsExportService::UTF8_BOM)->toBe("\xEF\xBB\xBF");
});

it('defines non-empty COMPLETED_STATUSES array', function (): void {
    expect(AnalyticsExportService::COMPLETED_STATUSES)->toBeArray()->not->toBeEmpty();
});

// ─── buildFilename ────────────────────────────────────────────────────────────

it('buildFilename follows BR-456 format', function (): void {
    $service = new AnalyticsExportService;
    $start = Carbon::parse('2025-01-01');
    $end = Carbon::parse('2025-01-31');

    $filename = $service->buildFilename('revenue', $start, $end, 'csv');

    // Must start with "dancymeals-revenue-20250101-20250131-" and end with ".csv"
    expect($filename)
        ->toStartWith('dancymeals-revenue-20250101-20250131-')
        ->toEndWith('.csv');
});

it('buildFilename uses correct type in name', function (): void {
    $service = new AnalyticsExportService;
    $start = Carbon::parse('2025-06-01');
    $end = Carbon::parse('2025-06-30');

    $csvFilename = $service->buildFilename('orders', $start, $end, 'csv');
    $pdfFilename = $service->buildFilename('orders', $start, $end, 'html');

    expect($csvFilename)->toContain('orders');
    expect($pdfFilename)->toContain('orders');
    expect($csvFilename)->toEndWith('.csv');
    expect($pdfFilename)->toEndWith('.html');
});

it('buildFilename contains a numeric timestamp segment', function (): void {
    $service = new AnalyticsExportService;
    $start = Carbon::parse('2025-01-01');
    $end = Carbon::parse('2025-03-31');

    $filename = $service->buildFilename('growth', $start, $end, 'csv');

    // Timestamp segment should be 14 digits (YmdHis), extract from name
    preg_match('/dancymeals-growth-\d{8}-\d{8}-(\d{14})\.csv/', $filename, $matches);
    expect($matches)->not->toBeEmpty();
    expect(strlen($matches[1]))->toBe(14);
});

// ─── formatXAF ───────────────────────────────────────────────────────────────

it('formatXAF formats integer correctly with thousands separator', function (): void {
    expect(AnalyticsExportService::formatXAF(1000))->toBe('1,000 XAF');
    expect(AnalyticsExportService::formatXAF(0))->toBe('0 XAF');
    expect(AnalyticsExportService::formatXAF(250000))->toBe('250,000 XAF');
});

it('formatXAF appends XAF currency code', function (): void {
    $formatted = AnalyticsExportService::formatXAF(5000);
    expect($formatted)->toEndWith(' XAF');
});

it('formatXAF handles large amounts correctly', function (): void {
    expect(AnalyticsExportService::formatXAF(1000000))->toBe('1,000,000 XAF');
    expect(AnalyticsExportService::formatXAF(10500750))->toBe('10,500,750 XAF');
});
