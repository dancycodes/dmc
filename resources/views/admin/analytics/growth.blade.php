@extends('layouts.admin')

@section('title', __('Growth Metrics'))
@section('page-title', __('Growth Metrics'))

@section('content')
<div
    x-data="{
        period: @js($period),

        changePeriod(newPeriod) {
            this.period = newPeriod;
            this.applyFilter();
        },

        applyFilter() {
            let url = '/vault-entry/analytics/growth?period=' + this.period;
            $navigate(url, { key: 'growth-metrics', replace: true });
        }
    }"
    class="space-y-6"
>
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[
        ['label' => __('Analytics'), 'url' => route('admin.analytics.index')],
        ['label' => __('Growth Metrics')],
    ]" />

    {{-- Page Header + Period Selector --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-on-surface-strong">{{ __('Platform Growth Metrics') }}</h2>
            <p class="text-sm text-on-surface mt-0.5">
                {{ __('User registrations, cook additions, order volume, and active user trends.') }}
            </p>
        </div>

        {{-- Period Selector --}}
        <div class="flex items-center gap-1 bg-surface-alt border border-outline rounded-lg p-1 flex-wrap shrink-0">
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
    </div>

    @fragment('growth-metrics-content')
    <div id="growth-metrics-content" class="space-y-6">

        {{-- Summary Cards (4) — Scenario 1 --}}
        @php
            $summaryCardItems = [
                [
                    'label'  => __('Total Users'),
                    'value'  => number_format($summaryCards['total_users']),
                    'change' => $summaryCards['changes']['new_users'],
                    'note'   => __(':new new this period', ['new' => number_format($summaryCards['new_users'])]),
                    'color'  => 'primary',
                    'icon'   => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
                ],
                [
                    'label'  => __('Total Cooks'),
                    'value'  => number_format($summaryCards['total_cooks']),
                    'change' => $summaryCards['changes']['new_cooks'],
                    'note'   => __(':new new this period', ['new' => number_format($summaryCards['new_cooks'])]),
                    'color'  => 'secondary',
                    'icon'   => '<path d="M6 13.87A4 4 0 0 1 7.41 6a5.11 5.11 0 0 1 1.05-1.54 5 5 0 0 1 7.08 0A5.11 5.11 0 0 1 16.59 6 4 4 0 0 1 18 13.87V21H6Z"></path><line x1="6" x2="18" y1="17" y2="17"></line><line x1="6" x2="18" y1="21" y2="21"></line>',
                ],
                [
                    'label'  => __('Orders This Month'),
                    'value'  => number_format($summaryCards['orders_this_month']),
                    'change' => null,
                    'note'   => __('Current calendar month'),
                    'color'  => 'success',
                    'icon'   => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" x2="21" y1="6" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path>',
                ],
                [
                    'label'  => __('Active Users (30d)'),
                    'value'  => number_format($summaryCards['active_users_30d']),
                    'change' => null,
                    'note'   => __('Placed at least one order'),
                    'color'  => 'info',
                    'icon'   => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>',
                ],
            ];
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($summaryCardItems as $card)
                <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-on-surface">{{ $card['label'] }}</p>
                            <p class="text-2xl font-bold text-on-surface-strong mt-1 truncate">{{ $card['value'] }}</p>
                            <p class="text-xs text-on-surface/60 mt-0.5">{{ $card['note'] }}</p>
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

        {{-- Charts + Milestones --}}
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

            {{-- Charts (2/3 width on xl) --}}
            <div class="xl:col-span-2 space-y-6">

                {{-- Row 1: New Registrations (line) + New Cooks (bar) --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    {{-- New User Registrations — Scenario 2 --}}
                    @php
                        $usersMax = $newUsersChartData->max('value') ?: 1;
                        $usersPoints = $newUsersChartData->values();
                        $usersShowAll = $usersPoints->count() <= 12;
                    @endphp
                    <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="font-semibold text-on-surface-strong">{{ __('New Registrations') }}</h3>
                                <p class="text-xs text-on-surface/60 mt-0.5">{{ __('Monthly new user signups') }}</p>
                            </div>
                            <span class="w-8 h-8 rounded-full bg-primary-subtle flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" x2="19" y1="8" y2="14"></line><line x1="22" x2="16" y1="11" y2="11"></line></svg>
                            </span>
                        </div>

                        @if($usersPoints->isEmpty() || $usersPoints->every(fn($p) => $p['value'] === 0))
                            <div class="h-32 flex items-center justify-center text-on-surface/50 text-sm">
                                {{ __('No registrations in this period') }}
                            </div>
                        @else
                            <div class="h-32 flex items-end gap-px overflow-x-auto">
                                @foreach($usersPoints as $point)
                                    @php $hPct = max($usersMax > 0 ? round(($point['value'] / $usersMax) * 100) : 0, $point['value'] > 0 ? 2 : 0); @endphp
                                    <div
                                        class="flex flex-col items-center flex-1 min-w-0 group relative"
                                        title="{{ $point['label'] }}: {{ number_format($point['value']) }} {{ __('users') }}"
                                    >
                                        <div
                                            class="w-full bg-primary/20 dark:bg-primary/20 rounded-t group-hover:bg-primary transition-colors duration-150"
                                            style="height: {{ max($hPct, 2) }}%"
                                        ></div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="flex items-start gap-px mt-1">
                                @foreach($usersPoints as $i => $point)
                                    @php $skip = !$usersShowAll && $i % max(1, intdiv($usersPoints->count(), 6)) !== 0; @endphp
                                    <div class="flex-1 min-w-0 text-center">
                                        @unless($skip)
                                            <span class="text-xs text-on-surface/50 truncate block">{{ $point['label'] }}</span>
                                        @endunless
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- New Cooks/Tenants — Scenario 3 (bar chart) --}}
                    @php
                        $cooksMax = $newCooksChartData->max('value') ?: 1;
                        $cooksPoints = $newCooksChartData->values();
                        $cooksShowAll = $cooksPoints->count() <= 12;
                    @endphp
                    <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="font-semibold text-on-surface-strong">{{ __('New Cooks') }}</h3>
                                <p class="text-xs text-on-surface/60 mt-0.5">{{ __('Monthly cook additions') }}</p>
                            </div>
                            <span class="w-8 h-8 rounded-full bg-secondary-subtle flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 13.87A4 4 0 0 1 7.41 6a5.11 5.11 0 0 1 1.05-1.54 5 5 0 0 1 7.08 0A5.11 5.11 0 0 1 16.59 6 4 4 0 0 1 18 13.87V21H6Z"></path><line x1="6" x2="18" y1="17" y2="17"></line></svg>
                            </span>
                        </div>

                        @if($cooksPoints->isEmpty() || $cooksPoints->every(fn($p) => $p['value'] === 0))
                            <div class="h-32 flex items-center justify-center text-on-surface/50 text-sm">
                                {{ __('No new cooks in this period') }}
                            </div>
                        @else
                            <div class="h-32 flex items-end gap-1 overflow-x-auto">
                                @foreach($cooksPoints as $point)
                                    @php $hPct = max($cooksMax > 0 ? round(($point['value'] / $cooksMax) * 100) : 0, $point['value'] > 0 ? 2 : 0); @endphp
                                    <div
                                        class="flex flex-col items-center flex-1 min-w-0 group relative"
                                        title="{{ $point['label'] }}: {{ number_format($point['value']) }} {{ __('cooks') }}"
                                    >
                                        <div
                                            class="w-full bg-secondary rounded-t group-hover:bg-secondary-hover transition-colors duration-150"
                                            style="height: {{ max($hPct, 2) }}%"
                                        ></div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="flex items-start gap-1 mt-1">
                                @foreach($cooksPoints as $i => $point)
                                    @php $skip = !$cooksShowAll && $i % max(1, intdiv($cooksPoints->count(), 6)) !== 0; @endphp
                                    <div class="flex-1 min-w-0 text-center">
                                        @unless($skip)
                                            <span class="text-xs text-on-surface/50 truncate block">{{ $point['label'] }}</span>
                                        @endunless
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Row 2: Order Volume (line) + Active Users (line) --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    {{-- Order Volume Growth — Scenario 4 --}}
                    @php
                        $ordersMax = $orderVolumeChartData->max('value') ?: 1;
                        $ordersPoints = $orderVolumeChartData->values();
                        $ordersShowAll = $ordersPoints->count() <= 12;
                    @endphp
                    <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="font-semibold text-on-surface-strong">{{ __('Order Volume') }}</h3>
                                <p class="text-xs text-on-surface/60 mt-0.5">{{ __('Monthly total orders placed') }}</p>
                            </div>
                            <span class="w-8 h-8 rounded-full bg-success-subtle flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" x2="21" y1="6" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                            </span>
                        </div>

                        @if($ordersPoints->isEmpty() || $ordersPoints->every(fn($p) => $p['value'] === 0))
                            <div class="h-32 flex items-center justify-center text-on-surface/50 text-sm">
                                {{ __('No orders in this period') }}
                            </div>
                        @else
                            <div class="h-32 flex items-end gap-px overflow-x-auto">
                                @foreach($ordersPoints as $point)
                                    @php $hPct = max($ordersMax > 0 ? round(($point['value'] / $ordersMax) * 100) : 0, $point['value'] > 0 ? 2 : 0); @endphp
                                    <div
                                        class="flex flex-col items-center flex-1 min-w-0 group relative"
                                        title="{{ $point['label'] }}: {{ number_format($point['value']) }} {{ __('orders') }}"
                                    >
                                        <div
                                            class="w-full bg-success/20 dark:bg-success/20 rounded-t group-hover:bg-success transition-colors duration-150"
                                            style="height: {{ max($hPct, 2) }}%"
                                        ></div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="flex items-start gap-px mt-1">
                                @foreach($ordersPoints as $i => $point)
                                    @php $skip = !$ordersShowAll && $i % max(1, intdiv($ordersPoints->count(), 6)) !== 0; @endphp
                                    <div class="flex-1 min-w-0 text-center">
                                        @unless($skip)
                                            <span class="text-xs text-on-surface/50 truncate block">{{ $point['label'] }}</span>
                                        @endunless
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Active User Trend — Scenario 5 --}}
                    @php
                        $activeMax = $activeUsersChartData->max('value') ?: 1;
                        $activePoints = $activeUsersChartData->values();
                        $activeShowAll = $activePoints->count() <= 12;
                    @endphp
                    <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="font-semibold text-on-surface-strong">{{ __('Active User Trend') }}</h3>
                                <p class="text-xs text-on-surface/60 mt-0.5">{{ __('Monthly distinct ordering users') }}</p>
                            </div>
                            <span class="w-8 h-8 rounded-full bg-info-subtle flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
                            </span>
                        </div>

                        @if($activePoints->isEmpty() || $activePoints->every(fn($p) => $p['value'] === 0))
                            <div class="h-32 flex items-center justify-center text-on-surface/50 text-sm">
                                {{ __('No active user data in this period') }}
                            </div>
                        @else
                            <div class="h-32 flex items-end gap-px overflow-x-auto">
                                @foreach($activePoints as $point)
                                    @php $hPct = max($activeMax > 0 ? round(($point['value'] / $activeMax) * 100) : 0, $point['value'] > 0 ? 2 : 0); @endphp
                                    <div
                                        class="flex flex-col items-center flex-1 min-w-0 group relative"
                                        title="{{ $point['label'] }}: {{ number_format($point['value']) }} {{ __('active users') }}"
                                    >
                                        <div
                                            class="w-full bg-info/20 dark:bg-info/20 rounded-t group-hover:bg-info transition-colors duration-150"
                                            style="height: {{ max($hPct, 2) }}%"
                                        ></div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="flex items-start gap-px mt-1">
                                @foreach($activePoints as $i => $point)
                                    @php $skip = !$activeShowAll && $i % max(1, intdiv($activePoints->count(), 6)) !== 0; @endphp
                                    <div class="flex-1 min-w-0 text-center">
                                        @unless($skip)
                                            <span class="text-xs text-on-surface/50 truncate block">{{ $point['label'] }}</span>
                                        @endunless
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

            </div>{{-- /charts col --}}

            {{-- Milestones Sidebar — Scenario 6 --}}
            <div class="xl:col-span-1">
                <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card h-full">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="w-8 h-8 rounded-full bg-warning-subtle flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                        </span>
                        <div>
                            <h3 class="font-semibold text-on-surface-strong">{{ __('Milestones') }}</h3>
                            <p class="text-xs text-on-surface/60">{{ __('Platform achievements') }}</p>
                        </div>
                    </div>

                    @if($milestones->isEmpty())
                        <div class="flex flex-col items-center justify-center py-8 text-center">
                            <svg class="w-10 h-10 text-on-surface/20 mb-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                            <p class="text-sm text-on-surface/50">{{ __('No milestones reached yet') }}</p>
                            <p class="text-xs text-on-surface/40 mt-1">{{ __('Keep growing!') }}</p>
                        </div>
                    @else
                        <div class="space-y-3 overflow-y-auto max-h-96">
                            @foreach($milestones as $milestone)
                                @php
                                    $typeColors = [
                                        'users' => ['bg' => 'bg-primary-subtle', 'text' => 'text-primary'],
                                        'cooks' => ['bg' => 'bg-secondary-subtle', 'text' => 'text-secondary'],
                                        'orders' => ['bg' => 'bg-success-subtle', 'text' => 'text-success'],
                                    ];
                                    $color = $typeColors[$milestone['type']] ?? ['bg' => 'bg-surface', 'text' => 'text-on-surface'];
                                    $typeLabels = [
                                        'users' => __('Users'),
                                        'cooks' => __('Cooks'),
                                        'orders' => __('Orders'),
                                    ];
                                @endphp
                                <div class="flex items-start gap-3 p-3 rounded-lg {{ $color['bg'] }} border border-outline/30">
                                    <span class="w-8 h-8 rounded-full bg-white/50 dark:bg-black/20 flex items-center justify-center shrink-0 mt-0.5">
                                        @if($milestone['icon'] === 'users')
                                            <svg class="w-4 h-4 {{ $color['text'] }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                        @elseif($milestone['icon'] === 'chef-hat')
                                            <svg class="w-4 h-4 {{ $color['text'] }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 13.87A4 4 0 0 1 7.41 6a5.11 5.11 0 0 1 1.05-1.54 5 5 0 0 1 7.08 0A5.11 5.11 0 0 1 16.59 6 4 4 0 0 1 18 13.87V21H6Z"></path><line x1="6" x2="18" y1="17" y2="17"></line></svg>
                                        @else
                                            <svg class="w-4 h-4 {{ $color['text'] }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" x2="21" y1="6" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                                        @endif
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-on-surface-strong">
                                            {{ __('Reached :label', ['label' => $milestone['label']]) }}
                                        </p>
                                        <p class="text-xs {{ $color['text'] }} font-medium">
                                            {{ $typeLabels[$milestone['type']] ?? $milestone['type'] }}
                                        </p>
                                        @if($milestone['achieved_at'])
                                            <p class="text-xs text-on-surface/60 mt-0.5">{{ $milestone['achieved_at'] }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

        </div>{{-- /charts + milestones grid --}}

        {{-- Date range info footer --}}
        <p class="text-xs text-on-surface/50 text-center">
            {{ __('Showing data from :start to :end', [
                'start' => $rangeStart->format('M j, Y'),
                'end'   => $rangeEnd->format('M j, Y'),
            ]) }}
        </p>

    </div>
    @endfragment

</div>
@endsection
