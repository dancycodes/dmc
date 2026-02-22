@extends('layouts.cook-dashboard')

@section('title', __('Revenue Analytics'))
@section('page-title', __('Revenue Analytics'))

@section('content')
{{--
    F-200: Cook Revenue Analytics
    Tenant-scoped revenue dashboard with:
    - Summary cards (Total / This Month / This Week / Today)
    - Revenue trend line chart (Tailwind bar chart)
    - Revenue by meal breakdown (horizontal bar chart)
    - Period selector (Today / This Week / This Month / Last 3 Months / Last 6 Months / This Year / Custom)
    - Comparison toggle (current vs previous period)
    BR-377: All chart updates via Gale fragment — no page reload
--}}
<div
    x-data="{
        period: @js($period),
        customStart: @js($customStart ?? ''),
        customEnd: @js($customEnd ?? ''),
        compare: @js($compare),
        showCustom: @js($period === 'custom'),

        changePeriod(newPeriod) {
            this.period = newPeriod;
            this.showCustom = (newPeriod === 'custom');
            if (newPeriod !== 'custom') {
                this.applyFilter();
            }
        },

        applyFilter() {
            let url = '/dashboard/analytics?period=' + this.period;
            url += '&compare=' + (this.compare ? '1' : '0');
            if (this.period === 'custom' && this.customStart && this.customEnd) {
                url += '&custom_start=' + this.customStart + '&custom_end=' + this.customEnd;
            }
            $navigate(url, { key: 'analytics', replace: true });
        },

        toggleCompare() {
            this.compare = !this.compare;
            this.applyFilter();
        }
    }"
    class="space-y-6"
>
    {{-- Page Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-on-surface-strong">{{ __('Revenue Analytics') }}</h2>
            <p class="text-sm text-on-surface mt-0.5">
                {{ __('Your revenue performance and trends.') }}
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

    @fragment('analytics-content')
    <div id="analytics-content" class="space-y-6">

        @if(! $hasData)
            {{-- Empty state: no completed orders yet --}}
            <div class="flex flex-col items-center justify-center py-20 text-center">
                <span class="w-16 h-16 rounded-full bg-primary-subtle flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 3v18h18"></path><path d="m19 9-5 5-4-4-3 3"></path>
                    </svg>
                </span>
                <h3 class="text-lg font-semibold text-on-surface-strong mb-2">{{ __('No revenue data yet') }}</h3>
                <p class="text-sm text-on-surface max-w-sm">
                    {{ __('Your revenue analytics will appear here once you receive your first paid order.') }}
                </p>
            </div>
        @else

        {{-- Summary Cards (always all-time stats) --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            @php
                $cards = [
                    [
                        'label'  => __('Total Revenue'),
                        'value'  => \App\Services\CookRevenueAnalyticsService::formatXAF($summaryCards['total']),
                        'note'   => __('All time'),
                        'color'  => 'primary',
                        'icon'   => '<path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>',
                    ],
                    [
                        'label'  => __('This Month'),
                        'value'  => \App\Services\CookRevenueAnalyticsService::formatXAF($summaryCards['thisMonth']),
                        'note'   => now()->format('F Y'),
                        'color'  => 'success',
                        'icon'   => '<rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M8 2v4M16 2v4M3 10h18"></path>',
                    ],
                    [
                        'label'  => __('This Week'),
                        'value'  => \App\Services\CookRevenueAnalyticsService::formatXAF($summaryCards['thisWeek']),
                        'note'   => __('Mon – Sun'),
                        'color'  => 'secondary',
                        'icon'   => '<path d="M8 2v4M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01M16 18h.01"></path>',
                    ],
                    [
                        'label'  => __('Today'),
                        'value'  => \App\Services\CookRevenueAnalyticsService::formatXAF($summaryCards['today']),
                        'note'   => now()->format('M j, Y'),
                        'color'  => 'info',
                        'icon'   => '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>',
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

        {{-- Period Revenue + Comparison Toggle --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <div>
                    <h3 class="font-semibold text-on-surface-strong">{{ __('Revenue Trend') }}</h3>
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

                <div class="flex items-center gap-3 flex-wrap">
                    {{-- Period revenue badge --}}
                    <div class="text-right">
                        <p class="text-sm font-bold text-on-surface-strong">
                            {{ \App\Services\CookRevenueAnalyticsService::formatXAF($periodRevenue) }}
                        </p>
                        @if($revenueChange !== null)
                            <span class="inline-flex items-center gap-0.5 text-xs font-semibold rounded-full px-2 py-0.5 mt-0.5
                                {{ $revenueChange >= 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                @if($revenueChange >= 0)
                                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m18 15-6-6-6 6"/></svg>
                                @else
                                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                                @endif
                                {{ abs($revenueChange) }}% {{ __('vs previous') }}
                            </span>
                        @endif
                    </div>

                    {{-- Compare toggle --}}
                    <button
                        type="button"
                        @click="toggleCompare()"
                        :class="compare
                            ? 'bg-primary text-on-primary border-primary'
                            : 'bg-surface text-on-surface border-outline hover:bg-surface-alt dark:hover:bg-surface-alt'"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors duration-150"
                        :aria-pressed="compare.toString()"
                    >
                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 3v18h18"></path><path d="M7 16l4-4 4 4 4-4"></path>
                        </svg>
                        {{ __('Compare') }}
                    </button>
                </div>
            </div>

            {{-- Revenue Chart --}}
            @php
                $chartMax = $chartData->max('value');
                $compMax = $comparisonData->max('value') ?? 0;
                $axisMax = max($chartMax, $compMax, 1);
                $showAllLabels = $chartData->count() <= 14;
            @endphp

            @if($chartData->isEmpty() || $chartData->every(fn($p) => $p['value'] === 0))
                <div class="h-48 flex items-center justify-center">
                    <div class="text-center">
                        <p class="text-sm text-on-surface/50">{{ __('No revenue data for this period') }}</p>
                        @if($compare)
                            <p class="text-xs text-on-surface/40 mt-1">{{ __('Previous period also has no data') }}</p>
                        @endif
                    </div>
                </div>
            @else
                {{-- Bar chart --}}
                <div class="h-48 flex items-end gap-0.5 sm:gap-1 overflow-x-auto pb-1">
                    @foreach($chartData as $i => $point)
                        @php
                            $heightPct = $axisMax > 0 ? round(($point['value'] / $axisMax) * 100) : 0;
                            $heightPct = max($heightPct, $point['value'] > 0 ? 2 : 0);
                            $prevPoint = $comparisonData->get($i);
                            $prevHeightPct = ($compare && $prevPoint && $axisMax > 0)
                                ? max(round(($prevPoint['value'] / $axisMax) * 100), $prevPoint['value'] > 0 ? 2 : 0)
                                : 0;
                        @endphp
                        <div class="flex flex-col items-end gap-0.5 flex-1 min-w-[6px]">
                            {{-- Bars side by side when comparing --}}
                            <div class="flex items-end gap-0.5 w-full h-48">
                                @if($compare && $prevPoint !== null)
                                    <div
                                        class="flex-1 rounded-t bg-primary/25 dark:bg-primary/25"
                                        style="height: {{ max($prevHeightPct, 1) }}%"
                                        title="{{ $prevPoint['label'] }}: {{ \App\Services\CookRevenueAnalyticsService::formatXAF($prevPoint['value']) }}"
                                    ></div>
                                @endif
                                <div
                                    class="flex-1 rounded-t bg-primary dark:bg-primary transition-colors duration-150 group relative cursor-pointer"
                                    style="height: {{ max($heightPct, 1) }}%"
                                    title="{{ $point['label'] }}: {{ \App\Services\CookRevenueAnalyticsService::formatXAF($point['value']) }}"
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

                {{-- Legend --}}
                @if($compare)
                    <div class="flex items-center gap-4 mt-3">
                        <div class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-sm bg-primary inline-block"></span>
                            <span class="text-xs text-on-surface">
                                {{ $rangeStart->format('M j') }} – {{ $rangeEnd->format('M j, Y') }}
                            </span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-sm bg-primary/25 inline-block"></span>
                            <span class="text-xs text-on-surface/60">
                                {{ $prevRangeStart->format('M j') }} – {{ $prevRangeEnd->format('M j, Y') }}
                                ({{ __('Previous') }})
                            </span>
                        </div>
                    </div>
                @endif
            @endif
        </div>

        {{-- Revenue by Meal Chart --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="font-semibold text-on-surface-strong">{{ __('Revenue by Meal') }}</h3>
                    <p class="text-xs text-on-surface mt-0.5">
                        {{ __('Top :count meals for the selected period', ['count' => min($mealBreakdown->count(), 10)]) }}
                    </p>
                </div>
                <span class="text-xs text-on-surface bg-surface px-2 py-1 rounded-md border border-outline">
                    {{ __('Net revenue') }}
                </span>
            </div>

            @if($mealBreakdown->isEmpty())
                <div class="flex flex-col items-center justify-center py-10 text-on-surface/50">
                    <svg class="w-8 h-8 mb-2 opacity-40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" x2="21" y1="6" y2="6"></line></svg>
                    <p class="text-sm">{{ __('No meal data for this period') }}</p>
                </div>
            @else
                @php
                    $mealMax = $mealBreakdown->max('revenue') ?: 1;
                    $barColors = ['primary', 'success', 'secondary', 'info', 'warning', 'primary', 'success', 'secondary', 'info', 'warning'];
                @endphp
                <div class="space-y-3">
                    @foreach($mealBreakdown as $i => $meal)
                        @php
                            $barPct = $mealMax > 0 ? round(($meal['revenue'] / $mealMax) * 100) : 0;
                            $color = $barColors[$i % count($barColors)];
                            $isOthers = $meal['meal_name'] === __('Others');
                        @endphp
                        <div>
                            <div class="flex items-center justify-between mb-1 gap-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="w-5 h-5 rounded-full bg-{{ $color }}-subtle flex items-center justify-center text-xs font-bold text-{{ $color }} shrink-0">
                                        {{ $i + 1 }}
                                    </span>
                                    <span class="text-sm font-medium text-on-surface-strong truncate {{ $isOthers ? 'italic text-on-surface' : '' }}">
                                        {{ $meal['meal_name'] }}
                                    </span>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="text-sm font-semibold text-on-surface-strong">
                                        {{ \App\Services\CookRevenueAnalyticsService::formatXAF($meal['revenue']) }}
                                    </span>
                                    <span class="text-xs text-on-surface/60 ml-1">({{ $meal['percentage'] }}%)</span>
                                </div>
                            </div>
                            {{-- Horizontal progress bar --}}
                            <div class="w-full bg-surface dark:bg-surface rounded-full h-2 overflow-hidden">
                                <div
                                    class="h-2 rounded-full bg-{{ $color }} transition-all duration-500"
                                    style="width: {{ max($barPct, 1) }}%"
                                ></div>
                            </div>
                            <p class="text-xs text-on-surface/50 mt-0.5">
                                {{ trans_choice(':count order|:count orders', $meal['order_count'], ['count' => number_format($meal['order_count'])]) }}
                            </p>
                        </div>
                        @if(! $loop->last)
                            <div class="border-t border-outline/30 dark:border-outline/30 my-1"></div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Date range footer --}}
        <p class="text-xs text-on-surface/50 text-center">
            {{ __('Showing net revenue from :start to :end', [
                'start' => $rangeStart->format('M j, Y'),
                'end'   => $rangeEnd->format('M j, Y'),
            ]) }}
            &mdash; {{ __('completed orders only') }}
        </p>

        @endif {{-- end hasData --}}

    </div>
    @endfragment

</div>
@endsection
