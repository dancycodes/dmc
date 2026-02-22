<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FinancialReportRequest;
use App\Services\FinancialReportsService;
use Illuminate\Http\Response;
use Illuminate\Http\StreamedResponse;

/**
 * FinancialReportsController — admin financial reporting.
 *
 * F-058: Financial Reports & Export
 * Routes:
 *   GET  /vault-entry/finance/reports             — index (Gale fragment on tab/filter change)
 *   GET  /vault-entry/finance/reports/export-csv  — exportCsv (plain response)
 *   GET  /vault-entry/finance/reports/export-pdf  — exportPdf (plain response)
 *
 * Permission: can-view-platform-analytics
 */
class FinancialReportsController extends Controller
{
    public function __construct(
        private readonly FinancialReportsService $service
    ) {}

    /**
     * Display the financial reports page with tab navigation and filters.
     * Handles both initial page load and Gale fragment updates on filter/tab change.
     */
    public function index(FinancialReportRequest $request): mixed
    {
        $tab = $request->getTab();
        $cookId = $request->getCookId();

        $range = $this->service->parseDateRange($request->getStartDate(), $request->getEndDate());
        $start = $range['start'];
        $end = $range['end'];

        $summary = $this->service->getSummaryMetrics($start, $end, $cookId);
        $cooks = $this->service->getCookOptions();

        $tableData = match ($tab) {
            'by_cook' => $this->service->getByCookData($start, $end, $cookId),
            'pending_payouts' => $this->service->getPendingPayoutsData($cookId),
            'failed_payments' => $this->service->getFailedPaymentsData($start, $end, $cookId),
            default => $this->service->getOverviewData($start, $end, $cookId),
        };

        $data = [
            'tab' => $tab,
            'startDate' => $start->format('Y-m-d'),
            'endDate' => $end->format('Y-m-d'),
            'cookId' => $cookId,
            'summary' => $summary,
            'tableData' => $tableData,
            'cooks' => $cooks,
            'rangeStart' => $start,
            'rangeEnd' => $end,
        ];

        if ($request->isGaleNavigate('finance-reports')) {
            return gale()->fragment('admin.finance.reports', 'finance-reports-content', $data);
        }

        return gale()->view('admin.finance.reports', $data, web: true);
    }

    /**
     * Export all filtered data as a CSV download.
     * BR-147: CSV includes ALL rows matching current filters (not paginated)
     * BR-149: Amounts in XAF
     */
    public function exportCsv(FinancialReportRequest $request): StreamedResponse
    {
        $tab = $request->getTab();
        $cookId = $request->getCookId();
        $range = $this->service->parseDateRange($request->getStartDate(), $request->getEndDate());

        $filename = 'financial-report-'.$tab.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($tab, $cookId, $range) {
            $output = fopen('php://output', 'w');

            match ($tab) {
                'by_cook' => $this->writeByCookCsv($output, $range, $cookId),
                'pending_payouts' => $this->writePendingPayoutsCsv($output, $cookId),
                'failed_payments' => $this->writeFailedPaymentsCsv($output, $range, $cookId),
                default => $this->writeOverviewCsv($output, $range, $cookId),
            };

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Export first 500 rows as an HTML-based PDF download.
     * BR-148: PDF includes summary header and first 500 rows.
     */
    public function exportPdf(FinancialReportRequest $request): Response
    {
        $tab = $request->getTab();
        $cookId = $request->getCookId();
        $range = $this->service->parseDateRange($request->getStartDate(), $request->getEndDate());

        $summary = $this->service->getSummaryMetrics($range['start'], $range['end'], $cookId);

        $allData = match ($tab) {
            'by_cook' => $this->service->getByCookData($range['start'], $range['end'], $cookId),
            'pending_payouts' => $this->service->getPendingPayoutsData($cookId),
            'failed_payments' => $this->service->getFailedPaymentsData($range['start'], $range['end'], $cookId),
            default => $this->service->getOverviewData($range['start'], $range['end'], $cookId),
        };

        $truncated = $allData->count() > 500;
        $tableData = $allData->take(500);

        $tabLabel = match ($tab) {
            'by_cook' => __('Revenue by Cook'),
            'pending_payouts' => __('Pending Payouts'),
            'failed_payments' => __('Failed Payments'),
            default => __('Overview'),
        };

        $html = view('admin.finance.reports-pdf', [
            'tab' => $tab,
            'tabLabel' => $tabLabel,
            'summary' => $summary,
            'tableData' => $tableData,
            'truncated' => $truncated,
            'totalCount' => $allData->count(),
            'rangeStart' => $range['start'],
            'rangeEnd' => $range['end'],
        ])->render();

        $filename = 'financial-report-'.$tab.'-'.now()->format('Y-m-d').'.html';

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    // ─── Private CSV writers ─────────────────────────────────────────────────

    /** @param resource $output */
    private function writeOverviewCsv($output, array $range, ?int $cookId): void
    {
        fputcsv($output, [
            __('Date'),
            __('Gross Revenue (XAF)'),
            __('Commission (XAF)'),
            __('Net Payout (XAF)'),
            __('Orders'),
        ]);

        $rows = $this->service->getOverviewData($range['start'], $range['end'], $cookId);
        foreach ($rows as $row) {
            fputcsv($output, [
                $row['date'],
                $row['gross_revenue'],
                $row['commission'],
                $row['net_payout'],
                $row['order_count'],
            ]);
        }
    }

    /** @param resource $output */
    private function writeByCookCsv($output, array $range, ?int $cookId): void
    {
        fputcsv($output, [
            __('Cook Name'),
            __('Tenant'),
            __('Gross Revenue (XAF)'),
            __('Commission Rate (%)'),
            __('Commission (XAF)'),
            __('Net Payout (XAF)'),
            __('Orders'),
        ]);

        $rows = $this->service->getByCookData($range['start'], $range['end'], $cookId);
        foreach ($rows as $row) {
            fputcsv($output, [
                $row['cook_name'],
                $row['tenant_name'],
                $row['gross_revenue'],
                $row['commission_rate'],
                $row['commission'],
                $row['net_payout'],
                $row['order_count'],
            ]);
        }
    }

    /** @param resource $output */
    private function writePendingPayoutsCsv($output, ?int $cookId): void
    {
        fputcsv($output, [
            __('Cook Name'),
            __('Tenant'),
            __('Total Balance (XAF)'),
            __('Withdrawable (XAF)'),
            __('Unwithdrawable (XAF)'),
        ]);

        $rows = $this->service->getPendingPayoutsData($cookId);
        foreach ($rows as $row) {
            fputcsv($output, [
                $row['cook_name'],
                $row['tenant_name'],
                $row['total_balance'],
                $row['withdrawable_balance'],
                $row['unwithdrawable_balance'],
            ]);
        }
    }

    /** @param resource $output */
    private function writeFailedPaymentsCsv($output, array $range, ?int $cookId): void
    {
        fputcsv($output, [
            __('Order Number'),
            __('Client Name'),
            __('Amount (XAF)'),
            __('Payment Method'),
            __('Failure Reason'),
            __('Date'),
        ]);

        $rows = $this->service->getFailedPaymentsData($range['start'], $range['end'], $cookId);
        foreach ($rows as $row) {
            fputcsv($output, [
                $row['order_number'],
                $row['client_name'],
                $row['amount'],
                $row['payment_method'],
                $row['failure_reason'],
                $row['created_at'],
            ]);
        }
    }
}
