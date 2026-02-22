@extends('layouts.cook-dashboard')

@section('title', __('Delivery Analytics'))
@section('page-title', __('Delivery Analytics'))

@section('content')
{{--
    F-203: Cook Delivery Performance Analytics
    Tenant-scoped delivery analytics dashboard with:
    - Summary card: Total Deliveries / Most Popular Area
    - Delivery vs Pickup donut chart (CSS-based)
    - Orders by area horizontal bar chart
    - Period selector (Today / This Week / This Month / Last 3 Months / Last 6 Months / This Year / Custom)
    BR-401: All data tenant-scoped
    BR-406: Date range selector applies to all charts and metrics
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
            let url = '/dashboard/analytics/delivery?period=' + this.period;
            if (this.period === 'custom' && this.customStart && this.customEnd) {
                url += '&custom_start=' + this.customStart + '&custom_end=' + this.customEnd;
            }
            $navigate(url, { key: 'analytics', replace: true });
        }
    }"
    class="space-y-6"
>
    {{-- Tab navigation: Revenue | Orders | Customers | Delivery --}}
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
            class="px-4 py-2.5 text-sm font-medium text-on-surface hover:text-on-surface-strong border-b-2 border-transparent hover:border-outline-strong transition-colors duration-150 -mb-px whitespace-nowrap"
        >
            <span class="flex items-center gap-1.5">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                {{ __('Customers') }}
            </span>
        </a>
        <a
            href="/dashboard/analytics/delivery"
            x-navigate
            class="px-4 py-2.5 text-sm font-medium text-primary border-b-2 border-primary -mb-px whitespace-nowrap"
            aria-current="page"
        >
            <span class="flex items-center gap-1.5">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 3h15v13H1z"></path><path d="M16 8h4l3 3v5h-7V8z"></path><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle>
                </svg>
                {{ __('Delivery') }}
            </span>
        </a>
    </div>

    {{-- Page Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-on-surface-strong">{{ __('Delivery Analytics') }}</h2>
            <p class="text-sm text-on-surface mt-0.5">
                {{ __('Delivery performance, area breakdown, and delivery vs pickup trends.') }}
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
                    class="flex-1 min-w-0 px-3 py-1.5 text-sm bg-surface border border-outline rounded-lg text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/30 dark:bg-surface dark:border-outline dark:text-on-surface"
                    aria-label="{{ __('Start date') }}"
                />
                <span class="text-on-surface text-sm">{{ __('to') }}</span>
                <input
                    type="date"
                    x-model="customEnd"
                    :min="customStart || ''"
                    class="flex-1 min-w-0 px-3 py-1.5 text-sm bg-surface border border-outline rounded-lg text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/30 dark:bg-surface dark:border-outline dark:text-on-surface"
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

        @if(! $hasAnyOrders)
            {{-- Empty state: no orders at all --}}
            <div class="flex flex-col items-center justify-center py-20 text-center">
                <span class="w-16 h-16 rounded-full bg-primary-subtle flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 3h15v13H1z"></path><path d="M16 8h4l3 3v5h-7V8z"></path><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle>
                    </svg>
                </span>
                <h3 class="text-lg font-semibold text-on-surface-strong mb-2">{{ __('No delivery data yet') }}</h3>
                <p class="text-sm text-on-surface max-w-sm">
                    {{ __('Your delivery analytics will appear here once you receive your first completed order.') }}
                </p>
            </div>
        @else

        {{-- Summary Card: Total Deliveries / Total Pickups / Most Popular Area --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {{-- Total Deliveries --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-4 shadow-card">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium text-on-surface truncate">{{ __('Total Deliveries') }}</p>
                        <p class="text-lg font-bold text-on-surface-strong mt-1">{{ number_format($summaryMetrics['total_deliveries']) }}</p>
                        <p class="text-xs text-on-surface/60 mt-0.5 truncate">{{ __('Selected period') }}</p>
                    </div>
                    <span class="w-9 h-9 rounded-full bg-primary-subtle flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 3h15v13H1z"></path><path d="M16 8h4l3 3v5h-7V8z"></path><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle>
                        </svg>
                    </span>
                </div>
            </div>

            {{-- Total Pickups --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-4 shadow-card">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium text-on-surface truncate">{{ __('Total Pickups') }}</p>
                        <p class="text-lg font-bold text-on-surface-strong mt-1">{{ number_format($summaryMetrics['total_pickups']) }}</p>
                        <p class="text-xs text-on-surface/60 mt-0.5 truncate">{{ __('Selected period') }}</p>
                    </div>
                    <span class="w-9 h-9 rounded-full bg-success-subtle flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle>
                        </svg>
                    </span>
                </div>
            </div>

            {{-- Most Popular Area --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-4 shadow-card">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium text-on-surface truncate">{{ __('Most Popular Area') }}</p>
                        <p class="text-sm font-bold text-on-surface-strong mt-1 truncate" title="{{ $summaryMetrics['most_popular_area'] ?? __('N/A') }}">
                            {{ $summaryMetrics['most_popular_area'] ?? __('N/A') }}
                        </p>
                        <p class="text-xs text-on-surface/60 mt-0.5 truncate">{{ __('Top delivery destination') }}</p>
                    </div>
                    <span class="w-9 h-9 rounded-full bg-secondary-subtle flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="3 11 22 2 13 21 11 13 3 11"></polygon>
                        </svg>
                    </span>
                </div>
            </div>
        </div>

        {{-- Two-column grid: Delivery vs Pickup donut + Orders by Area bar chart --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Delivery vs Pickup Donut Chart (BR-404) --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card">
                <div class="mb-5">
                    <h3 class="font-semibold text-on-surface-strong">{{ __('Delivery vs Pickup') }}</h3>
                    <p class="text-xs text-on-surface mt-0.5">{{ __('Distribution of order fulfillment method') }}</p>
                </div>

                @php
                    $deliveryPct = $deliveryVsPickup['delivery_pct'];
                    $pickupPct = $deliveryVsPickup['pickup_pct'];
                    $totalOrders = $deliveryVsPickup['delivery'] + $deliveryVsPickup['pickup'];
                    $hasRatioData = $totalOrders > 0;

                    // Donut chart CSS: conic-gradient from delivery percentage
                    $deliveryDeg = $hasRatioData ? round(($deliveryPct / 100) * 360) : 0;
                @endphp

                @if(! $hasRatioData)
                    <div class="flex flex-col items-center justify-center py-10 text-on-surface/50">
                        <svg class="w-8 h-8 mb-2 opacity-40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 8v4m0 4h.01"></path></svg>
                        <p class="text-sm">{{ __('No orders in this period') }}</p>
                    </div>
                @else
                    <div class="flex flex-col sm:flex-row items-center gap-6">
                        {{-- Donut chart via conic-gradient --}}
                        <div class="relative flex-shrink-0">
                            <div
                                class="w-36 h-36 rounded-full"
                                style="background: conic-gradient(
                                    var(--color-primary) 0deg {{ $deliveryDeg }}deg,
                                    var(--color-success) {{ $deliveryDeg }}deg 360deg
                                );"
                            ></div>
                            {{-- Inner cutout (donut hole) --}}
                            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-20 h-20 rounded-full bg-surface-alt dark:bg-surface-alt flex flex-col items-center justify-center">
                                <span class="text-lg font-bold text-on-surface-strong leading-tight">{{ $totalOrders }}</span>
                                <span class="text-xs text-on-surface/60">{{ __('orders') }}</span>
                            </div>
                        </div>

                        {{-- Legend with percentages --}}
                        <div class="space-y-4 flex-1 w-full">
                            {{-- Delivery row --}}
                            <div>
                                <div class="flex items-center justify-between mb-1.5 gap-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="w-3 h-3 rounded-full bg-primary shrink-0"></span>
                                        <span class="text-sm font-medium text-on-surface truncate">{{ __('Delivery') }}</span>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span class="text-sm font-bold text-on-surface-strong">{{ $deliveryPct }}%</span>
                                        <span class="text-xs text-on-surface/60 ml-1">({{ number_format($deliveryVsPickup['delivery']) }})</span>
                                    </div>
                                </div>
                                <div class="w-full bg-surface dark:bg-surface rounded-full h-2 overflow-hidden">
                                    <div
                                        class="h-2 rounded-full bg-primary transition-all duration-500"
                                        style="width: {{ max($deliveryPct, $deliveryVsPickup['delivery'] > 0 ? 2 : 0) }}%"
                                    ></div>
                                </div>
                            </div>

                            {{-- Pickup row --}}
                            <div>
                                <div class="flex items-center justify-between mb-1.5 gap-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="w-3 h-3 rounded-full bg-success shrink-0"></span>
                                        <span class="text-sm font-medium text-on-surface truncate">{{ __('Pickup') }}</span>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span class="text-sm font-bold text-on-surface-strong">{{ $pickupPct }}%</span>
                                        <span class="text-xs text-on-surface/60 ml-1">({{ number_format($deliveryVsPickup['pickup']) }})</span>
                                    </div>
                                </div>
                                <div class="w-full bg-surface dark:bg-surface rounded-full h-2 overflow-hidden">
                                    <div
                                        class="h-2 rounded-full bg-success transition-all duration-500"
                                        style="width: {{ max($pickupPct, $deliveryVsPickup['pickup'] > 0 ? 2 : 0) }}%"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Orders by Delivery Area — Horizontal Bar Chart (BR-403, BR-405) --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card">
                <div class="mb-5">
                    <h3 class="font-semibold text-on-surface-strong">{{ __('Orders by Delivery Area') }}</h3>
                    <p class="text-xs text-on-surface mt-0.5">{{ __('Top delivery destinations (by order count)') }}</p>
                </div>

                @if(! $hasDeliveryData || $topAreas->isEmpty())
                    {{-- Edge case: all orders are pickup --}}
                    <div class="flex flex-col items-center justify-center py-10 text-on-surface/50">
                        <svg class="w-8 h-8 mb-2 opacity-40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 3h15v13H1z"></path><path d="M16 8h4l3 3v5h-7V8z"></path><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle>
                        </svg>
                        <p class="text-sm">{{ __('No delivery orders in this period') }}</p>
                        <p class="text-xs mt-1 text-center max-w-xs">{{ __('All orders are being picked up. Delivery area data will appear when customers choose delivery.') }}</p>
                    </div>
                @else
                    @php
                        $maxAreaCount = $topAreas->max('order_count') ?: 1;
                    @endphp
                    <div class="space-y-3">
                        @foreach($topAreas as $i => $area)
                            @php
                                $barPct = $maxAreaCount > 0 ? round(($area['order_count'] / $maxAreaCount) * 100) : 0;
                                // Alternate between primary/secondary/info for visual variety
                                $barColors = ['primary', 'secondary', 'info', 'success', 'warning'];
                                $barColor = $barColors[$i % count($barColors)];
                            @endphp
                            <div>
                                <div class="flex items-center justify-between mb-1 gap-2">
                                    <span class="text-sm text-on-surface truncate max-w-0 flex-1 block" title="{{ $area['area_label'] }}">{{ $area['area_label'] }}</span>
                                    <div class="text-right shrink-0">
                                        <span class="text-sm font-bold text-on-surface-strong">{{ number_format($area['order_count']) }}</span>
                                        <span class="text-xs text-on-surface/50 ml-1">{{ $area['percentage'] }}%</span>
                                    </div>
                                </div>
                                <div class="w-full bg-surface dark:bg-surface rounded-full h-2.5 overflow-hidden">
                                    <div
                                        class="h-2.5 rounded-full bg-{{ $barColor }} transition-all duration-500"
                                        style="width: {{ max($barPct, $area['order_count'] > 0 ? 2 : 0) }}%"
                                    ></div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @php $totalDeliveryOrders = $topAreas->sum('order_count'); @endphp
                    <p class="text-xs text-on-surface/50 mt-4 text-right">
                        {{ __(':count total delivery orders', ['count' => number_format($totalDeliveryOrders)]) }}
                    </p>
                @endif
            </div>

        </div>

        @endif {{-- end hasAnyOrders --}}

    </div>
    @endfragment

</div>
@endsection
