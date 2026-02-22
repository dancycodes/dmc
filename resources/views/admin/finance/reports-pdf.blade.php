<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ __('Financial Report') }} — DancyMeals</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #1a1a2e; background: #fff; padding: 24px; }
        h1 { font-size: 20px; font-weight: 700; color: #1a1a2e; }
        h2 { font-size: 14px; font-weight: 600; color: #374151; margin-top: 20px; margin-bottom: 8px; }
        .header { border-bottom: 2px solid #14b8a6; padding-bottom: 12px; margin-bottom: 20px; }
        .header-meta { font-size: 11px; color: #6b7280; margin-top: 4px; }
        .summary-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 20px; }
        .summary-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; background: #f9fafb; }
        .summary-card .label { font-size: 10px; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
        .summary-card .value { font-size: 14px; font-weight: 700; color: #1a1a2e; margin-top: 4px; }
        .summary-card .note { font-size: 10px; color: #9ca3af; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 11px; }
        thead tr { background: #f3f4f6; }
        th { padding: 8px 10px; text-align: left; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
        th.right, td.right { text-align: right; }
        td { padding: 7px 10px; border-bottom: 1px solid #f3f4f6; color: #374151; }
        tr.striped { background: #f9fafb; }
        tr:last-child td { border-bottom: none; }
        tfoot tr { background: #f3f4f6; }
        tfoot td { font-weight: 700; border-top: 2px solid #e5e7eb; }
        .mono { font-family: 'Courier New', monospace; }
        .truncated-note { background: #fef3c7; border: 1px solid #fde68a; border-radius: 6px; padding: 10px 14px; margin-bottom: 16px; font-size: 11px; color: #92400e; }
        .footer { margin-top: 24px; padding-top: 12px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #9ca3af; text-align: center; }
        @media print {
            body { padding: 12px; }
            .summary-grid { grid-template-columns: repeat(3, 1fr); }
        }
    </style>
</head>
<body>

    {{-- Header --}}
    <div class="header">
        <h1>DancyMeals — {{ __('Financial Report') }}</h1>
        <p class="header-meta">
            {{ $tabLabel }}
            @if($tab !== 'pending_payouts')
                &nbsp;|&nbsp; {{ __('Period: :start to :end', ['start' => $rangeStart->format('M j, Y'), 'end' => $rangeEnd->format('M j, Y')]) }}
            @endif
            &nbsp;|&nbsp; {{ __('Generated: :date', ['date' => now()->format('M j, Y H:i')]) }}
        </p>
    </div>

    {{-- Summary Cards --}}
    <h2>{{ __('Summary') }}</h2>
    <div class="summary-grid">
        <div class="summary-card">
            <p class="label">{{ __('Gross Revenue') }}</p>
            <p class="value">{{ \App\Services\FinancialReportsService::formatXAF($summary['gross_revenue']) }}</p>
            <p class="note">{{ __('Completed orders') }}</p>
        </div>
        <div class="summary-card">
            <p class="label">{{ __('Commission') }}</p>
            <p class="value">{{ \App\Services\FinancialReportsService::formatXAF($summary['commission']) }}</p>
            <p class="note">{{ __('Platform share') }}</p>
        </div>
        <div class="summary-card">
            <p class="label">{{ __('Net Payouts') }}</p>
            <p class="value">{{ \App\Services\FinancialReportsService::formatXAF($summary['net_payouts']) }}</p>
            <p class="note">{{ __('After commission') }}</p>
        </div>
        <div class="summary-card">
            <p class="label">{{ __('Pending Payouts') }}</p>
            <p class="value">{{ \App\Services\FinancialReportsService::formatXAF($summary['pending_payouts']) }}</p>
            <p class="note">{{ __('Cook wallets') }}</p>
        </div>
        <div class="summary-card">
            <p class="label">{{ __('Failed Payments') }}</p>
            <p class="value">{{ number_format($summary['failed_count']) }}</p>
            <p class="note">{{ __('Flutterwave failed') }}</p>
        </div>
    </div>

    {{-- Truncation Notice --}}
    @if($truncated)
        <div class="truncated-note">
            {{ __('Note: This PDF shows the first 500 rows out of :total total rows. For complete data, use the CSV export.', ['total' => number_format($totalCount)]) }}
        </div>
    @endif

    {{-- Table --}}
    <h2>{{ $tabLabel }}</h2>

    @if($tableData->isEmpty())
        <p style="color:#9ca3af;padding:16px 0;">{{ __('No data for this period.') }}</p>
    @else

        @if($tab === 'overview')
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th class="right">{{ __('Gross Revenue (XAF)') }}</th>
                        <th class="right">{{ __('Commission (XAF)') }}</th>
                        <th class="right">{{ __('Net Payout (XAF)') }}</th>
                        <th class="right">{{ __('Orders') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tableData as $row)
                        <tr class="{{ $loop->even ? 'striped' : '' }}">
                            <td>{{ $row['date'] }}</td>
                            <td class="right mono">{{ number_format($row['gross_revenue']) }}</td>
                            <td class="right mono">{{ number_format($row['commission']) }}</td>
                            <td class="right mono">{{ number_format($row['net_payout']) }}</td>
                            <td class="right">{{ number_format($row['order_count']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td>{{ __('Total') }}</td>
                        <td class="right mono">{{ number_format($tableData->sum('gross_revenue')) }} XAF</td>
                        <td class="right mono">{{ number_format($tableData->sum('commission')) }} XAF</td>
                        <td class="right mono">{{ number_format($tableData->sum('net_payout')) }} XAF</td>
                        <td class="right">{{ number_format($tableData->sum('order_count')) }}</td>
                    </tr>
                </tfoot>
            </table>

        @elseif($tab === 'by_cook')
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Cook') }}</th>
                        <th>{{ __('Tenant') }}</th>
                        <th class="right">{{ __('Gross Revenue (XAF)') }}</th>
                        <th class="right">{{ __('Rate (%)') }}</th>
                        <th class="right">{{ __('Commission (XAF)') }}</th>
                        <th class="right">{{ __('Net Payout (XAF)') }}</th>
                        <th class="right">{{ __('Orders') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tableData as $row)
                        <tr class="{{ $loop->even ? 'striped' : '' }}">
                            <td>{{ $row['cook_name'] }}</td>
                            <td>{{ $row['tenant_name'] }}</td>
                            <td class="right mono">{{ number_format($row['gross_revenue']) }}</td>
                            <td class="right">{{ $row['commission_rate'] }}%</td>
                            <td class="right mono">{{ number_format($row['commission']) }}</td>
                            <td class="right mono">{{ number_format($row['net_payout']) }}</td>
                            <td class="right">{{ number_format($row['order_count']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

        @elseif($tab === 'pending_payouts')
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Cook') }}</th>
                        <th>{{ __('Tenant') }}</th>
                        <th class="right">{{ __('Total Balance (XAF)') }}</th>
                        <th class="right">{{ __('Withdrawable (XAF)') }}</th>
                        <th class="right">{{ __('On Hold (XAF)') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tableData as $row)
                        <tr class="{{ $loop->even ? 'striped' : '' }}">
                            <td>{{ $row['cook_name'] }}</td>
                            <td>{{ $row['tenant_name'] }}</td>
                            <td class="right mono">{{ number_format($row['total_balance']) }}</td>
                            <td class="right mono">{{ number_format($row['withdrawable_balance']) }}</td>
                            <td class="right mono">{{ number_format($row['unwithdrawable_balance']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">{{ __('Total') }}</td>
                        <td class="right mono">{{ number_format($tableData->sum('total_balance')) }} XAF</td>
                        <td class="right mono">{{ number_format($tableData->sum('withdrawable_balance')) }} XAF</td>
                        <td class="right mono">{{ number_format($tableData->sum('unwithdrawable_balance')) }} XAF</td>
                    </tr>
                </tfoot>
            </table>

        @elseif($tab === 'failed_payments')
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Order') }}</th>
                        <th>{{ __('Client') }}</th>
                        <th class="right">{{ __('Amount (XAF)') }}</th>
                        <th>{{ __('Method') }}</th>
                        <th>{{ __('Reason') }}</th>
                        <th>{{ __('Date') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tableData as $row)
                        <tr class="{{ $loop->even ? 'striped' : '' }}">
                            <td class="mono">{{ $row['order_number'] }}</td>
                            <td>{{ $row['client_name'] }}</td>
                            <td class="right mono">{{ number_format($row['amount']) }}</td>
                            <td>{{ $row['payment_method'] }}</td>
                            <td>{{ Str::limit($row['failure_reason'], 60) }}</td>
                            <td>{{ $row['created_at'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

    @endif

    <div class="footer">
        {{ __('DancyMeals Financial Report') }} &mdash; {{ __('All amounts in XAF') }} &mdash; {{ now()->format('Y') }}
    </div>

</body>
</html>
