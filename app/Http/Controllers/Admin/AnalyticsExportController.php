<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminRevenueAnalyticsService;
use App\Services\AnalyticsExportService;
use App\Services\PlatformAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * AnalyticsExportController — CSV and PDF export for admin analytics.
 *
 * F-208: Analytics CSV/PDF Export (Admin side)
 *
 * Routes:
 *   GET /vault-entry/analytics/export-csv            — platform analytics CSV
 *   GET /vault-entry/analytics/export-pdf            — platform analytics PDF
 *   GET /vault-entry/analytics/revenue/export-csv    — admin revenue CSV
 *   GET /vault-entry/analytics/revenue/export-pdf    — admin revenue PDF
 *   GET /vault-entry/analytics/performance/export-csv — cook performance CSV
 *   GET /vault-entry/analytics/performance/export-pdf — cook performance PDF
 *   GET /vault-entry/analytics/growth/export-csv     — growth metrics CSV
 *   GET /vault-entry/analytics/growth/export-pdf     — growth metrics PDF
 *
 * BR-449: Export formats: CSV and PDF.
 * BR-450: Exports respect currently applied date range and filters.
 * BR-453: For datasets < 5,000 rows: immediate browser download.
 * BR-456: Filename: dancymeals-{type}-{range}-{timestamp}.{ext}
 * BR-457: CSV uses UTF-8 BOM for Excel compatibility.
 * BR-458: PDF includes DancyMeals header, generated date, user name.
 * BR-459: Admin sees platform-wide data.
 * BR-461: Export events are logged via Spatie Activitylog.
 */
class AnalyticsExportController extends Controller
{
    public function __construct(
        private readonly AnalyticsExportService $exportService,
        private readonly AdminRevenueAnalyticsService $revenueService,
        private readonly PlatformAnalyticsService $platformService,
    ) {}

    // ─── Platform Analytics (F-057) ───────────────────────────────────────────

    /**
     * Export platform analytics overview as CSV.
     *
     * GET /vault-entry/analytics/export-csv
     */
    public function platformExportCsv(Request $request): StreamedResponse
    {
        [$start, $end] = $this->resolveDateRange($request);
        $filename = $this->exportService->buildFilename('platform-analytics', $start, $end, 'csv');
        $rows = $this->exportService->getAdminRevenueRows($start, $end);

        $this->logExport($request, 'platform-analytics-csv', $start, $end);

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');
            fwrite($output, AnalyticsExportService::UTF8_BOM);

            fputcsv($output, [
                __('Date'),
                __('Gross Revenue (XAF)'),
                __('Commission (XAF)'),
                __('Net Payout (XAF)'),
                __('Orders'),
            ]);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row['date'],
                    $row['gross'],
                    $row['commission'],
                    $row['net'],
                    $row['order_count'],
                ]);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Export platform analytics overview as PDF (HTML-rendered).
     *
     * GET /vault-entry/analytics/export-pdf
     */
    public function platformExportPdf(Request $request): Response
    {
        [$start, $end] = $this->resolveDateRange($request);
        $filename = $this->exportService->buildFilename('platform-analytics', $start, $end, 'html');

        $rows = $this->exportService->getAdminRevenueRows($start, $end);
        $totalGross = $rows->sum('gross');
        $totalCommission = $rows->sum('commission');
        $totalNet = $rows->sum('net');
        $totalOrders = $rows->sum('order_count');

        $this->logExport($request, 'platform-analytics-pdf', $start, $end);

        $html = view('exports.admin-platform-pdf', [
            'rows' => $rows,
            'totalGross' => $totalGross,
            'totalCommission' => $totalCommission,
            'totalNet' => $totalNet,
            'totalOrders' => $totalOrders,
            'rangeStart' => $start,
            'rangeEnd' => $end,
            'generatedAt' => now(),
            'userName' => $request->user()->name,
            'title' => __('Platform Analytics'),
            'hasData' => $rows->isNotEmpty(),
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    // ─── Revenue Analytics (F-205) ────────────────────────────────────────────

    /**
     * Export admin revenue analytics as CSV.
     *
     * GET /vault-entry/analytics/revenue/export-csv
     */
    public function revenueExportCsv(Request $request): StreamedResponse
    {
        [$start, $end] = $this->resolveDateRange($request);
        $filename = $this->exportService->buildFilename('admin-revenue', $start, $end, 'csv');
        $rows = $this->exportService->getAdminRevenueRows($start, $end);

        $this->logExport($request, 'admin-revenue-csv', $start, $end);

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');
            fwrite($output, AnalyticsExportService::UTF8_BOM);

            fputcsv($output, [
                __('Date'),
                __('Gross Revenue (XAF)'),
                __('Commission (XAF)'),
                __('Net Payout (XAF)'),
                __('Orders'),
            ]);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row['date'],
                    $row['gross'],
                    $row['commission'],
                    $row['net'],
                    $row['order_count'],
                ]);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Export admin revenue analytics as PDF (HTML-rendered).
     *
     * GET /vault-entry/analytics/revenue/export-pdf
     */
    public function revenueExportPdf(Request $request): Response
    {
        [$start, $end] = $this->resolveDateRange($request);
        $filename = $this->exportService->buildFilename('admin-revenue', $start, $end, 'html');

        $rows = $this->exportService->getAdminRevenueRows($start, $end);
        $totalGross = $rows->sum('gross');
        $totalCommission = $rows->sum('commission');
        $totalNet = $rows->sum('net');
        $totalOrders = $rows->sum('order_count');

        $this->logExport($request, 'admin-revenue-pdf', $start, $end);

        $html = view('exports.admin-platform-pdf', [
            'rows' => $rows,
            'totalGross' => $totalGross,
            'totalCommission' => $totalCommission,
            'totalNet' => $totalNet,
            'totalOrders' => $totalOrders,
            'rangeStart' => $start,
            'rangeEnd' => $end,
            'generatedAt' => now(),
            'userName' => $request->user()->name,
            'title' => __('Platform Revenue Analytics'),
            'hasData' => $rows->isNotEmpty(),
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    // ─── Cook Performance (F-206) ─────────────────────────────────────────────

    /**
     * Export admin cook performance metrics as CSV.
     *
     * GET /vault-entry/analytics/performance/export-csv
     */
    public function performanceExportCsv(Request $request): StreamedResponse
    {
        [$start, $end] = $this->resolveDateRange($request);
        $regionId = $request->input('region') ? (int) $request->input('region') : null;
        $filename = $this->exportService->buildFilename('cook-performance', $start, $end, 'csv');
        $rows = $this->exportService->getAdminPerformanceRows($start, $end, $regionId);

        $this->logExport($request, 'cook-performance-csv', $start, $end);

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');
            fwrite($output, AnalyticsExportService::UTF8_BOM);

            fputcsv($output, [
                __('Cook Name'),
                __('Region'),
                __('Orders'),
                __('Revenue (XAF)'),
            ]);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row['cook_name'],
                    $row['region'],
                    $row['order_count'],
                    $row['net_revenue'],
                ]);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Export admin cook performance metrics as PDF (HTML-rendered).
     *
     * GET /vault-entry/analytics/performance/export-pdf
     */
    public function performanceExportPdf(Request $request): Response
    {
        [$start, $end] = $this->resolveDateRange($request);
        $regionId = $request->input('region') ? (int) $request->input('region') : null;
        $filename = $this->exportService->buildFilename('cook-performance', $start, $end, 'html');

        $rows = $this->exportService->getAdminPerformanceRows($start, $end, $regionId);

        $this->logExport($request, 'cook-performance-pdf', $start, $end);

        $html = view('exports.admin-performance-pdf', [
            'rows' => $rows,
            'rangeStart' => $start,
            'rangeEnd' => $end,
            'generatedAt' => now(),
            'userName' => $request->user()->name,
            'hasData' => $rows->isNotEmpty(),
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    // ─── Growth Metrics (F-207) ───────────────────────────────────────────────

    /**
     * Export admin growth metrics as CSV.
     *
     * GET /vault-entry/analytics/growth/export-csv
     */
    public function growthExportCsv(Request $request): StreamedResponse
    {
        [$start, $end] = $this->resolveDateRange($request);
        $filename = $this->exportService->buildFilename('growth-metrics', $start, $end, 'csv');
        $rows = $this->exportService->getAdminGrowthRows($start, $end);

        $this->logExport($request, 'growth-metrics-csv', $start, $end);

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');
            fwrite($output, AnalyticsExportService::UTF8_BOM);

            fputcsv($output, [
                __('Month'),
                __('New Users'),
                __('New Cooks'),
                __('Total Orders'),
                __('Gross Revenue (XAF)'),
            ]);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row['month'],
                    $row['new_users'],
                    $row['new_tenants'],
                    $row['order_count'],
                    $row['gross_revenue'],
                ]);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Export admin growth metrics as PDF (HTML-rendered).
     *
     * GET /vault-entry/analytics/growth/export-pdf
     */
    public function growthExportPdf(Request $request): Response
    {
        [$start, $end] = $this->resolveDateRange($request);
        $filename = $this->exportService->buildFilename('growth-metrics', $start, $end, 'html');

        $rows = $this->exportService->getAdminGrowthRows($start, $end);

        $this->logExport($request, 'growth-metrics-pdf', $start, $end);

        $html = view('exports.admin-growth-pdf', [
            'rows' => $rows,
            'rangeStart' => $start,
            'rangeEnd' => $end,
            'generatedAt' => now(),
            'userName' => $request->user()->name,
            'hasData' => $rows->isNotEmpty(),
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    /**
     * Resolve the date range from request parameters.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(Request $request): array
    {
        $period = $request->input('period', 'this_month');
        $validPeriods = array_keys(AdminRevenueAnalyticsService::PERIODS);

        if (! in_array($period, $validPeriods, true)) {
            $period = 'this_month';
        }

        $customStart = $request->input('custom_start');
        $customEnd = $request->input('custom_end');

        if ($period === 'custom' && (! $customStart || ! $customEnd)) {
            $period = 'this_month';
        }

        $range = $this->revenueService->resolveDateRange($period, $customStart, $customEnd);

        return [$range['start'], $range['end']];
    }

    /**
     * Log the export event via Spatie Activitylog (BR-461).
     */
    private function logExport(Request $request, string $exportType, Carbon $start, Carbon $end): void
    {
        activity()
            ->causedBy($request->user())
            ->withProperties([
                'export_type' => $exportType,
                'range_start' => $start->toDateString(),
                'range_end' => $end->toDateString(),
                'ip' => $request->ip(),
            ])
            ->log('analytics_exported');
    }
}
