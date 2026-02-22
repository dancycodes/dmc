@extends('layouts.admin')

@section('title', __('Platform Analytics'))
@section('page-title', __('Analytics'))

@section('content')
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
            let url = '/vault-entry/analytics?period=' + this.period;
            if (this.period === 'custom' && this.customStart && this.customEnd) {
                url += '&custom_start=' + this.customStart + '&custom_end=' + this.customEnd;
            }
            $navigate(url, { key: 'analytics', replace: true });
        }
    }"
    class="space-y-6"
>
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[
        ['label' => __('Analytics')],
    ]" />

    {{-- Page Header + Period Selector --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-on-surface-strong">{{ __('Platform Analytics') }}</h2>
            <p class="text-sm text-on-surface mt-0.5">
                {{ __('Platform-wide performance metrics and trends.') }}
            </p>
        </div>

        {{-- Period Selector --}}
        <div class="flex flex-col gap-2">
            {{-- Segmented Control --}}
            <div class="flex items-center gap-1 bg-surface-alt border border-outline rounded-lg p-1 flex-wrap">
                @foreach([
                    'today' => __('Today'),
                    'week'  => __('Week'),
                    'month' => __('Month'),
                    'year'  => __('Year'),
                    'custom'=> __('Custom'),
                ] as $key => $label)
                    <button
                        type="button"
                        @click="changePeriod('{{ $key }}')"
                        :class="period === '{{ $key }}'
                            ? 'bg-primary text-on-primary shadow-sm'
                            : 'text-on-surface hover:bg-surface'"
                        class="px-3 py-1.5 rounded-md text-sm font-medium transition-all duration-150 whitespace-nowrap"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            {{-- Custom Range Picker --}}
            <div x-show="showCustom" x-cloak class="flex items-center gap-2">
                <input
                    type="date"
                    x-model="customStart"
                    :max="customEnd || ''"
                    class="flex-1 px-3 py-1.5 text-sm bg-surface border border-outline rounded-lg text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/30 dark:bg-surface dark:border-outline"
                    aria-label="{{ __('Start date') }}"
                />
                <span class="text-on-surface text-sm">{{ __('to') }}</span>
                <input
                    type="date"
                    x-model="customEnd"
                    :min="customStart || ''"
                    class="flex-1 px-3 py-1.5 text-sm bg-surface border border-outline rounded-lg text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/30 dark:bg-surface dark:border-outline"
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

        {{-- Summary Cards (6) --}}
        @php
            $cards = [
                [
                    'label'   => __('Revenue'),
                    'value'   => \App\Services\PlatformAnalyticsService::formatXAF($metrics['revenue']),
                    'change'  => $metrics['changes']['revenue'],
                    'color'   => 'primary',
                    'icon'    => '<line x1="12" x2="12" y1="1" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>',
                    'note'    => __('Completed orders only'),
                ],
                [
                    'label'   => __('Commission Earned'),
                    'value'   => \App\Services\PlatformAnalyticsService::formatXAF($metrics['commission']),
                    'change'  => $metrics['changes']['commission'],
                    'color'   => 'success',
                    'icon'    => '<path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>',
                    'note'    => __('Platform share'),
                ],
                [
                    'label'   => __('Total Orders'),
                    'value'   => number_format($metrics['orders']),
                    'change'  => $metrics['changes']['orders'],
                    'color'   => 'secondary',
                    'icon'    => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" x2="21" y1="6" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path>',
                    'note'    => __('All statuses'),
                ],
                [
                    'label'   => __('Active Tenants'),
                    'value'   => number_format($metrics['active_tenants']),
                    'change'  => null,
                    'color'   => 'info',
                    'icon'    => '<rect width="7" height="7" x="3" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="14" rx="1"></rect><rect width="7" height="7" x="3" y="14" rx="1"></rect>',
                    'note'    => __('Currently active'),
                ],
                [
                    'label'   => __('Active Users'),
                    'value'   => number_format($metrics['active_users']),
                    'change'  => $metrics['changes']['active_users'],
                    'color'   => 'warning',
                    'icon'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
                    'note'    => __('Logged in during period'),
                ],
                [
                    'label'   => __('New Registrations'),
                    'value'   => number_format($metrics['new_users']),
                    'change'  => $metrics['changes']['new_users'],
                    'color'   => 'success',
                    'icon'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" x2="19" y1="8" y2="14"></line><line x1="22" x2="16" y1="11" y2="11"></line>',
                    'note'    => __('New accounts'),
                ],
            ];
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($cards as $card)
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

        {{-- Charts Section --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Revenue Chart --}}
            @php
                $revenueMax = $revenueData->max('value') ?: 1;
                $revenuePoints = $revenueData->values();
                $showAllLabels = $revenuePoints->count() <= 14;
            @endphp
            <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-semibold text-on-surface-strong">{{ __('Revenue Trend') }}</h3>
                        <p class="text-xs text-on-surface mt-0.5">
                            {{ $granularity === 'daily' ? __('Daily breakdown') : __('Weekly breakdown') }}
                            &mdash; {{ __('completed orders only') }}
                        </p>
                    </div>
                    <span class="text-xs text-on-surface bg-surface px-2 py-1 rounded-md border border-outline">
                        {{ $granularity === 'daily' ? __('Daily') : __('Weekly') }}
                    </span>
                </div>

                @if($revenuePoints->isEmpty() || $revenuePoints->every(fn($p) => $p['value'] === 0))
                    <div class="h-40 flex items-center justify-center text-on-surface/50 text-sm">
                        {{ __('No revenue data for this period') }}
                    </div>
                @else
                    {{-- Tailwind bar chart --}}
                    <div class="h-40 flex items-end gap-px overflow-x-auto">
                        @foreach($revenuePoints as $i => $point)
                            @php
                                $heightPct = $revenueMax > 0 ? round(($point['value'] / $revenueMax) * 100) : 0;
                                $heightPct = max($heightPct, $point['value'] > 0 ? 2 : 0);
                            @endphp
                            <div class="flex flex-col items-center gap-1 flex-1 min-w-0 group relative"
                                 title="{{ $point['label'] }}: {{ \App\Services\PlatformAnalyticsService::formatXAF($point['value']) }}">
                                <div
                                    class="w-full bg-primary/20 dark:bg-primary/20 rounded-t group-hover:bg-primary transition-colors duration-150"
                                    style="height: {{ max($heightPct, 2) }}%"
                                ></div>
                            </div>
                        @endforeach
                    </div>
                    {{-- X axis labels (show every nth label to avoid crowding) --}}
                    <div class="flex items-start gap-px mt-1 overflow-x-auto">
                        @foreach($revenuePoints as $i => $point)
                            @php $skip = !$showAllLabels && $i % max(1, intdiv($revenuePoints->count(), 7)) !== 0; @endphp
                            <div class="flex-1 min-w-0 text-center">
                                @unless($skip)
                                    <span class="text-xs text-on-surface/50 truncate block">{{ $point['label'] }}</span>
                                @endunless
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Orders Chart --}}
            @php
                $orderMax = $orderData->max('value') ?: 1;
                $orderPoints = $orderData->values();
            @endphp
            <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-semibold text-on-surface-strong">{{ __('Order Volume') }}</h3>
                        <p class="text-xs text-on-surface mt-0.5">
                            {{ $granularity === 'daily' ? __('Daily order count') : __('Weekly order count') }}
                        </p>
                    </div>
                    <span class="text-xs text-on-surface bg-surface px-2 py-1 rounded-md border border-outline">
                        {{ $granularity === 'daily' ? __('Daily') : __('Weekly') }}
                    </span>
                </div>

                @if($orderPoints->isEmpty() || $orderPoints->every(fn($p) => $p['value'] === 0))
                    <div class="h-40 flex items-center justify-center text-on-surface/50 text-sm">
                        {{ __('No order data for this period') }}
                    </div>
                @else
                    <div class="h-40 flex items-end gap-px overflow-x-auto">
                        @foreach($orderPoints as $i => $point)
                            @php
                                $heightPct = $orderMax > 0 ? round(($point['value'] / $orderMax) * 100) : 0;
                                $heightPct = max($heightPct, $point['value'] > 0 ? 2 : 0);
                            @endphp
                            <div class="flex flex-col items-center gap-1 flex-1 min-w-0 group relative"
                                 title="{{ $point['label'] }}: {{ $point['value'] }} {{ __('orders') }}">
                                <div
                                    class="w-full bg-secondary/20 dark:bg-secondary/20 rounded-t group-hover:bg-secondary transition-colors duration-150"
                                    style="height: {{ max($heightPct, 2) }}%"
                                ></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex items-start gap-px mt-1 overflow-x-auto">
                        @foreach($orderPoints as $i => $point)
                            @php $skip = !$showAllLabels && $i % max(1, intdiv($orderPoints->count(), 7)) !== 0; @endphp
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

        {{-- Top Cooks & Top Meals --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Top 10 Cooks --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-on-surface-strong">{{ __('Top Cooks') }}</h3>
                    <span class="text-xs text-on-surface/60">{{ __('by revenue') }}</span>
                </div>

                @if($topCooks->isEmpty())
                    <div class="text-center py-8 text-on-surface/50">
                        <svg class="w-8 h-8 mx-auto mb-2 opacity-40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                        <p class="text-sm">{{ __('No data for this period') }}</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($topCooks as $rank => $cook)
                            <div class="flex items-center gap-3">
                                <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0
                                    {{ $rank === 0 ? 'bg-warning text-on-warning' : ($rank === 1 ? 'bg-on-surface/20 text-on-surface-strong' : 'bg-surface text-on-surface border border-outline') }}">
                                    {{ $rank + 1 }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-on-surface-strong truncate">{{ $cook['cook_name'] }}</p>
                                    <p class="text-xs text-on-surface/60 truncate">{{ $cook['tenant_name'] }}</p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-sm font-semibold text-on-surface-strong">
                                        {{ \App\Services\PlatformAnalyticsService::formatXAF($cook['revenue']) }}
                                    </p>
                                    <p class="text-xs text-on-surface/60">
                                        {{ trans_choice(':count order|:count orders', $cook['order_count'], ['count' => number_format($cook['order_count'])]) }}
                                    </p>
                                </div>
                            </div>
                            @if(!$loop->last)
                                <div class="border-t border-outline/50 dark:border-outline/50"></div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Top 10 Meals --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-on-surface-strong">{{ __('Top Meals') }}</h3>
                    <span class="text-xs text-on-surface/60">{{ __('by orders') }}</span>
                </div>

                @if($topMeals->isEmpty())
                    <div class="text-center py-8 text-on-surface/50">
                        <svg class="w-8 h-8 mx-auto mb-2 opacity-40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" x2="21" y1="6" y2="6"></line></svg>
                        <p class="text-sm">{{ __('No data for this period') }}</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($topMeals as $rank => $meal)
                            <div class="flex items-center gap-3">
                                <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0
                                    {{ $rank === 0 ? 'bg-warning text-on-warning' : ($rank === 1 ? 'bg-on-surface/20 text-on-surface-strong' : 'bg-surface text-on-surface border border-outline') }}">
                                    {{ $rank + 1 }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-on-surface-strong truncate">{{ $meal['meal_name'] }}</p>
                                    <p class="text-xs text-on-surface/60 truncate">{{ $meal['tenant_name'] }}</p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-sm font-semibold text-on-surface-strong">
                                        {{ \App\Services\PlatformAnalyticsService::formatXAF($meal['revenue']) }}
                                    </p>
                                    <p class="text-xs text-on-surface/60">
                                        {{ trans_choice(':count order|:count orders', $meal['order_count'], ['count' => number_format($meal['order_count'])]) }}
                                    </p>
                                </div>
                            </div>
                            @if(!$loop->last)
                                <div class="border-t border-outline/50 dark:border-outline/50"></div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

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
