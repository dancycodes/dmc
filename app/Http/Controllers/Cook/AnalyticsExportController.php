<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsExportService;
use App\Services\CookOrderAnalyticsService;
use App\Services\CookRevenueAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * AnalyticsExportController — CSV and PDF export for cook analytics.
 *
 * F-208: Analytics CSV/PDF Export (Cook side)
 *
 * Routes:
 *   GET /dashboard/analytics/export-csv       — cook revenue CSV
 *   GET /dashboard/analytics/export-pdf       — cook revenue PDF
 *   GET /dashboard/analytics/orders/export-csv — cook orders CSV
 *   GET /dashboard/analytics/orders/export-pdf — cook orders PDF
 *
 * BR-449: Export formats: CSV and PDF.
 * BR-450: Exports respect currently applied date range and filters.
 * BR-453: For datasets < 5,000 rows: immediate browser download.
 * BR-456: Filename: dancymeals-{type}-{range}-{timestamp}.{ext}
 * BR-457: CSV uses UTF-8 BOM for Excel compatibility.
 * BR-458: PDF includes DancyMeals header, generated date, user name.
 * BR-459: Cook sees only their tenant's data.
 * BR-461: Export events are logged via Spatie Activitylog.
 */
class AnalyticsExportController extends Controller
{
    public function __construct(
        private readonly AnalyticsExportService $exportService,
        private readonly CookRevenueAnalyticsService $revenueService,
        private readonly CookOrderAnalyticsService $orderService,
    ) {}

    // ─── Revenue Analytics Exports ────────────────────────────────────────────

    /**
     * Export cook revenue analytics as CSV.
     *
     * GET /dashboard/analytics/export-csv
     */
    public function revenueExportCsv(Request $request): StreamedResponse
    {
        $tenant = tenant();
        $this->authorizeAnalyticsAccess($request);

        [$start, $end] = $this->resolveDateRange($request, 'revenue');
        $filename = $this->exportService->buildFilename('cook-revenue', $start, $end, 'csv');
        $rows = $this->exportService->getCookRevenueRows($tenant->id, $start, $end);

        $this->logExport($request, 'cook-revenue-csv', $start, $end);

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');

            // BR-457: UTF-8 BOM for Excel compatibility
            fwrite($output, AnalyticsExportService::UTF8_BOM);

            fputcsv($output, [
                __('Date'),
                __('Revenue (XAF)'),
                __('Order Count'),
            ]);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row['date'],
                    $row['revenue'],
                    $row['order_count'],
                ]);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Export cook revenue analytics as PDF (HTML-rendered).
     *
     * GET /dashboard/analytics/export-pdf
     */
    public function revenueExportPdf(Request $request): Response
    {
        $tenant = tenant();
        $this->authorizeAnalyticsAccess($request);

        [$start, $end] = $this->resolveDateRange($request, 'revenue');
        $filename = $this->exportService->buildFilename('cook-revenue', $start, $end, 'html');

        $rows = $this->exportService->getCookRevenueRows($tenant->id, $start, $end);
        $totalRevenue = $rows->sum('revenue');
        $totalOrders = $rows->sum('order_count');

        $this->logExport($request, 'cook-revenue-pdf', $start, $end);

        $html = view('exports.cook-revenue-pdf', [
            'rows' => $rows,
            'totalRevenue' => $totalRevenue,
            'totalOrders' => $totalOrders,
            'rangeStart' => $start,
            'rangeEnd' => $end,
            'generatedAt' => now(),
            'userName' => $request->user()->name,
            'cookName' => $tenant->name,
            'hasData' => $rows->isNotEmpty(),
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    // ─── Order Analytics Exports ──────────────────────────────────────────────

    /**
     * Export cook order analytics as CSV.
     *
     * GET /dashboard/analytics/orders/export-csv
     */
    public function orderExportCsv(Request $request): StreamedResponse
    {
        $tenant = tenant();
        $this->authorizeAnalyticsAccess($request);

        [$start, $end] = $this->resolveDateRange($request, 'orders');
        $filename = $this->exportService->buildFilename('cook-orders', $start, $end, 'csv');
        $rows = $this->exportService->getCookOrderRows($tenant->id, $start, $end);

        $this->logExport($request, 'cook-orders-csv', $start, $end);

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');

            fwrite($output, AnalyticsExportService::UTF8_BOM);

            fputcsv($output, [
                __('Date'),
                __('Total Orders'),
                __('Confirmed'),
                __('Completed'),
                __('Cancelled'),
            ]);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row['date'],
                    $row['total_orders'],
                    $row['confirmed'],
                    $row['completed'],
                    $row['cancelled'],
                ]);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Export cook order analytics as PDF (HTML-rendered).
     *
     * GET /dashboard/analytics/orders/export-pdf
     */
    public function orderExportPdf(Request $request): Response
    {
        $tenant = tenant();
        $this->authorizeAnalyticsAccess($request);

        [$start, $end] = $this->resolveDateRange($request, 'orders');
        $filename = $this->exportService->buildFilename('cook-orders', $start, $end, 'html');

        $rows = $this->exportService->getCookOrderRows($tenant->id, $start, $end);
        $totalOrders = $rows->sum('total_orders');
        $totalCompleted = $rows->sum('completed');
        $totalCancelled = $rows->sum('cancelled');

        $this->logExport($request, 'cook-orders-pdf', $start, $end);

        $html = view('exports.cook-orders-pdf', [
            'rows' => $rows,
            'totalOrders' => $totalOrders,
            'totalCompleted' => $totalCompleted,
            'totalCancelled' => $totalCancelled,
            'rangeStart' => $start,
            'rangeEnd' => $end,
            'generatedAt' => now(),
            'userName' => $request->user()->name,
            'cookName' => $tenant->name,
            'hasData' => $rows->isNotEmpty(),
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    /**
     * Verify the user has the right to view cook analytics.
     */
    private function authorizeAnalyticsAccess(Request $request): void
    {
        $user = $request->user();

        if (! $user->can('can-view-cook-analytics')) {
            abort(403, __('You do not have permission to export analytics.'));
        }
    }

    /**
     * Resolve the date range from request parameters.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(Request $request, string $type): array
    {
        $service = $type === 'orders' ? $this->orderService : $this->revenueService;

        $period = $request->input('period', 'this_month');
        $validPeriods = array_keys(CookRevenueAnalyticsService::PERIODS);

        if (! in_array($period, $validPeriods, true)) {
            $period = 'this_month';
        }

        $customStart = $request->input('custom_start');
        $customEnd = $request->input('custom_end');

        if ($period === 'custom' && (! $customStart || ! $customEnd)) {
            $period = 'this_month';
        }

        $range = $service->resolveDateRange($period, $customStart, $customEnd);

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
                'tenant_id' => tenant()?->id,
                'ip' => $request->ip(),
            ])
            ->log('analytics_exported');
    }
}
