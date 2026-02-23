@extends('layouts.admin')

@section('title', __('Cook Performance Metrics'))
@section('page-title', __('Cook Performance'))

@section('content')
<div
    x-data="{
        period: @js($period),
        customStart: @js($customStart ?? ''),
        customEnd: @js($customEnd ?? ''),
        showCustom: @js($period === 'custom'),
        sortBy: @js($sortBy),
        sortDir: @js($sortDir),
        search: @js($search),
        status: @js($status),
        regionId: @js($regionId),

        changePeriod(newPeriod) {
            this.period = newPeriod;
            this.showCustom = (newPeriod === 'custom');
            if (newPeriod !== 'custom') {
                this.applyFilters();
            }
        },

        toggleSort(column) {
            if (this.sortBy === column) {
                this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortBy = column;
                this.sortDir = 'desc';
            }
            this.applyFilters();
        },

        applyFilters(resetPage) {
            let url = '/vault-entry/analytics/performance?period=' + this.period
                + '&sort=' + this.sortBy
                + '&direction=' + this.sortDir;
            if (this.search) {
                url += '&search=' + encodeURIComponent(this.search);
            }
            if (this.status) {
                url += '&status=' + this.status;
            }
            if (parseInt(this.regionId) > 0) {
                url += '&region=' + this.regionId;
            }
            if (this.period === 'custom' && this.customStart && this.customEnd) {
                url += '&custom_start=' + this.customStart + '&custom_end=' + this.customEnd;
            }
            $navigate(url, { key: 'cook-performance', replace: true });
        },

        clearFilters() {
            this.search = '';
            this.status = '';
            this.regionId = 0;
            this.applyFilters();
        },

        showExportMenu: false,

        buildExportUrl(format) {
            let url = '/vault-entry/analytics/performance/export-' + format + '?period=' + this.period;
            if (parseInt(this.regionId) > 0) {
                url += '&region=' + this.regionId;
            }
            if (this.period === 'custom' && this.customStart && this.customEnd) {
                url += '&custom_start=' + this.customStart + '&custom_end=' + this.customEnd;
            }
            return url;
        }
    }"
    class="space-y-6"
>
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[
        ['label' => __('Analytics'), 'url' => route('admin.analytics.index')],
        ['label' => __('Cook Performance')],
    ]" />

    {{-- Page Header + Period Selector --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-on-surface-strong">{{ __('Cook Performance Metrics') }}</h2>
            <p class="text-sm text-on-surface mt-0.5">
                {{ __('Compare cook performance across orders, revenue, ratings, and complaints.') }}
            </p>
        </div>

        {{-- Export Button (F-208) --}}
        <div class="flex items-start gap-2">
        <div class="relative" @click.outside="showExportMenu = false">
            <button type="button" @click="showExportMenu = !showExportMenu"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-surface-alt border border-outline text-on-surface hover:bg-surface hover:border-outline-strong transition-colors duration-150 dark:bg-surface-alt dark:border-outline dark:text-on-surface dark:hover:bg-surface"
                :aria-expanded="showExportMenu.toString()">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                {{ __('Export') }}
                <svg class="w-3.5 h-3.5 transition-transform duration-150" :class="showExportMenu ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div x-show="showExportMenu" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" x-cloak class="absolute right-0 mt-1 w-48 bg-surface border border-outline rounded-xl shadow-dropdown z-20 py-1 dark:bg-surface dark:border-outline" role="menu">
                <a :href="buildExportUrl('csv')" x-navigate-skip class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors duration-100" role="menuitem" @click="showExportMenu = false">
                    <svg class="w-4 h-4 text-success shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><line x1="10" x2="8" y1="9" y2="9"/></svg>
                    <span>{{ __('Export as CSV') }}</span>
                </a>
                <a :href="buildExportUrl('pdf')" x-navigate-skip class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors duration-100" role="menuitem" @click="showExportMenu = false">
                    <svg class="w-4 h-4 text-danger shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>
                    <span>{{ __('Export as PDF') }}</span>
                </a>
            </div>
        </div>

        {{-- Period Selector --}}
        <div class="flex flex-col gap-2 shrink-0">
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

            {{-- Custom Range Picker --}}
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
                    @click="applyFilters()"
                    :disabled="!customStart || !customEnd"
                    class="px-3 py-1.5 text-sm font-medium bg-primary text-on-primary rounded-lg hover:bg-primary-hover transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {{ __('Apply') }}
                </button>
            </div>
        </div>
        </div>{{-- end flex actions wrapper --}}
    </div>

    @fragment('cook-performance-content')
    <div id="cook-performance-content" class="space-y-4">

        {{-- Filter Bar --}}
        <div class="bg-surface-alt border border-outline rounded-xl p-4 shadow-card">
            <div class="flex flex-col sm:flex-row gap-3">

                {{-- Search Input --}}
                <div class="flex-1 relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-on-surface/40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" x2="16.65" y1="21" y2="16.65"></line></svg>
                    <input
                        type="text"
                        x-model="search"
                        @input.debounce.400ms="applyFilters()"
                        placeholder="{{ __('Search by cook name...') }}"
                        class="w-full pl-9 pr-3 py-2 text-sm bg-surface border border-outline rounded-lg text-on-surface placeholder-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30"
                        aria-label="{{ __('Search cooks') }}"
                    />
                </div>

                {{-- Status Filter --}}
                <select
                    x-model="status"
                    @change="applyFilters()"
                    class="px-3 py-2 text-sm bg-surface border border-outline rounded-lg text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/30"
                    aria-label="{{ __('Filter by status') }}"
                >
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="active">{{ __('Active') }}</option>
                    <option value="inactive">{{ __('Inactive') }}</option>
                </select>

                {{-- Region Filter --}}
                @if($regions->isNotEmpty())
                    <select
                        x-model="regionId"
                        @change="applyFilters()"
                        class="px-3 py-2 text-sm bg-surface border border-outline rounded-lg text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/30"
                        aria-label="{{ __('Filter by region') }}"
                    >
                        <option value="0">{{ __('All Regions') }}</option>
                        @foreach($regions as $region)
                            <option value="{{ $region->id }}">{{ $region->name }}</option>
                        @endforeach
                    </select>
                @endif

                {{-- Clear Filters --}}
                <button
                    type="button"
                    @click="clearFilters()"
                    x-show="search || status || parseInt(regionId) > 0"
                    x-cloak
                    class="px-3 py-2 text-sm text-on-surface/70 border border-outline rounded-lg hover:bg-surface hover:text-on-surface-strong transition-colors whitespace-nowrap"
                >
                    {{ __('Clear') }}
                </button>
            </div>
        </div>

        {{-- Results Count --}}
        <div class="flex items-center justify-between">
            <p class="text-sm text-on-surface/60">
                @if($cooks->total() > 0)
                    {{ __('Showing :from–:to of :total cooks', [
                        'from' => $cooks->firstItem(),
                        'to' => $cooks->lastItem(),
                        'total' => number_format($cooks->total()),
                    ]) }}
                @else
                    {{ __('No cooks found') }}
                @endif
            </p>
            {{-- Export link stub for F-208 --}}
            <a
                href="#"
                x-navigate-skip
                class="inline-flex items-center gap-1.5 text-sm text-on-surface/60 hover:text-on-surface transition-colors"
                aria-label="{{ __('Export data') }}"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" x2="12" y1="15" y2="3"></line></svg>
                {{ __('Export') }}
            </a>
        </div>

        @if($cooks->isEmpty())
            {{-- Empty State --}}
            <div class="bg-surface-alt border border-outline rounded-xl p-12 text-center shadow-card">
                <svg class="w-12 h-12 mx-auto mb-4 text-on-surface/20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                <p class="text-on-surface-strong font-medium">{{ __('No cooks found') }}</p>
                <p class="text-sm text-on-surface/60 mt-1">{{ __('Try adjusting your filters or search term.') }}</p>
            </div>
        @else
            {{-- Desktop Table --}}
            <div class="hidden md:block bg-surface-alt border border-outline rounded-xl shadow-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-outline bg-surface">
                                @php
                                    $columns = [
                                        ['key' => 'cook_name',         'label' => __('Cook')],
                                        ['key' => 'region',            'label' => __('Region')],
                                        ['key' => 'total_orders',      'label' => __('Orders')],
                                        ['key' => 'total_revenue',     'label' => __('Revenue (XAF)')],
                                        ['key' => 'avg_rating',        'label' => __('Avg Rating')],
                                        ['key' => 'complaint_count',   'label' => __('Complaints')],
                                        ['key' => 'avg_response_hours','label' => __('Avg Response')],
                                    ];
                                @endphp
                                @foreach($columns as $col)
                                    <th
                                        scope="col"
                                        class="px-4 py-3 text-left font-medium text-on-surface/70 whitespace-nowrap cursor-pointer select-none hover:text-on-surface-strong transition-colors"
                                        @click="toggleSort('{{ $col['key'] }}')"
                                        aria-label="{{ __('Sort by :col', ['col' => $col['label']]) }}"
                                    >
                                        <span class="inline-flex items-center gap-1">
                                            {{ $col['label'] }}
                                            <span class="inline-flex flex-col gap-px opacity-40">
                                                <svg
                                                    class="w-3 h-3 transition-opacity"
                                                    :class="sortBy === '{{ $col['key'] }}' && sortDir === 'asc' ? 'opacity-100 text-primary' : 'opacity-30'"
                                                    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                                ><path d="m18 15-6-6-6 6"/></svg>
                                                <svg
                                                    class="w-3 h-3 transition-opacity"
                                                    :class="sortBy === '{{ $col['key'] }}' && sortDir === 'desc' ? 'opacity-100 text-primary' : 'opacity-30'"
                                                    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                                ><path d="m6 9 6 6 6-6"/></svg>
                                            </span>
                                        </span>
                                    </th>
                                @endforeach
                                <th scope="col" class="px-4 py-3 text-left font-medium text-on-surface/70 whitespace-nowrap">
                                    {{ __('Status') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline/50">
                            @foreach($cooks as $cook)
                                @php
                                    $avgRating = $cook->avg_rating !== null ? (float) $cook->avg_rating : null;
                                    $avgRespHours = $cook->avg_response_hours !== null ? (float) $cook->avg_response_hours : null;
                                    $ratingClass = \App\Services\CookPerformanceService::ratingColorClass($avgRating);
                                    $complaintClass = \App\Services\CookPerformanceService::complaintColorClass((int) $cook->complaint_count);
                                @endphp
                                <tr class="hover:bg-surface/50 dark:hover:bg-surface/50 transition-colors">
                                    {{-- Cook Name --}}
                                    <td class="px-4 py-3">
                                        <a
                                            href="{{ route('admin.tenants.show', $cook->slug) }}"
                                            class="font-medium text-on-surface-strong hover:text-primary transition-colors"
                                        >
                                            {{ $cook->cook_name }}
                                        </a>
                                    </td>

                                    {{-- Region --}}
                                    <td class="px-4 py-3 text-on-surface/70">
                                        @if($cook->region)
                                            {{ $cook->region }}
                                        @else
                                            <span class="text-on-surface/40 italic">{{ __('—') }}</span>
                                        @endif
                                    </td>

                                    {{-- Total Orders --}}
                                    <td class="px-4 py-3 text-on-surface font-medium">
                                        {{ number_format((int) $cook->total_orders) }}
                                    </td>

                                    {{-- Total Revenue --}}
                                    <td class="px-4 py-3 text-on-surface font-medium">
                                        {{ \App\Services\CookPerformanceService::formatXAF((int) $cook->total_revenue) }}
                                    </td>

                                    {{-- Average Rating --}}
                                    <td class="px-4 py-3">
                                        @if($avgRating !== null)
                                            <span class="inline-flex items-center gap-1 {{ $ratingClass }}">
                                                <svg class="w-3.5 h-3.5 text-warning shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                                {{ $avgRating }}
                                            </span>
                                        @else
                                            <span class="text-on-surface/40 text-xs italic">{{ __('N/A') }}</span>
                                        @endif
                                    </td>

                                    {{-- Complaint Count --}}
                                    <td class="px-4 py-3">
                                        <span class="{{ $complaintClass }}">
                                            {{ number_format((int) $cook->complaint_count) }}
                                        </span>
                                    </td>

                                    {{-- Average Response Time --}}
                                    <td class="px-4 py-3">
                                        @php $respText = \App\Services\CookPerformanceService::formatResponseTime($avgRespHours); @endphp
                                        @if($respText === 'N/A')
                                            <span class="text-on-surface/40 text-xs italic">{{ __('No response') }}</span>
                                        @else
                                            <span class="text-on-surface/80">{{ $respText }}</span>
                                        @endif
                                    </td>

                                    {{-- Status --}}
                                    <td class="px-4 py-3">
                                        @if($cook->is_active)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-success-subtle text-success">
                                                <span class="w-1.5 h-1.5 rounded-full bg-success shrink-0"></span>
                                                {{ __('Active') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-danger-subtle text-danger">
                                                <span class="w-1.5 h-1.5 rounded-full bg-danger shrink-0"></span>
                                                {{ __('Inactive') }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Mobile Cards --}}
            <div class="md:hidden space-y-3">
                @foreach($cooks as $cook)
                    @php
                        $avgRating = $cook->avg_rating !== null ? (float) $cook->avg_rating : null;
                        $avgRespHours = $cook->avg_response_hours !== null ? (float) $cook->avg_response_hours : null;
                        $ratingClass = \App\Services\CookPerformanceService::ratingColorClass($avgRating);
                        $complaintClass = \App\Services\CookPerformanceService::complaintColorClass((int) $cook->complaint_count);
                    @endphp
                    <div class="bg-surface-alt border border-outline rounded-xl p-4 shadow-card space-y-3">
                        {{-- Cook Header --}}
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <a
                                    href="{{ route('admin.tenants.show', $cook->slug) }}"
                                    class="font-semibold text-on-surface-strong hover:text-primary transition-colors truncate block"
                                >
                                    {{ $cook->cook_name }}
                                </a>
                                @if($cook->region)
                                    <p class="text-xs text-on-surface/60 mt-0.5">{{ $cook->region }}</p>
                                @endif
                            </div>
                            @if($cook->is_active)
                                <span class="shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-success-subtle text-success">
                                    <span class="w-1.5 h-1.5 rounded-full bg-success"></span>
                                    {{ __('Active') }}
                                </span>
                            @else
                                <span class="shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-danger-subtle text-danger">
                                    <span class="w-1.5 h-1.5 rounded-full bg-danger"></span>
                                    {{ __('Inactive') }}
                                </span>
                            @endif
                        </div>

                        {{-- Metrics Grid --}}
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-surface rounded-lg p-2.5 border border-outline/50">
                                <p class="text-xs text-on-surface/50 mb-0.5">{{ __('Orders') }}</p>
                                <p class="text-sm font-semibold text-on-surface-strong">{{ number_format((int) $cook->total_orders) }}</p>
                            </div>
                            <div class="bg-surface rounded-lg p-2.5 border border-outline/50">
                                <p class="text-xs text-on-surface/50 mb-0.5">{{ __('Revenue') }}</p>
                                <p class="text-sm font-semibold text-on-surface-strong">{{ \App\Services\CookPerformanceService::formatXAF((int) $cook->total_revenue) }}</p>
                            </div>
                            <div class="bg-surface rounded-lg p-2.5 border border-outline/50">
                                <p class="text-xs text-on-surface/50 mb-0.5">{{ __('Avg Rating') }}</p>
                                @if($avgRating !== null)
                                    <p class="text-sm {{ $ratingClass }} inline-flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                        {{ $avgRating }}
                                    </p>
                                @else
                                    <p class="text-sm text-on-surface/40 italic">{{ __('N/A') }}</p>
                                @endif
                            </div>
                            <div class="bg-surface rounded-lg p-2.5 border border-outline/50">
                                <p class="text-xs text-on-surface/50 mb-0.5">{{ __('Complaints') }}</p>
                                <p class="text-sm {{ $complaintClass }}">{{ number_format((int) $cook->complaint_count) }}</p>
                            </div>
                            <div class="bg-surface rounded-lg p-2.5 border border-outline/50 col-span-2">
                                <p class="text-xs text-on-surface/50 mb-0.5">{{ __('Avg Response Time') }}</p>
                                @php $respText = \App\Services\CookPerformanceService::formatResponseTime($avgRespHours); @endphp
                                @if($respText === 'N/A')
                                    <p class="text-sm text-on-surface/40 italic">{{ __('No response') }}</p>
                                @else
                                    <p class="text-sm text-on-surface font-medium">{{ $respText }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if($cooks->hasPages())
                <div class="flex items-center justify-between pt-2">
                    <p class="text-sm text-on-surface/60 hidden sm:block">
                        {{ __('Page :current of :last', ['current' => $cooks->currentPage(), 'last' => $cooks->lastPage()]) }}
                    </p>
                    <div class="flex items-center gap-1 mx-auto sm:mx-0">
                        {{-- Previous --}}
                        @if($cooks->onFirstPage())
                            <span class="px-3 py-1.5 text-sm text-on-surface/30 border border-outline rounded-lg cursor-not-allowed">
                                {{ __('Prev') }}
                            </span>
                        @else
                            <button
                                type="button"
                                @click="$navigate('/vault-entry/analytics/performance?period={{ $period }}&sort={{ $sortBy }}&direction={{ $sortDir }}&search={{ urlencode($search) }}&status={{ $status }}&region={{ $regionId }}&page={{ $cooks->currentPage() - 1 }}', { key: 'cook-performance', replace: true })"
                                class="px-3 py-1.5 text-sm text-on-surface border border-outline rounded-lg hover:bg-surface hover:text-on-surface-strong transition-colors"
                            >
                                {{ __('Prev') }}
                            </button>
                        @endif

                        {{-- Page numbers (show up to 5 around current) --}}
                        @php
                            $start = max(1, $cooks->currentPage() - 2);
                            $end = min($cooks->lastPage(), $cooks->currentPage() + 2);
                        @endphp
                        @for($p = $start; $p <= $end; $p++)
                            @if($p === $cooks->currentPage())
                                <span class="px-3 py-1.5 text-sm font-semibold bg-primary text-on-primary rounded-lg">
                                    {{ $p }}
                                </span>
                            @else
                                <button
                                    type="button"
                                    @click="$navigate('/vault-entry/analytics/performance?period={{ $period }}&sort={{ $sortBy }}&direction={{ $sortDir }}&search={{ urlencode($search) }}&status={{ $status }}&region={{ $regionId }}&page={{ $p }}', { key: 'cook-performance', replace: true })"
                                    class="px-3 py-1.5 text-sm text-on-surface border border-outline rounded-lg hover:bg-surface hover:text-on-surface-strong transition-colors"
                                >
                                    {{ $p }}
                                </button>
                            @endif
                        @endfor

                        {{-- Next --}}
                        @if($cooks->hasMorePages())
                            <button
                                type="button"
                                @click="$navigate('/vault-entry/analytics/performance?period={{ $period }}&sort={{ $sortBy }}&direction={{ $sortDir }}&search={{ urlencode($search) }}&status={{ $status }}&region={{ $regionId }}&page={{ $cooks->currentPage() + 1 }}', { key: 'cook-performance', replace: true })"
                                class="px-3 py-1.5 text-sm text-on-surface border border-outline rounded-lg hover:bg-surface hover:text-on-surface-strong transition-colors"
                            >
                                {{ __('Next') }}
                            </button>
                        @else
                            <span class="px-3 py-1.5 text-sm text-on-surface/30 border border-outline rounded-lg cursor-not-allowed">
                                {{ __('Next') }}
                            </span>
                        @endif
                    </div>
                </div>
            @endif
        @endif

        {{-- Date range footer --}}
        <p class="text-xs text-on-surface/50 text-center">
            @if($rangeStart && $rangeEnd)
                {{ __('Showing data from :start to :end', [
                    'start' => $rangeStart->format('M j, Y'),
                    'end'   => $rangeEnd->format('M j, Y'),
                ]) }}
            @else
                {{ __('Showing all-time data') }}
            @endif
        </p>

    </div>
    @endfragment

</div>
@endsection
