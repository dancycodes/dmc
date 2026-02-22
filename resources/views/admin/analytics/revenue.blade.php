@extends('layouts.admin')

@section('title', __('Revenue Analytics'))
@section('page-title', __('Revenue Analytics'))

@section('content')
<div
    x-data="{
        period: @js($period),
        customStart: @js($customStart ?? ''),
        customEnd: @js($customEnd ?? ''),
        showCustom: @js($period === 'custom'),
        compare: @js($compare),

        changePeriod(newPeriod) {
            this.period = newPeriod;
            this.showCustom = (newPeriod === 'custom');
            if (newPeriod !== 'custom') {
                this.applyFilter();
            }
        },

        toggleCompare() {
            this.compare = !this.compare;
            this.applyFilter();
        },

        applyFilter() {
            let url = '/vault-entry/analytics/revenue?period=' + this.period
                + '&compare=' + (this.compare ? '1' : '0');
            if (this.period === 'custom' && this.customStart && this.customEnd) {
                url += '&custom_start=' + this.customStart + '&custom_end=' + this.customEnd;
            }
            $navigate(url, { key: 'revenue-analytics', replace: true });
        }
    }"
    class="space-y-6"
>
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[
        ['label' => __('Analytics'), 'url' => route('admin.analytics.index')],
        ['label' => __('Revenue Analytics')],
    ]" />

    {{-- Page Header + Toolbar --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-on-surface-strong">{{ __('Platform Revenue Analytics') }}</h2>
            <p class="text-sm text-on-surface mt-0.5">
                {{ __('Revenue, commission, cook performance and regional breakdown.') }}
            </p>
        </div>

        {{-- Toolbar: period selector + comparison toggle --}}
        <div class="flex flex-col gap-2">
            {{-- Period segmented control --}}
            <div class="flex items-center gap-1 bg-surface-alt border border-outline rounded-lg p-1 flex-wrap">
                @foreach($periods as $key => $label)
                    <button
                        type="button"
                        @click="changePeriod('{{ $key }}')"
                        :class="period === '{{ $key }}'
                            ? 'bg-primary text-on-primary shadow-sm'
                            : 'text-on-surface hover:bg-surface'"
                        class="px-3 py-1.5 rounded-md text-sm font-medium transition-all duration-150 whitespace-nowrap"
                    >
                        {{ __($label) }}
                    </button>
                @endforeach
            </div>

            {{-- Custom range picker --}}
            <div x-show="showCustom" x-cloak class="flex items-center gap-2">
                <input
                    type="date"
                    x-model="customStart"
                    :max="customEnd || ''"
                    class="flex-1 px-3 py-1.5 text-sm bg-surface border border-outline rounded-lg text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/30"
                    aria-label="{{ __('Start date') }}"
                />
                <span class="text-on-surface text-sm">{{ __('to') }}</span>
                <input
                    type="date"
                    x-model="customEnd"
                    :min="customStart || ''"
                    class="flex-1 px-3 py-1.5 text-sm bg-surface border border-outline rounded-lg text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/30"
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

            {{-- Comparison toggle --}}
            <div class="flex items-center gap-2 self-end">
                <span class="text-sm text-on-surface">{{ __('Compare to previous period') }}</span>
                <button
                    type="button"
                    @click="toggleCompare()"
                    :class="compare ? 'bg-primary' : 'bg-outline'"
                    class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary/30"
                    role="switch"
                    :aria-checked="compare.toString()"
                    :aria-label="compare ? '{{ __('Disable comparison') }}' : '{{ __('Enable comparison') }}'"
                >
                    <span
                        :class="compare ? 'translate-x-5' : 'translate-x-1'"
                        class="inline-block h-3 w-3 transform rounded-full bg-white shadow-sm transition-transform duration-200"
                    ></span>
                </button>
            </div>
        </div>
    </div>

    @fragment('revenue-analytics-content')
    <div id="revenue-analytics-content" class="space-y-6">

        {{-- Summary Cards (4) — BR-418, BR-419, BR-420, BR-421 --}}
        @php
            $cards = [
                [
                    'label'  => __('Platform Revenue'),
                    'value'  => \App\Services\AdminRevenueAnalyticsService::formatXAF($summaryCards['revenue']),
                    'change' => $summaryCards['changes']['revenue'],
                    'prev'   => \App\Services\AdminRevenueAnalyticsService::formatXAF($summaryCards['prev_revenue']),
                    'color'  => 'primary',
                    'note'   => __('Completed orders only'),
                    'icon'   => '<line x1="12" x2="12" y1="1" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>',
                ],
                [
                    'label'  => __('Commission Earned'),
                    'value'  => \App\Services\AdminRevenueAnalyticsService::formatXAF($summaryCards['commission']),
                    'change' => $summaryCards['changes']['commission'],
                    'prev'   => \App\Services\AdminRevenueAnalyticsService::formatXAF($summaryCards['prev_commission']),
                    'color'  => 'success',
                    'note'   => __('Platform share'),
                    'icon'   => '<path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>',
                ],
                [
                    'label'  => __('Active Cooks'),
                    'value'  => number_format($summaryCards['active_cooks']),
                    'change' => $summaryCards['changes']['active_cooks'],
                    'prev'   => number_format($summaryCards['prev_active_cooks']),
                    'color'  => 'secondary',
                    'note'   => __('With completed orders'),
                    'icon'   => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
                ],
                [
                    'label'  => __('Transactions'),
                    'value'  => number_format($summaryCards['transaction_count']),
                    'change' => $summaryCards['changes']['transaction_count'],
                    'prev'   => number_format($summaryCards['prev_transaction_count']),
                    'color'  => 'info',
                    'note'   => __('Completed payments'),
                    'icon'   => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>',
                ],
            ];
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            @foreach($cards as $card)
                <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-on-surface">{{ $card['label'] }}</p>
                            <p class="text-lg font-bold text-on-surface-strong mt-1 break-words leading-tight">{{ $card['value'] }}</p>
                            <p class="text-xs text-on-surface/60 mt-0.5">{{ $card['note'] }}</p>
                            @if($compare && $card['prev'])
                                <p class="text-xs text-on-surface/50 mt-1">
                                    {{ __('Prev:') }} {{ $card['prev'] }}
                                </p>
                            @endif
                        </div>
                        <div class="flex flex-col items-end gap-2 shrink-0">
                            <span class="w-10 h-10 rounded-full bg-{{ $card['color'] }}-subtle flex items-center justify-center">
                                <svg class="w-5 h-5 text-{{ $card['color'] }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $card['icon'] !!}</svg>
                            </span>
                            @if($card['change'] !== null)
                                <span class="inline-flex items-center gap-0.5 text-xs font-semibold rounded-full px-2 py-0.5
                                    {{ $card['change'] >= 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                    @if($card['change'] >= 0)
                                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m18 15-6-6-6 6"/></svg>
                                    @else
                                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                                    @endif
                                    {{ abs($card['change']) }}%
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Revenue Over Time + Commission Over Time Charts --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Revenue Over Time --}}
            @php
                $revenueMax = $revenueChartData->max('value') ?: 1;
                $revenuePoints = $revenueChartData->values();
                $showAllRevenueLabels = $revenuePoints->count() <= 14;
                $hasRevenueData = $revenuePoints->isNotEmpty() && $revenuePoints->contains(fn ($p) => $p['value'] > 0);

                // Comparison data normalized to same number of points
                $revCompPoints = $revenueComparisonData->values();
                $revCompMax = $revCompPoints->max('value') ?: 1;
                $combinedRevMax = max($revenueMax, $revCompMax, 1);
            @endphp
            <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-semibold text-on-surface-strong">{{ __('Revenue Over Time') }}</h3>
                        <p class="text-xs text-on-surface mt-0.5">
                            {{ $granularity === 'daily' ? __('Daily') : __('Monthly') }} &mdash; {{ __('completed orders') }}
                        </p>
                    </div>
                    <span class="text-xs text-on-surface bg-surface px-2 py-1 rounded-md border border-outline">
                        {{ $granularity === 'daily' ? __('Daily') : __('Monthly') }}
                    </span>
                </div>

                @if(! $hasRevenueData)
                    <div class="h-44 flex flex-col items-center justify-center text-on-surface/40 gap-2">
                        <svg class="w-8 h-8 opacity-40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"></path><path d="m19 9-5 5-4-4-3 3"></path></svg>
                        <p class="text-sm">{{ __('No revenue data for this period') }}</p>
                    </div>
                @else
                    <div class="h-44 flex items-end gap-px overflow-hidden">
                        @foreach($revenuePoints as $i => $point)
                            @php
                                $hPct = $combinedRevMax > 0 ? round(($point['value'] / $combinedRevMax) * 100) : 0;
                                $hPct = max($hPct, $point['value'] > 0 ? 2 : 0);
                                $compPoint = $revCompPoints->get($i);
                                $compHPct = ($compare && $compPoint)
                                    ? max(round(($compPoint['value'] / $combinedRevMax) * 100), $compPoint['value'] > 0 ? 2 : 0)
                                    : 0;
                            @endphp
                            <div class="flex flex-col items-center gap-px flex-1 min-w-0 group relative"
                                 title="{{ $point['label'] }}: {{ \App\Services\AdminRevenueAnalyticsService::formatXAF($point['value']) }}{{ $compare && $compPoint ? ' / ' . \App\Services\AdminRevenueAnalyticsService::formatXAF($compPoint['value']) : '' }}">
                                {{-- Comparison bar (behind) --}}
                                @if($compare && $compPoint)
                                    <div class="absolute bottom-0 left-0 w-full bg-on-surface/10 rounded-t transition-colors duration-150"
                                         style="height: {{ $compHPct }}%"></div>
                                @endif
                                {{-- Current bar --}}
                                <div
                                    class="w-full bg-primary/30 dark:bg-primary/30 rounded-t group-hover:bg-primary transition-colors duration-150 relative z-10"
                                    style="height: {{ $hPct }}%"
                                ></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex items-start gap-px mt-1 overflow-hidden">
                        @foreach($revenuePoints as $i => $point)
                            @php $skip = !$showAllRevenueLabels && $i % max(1, intdiv($revenuePoints->count(), 7)) !== 0; @endphp
                            <div class="flex-1 min-w-0 text-center">
                                @unless($skip)
                                    <span class="text-xs text-on-surface/50 truncate block">{{ $point['label'] }}</span>
                                @endunless
                            </div>
                        @endforeach
                    </div>
                    @if($compare)
                        <div class="flex items-center gap-3 mt-2">
                            <span class="flex items-center gap-1 text-xs text-on-surface/60">
                                <span class="w-3 h-2 rounded-sm bg-primary/30 inline-block"></span>
                                {{ __('Current period') }}
                            </span>
                            <span class="flex items-center gap-1 text-xs text-on-surface/40">
                                <span class="w-3 h-2 rounded-sm bg-on-surface/10 inline-block"></span>
                                {{ __('Previous period') }}
                            </span>
                        </div>
                    @endif
                @endif
            </div>

            {{-- Commission Over Time --}}
            @php
                $commMax = $commissionChartData->max('value') ?: 1;
                $commPoints = $commissionChartData->values();
                $showAllCommLabels = $commPoints->count() <= 14;
                $hasCommData = $commPoints->isNotEmpty() && $commPoints->contains(fn ($p) => $p['value'] > 0);

                $commCompPoints = $commissionComparisonData->values();
                $commCompMax = $commCompPoints->max('value') ?: 1;
                $combinedCommMax = max($commMax, $commCompMax, 1);
            @endphp
            <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-semibold text-on-surface-strong">{{ __('Commission Over Time') }}</h3>
                        <p class="text-xs text-on-surface mt-0.5">
                            {{ $granularity === 'daily' ? __('Daily') : __('Monthly') }} &mdash; {{ __('platform share') }}
                        </p>
                    </div>
                    <span class="text-xs text-on-surface bg-surface px-2 py-1 rounded-md border border-outline">
                        {{ $granularity === 'daily' ? __('Daily') : __('Monthly') }}
                    </span>
                </div>

                @if(! $hasCommData)
                    <div class="h-44 flex flex-col items-center justify-center text-on-surface/40 gap-2">
                        <svg class="w-8 h-8 opacity-40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                        <p class="text-sm">{{ __('No commission data for this period') }}</p>
                    </div>
                @else
                    <div class="h-44 flex items-end gap-px overflow-hidden">
                        @foreach($commPoints as $i => $point)
                            @php
                                $hPct = $combinedCommMax > 0 ? round(($point['value'] / $combinedCommMax) * 100) : 0;
                                $hPct = max($hPct, $point['value'] > 0 ? 2 : 0);
                                $compPoint = $commCompPoints->get($i);
                                $compHPct = ($compare && $compPoint)
                                    ? max(round(($compPoint['value'] / $combinedCommMax) * 100), $compPoint['value'] > 0 ? 2 : 0)
                                    : 0;
                            @endphp
                            <div class="flex flex-col items-center gap-px flex-1 min-w-0 group relative"
                                 title="{{ $point['label'] }}: {{ \App\Services\AdminRevenueAnalyticsService::formatXAF($point['value']) }}{{ $compare && $compPoint ? ' / ' . \App\Services\AdminRevenueAnalyticsService::formatXAF($compPoint['value']) : '' }}">
                                @if($compare && $compPoint)
                                    <div class="absolute bottom-0 left-0 w-full bg-on-surface/10 rounded-t transition-colors duration-150"
                                         style="height: {{ $compHPct }}%"></div>
                                @endif
                                <div
                                    class="w-full bg-success/30 dark:bg-success/30 rounded-t group-hover:bg-success transition-colors duration-150 relative z-10"
                                    style="height: {{ $hPct }}%"
                                ></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex items-start gap-px mt-1 overflow-hidden">
                        @foreach($commPoints as $i => $point)
                            @php $skip = !$showAllCommLabels && $i % max(1, intdiv($commPoints->count(), 7)) !== 0; @endphp
                            <div class="flex-1 min-w-0 text-center">
                                @unless($skip)
                                    <span class="text-xs text-on-surface/50 truncate block">{{ $point['label'] }}</span>
                                @endunless
                            </div>
                        @endforeach
                    </div>
                    @if($compare)
                        <div class="flex items-center gap-3 mt-2">
                            <span class="flex items-center gap-1 text-xs text-on-surface/60">
                                <span class="w-3 h-2 rounded-sm bg-success/30 inline-block"></span>
                                {{ __('Current period') }}
                            </span>
                            <span class="flex items-center gap-1 text-xs text-on-surface/40">
                                <span class="w-3 h-2 rounded-sm bg-on-surface/10 inline-block"></span>
                                {{ __('Previous period') }}
                            </span>
                        </div>
                    @endif
                @endif
            </div>
        </div>

        {{-- Revenue by Cook (Top 10 + Others) + Revenue by Region --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Revenue by Cook — BR-422: Top 10, rest as "Others" --}}
            @php
                $cookMax = $revenueByCook->max('revenue') ?: 1;
            @endphp
            <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-semibold text-on-surface-strong">{{ __('Revenue by Cook') }}</h3>
                        <p class="text-xs text-on-surface mt-0.5">{{ __('Top 10 cooks, rest grouped as Others') }}</p>
                    </div>
                    <span class="text-xs text-on-surface/60">{{ __('by revenue') }}</span>
                </div>

                @if($revenueByCook->isEmpty())
                    <div class="text-center py-10 text-on-surface/40 flex flex-col items-center gap-2">
                        <svg class="w-8 h-8 opacity-40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                        <p class="text-sm">{{ __('No revenue data for this period') }}</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($revenueByCook as $idx => $cook)
                            @php
                                $barPct = $cookMax > 0 ? round(($cook['revenue'] / $cookMax) * 100) : 0;
                            @endphp
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    @if(! $cook['is_others'])
                                        <span class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold shrink-0
                                            {{ $idx === 0 ? 'bg-warning text-on-warning' : ($idx === 1 ? 'bg-on-surface/20 text-on-surface-strong' : ($idx === 2 ? 'bg-secondary-subtle text-secondary' : 'bg-surface text-on-surface border border-outline')) }}">
                                            {{ $idx + 1 }}
                                        </span>
                                    @else
                                        <span class="w-5 h-5 rounded-full flex items-center justify-center shrink-0 bg-surface border border-outline">
                                            <svg class="w-3 h-3 text-on-surface/40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                                        </span>
                                    @endif
                                    <div class="flex-1 min-w-0 flex items-center justify-between gap-2">
                                        <p class="text-sm font-medium text-on-surface-strong truncate">
                                            {{ $cook['cook_name'] }}
                                            @if($cook['tenant_name'] && ! $cook['is_others'])
                                                <span class="text-on-surface/50 font-normal text-xs"> &mdash; {{ $cook['tenant_name'] }}</span>
                                            @endif
                                        </p>
                                        <span class="text-sm font-semibold text-on-surface-strong shrink-0">
                                            {{ \App\Services\AdminRevenueAnalyticsService::formatXAF($cook['revenue']) }}
                                        </span>
                                    </div>
                                </div>
                                {{-- Horizontal bar --}}
                                <div class="ml-7 h-2 bg-surface rounded-full overflow-hidden">
                                    <div
                                        class="{{ $cook['is_others'] ? 'bg-on-surface/20' : 'bg-primary/60' }} h-2 rounded-full transition-all duration-500"
                                        style="width: {{ max($barPct, $cook['revenue'] > 0 ? 3 : 0) }}%"
                                    ></div>
                                </div>
                                <p class="ml-7 text-xs text-on-surface/50 mt-0.5">
                                    {{ trans_choice(':count order|:count orders', $cook['order_count'], ['count' => number_format($cook['order_count'])]) }}
                                </p>
                            </div>
                            @if(! $loop->last)
                                <div class="border-t border-outline/50 dark:border-outline/50"></div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Revenue by Region — BR-423 --}}
            @php
                $regionMax = $revenueByRegion->max('revenue') ?: 1;
            @endphp
            <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-semibold text-on-surface-strong">{{ __('Revenue by Region') }}</h3>
                        <p class="text-xs text-on-surface mt-0.5">{{ __('Based on delivery town') }}</p>
                    </div>
                    <span class="text-xs text-on-surface/60">{{ __('by revenue') }}</span>
                </div>

                @if($revenueByRegion->isEmpty())
                    <div class="text-center py-10 text-on-surface/40 flex flex-col items-center gap-2">
                        <svg class="w-8 h-8 opacity-40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        <p class="text-sm">{{ __('No regional data for this period') }}</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($revenueByRegion as $idx => $region)
                            @php
                                $barPct = $regionMax > 0 ? round(($region['revenue'] / $regionMax) * 100) : 0;
                            @endphp
                            <div>
                                <div class="flex items-center justify-between gap-2 mb-1">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="w-5 h-5 rounded flex items-center justify-center shrink-0
                                            {{ $region['is_unknown'] ? 'bg-surface border border-outline' : 'bg-info-subtle' }}">
                                            @if($region['is_unknown'])
                                                <svg class="w-3 h-3 text-on-surface/40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" x2="12.01" y1="17" y2="17"></line></svg>
                                            @else
                                                <svg class="w-3 h-3 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                            @endif
                                        </span>
                                        <p class="text-sm font-medium text-on-surface-strong truncate {{ $region['is_unknown'] ? 'italic text-on-surface/60' : '' }}">
                                            {{ $region['region'] }}
                                        </p>
                                    </div>
                                    <span class="text-sm font-semibold text-on-surface-strong shrink-0">
                                        {{ \App\Services\AdminRevenueAnalyticsService::formatXAF($region['revenue']) }}
                                    </span>
                                </div>
                                {{-- Horizontal bar --}}
                                <div class="ml-7 h-2 bg-surface rounded-full overflow-hidden">
                                    <div
                                        class="{{ $region['is_unknown'] ? 'bg-on-surface/20' : 'bg-info/60' }} h-2 rounded-full transition-all duration-500"
                                        style="width: {{ max($barPct, $region['revenue'] > 0 ? 3 : 0) }}%"
                                    ></div>
                                </div>
                                <p class="ml-7 text-xs text-on-surface/50 mt-0.5">
                                    {{ trans_choice(':count order|:count orders', $region['order_count'], ['count' => number_format($region['order_count'])]) }}
                                </p>
                            </div>
                            @if(! $loop->last)
                                <div class="border-t border-outline/50 dark:border-outline/50"></div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Date range footer --}}
        <p class="text-xs text-on-surface/50 text-center">
            {{ __('Showing data from :start to :end', [
                'start' => $rangeStart->format('M j, Y'),
                'end'   => $rangeEnd->format('M j, Y'),
            ]) }}
            @if($compare)
                &mdash;
                {{ __('Compared to :start to :end', [
                    'start' => $prevRangeStart->format('M j, Y'),
                    'end'   => $prevRangeEnd->format('M j, Y'),
                ]) }}
            @endif
        </p>

    </div>
    @endfragment

</div>
@endsection
