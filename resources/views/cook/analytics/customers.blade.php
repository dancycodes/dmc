@extends('layouts.cook-dashboard')

@section('title', __('Customer Analytics'))
@section('page-title', __('Customer Analytics'))

@section('content')
{{--
    F-202: Cook Customer Retention Analytics
    Tenant-scoped customer analytics dashboard with:
    - Summary cards (Unique Customers / Repeat Rate / New This Period / Returning This Period)
    - New vs returning customers stacked bar chart (per month)
    - Top 20 customers table sortable by spend or order count
    - CLV (Customer Lifetime Value) distribution histogram
    - Period selector (This Month / Last 3 Months / Last 6 Months / This Year / Custom)
    BR-390: All data tenant-scoped
    BR-398: Date range selector applies to all widgets
--}}
<div
    x-data="{
        period: @js($period),
        customStart: @js($customStart ?? ''),
        customEnd: @js($customEnd ?? ''),
        showCustom: @js($period === 'custom'),
        sortBy: @js($sortBy),

        changePeriod(newPeriod) {
            this.period = newPeriod;
            this.showCustom = (newPeriod === 'custom');
            if (newPeriod !== 'custom') {
                this.applyFilter();
            }
        },

        applyFilter() {
            let url = '/dashboard/analytics/customers?period=' + this.period + '&sort_by=' + this.sortBy;
            if (this.period === 'custom' && this.customStart && this.customEnd) {
                url += '&custom_start=' + this.customStart + '&custom_end=' + this.customEnd;
            }
            $navigate(url, { key: 'analytics', replace: true });
        },

        changeSort(newSort) {
            this.sortBy = newSort;
            this.applyFilter();
        }
    }"
    class="space-y-6"
>
    {{-- Page Header with Analytics Tab Navigation --}}
    <div class="flex flex-col gap-4">
        {{-- Tab navigation: Revenue | Orders | Customers --}}
        <div class="flex items-center gap-1 border-b border-outline overflow-x-auto">
            <a
                href="/dashboard/analytics"
                x-navigate
                class="px-4 py-2.5 text-sm font-medium text-on-surface hover:text-on-surface-strong border-b-2 border-transparent hover:border-outline-strong transition-colors duration-150 -mb-px whitespace-nowrap"
            >
                <span class="flex items-center gap-1.5">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                    {{ __('Revenue') }}
                </span>
            </a>
            <a
                href="/dashboard/analytics/orders"
                x-navigate
                class="px-4 py-2.5 text-sm font-medium text-on-surface hover:text-on-surface-strong border-b-2 border-transparent hover:border-outline-strong transition-colors duration-150 -mb-px whitespace-nowrap"
            >
                <span class="flex items-center gap-1.5">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 3v18h18"></path><path d="m19 9-5 5-4-4-3 3"></path>
                    </svg>
                    {{ __('Orders') }}
                </span>
            </a>
            <a
                href="/dashboard/analytics/customers"
                x-navigate
                class="px-4 py-2.5 text-sm font-medium text-primary border-b-2 border-primary -mb-px whitespace-nowrap"
                aria-current="page"
            >
                <span class="flex items-center gap-1.5">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    {{ __('Customers') }}
                </span>
            </a>
        </div>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-on-surface-strong">{{ __('Customer Analytics') }}</h2>
                <p class="text-sm text-on-surface mt-0.5">
                    {{ __('Customer retention, loyalty, and lifetime value insights.') }}
                </p>
            </div>

            {{-- Period Selector --}}
            <div class="flex flex-col gap-2">
                {{-- Period buttons — scrollable on mobile --}}
                <div class="flex items-center gap-1 bg-surface-alt border border-outline rounded-lg p-1 overflow-x-auto">
                    @foreach($periods as $key => $label)
                        <button
                            type="button"
                            @click="changePeriod('{{ $key }}')"
                            :class="period === '{{ $key }}'
                                ? 'bg-primary text-on-primary shadow-sm'
                                : 'text-on-surface hover:bg-surface dark:hover:bg-surface'"
                            class="px-3 py-1.5 rounded-md text-sm font-medium transition-all duration-150 whitespace-nowrap shrink-0"
                        >
                            {{ __($label) }}
                        </button>
                    @endforeach
                </div>

                {{-- Custom date range --}}
                <div x-show="showCustom" x-cloak class="flex items-center gap-2 flex-wrap">
                    <input
                        type="date"
                        x-model="customStart"
                        :max="customEnd || ''"
                        class="flex-1 min-w-0 px-3 py-1.5 text-sm bg-surface border border-outline rounded-lg text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/30"
                        aria-label="{{ __('Start date') }}"
                    />
                    <span class="text-on-surface text-sm">{{ __('to') }}</span>
                    <input
                        type="date"
                        x-model="customEnd"
                        :min="customStart || ''"
                        class="flex-1 min-w-0 px-3 py-1.5 text-sm bg-surface border border-outline rounded-lg text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/30"
                        aria-label="{{ __('End date') }}"
                    />
                    <button
                        type="button"
                        @click="applyFilter()"
                        :disabled="!customStart || !customEnd"
                        class="px-3 py-1.5 text-sm font-medium bg-primary text-on-primary rounded-lg hover:bg-primary-hover transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ __('Apply') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    @fragment('analytics-content')
    <div id="analytics-content" class="space-y-6">

        @if(! $hasData)
            {{-- Empty state --}}
            <div class="flex flex-col items-center justify-center py-20 text-center">
                <span class="w-16 h-16 rounded-full bg-primary-subtle flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </span>
                <h3 class="text-lg font-semibold text-on-surface-strong mb-2">{{ __('No customer data yet') }}</h3>
                <p class="text-sm text-on-surface max-w-sm">
                    {{ __('Your customer analytics will appear here once you receive your first completed order.') }}
                </p>
            </div>
        @else

        {{-- Summary Cards — 4-column grid (BR-399: all amounts in XAF) --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            @php
                $cards = [
                    [
                        'label' => __('Unique Customers'),
                        'value' => number_format($summaryCards['uniqueCustomers']),
                        'note' => __('All time'),
                        'color' => 'primary',
                        'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
                    ],
                    [
                        'label' => __('Repeat Rate'),
                        'value' => $summaryCards['repeatRate'].'%',
                        'note' => __('2+ orders'),
                        'color' => 'success',
                        'icon' => '<path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>',
                    ],
                    [
                        'label' => __('New This Period'),
                        'value' => number_format($summaryCards['newThisPeriod']),
                        'note' => __('First-time customers'),
                        'color' => 'secondary',
                        'icon' => '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line>',
                    ],
                    [
                        'label' => __('Returning This Period'),
                        'value' => number_format($summaryCards['returningThisPeriod']),
                        'note' => __('Repeat customers'),
                        'color' => 'info',
                        'icon' => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path>',
                    ],
                ];
            @endphp

            @foreach($cards as $card)
                <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-4 shadow-card">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-medium text-on-surface truncate">{{ $card['label'] }}</p>
                            <p class="text-lg font-bold text-on-surface-strong mt-1 truncate">{{ $card['value'] }}</p>
                            <p class="text-xs text-on-surface/60 mt-0.5 truncate">{{ $card['note'] }}</p>
                        </div>
                        <span class="w-9 h-9 rounded-full bg-{{ $card['color'] }}-subtle flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-{{ $card['color'] }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $card['icon'] !!}</svg>
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- New vs Returning Customers — Stacked Bar Chart --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-5">
                <div>
                    <h3 class="font-semibold text-on-surface-strong">{{ __('New vs Returning Customers') }}</h3>
                    <p class="text-xs text-on-surface mt-0.5">
                        {{ __('Monthly breakdown for the selected period') }}
                    </p>
                </div>
                {{-- Legend --}}
                <div class="flex items-center gap-4 shrink-0">
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm bg-primary shrink-0"></span>
                        <span class="text-xs text-on-surface">{{ __('New') }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm bg-success shrink-0"></span>
                        <span class="text-xs text-on-surface">{{ __('Returning') }}</span>
                    </div>
                </div>
            </div>

            @if($chartData->isEmpty() || $chartData->every(fn($p) => $p['new'] === 0 && $p['returning'] === 0))
                <div class="h-40 flex items-center justify-center">
                    <p class="text-sm text-on-surface/50">{{ __('No customer data for this period') }}</p>
                </div>
            @else
                @php
                    $maxValue = $chartData->max(fn($p) => $p['new'] + $p['returning']) ?: 1;
                @endphp

                {{-- Stacked bar chart: each column is a flex-col container with explicit h-40 reference --}}
                <div class="flex items-end gap-1 sm:gap-2 overflow-x-auto pb-1">
                    @foreach($chartData as $point)
                        @php
                            $stackTotal = $point['new'] + $point['returning'];
                            $heightPx = $maxValue > 0 ? round(($stackTotal / $maxValue) * 160) : 0;
                            $heightPx = $stackTotal > 0 ? max($heightPx, 4) : 0;
                            $newPx = $stackTotal > 0 ? round(($point['new'] / $stackTotal) * $heightPx) : 0;
                            $retPx = $heightPx - $newPx;
                        @endphp
                        <div
                            class="flex flex-col-reverse flex-1 min-w-[28px] rounded-t overflow-hidden cursor-default"
                            style="height: 160px;"
                            title="{{ $point['label'] }}: {{ $point['new'] }} {{ __('new') }}, {{ $point['returning'] }} {{ __('returning') }}"
                        >
                            {{-- Spacer to push bars to bottom --}}
                            <div class="flex-1"></div>
                            @if($retPx > 0)
                                <div class="bg-success w-full shrink-0" style="height: {{ $retPx }}px;"></div>
                            @endif
                            @if($newPx > 0)
                                <div class="bg-primary w-full shrink-0" style="height: {{ $newPx }}px;"></div>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- X-axis labels --}}
                <div class="flex items-start gap-1 sm:gap-2 mt-2 overflow-x-auto">
                    @foreach($chartData as $point)
                        <div class="flex-1 min-w-[28px] text-center">
                            <span class="text-xs text-on-surface/50 truncate block">{{ $point['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Two-column grid: CLV Distribution + Top Customers (responsive) --}}
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

            {{-- CLV Distribution Histogram --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card">
                <div class="mb-4">
                    <h3 class="font-semibold text-on-surface-strong">{{ __('Customer Lifetime Value') }}</h3>
                    <p class="text-xs text-on-surface mt-0.5">{{ __('Distribution across spend tiers (all time)') }}</p>
                </div>

                @if($clvDistribution->every(fn($b) => $b['count'] === 0))
                    <div class="flex flex-col items-center justify-center py-10 text-on-surface/50">
                        <svg class="w-8 h-8 mb-2 opacity-40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"></rect><path d="M8 21h8M12 17v4"></path></svg>
                        <p class="text-sm">{{ __('No CLV data available yet') }}</p>
                    </div>
                @else
                    @php
                        $clvMax = $clvDistribution->max('count') ?: 1;
                        $clvColors = ['primary', 'success', 'secondary'];
                    @endphp
                    <div class="space-y-4">
                        @foreach($clvDistribution as $i => $bucket)
                            @php
                                $barPct = $clvMax > 0 ? round(($bucket['count'] / $clvMax) * 100) : 0;
                                $color = $clvColors[$i % count($clvColors)];
                            @endphp
                            <div>
                                <div class="flex items-center justify-between mb-1.5 gap-2">
                                    <span class="text-sm font-medium text-on-surface-strong truncate">{{ $bucket['label'] }}</span>
                                    <span class="text-sm font-bold text-on-surface-strong shrink-0">
                                        {{ number_format($bucket['count']) }}
                                        <span class="text-xs font-normal text-on-surface/60 ml-0.5">{{ trans_choice('customer|customers', $bucket['count']) }}</span>
                                    </span>
                                </div>
                                <div class="w-full bg-surface dark:bg-surface rounded-full h-3 overflow-hidden">
                                    <div
                                        class="h-3 rounded-full bg-{{ $color }} transition-all duration-500"
                                        style="width: {{ max($barPct, $bucket['count'] > 0 ? 2 : 0) }}%"
                                    ></div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Total unique CLV customers --}}
                    @php $clvTotal = $clvDistribution->sum('count'); @endphp
                    <p class="text-xs text-on-surface/50 mt-4 text-right">
                        {{ __(':count unique customers tracked', ['count' => number_format($clvTotal)]) }}
                    </p>
                @endif
            </div>

            {{-- CLV Summary note on mobile — hidden on xl --}}
            {{-- Intentionally empty — top customers table takes the second column slot --}}

        </div>

        {{-- Top Customers Table (BR-396: sortable by order count or total spend) --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <div>
                    <h3 class="font-semibold text-on-surface-strong">{{ __('Top Customers') }}</h3>
                    <p class="text-xs text-on-surface mt-0.5">
                        {{ __('Top :count customers ranked by selected metric', ['count' => $topCustomers->count()]) }}
                    </p>
                </div>
                {{-- Sort toggle (BR-396) --}}
                <div class="flex items-center gap-1 bg-surface border border-outline rounded-lg p-1 shrink-0">
                    @foreach($sortOptions as $key => $label)
                        <button
                            type="button"
                            @click="changeSort('{{ $key }}')"
                            :class="sortBy === '{{ $key }}'
                                ? 'bg-primary text-on-primary shadow-sm'
                                : 'text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt'"
                            class="px-3 py-1.5 rounded-md text-xs font-medium transition-all duration-150 whitespace-nowrap"
                        >
                            {{ __($label) }}
                        </button>
                    @endforeach
                </div>
            </div>

            @if($topCustomers->isEmpty())
                <div class="flex flex-col items-center justify-center py-10 text-on-surface/50">
                    <svg class="w-8 h-8 mb-2 opacity-40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                    <p class="text-sm">{{ __('No customer data for this period') }}</p>
                </div>
            @else
                {{-- Desktop table --}}
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-outline">
                                <th class="text-left py-2.5 pr-4 font-semibold text-on-surface text-xs uppercase tracking-wide w-8">#</th>
                                <th class="text-left py-2.5 pr-4 font-semibold text-on-surface text-xs uppercase tracking-wide w-full max-w-0">{{ __('Customer') }}</th>
                                <th class="text-right py-2.5 pr-4 font-semibold text-on-surface text-xs uppercase tracking-wide">{{ __('Orders') }}</th>
                                <th class="text-right py-2.5 pr-4 font-semibold text-on-surface text-xs uppercase tracking-wide">{{ __('Total Spent') }}</th>
                                <th class="text-right py-2.5 font-semibold text-on-surface text-xs uppercase tracking-wide">{{ __('Last Order') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline/50">
                            @foreach($topCustomers as $i => $customer)
                                <tr class="hover:bg-surface dark:hover:bg-surface transition-colors duration-100">
                                    <td class="py-3 pr-4">
                                        <span class="w-6 h-6 rounded-full bg-surface-alt dark:bg-surface flex items-center justify-center text-xs font-bold text-on-surface border border-outline">
                                            {{ $i + 1 }}
                                        </span>
                                    </td>
                                    <td class="py-3 pr-4 max-w-0">
                                        <span class="font-medium text-on-surface-strong truncate block">{{ $customer['name'] }}</span>
                                    </td>
                                    <td class="py-3 pr-4 text-right">
                                        <span class="font-semibold text-on-surface-strong">{{ number_format($customer['order_count']) }}</span>
                                    </td>
                                    <td class="py-3 pr-4 text-right">
                                        <span class="font-semibold text-on-surface-strong">{{ \App\Services\CookCustomerRetentionService::formatXAF($customer['total_spent']) }}</span>
                                    </td>
                                    <td class="py-3 text-right">
                                        <span class="text-on-surface/70">{{ $customer['last_order_date'] }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile cards --}}
                <div class="md:hidden space-y-3">
                    @foreach($topCustomers as $i => $customer)
                        <div class="bg-surface dark:bg-surface rounded-xl border border-outline p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="w-7 h-7 rounded-full bg-primary-subtle flex items-center justify-center text-xs font-bold text-primary shrink-0">
                                        {{ $i + 1 }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-on-surface-strong text-sm truncate">{{ $customer['name'] }}</p>
                                        <p class="text-xs text-on-surface/60 mt-0.5">{{ __('Last order: :date', ['date' => $customer['last_order_date']]) }}</p>
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-sm font-bold text-on-surface-strong">{{ \App\Services\CookCustomerRetentionService::formatXAF($customer['total_spent']) }}</p>
                                    <p class="text-xs text-on-surface/60 mt-0.5">
                                        {{ number_format($customer['order_count']) }} {{ trans_choice('order|orders', $customer['order_count']) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Date range footer --}}
        <p class="text-xs text-on-surface/50 text-center">
            {{ __('Showing data from :start to :end', [
                'start' => $rangeStart->format('M j, Y'),
                'end'   => $rangeEnd->format('M j, Y'),
            ]) }}
        </p>

        @endif {{-- end hasData --}}

    </div>
    @endfragment

</div>
@endsection
