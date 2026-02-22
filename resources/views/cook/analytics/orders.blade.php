@extends('layouts.cook-dashboard')

@section('title', __('Order Analytics'))
@section('page-title', __('Order Analytics'))

@section('content')
{{--
    F-201: Cook Order Analytics
    Tenant-scoped order analytics dashboard with:
    - Summary cards (Total Orders / Period Orders / Avg Order Value / Most Popular Meal)
    - Order count trend bar chart
    - Orders by status donut/distribution chart
    - Popular meals horizontal bar chart (top 10 by order count)
    - Peak ordering times heatmap (hour x day)
    - Period selector (Today / This Week / This Month / Last 3 Months / Last 6 Months / This Year / Custom)
    BR-387: All chart updates via Gale fragment — no page reload
--}}
<div
    x-data="{
        period: @js($period),
        customStart: @js($customStart ?? ''),
        customEnd: @js($customEnd ?? ''),
        showCustom: @js($period === 'custom'),

        changePeriod(newPeriod) {
            this.period = newPeriod;
            this.showCustom = (newPeriod === 'custom');
            if (newPeriod !== 'custom') {
                this.applyFilter();
            }
        },

        applyFilter() {
            let url = '/dashboard/analytics/orders?period=' + this.period;
            if (this.period === 'custom' && this.customStart && this.customEnd) {
                url += '&custom_start=' + this.customStart + '&custom_end=' + this.customEnd;
            }
            $navigate(url, { key: 'analytics', replace: true });
        }
    }"
    class="space-y-6"
>
    {{-- Page Header with Analytics Tab Navigation --}}
    <div class="flex flex-col gap-4">
        {{-- Tab navigation: Revenue | Orders --}}
        <div class="flex items-center gap-1 border-b border-outline">
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
                class="px-4 py-2.5 text-sm font-medium text-primary border-b-2 border-primary -mb-px whitespace-nowrap"
                aria-current="page"
            >
                <span class="flex items-center gap-1.5">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 3v18h18"></path><path d="m19 9-5 5-4-4-3 3"></path>
                    </svg>
                    {{ __('Orders') }}
                </span>
            </a>
        </div>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-on-surface-strong">{{ __('Order Analytics') }}</h2>
                <p class="text-sm text-on-surface mt-0.5">
                    {{ __('Your order volume, status distribution, and peak times.') }}
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
                        <path d="M3 3v18h18"></path><path d="m19 9-5 5-4-4-3 3"></path>
                    </svg>
                </span>
                <h3 class="text-lg font-semibold text-on-surface-strong mb-2">{{ __('No order data yet') }}</h3>
                <p class="text-sm text-on-surface max-w-sm">
                    {{ __('Your order analytics will appear here once you receive your first paid order.') }}
                </p>
            </div>
        @else

        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            @php
                $cards = [
                    [
                        'label' => __('Total Orders'),
                        'value' => number_format($summaryCards['totalOrders']),
                        'note' => __('All time'),
                        'color' => 'primary',
                        'icon' => '<path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>',
                    ],
                    [
                        'label' => __('Avg Order Value'),
                        'value' => \App\Services\CookOrderAnalyticsService::formatXAF($summaryCards['avgOrderValue']),
                        'note' => __('Completed orders'),
                        'color' => 'success',
                        'icon' => '<path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>',
                    ],
                    [
                        'label' => __('Most Popular Meal'),
                        'value' => $summaryCards['mostPopularMeal'] ?? '—',
                        'note' => __('By order count'),
                        'color' => 'secondary',
                        'icon' => '<path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path><line x1="6" x2="6" y1="1" y2="4"></line><line x1="10" x2="10" y1="1" y2="4"></line><line x1="14" x2="14" y1="1" y2="4"></line>',
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

        {{-- Period info + orders count for selected period --}}
        @if($summaryCards['periodOrders'] > 0 || true)
        <div class="bg-primary-subtle/40 dark:bg-primary-subtle/20 rounded-xl border border-primary/20 px-4 py-3 flex items-center gap-3">
            <svg class="w-4 h-4 text-primary shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle><line x1="12" x2="12" y1="8" y2="12"></line><line x1="12" x2="12.01" y1="16" y2="16"></line>
            </svg>
            <p class="text-sm text-on-surface">
                <span class="font-semibold text-on-surface-strong">{{ number_format($summaryCards['periodOrders']) }}</span>
                {{ trans_choice(' order| orders', $summaryCards['periodOrders']) }}
                {{ __('from :start to :end', ['start' => $rangeStart->format('M j, Y'), 'end' => $rangeEnd->format('M j, Y')]) }}
            </p>
        </div>
        @endif

        {{-- Order Count Trend Chart --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <div>
                    <h3 class="font-semibold text-on-surface-strong">{{ __('Orders Over Time') }}</h3>
                    <p class="text-xs text-on-surface mt-0.5">
                        @if($granularity === 'daily')
                            {{ __('Daily breakdown') }}
                        @elseif($granularity === 'weekly')
                            {{ __('Weekly breakdown') }}
                        @else
                            {{ __('Monthly breakdown') }}
                        @endif
                        &mdash;
                        {{ $rangeStart->format('M j, Y') }} – {{ $rangeEnd->format('M j, Y') }}
                    </p>
                </div>
                <span class="text-sm font-bold text-on-surface-strong">
                    {{ number_format($summaryCards['periodOrders']) }} {{ trans_choice('order|orders', $summaryCards['periodOrders']) }}
                </span>
            </div>

            @php
                $chartMax = $chartData->max('value') ?: 1;
                $showAllLabels = $chartData->count() <= 14;
            @endphp

            @if($chartData->isEmpty() || $chartData->every(fn($p) => $p['value'] === 0))
                <div class="h-48 flex items-center justify-center">
                    <p class="text-sm text-on-surface/50">{{ __('No order data for this period') }}</p>
                </div>
            @else
                {{-- Bar chart --}}
                <div class="h-48 flex items-end gap-0.5 sm:gap-1 overflow-x-auto pb-1">
                    @foreach($chartData as $i => $point)
                        @php
                            $heightPct = $chartMax > 0 ? round(($point['value'] / $chartMax) * 100) : 0;
                            $heightPct = max($heightPct, $point['value'] > 0 ? 2 : 0);
                        @endphp
                        <div class="flex flex-col items-end gap-0.5 flex-1 min-w-[6px]">
                            <div class="flex items-end gap-0.5 w-full h-48">
                                <div
                                    class="flex-1 rounded-t bg-primary dark:bg-primary transition-colors duration-150 cursor-pointer"
                                    style="height: {{ max($heightPct, 1) }}%"
                                    title="{{ $point['label'] }}: {{ number_format($point['value']) }} {{ trans_choice('order|orders', $point['value']) }}"
                                ></div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- X-axis labels --}}
                <div class="flex items-start gap-0.5 sm:gap-1 mt-1 overflow-x-auto">
                    @foreach($chartData as $i => $point)
                        @php $skip = !$showAllLabels && $i % max(1, intdiv($chartData->count(), 7)) !== 0; @endphp
                        <div class="flex-1 min-w-0 text-center">
                            @unless($skip)
                                <span class="text-xs text-on-surface/50 truncate block">{{ $point['label'] }}</span>
                            @endunless
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Two-column grid: Status Distribution + Popular Meals --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Orders by Status Distribution --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card">
                <div class="mb-4">
                    <h3 class="font-semibold text-on-surface-strong">{{ __('Orders by Status') }}</h3>
                    <p class="text-xs text-on-surface mt-0.5">{{ __('Distribution for selected period') }}</p>
                </div>

                @if($ordersByStatus->isEmpty())
                    <div class="flex flex-col items-center justify-center py-10 text-on-surface/50">
                        <svg class="w-8 h-8 mb-2 opacity-40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle></svg>
                        <p class="text-sm">{{ __('No status data for this period') }}</p>
                    </div>
                @else
                    @php $totalOrders = $ordersByStatus->sum('count'); @endphp
                    {{-- Donut-style visual using stacked horizontal bars --}}
                    <div class="mb-4">
                        <div class="flex h-4 rounded-full overflow-hidden gap-0.5">
                            @foreach($ordersByStatus as $statusItem)
                                <div
                                    class="bg-{{ $statusItem['color'] }} h-full transition-all duration-500"
                                    style="width: {{ $statusItem['percentage'] }}%"
                                    title="{{ $statusItem['label'] }}: {{ $statusItem['count'] }} ({{ $statusItem['percentage'] }}%)"
                                ></div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Status legend list --}}
                    <div class="space-y-2">
                        @foreach($ordersByStatus as $statusItem)
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="w-3 h-3 rounded-full bg-{{ $statusItem['color'] }} shrink-0"></span>
                                    <span class="text-sm text-on-surface truncate">{{ $statusItem['label'] }}</span>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="text-sm font-semibold text-on-surface-strong">{{ number_format($statusItem['count']) }}</span>
                                    <span class="text-xs text-on-surface/50 w-10 text-right">({{ $statusItem['percentage'] }}%)</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Popular Meals by Order Count --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-semibold text-on-surface-strong">{{ __('Popular Meals') }}</h3>
                        <p class="text-xs text-on-surface mt-0.5">
                            {{ __('Top :count by order count', ['count' => $popularMeals->count()]) }}
                        </p>
                    </div>
                    <span class="text-xs text-on-surface bg-surface px-2 py-1 rounded-md border border-outline">
                        {{ __('Orders') }}
                    </span>
                </div>

                @if($popularMeals->isEmpty())
                    <div class="flex flex-col items-center justify-center py-10 text-on-surface/50">
                        <svg class="w-8 h-8 mb-2 opacity-40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path></svg>
                        <p class="text-sm">{{ __('No meal data for this period') }}</p>
                    </div>
                @else
                    @php
                        $mealMax = $popularMeals->max('order_count') ?: 1;
                        $barColors = ['primary', 'success', 'secondary', 'info', 'warning', 'primary', 'success', 'secondary', 'info', 'warning'];
                    @endphp
                    <div class="space-y-3">
                        @foreach($popularMeals as $i => $meal)
                            @php
                                $barPct = $mealMax > 0 ? round(($meal['order_count'] / $mealMax) * 100) : 0;
                                $color = $barColors[$i % count($barColors)];
                            @endphp
                            <div>
                                <div class="flex items-center justify-between mb-1 gap-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="w-5 h-5 rounded-full bg-{{ $color }}-subtle flex items-center justify-center text-xs font-bold text-{{ $color }} shrink-0">
                                            {{ $i + 1 }}
                                        </span>
                                        <span class="text-sm font-medium text-on-surface-strong truncate">
                                            {{ $meal['meal_name'] }}
                                        </span>
                                    </div>
                                    <span class="text-sm font-semibold text-on-surface-strong shrink-0">
                                        {{ number_format($meal['order_count']) }}
                                    </span>
                                </div>
                                {{-- Horizontal progress bar --}}
                                <div class="w-full bg-surface dark:bg-surface rounded-full h-2 overflow-hidden">
                                    <div
                                        class="h-2 rounded-full bg-{{ $color }} transition-all duration-500"
                                        style="width: {{ max($barPct, 1) }}%"
                                    ></div>
                                </div>
                            </div>
                            @if(! $loop->last)
                                <div class="border-t border-outline/30 dark:border-outline/30 my-1"></div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

        </div>

        {{-- Peak Ordering Times Heatmap --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card">
            <div class="mb-4">
                <h3 class="font-semibold text-on-surface-strong">{{ __('Peak Ordering Times') }}</h3>
                <p class="text-xs text-on-surface mt-0.5">
                    {{ __('Order volume by hour and day of week (Africa/Douala timezone)') }}
                </p>
            </div>

            @php
                $matrix = $peakTimes['matrix'];
                $maxValue = $peakTimes['max_value'];
                $dayLabels = $peakTimes['day_labels'];
                $hourLabels = $peakTimes['hour_labels'];
                $hasHeatmapData = collect($matrix)->flatten()->sum() > 0;
            @endphp

            @if(! $hasHeatmapData)
                <div class="flex flex-col items-center justify-center py-10 text-on-surface/50">
                    <svg class="w-8 h-8 mb-2 opacity-40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path></svg>
                    <p class="text-sm">{{ __('No timing data for this period') }}</p>
                    <p class="text-xs mt-1">{{ __('Heatmap shows when paid orders are placed') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <div class="min-w-[500px]">
                        {{-- Hour labels header --}}
                        <div class="flex items-center mb-1">
                            <div class="w-10 shrink-0"></div>
                            <div class="flex flex-1 gap-0.5">
                                @for($h = 0; $h < 24; $h++)
                                    <div class="flex-1 text-center">
                                        @if($h % 3 === 0)
                                            <span class="text-xs text-on-surface/40 block">{{ sprintf('%02d', $h) }}</span>
                                        @else
                                            <span class="text-xs block">&nbsp;</span>
                                        @endif
                                    </div>
                                @endfor
                            </div>
                        </div>

                        {{-- Heatmap rows: each day --}}
                        @foreach($dayLabels as $dayIndex => $dayLabel)
                            <div class="flex items-center gap-1 mb-0.5">
                                {{-- Day label --}}
                                <div class="w-10 shrink-0">
                                    <span class="text-xs font-medium text-on-surface/60">{{ __($dayLabel) }}</span>
                                </div>
                                {{-- Hour cells --}}
                                <div class="flex flex-1 gap-0.5">
                                    @for($h = 0; $h < 24; $h++)
                                        @php
                                            $cellValue = $matrix[$dayIndex][$h];
                                            $intensity = $maxValue > 0 ? $cellValue / $maxValue : 0;
                                            // Map intensity to opacity classes
                                            if ($intensity === 0) {
                                                $cellClass = 'bg-surface-alt dark:bg-surface-alt border border-outline/30';
                                            } elseif ($intensity < 0.2) {
                                                $cellClass = 'bg-primary/10 dark:bg-primary/10';
                                            } elseif ($intensity < 0.4) {
                                                $cellClass = 'bg-primary/25 dark:bg-primary/25';
                                            } elseif ($intensity < 0.6) {
                                                $cellClass = 'bg-primary/45 dark:bg-primary/45';
                                            } elseif ($intensity < 0.8) {
                                                $cellClass = 'bg-primary/65 dark:bg-primary/65';
                                            } else {
                                                $cellClass = 'bg-primary dark:bg-primary';
                                            }
                                        @endphp
                                        <div
                                            class="flex-1 h-6 rounded-sm {{ $cellClass }} transition-colors duration-150 cursor-default"
                                            title="{{ __($dayLabel) }} {{ sprintf('%02d:00', $h) }}: {{ $cellValue }} {{ trans_choice('order|orders', $cellValue) }}"
                                        ></div>
                                    @endfor
                                </div>
                            </div>
                        @endforeach

                        {{-- Legend --}}
                        <div class="flex items-center gap-3 mt-4 justify-end">
                            <span class="text-xs text-on-surface/50">{{ __('Low') }}</span>
                            <div class="flex items-center gap-0.5">
                                <div class="w-4 h-4 rounded-sm bg-surface-alt border border-outline/30"></div>
                                <div class="w-4 h-4 rounded-sm bg-primary/10"></div>
                                <div class="w-4 h-4 rounded-sm bg-primary/25"></div>
                                <div class="w-4 h-4 rounded-sm bg-primary/45"></div>
                                <div class="w-4 h-4 rounded-sm bg-primary/65"></div>
                                <div class="w-4 h-4 rounded-sm bg-primary"></div>
                            </div>
                            <span class="text-xs text-on-surface/50">{{ __('High') }}</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Date range footer --}}
        <p class="text-xs text-on-surface/50 text-center">
            {{ __('Showing orders from :start to :end', [
                'start' => $rangeStart->format('M j, Y'),
                'end'   => $rangeEnd->format('M j, Y'),
            ]) }}
        </p>

        @endif {{-- end hasData --}}

    </div>
    @endfragment

</div>
@endsection
