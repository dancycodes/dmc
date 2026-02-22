@extends('layouts.admin')

@section('title', __('Financial Reports'))
@section('page-title', __('Financial Reports'))

@section('content')
<div
    x-data="{
        tab: @js($tab),
        startDate: @js($startDate),
        endDate: @js($endDate),
        cookId: @js((string) ($cookId ?? '')),

        applyFilter() {
            let url = '/vault-entry/finance/reports?tab=' + this.tab;
            if (this.startDate) { url += '&start_date=' + this.startDate; }
            if (this.endDate)   { url += '&end_date=' + this.endDate; }
            if (this.cookId)    { url += '&cook_id=' + this.cookId; }
            $navigate(url, { key: 'finance-reports', replace: true });
        },

        switchTab(newTab) {
            this.tab = newTab;
            this.applyFilter();
        },

        exportUrl(format) {
            let url = '/vault-entry/finance/reports/export-' + format + '?tab=' + this.tab;
            if (this.startDate) { url += '&start_date=' + this.startDate; }
            if (this.endDate)   { url += '&end_date=' + this.endDate; }
            if (this.cookId)    { url += '&cook_id=' + this.cookId; }
            return url;
        }
    }"
    class="space-y-6"
>
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[
        ['label' => __('Finance')],
        ['label' => __('Reports')],
    ]" />

    {{-- Page Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-on-surface-strong">{{ __('Financial Reports') }}</h2>
            <p class="text-sm text-on-surface mt-0.5">
                {{ __('Revenue, commissions, pending payouts, and failed payments.') }}
            </p>
        </div>

        {{-- Export Buttons --}}
        <div class="flex items-center gap-2 shrink-0">
            <a
                :href="exportUrl('csv')"
                x-navigate-skip
                class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium bg-surface-alt border border-outline rounded-lg text-on-surface hover:bg-surface transition-colors"
                title="{{ __('Export CSV — all rows matching current filters') }}"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>
                </svg>
                {{ __('Export CSV') }}
            </a>
            <a
                :href="exportUrl('pdf')"
                x-navigate-skip
                class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium bg-primary text-on-primary rounded-lg hover:bg-primary-hover transition-colors"
                title="{{ __('Export PDF — first 500 rows with summary header') }}"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><polyline points="10 9 9 9 8 9"/>
                </svg>
                {{ __('Export PDF') }}
            </a>
        </div>
    </div>

    {{-- Filters Row --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:flex-wrap bg-surface-alt border border-outline rounded-xl p-4">
        {{-- Date Range --}}
        <div class="flex items-center gap-2 flex-wrap">
            <label class="text-sm font-medium text-on-surface-strong whitespace-nowrap">{{ __('From') }}</label>
            <input
                type="date"
                x-model="startDate"
                :max="endDate || ''"
                @change="applyFilter()"
                class="px-3 py-1.5 text-sm bg-surface border border-outline rounded-lg text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/30 dark:bg-surface dark:border-outline"
                aria-label="{{ __('Start date') }}"
            />
            <label class="text-sm font-medium text-on-surface-strong">{{ __('to') }}</label>
            <input
                type="date"
                x-model="endDate"
                :min="startDate || ''"
                @change="applyFilter()"
                class="px-3 py-1.5 text-sm bg-surface border border-outline rounded-lg text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/30 dark:bg-surface dark:border-outline"
                aria-label="{{ __('End date') }}"
            />
        </div>

        {{-- Cook Filter --}}
        @if($cooks->isNotEmpty())
        <div class="flex items-center gap-2">
            <label class="text-sm font-medium text-on-surface-strong whitespace-nowrap">{{ __('Cook') }}</label>
            <select
                x-model="cookId"
                @change="applyFilter()"
                class="px-3 py-1.5 text-sm bg-surface border border-outline rounded-lg text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/30 dark:bg-surface dark:border-outline"
                aria-label="{{ __('Filter by cook') }}"
            >
                <option value="">{{ __('All Cooks') }}</option>
                @foreach($cooks as $cook)
                    <option value="{{ $cook['id'] }}" {{ $cookId == $cook['id'] ? 'selected' : '' }}>
                        {{ $cook['name'] }} — {{ $cook['tenant_name'] }}
                    </option>
                @endforeach
            </select>
        </div>
        @endif

        {{-- Range Display --}}
        <p class="text-xs text-on-surface/60 sm:ml-auto">
            {{ __('Showing: :start — :end', ['start' => $rangeStart->format('M j, Y'), 'end' => $rangeEnd->format('M j, Y')]) }}
        </p>
    </div>

    @fragment('finance-reports-content')
    <div id="finance-reports-content" class="space-y-6">

        {{-- Summary Cards (5) --}}
        @php
            $summaryCards = [
                [
                    'label' => __('Gross Revenue'),
                    'value' => \App\Services\FinancialReportsService::formatXAF($summary['gross_revenue']),
                    'note' => __('Completed orders only'),
                    'color' => 'primary',
                    'icon' => '<line x1="12" x2="12" y1="1" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
                ],
                [
                    'label' => __('Platform Commission'),
                    'value' => \App\Services\FinancialReportsService::formatXAF($summary['commission']),
                    'note' => __('Platform share earned'),
                    'color' => 'success',
                    'icon' => '<path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
                ],
                [
                    'label' => __('Net Payouts'),
                    'value' => \App\Services\FinancialReportsService::formatXAF($summary['net_payouts']),
                    'note' => __('Cook earnings after commission'),
                    'color' => 'secondary',
                    'icon' => '<path d="M17 9V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2m2 4h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2zm7-5a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/>',
                ],
                [
                    'label' => __('Pending Payouts'),
                    'value' => \App\Services\FinancialReportsService::formatXAF($summary['pending_payouts']),
                    'note' => __('Cook wallet balances not withdrawn'),
                    'color' => 'warning',
                    'icon' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
                ],
                [
                    'label' => __('Failed Payments'),
                    'value' => number_format($summary['failed_count']),
                    'note' => __('Flutterwave failed transactions'),
                    'color' => 'danger',
                    'icon' => '<circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/>',
                ],
            ];
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
            @foreach($summaryCards as $card)
                <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-4 shadow-card">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-medium text-on-surface">{{ $card['label'] }}</p>
                            <p class="text-lg font-bold text-on-surface-strong mt-1 truncate">{{ $card['value'] }}</p>
                            <p class="text-xs text-on-surface/50 mt-0.5 leading-tight">{{ $card['note'] }}</p>
                        </div>
                        <span class="w-9 h-9 rounded-full bg-{{ $card['color'] }}-subtle flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-{{ $card['color'] }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $card['icon'] !!}</svg>
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Tab Navigation --}}
        <div class="flex gap-1 bg-surface-alt border border-outline rounded-lg p-1 overflow-x-auto">
            @foreach([
                'overview'         => __('Overview'),
                'by_cook'          => __('By Cook'),
                'pending_payouts'  => __('Pending Payouts'),
                'failed_payments'  => __('Failed Payments'),
            ] as $key => $label)
                <button
                    type="button"
                    @click="switchTab('{{ $key }}')"
                    :class="tab === '{{ $key }}'
                        ? 'bg-primary text-on-primary shadow-sm'
                        : 'text-on-surface hover:bg-surface'"
                    class="px-4 py-2 rounded-md text-sm font-medium transition-all duration-150 whitespace-nowrap flex-shrink-0"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Table Panel --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-card overflow-hidden">

            {{-- Tab: Overview --}}
            @if($tab === 'overview')
                <div class="px-5 py-4 border-b border-outline">
                    <h3 class="font-semibold text-on-surface-strong">{{ __('Daily Revenue Overview') }}</h3>
                    <p class="text-xs text-on-surface mt-0.5">{{ __('Revenue from completed orders grouped by day.') }}</p>
                </div>
                @if($tableData->isEmpty())
                    <div class="text-center py-12 text-on-surface/50">
                        <svg class="w-10 h-10 mx-auto mb-3 opacity-30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3h18v4H3z"/><path d="M3 7v13h18V7"/><path d="M9 11h6M9 15h6"/></svg>
                        <p class="text-sm font-medium">{{ __('No data for this period') }}</p>
                        <p class="text-xs mt-1">{{ __('No completed orders found in the selected date range.') }}</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-outline bg-surface/50">
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-on-surface uppercase tracking-wide">{{ __('Date') }}</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-on-surface uppercase tracking-wide">{{ __('Gross Revenue') }}</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-on-surface uppercase tracking-wide">{{ __('Commission') }}</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-on-surface uppercase tracking-wide">{{ __('Net Payout') }}</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-on-surface uppercase tracking-wide">{{ __('Orders') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline/50">
                                @foreach($tableData as $row)
                                    <tr class="hover:bg-surface/50 dark:hover:bg-surface/30 transition-colors {{ $loop->even ? 'bg-surface/30' : '' }}">
                                        <td class="px-5 py-3 font-medium text-on-surface-strong whitespace-nowrap">{{ $row['date'] }}</td>
                                        <td class="px-5 py-3 text-right text-on-surface-strong font-mono tabular-nums">{{ \App\Services\FinancialReportsService::formatXAF($row['gross_revenue']) }}</td>
                                        <td class="px-5 py-3 text-right text-success font-mono tabular-nums">{{ \App\Services\FinancialReportsService::formatXAF($row['commission']) }}</td>
                                        <td class="px-5 py-3 text-right text-on-surface font-mono tabular-nums">{{ \App\Services\FinancialReportsService::formatXAF($row['net_payout']) }}</td>
                                        <td class="px-5 py-3 text-right text-on-surface tabular-nums">{{ number_format($row['order_count']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t border-outline bg-surface/50">
                                <tr>
                                    <td class="px-5 py-3 text-xs font-semibold text-on-surface uppercase">{{ __('Total') }}</td>
                                    <td class="px-5 py-3 text-right font-bold text-on-surface-strong font-mono tabular-nums">
                                        {{ \App\Services\FinancialReportsService::formatXAF($tableData->sum('gross_revenue')) }}
                                    </td>
                                    <td class="px-5 py-3 text-right font-bold text-success font-mono tabular-nums">
                                        {{ \App\Services\FinancialReportsService::formatXAF($tableData->sum('commission')) }}
                                    </td>
                                    <td class="px-5 py-3 text-right font-bold text-on-surface font-mono tabular-nums">
                                        {{ \App\Services\FinancialReportsService::formatXAF($tableData->sum('net_payout')) }}
                                    </td>
                                    <td class="px-5 py-3 text-right font-bold text-on-surface tabular-nums">
                                        {{ number_format($tableData->sum('order_count')) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif

            {{-- Tab: By Cook --}}
            @elseif($tab === 'by_cook')
                <div class="px-5 py-4 border-b border-outline">
                    <h3 class="font-semibold text-on-surface-strong">{{ __('Revenue by Cook') }}</h3>
                    <p class="text-xs text-on-surface mt-0.5">{{ __('Sorted by gross revenue, descending.') }}</p>
                </div>
                @if($tableData->isEmpty())
                    <div class="text-center py-12 text-on-surface/50">
                        <svg class="w-10 h-10 mx-auto mb-3 opacity-30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        <p class="text-sm font-medium">{{ __('No cook revenue data for this period') }}</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-outline bg-surface/50">
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-on-surface uppercase tracking-wide">{{ __('Cook') }}</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-on-surface uppercase tracking-wide hidden md:table-cell">{{ __('Tenant') }}</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-on-surface uppercase tracking-wide">{{ __('Gross Revenue') }}</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-on-surface uppercase tracking-wide hidden sm:table-cell">{{ __('Rate') }}</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-on-surface uppercase tracking-wide">{{ __('Commission') }}</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-on-surface uppercase tracking-wide hidden sm:table-cell">{{ __('Net Payout') }}</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-on-surface uppercase tracking-wide">{{ __('Orders') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline/50">
                                @foreach($tableData as $row)
                                    <tr class="hover:bg-surface/50 dark:hover:bg-surface/30 transition-colors {{ $loop->even ? 'bg-surface/30' : '' }}">
                                        <td class="px-5 py-3 font-medium text-on-surface-strong">{{ $row['cook_name'] }}</td>
                                        <td class="px-5 py-3 text-on-surface hidden md:table-cell">{{ $row['tenant_name'] }}</td>
                                        <td class="px-5 py-3 text-right text-on-surface-strong font-mono tabular-nums">{{ \App\Services\FinancialReportsService::formatXAF($row['gross_revenue']) }}</td>
                                        <td class="px-5 py-3 text-right text-on-surface hidden sm:table-cell">{{ $row['commission_rate'] }}%</td>
                                        <td class="px-5 py-3 text-right text-success font-mono tabular-nums">{{ \App\Services\FinancialReportsService::formatXAF($row['commission']) }}</td>
                                        <td class="px-5 py-3 text-right text-on-surface font-mono tabular-nums hidden sm:table-cell">{{ \App\Services\FinancialReportsService::formatXAF($row['net_payout']) }}</td>
                                        <td class="px-5 py-3 text-right text-on-surface tabular-nums">{{ number_format($row['order_count']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

            {{-- Tab: Pending Payouts --}}
            @elseif($tab === 'pending_payouts')
                <div class="px-5 py-4 border-b border-outline">
                    <h3 class="font-semibold text-on-surface-strong">{{ __('Pending Payouts') }}</h3>
                    <p class="text-xs text-on-surface mt-0.5">{{ __('Cook wallet balances that have not been withdrawn.') }}</p>
                </div>
                @if($tableData->isEmpty())
                    <div class="text-center py-12 text-on-surface/50">
                        <svg class="w-10 h-10 mx-auto mb-3 opacity-30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 9V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2m2 4h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2zm7-5a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/></svg>
                        <p class="text-sm font-medium">{{ __('No pending payouts') }}</p>
                        <p class="text-xs mt-1">{{ __('All cook wallets are empty.') }}</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-outline bg-surface/50">
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-on-surface uppercase tracking-wide">{{ __('Cook') }}</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-on-surface uppercase tracking-wide hidden md:table-cell">{{ __('Tenant') }}</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-on-surface uppercase tracking-wide">{{ __('Total Balance') }}</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-on-surface uppercase tracking-wide hidden sm:table-cell">{{ __('Withdrawable') }}</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-on-surface uppercase tracking-wide hidden sm:table-cell">{{ __('On Hold') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline/50">
                                @foreach($tableData as $row)
                                    <tr class="hover:bg-surface/50 dark:hover:bg-surface/30 transition-colors {{ $loop->even ? 'bg-surface/30' : '' }}">
                                        <td class="px-5 py-3 font-medium text-on-surface-strong">{{ $row['cook_name'] }}</td>
                                        <td class="px-5 py-3 text-on-surface hidden md:table-cell">{{ $row['tenant_name'] }}</td>
                                        <td class="px-5 py-3 text-right font-bold text-warning font-mono tabular-nums">{{ \App\Services\FinancialReportsService::formatXAF($row['total_balance']) }}</td>
                                        <td class="px-5 py-3 text-right text-success font-mono tabular-nums hidden sm:table-cell">{{ \App\Services\FinancialReportsService::formatXAF($row['withdrawable_balance']) }}</td>
                                        <td class="px-5 py-3 text-right text-on-surface/70 font-mono tabular-nums hidden sm:table-cell">{{ \App\Services\FinancialReportsService::formatXAF($row['unwithdrawable_balance']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t border-outline bg-surface/50">
                                <tr>
                                    <td class="px-5 py-3 text-xs font-semibold text-on-surface uppercase" colspan="2">{{ __('Total') }}</td>
                                    <td class="px-5 py-3 text-right font-bold text-warning font-mono tabular-nums">
                                        {{ \App\Services\FinancialReportsService::formatXAF($tableData->sum('total_balance')) }}
                                    </td>
                                    <td class="px-5 py-3 text-right font-bold text-success font-mono tabular-nums hidden sm:table-cell">
                                        {{ \App\Services\FinancialReportsService::formatXAF($tableData->sum('withdrawable_balance')) }}
                                    </td>
                                    <td class="px-5 py-3 text-right font-bold text-on-surface/70 font-mono tabular-nums hidden sm:table-cell">
                                        {{ \App\Services\FinancialReportsService::formatXAF($tableData->sum('unwithdrawable_balance')) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif

            {{-- Tab: Failed Payments --}}
            @elseif($tab === 'failed_payments')
                <div class="px-5 py-4 border-b border-outline">
                    <h3 class="font-semibold text-on-surface-strong">{{ __('Failed Payments') }}</h3>
                    <p class="text-xs text-on-surface mt-0.5">{{ __('Flutterwave transactions with failed status in the selected period.') }}</p>
                </div>
                @if($tableData->isEmpty())
                    <div class="text-center py-12 text-on-surface/50">
                        <svg class="w-10 h-10 mx-auto mb-3 opacity-30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
                        <p class="text-sm font-medium">{{ __('No failed payments for this period') }}</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-outline bg-surface/50">
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-on-surface uppercase tracking-wide">{{ __('Order') }}</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-on-surface uppercase tracking-wide hidden sm:table-cell">{{ __('Client') }}</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-on-surface uppercase tracking-wide">{{ __('Amount') }}</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-on-surface uppercase tracking-wide hidden md:table-cell">{{ __('Method') }}</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-on-surface uppercase tracking-wide hidden lg:table-cell">{{ __('Reason') }}</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-on-surface uppercase tracking-wide hidden sm:table-cell">{{ __('Date') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline/50">
                                @foreach($tableData as $row)
                                    <tr class="hover:bg-surface/50 dark:hover:bg-surface/30 transition-colors {{ $loop->even ? 'bg-surface/30' : '' }}">
                                        <td class="px-5 py-3">
                                            <span class="font-mono text-xs font-medium text-on-surface-strong bg-danger-subtle text-danger px-1.5 py-0.5 rounded">
                                                {{ $row['order_number'] }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3 text-on-surface hidden sm:table-cell">{{ $row['client_name'] }}</td>
                                        <td class="px-5 py-3 text-right font-mono tabular-nums text-danger font-semibold">{{ \App\Services\FinancialReportsService::formatXAF($row['amount']) }}</td>
                                        <td class="px-5 py-3 text-on-surface hidden md:table-cell">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-surface border border-outline text-on-surface">
                                                {{ $row['payment_method'] }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3 text-on-surface/70 text-xs max-w-48 truncate hidden lg:table-cell" :title="@js($row['failure_reason'])">
                                            {{ $row['failure_reason'] }}
                                        </td>
                                        <td class="px-5 py-3 text-right text-on-surface/70 whitespace-nowrap hidden sm:table-cell">{{ $row['created_at'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif

        </div>

        {{-- Footer note --}}
        <p class="text-xs text-on-surface/50 text-center pb-2">
            {{ __('All amounts in XAF.') }}
            @if($tab !== 'pending_payouts')
                {{ __('Period: :start to :end.', ['start' => $rangeStart->format('M j, Y'), 'end' => $rangeEnd->format('M j, Y')]) }}
            @endif
        </p>

    </div>
    @endfragment

</div>
@endsection
