<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SpendingStatsExportController — CSV and PDF export for client spending stats.
 *
 * F-208: Analytics CSV/PDF Export (Client side)
 *
 * Routes:
 *   GET /my-stats/export-csv — client spending CSV
 *   GET /my-stats/export-pdf — client spending PDF
 *
 * BR-449: Export formats: CSV and PDF.
 * BR-451: CSV contains raw tabular data with column headers.
 * BR-456: Filename: dancymeals-{type}-{range}-{timestamp}.{ext}
 * BR-457: CSV uses UTF-8 BOM for Excel compatibility.
 * BR-458: PDF includes DancyMeals header, generated date, user name.
 * BR-459: Client sees only their own data.
 * BR-461: Export events are logged via Spatie Activitylog.
 */
class SpendingStatsExportController extends Controller
{
    public function __construct(
        private readonly AnalyticsExportService $exportService,
    ) {}

    /**
     * Export client spending stats as CSV.
     *
     * GET /my-stats/export-csv
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $now = now();
        $filename = $this->exportService->buildFilename('client-spending', $now->copy()->subYear(), $now, 'csv');
        $rows = $this->exportService->getClientSpendingRows($user->id);

        $this->logExport($request, 'client-spending-csv');

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');

            // BR-457: UTF-8 BOM for Excel compatibility
            fwrite($output, AnalyticsExportService::UTF8_BOM);

            fputcsv($output, [
                __('Order Number'),
                __('Cook Name'),
                __('Amount (XAF)'),
                __('Status'),
                __('Date'),
            ]);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row['order_number'],
                    $row['cook_name'],
                    $row['amount'],
                    $row['status'],
                    $row['date'],
                ]);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Export client spending stats as PDF (HTML-rendered).
     *
     * GET /my-stats/export-pdf
     */
    public function exportPdf(Request $request): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $now = now();
        $filename = $this->exportService->buildFilename('client-spending', $now->copy()->subYear(), $now, 'html');

        $rows = $this->exportService->getClientSpendingRows($user->id);
        $totalSpent = $rows->sum('amount');
        $totalOrders = $rows->count();

        $this->logExport($request, 'client-spending-pdf');

        $html = view('exports.client-spending-pdf', [
            'rows' => $rows,
            'totalSpent' => $totalSpent,
            'totalOrders' => $totalOrders,
            'generatedAt' => now(),
            'userName' => $user->name,
            'hasData' => $rows->isNotEmpty(),
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Log the export event via Spatie Activitylog (BR-461).
     */
    private function logExport(Request $request, string $exportType): void
    {
        activity()
            ->causedBy($request->user())
            ->withProperties([
                'export_type' => $exportType,
                'ip' => $request->ip(),
            ])
            ->log('analytics_exported');
    }
}
