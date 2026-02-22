{{--
    Client Spending & Order Stats (F-204)
    --------------------------------------
    Personal stats page for the authenticated client.
    Shows total spent (all time + this month), total orders, top cooks, and top meals.

    BR-408: Stats are personal to the authenticated client.
    BR-409: Total spent = completed/delivered/picked_up orders only.
    BR-410: "This month" = from 1st of current month.
    BR-411: Top 5 most-ordered cooks by order count.
    BR-412: Top 5 most-ordered meals by frequency.
    BR-413: Amounts in XAF format.
    BR-414: Cook cards link to cook's tenant landing page.
    BR-415: Meal cards link to meal detail on cook's tenant domain.
    BR-416: All text via __() localization.
--}}
@extends(tenant() ? 'layouts.tenant-public' : 'layouts.main-public')

@section('title', __('My Stats'))

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8" x-data="{ showExportMenu: false }">

    {{-- Page Header --}}
    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-display font-bold text-on-surface-strong">
                {{ __('My Stats') }}
            </h1>
            <p class="mt-1 text-sm text-on-surface">
                {{ __('Your personal spending and ordering patterns.') }}
            </p>
        </div>

        @if($hasOrders)
        {{-- Export Dropdown --}}
        <div class="relative shrink-0">
            <button
                type="button"
                @click="showExportMenu = !showExportMenu"
                class="inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-outline bg-surface text-on-surface hover:bg-surface-alt text-sm font-medium transition-colors duration-150"
            >
                {{-- Download icon (Lucide, sm=16) --}}
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" x2="12" y1="15" y2="3"></line></svg>
                {{ __('Export') }}
                <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div
                x-show="showExportMenu"
                @click.outside="showExportMenu = false"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="absolute right-0 mt-1 w-40 bg-surface border border-outline rounded-lg shadow-dropdown z-20 overflow-hidden"
            >
                <a
                    href="{{ route('client.stats.export-csv') }}"
                    x-navigate-skip
                    @click="showExportMenu = false"
                    class="flex items-center gap-2 px-3 py-2 text-sm text-on-surface hover:bg-surface-alt transition-colors"
                >
                    {{-- FileText icon (Lucide, sm=16) --}}
                    <svg class="w-4 h-4 text-on-surface/60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" x2="8" y1="13" y2="13"></line><line x1="16" x2="8" y1="17" y2="17"></line><line x1="10" x2="8" y1="9" y2="9"></line></svg>
                    {{ __('Export CSV') }}
                </a>
                <a
                    href="{{ route('client.stats.export-pdf') }}"
                    x-navigate-skip
                    @click="showExportMenu = false"
                    class="flex items-center gap-2 px-3 py-2 text-sm text-on-surface hover:bg-surface-alt transition-colors"
                >
                    {{-- File icon (Lucide, sm=16) --}}
                    <svg class="w-4 h-4 text-on-surface/60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                    {{ __('Export PDF') }}
                </a>
            </div>
        </div>
        @endif
    </div>

    @if(!$hasOrders)
        {{-- Scenario 4: Empty state — no orders yet --}}
        <div class="text-center py-16 sm:py-24">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-surface-alt dark:bg-surface-alt mb-6">
                {{-- BarChart3 icon (Lucide, xl=32) --}}
                <svg class="w-8 h-8 text-on-surface/40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"></path><path d="M18 17V9"></path><path d="M13 17V5"></path><path d="M8 17v-3"></path></svg>
            </div>
            <h2 class="text-xl font-semibold text-on-surface-strong mb-2">
                {{ __('No orders yet.') }}
            </h2>
            <p class="text-on-surface mb-6">
                {{ __('Discover cooks and place your first order!') }}
            </p>
            <a
                href="{{ url('/') }}"
                class="inline-flex items-center gap-2 h-10 px-6 rounded-lg bg-primary hover:bg-primary-hover text-on-primary font-semibold text-sm transition-colors"
                x-navigate
            >
                {{-- Search icon (Lucide, sm=16) --}}
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                {{ __('Discover Cooks') }}
            </a>
        </div>

    @else
        {{-- Summary Cards: 3-column grid (stacks on mobile) --}}
        {{-- BR-413: Amounts in XAF format --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            {{-- Total Spent --}}
            <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-2xl p-5 shadow-card">
                <div class="flex items-start justify-between mb-3">
                    <span class="w-10 h-10 rounded-full bg-primary-subtle dark:bg-primary-subtle flex items-center justify-center shrink-0">
                        {{-- TrendingUp icon (Lucide, md=20) --}}
                        <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline><polyline points="16 7 22 7 22 13"></polyline></svg>
                    </span>
                </div>
                <p class="text-sm text-on-surface mb-1">{{ __('Total Spent') }}</p>
                <p class="text-2xl font-bold text-on-surface-strong font-mono tracking-tight">
                    {{ \App\Services\ClientSpendingStatsService::formatXAF($totalSpent) }}
                </p>
            </div>

            {{-- This Month --}}
            <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-2xl p-5 shadow-card">
                <div class="flex items-start justify-between mb-3">
                    <span class="w-10 h-10 rounded-full bg-secondary-subtle dark:bg-secondary-subtle flex items-center justify-center shrink-0">
                        {{-- Calendar icon (Lucide, md=20) --}}
                        <svg class="w-5 h-5 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"></rect><line x1="16" x2="16" y1="2" y2="6"></line><line x1="8" x2="8" y1="2" y2="6"></line><line x1="3" x2="21" y1="10" y2="10"></line></svg>
                    </span>
                </div>
                <p class="text-sm text-on-surface mb-1">{{ __('This Month') }}</p>
                <p class="text-2xl font-bold text-on-surface-strong font-mono tracking-tight">
                    {{ \App\Services\ClientSpendingStatsService::formatXAF($thisMonthSpent) }}
                </p>
            </div>

            {{-- Total Orders --}}
            <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-2xl p-5 shadow-card">
                <div class="flex items-start justify-between mb-3">
                    <span class="w-10 h-10 rounded-full bg-success-subtle dark:bg-success-subtle flex items-center justify-center shrink-0">
                        {{-- ShoppingBag icon (Lucide, md=20) --}}
                        <svg class="w-5 h-5 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"></path><path d="M3 6h18"></path><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                    </span>
                </div>
                <p class="text-sm text-on-surface mb-1">{{ __('Total Orders') }}</p>
                <p class="text-2xl font-bold text-on-surface-strong font-mono tracking-tight">
                    {{ number_format($totalOrders) }}
                </p>
            </div>
        </div>

        {{-- Most Ordered From (Top Cooks) --}}
        {{-- BR-411: Top 5 cooks by order count --}}
        <div class="mb-8">
            <h2 class="text-lg font-semibold text-on-surface-strong mb-4">
                {{ __('Your Most Ordered From') }}
            </h2>

            @if($topCooks->isEmpty())
                <p class="text-sm text-on-surface">{{ __('No cook data available.') }}</p>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach($topCooks as $cookData)
                        @php
                            $isActive = $cookData['is_active'];
                        @endphp

                        {{-- BR-414: Cook card links to tenant landing page --}}
                        @if($isActive)
                            <a
                                href="{{ $cookData['url'] }}"
                                class="flex items-center gap-3 bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-4 shadow-card hover:shadow-md hover:border-primary/30 dark:hover:border-primary/30 transition-all duration-200"
                                x-navigate-skip
                            >
                        @else
                            <div class="flex items-center gap-3 bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-4 shadow-card opacity-70">
                        @endif
                                {{-- Cook Avatar --}}
                                <div class="w-10 h-10 rounded-full bg-primary-subtle dark:bg-primary-subtle flex items-center justify-center text-primary font-bold text-sm shrink-0">
                                    {{ mb_strtoupper(mb_substr($cookData['name'], 0, 1)) }}
                                </div>

                                {{-- Cook Info --}}
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-on-surface-strong truncate">
                                        {{ $cookData['name'] }}
                                    </p>
                                    <p class="text-xs text-on-surface">
                                        {{ trans_choice(':count order|:count orders', $cookData['order_count'], ['count' => $cookData['order_count']]) }}
                                    </p>
                                </div>

                                {{-- Status / Arrow --}}
                                @if(!$isActive)
                                    <span class="shrink-0 text-xs font-medium text-warning bg-warning-subtle dark:bg-warning-subtle px-2 py-0.5 rounded-full">
                                        {{ __('Unavailable') }}
                                    </span>
                                @else
                                    {{-- ChevronRight icon (Lucide, sm=16) --}}
                                    <svg class="w-4 h-4 text-on-surface/40 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                                @endif

                        @if($isActive)
                            </a>
                        @else
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Most Ordered Meals --}}
        {{-- BR-412: Top 5 meals by frequency --}}
        <div>
            <h2 class="text-lg font-semibold text-on-surface-strong mb-4">
                {{ __('Your Most Ordered Meals') }}
            </h2>

            @if($topMeals->isEmpty())
                <p class="text-sm text-on-surface">{{ __('No meal data available.') }}</p>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach($topMeals as $mealData)
                        {{-- Edge case: meal deleted since ordering --}}
                        @if(!$mealData['meal_exists'])
                            <div class="flex items-center gap-3 bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-4 shadow-card opacity-60">
                                {{-- Meal icon placeholder --}}
                                <div class="w-10 h-10 rounded-lg bg-surface-alt dark:bg-surface-alt flex items-center justify-center shrink-0">
                                    {{-- Package icon (Lucide, md=20) --}}
                                    <svg class="w-5 h-5 text-on-surface/40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"></path><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path><path d="m3.3 7 8.7 5 8.7-5"></path><path d="M12 22V12"></path></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-on-surface truncate italic">
                                        {{ __('Meal no longer available') }}
                                    </p>
                                    <p class="text-xs text-on-surface">
                                        {{ trans_choice(':count order|:count orders', $mealData['order_count'], ['count' => $mealData['order_count']]) }}
                                    </p>
                                </div>
                            </div>

                        {{-- BR-415: Active meal links to meal detail on cook's tenant domain --}}
                        @else
                            <a
                                href="{{ $mealData['meal_url'] }}"
                                class="flex items-center gap-3 bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-4 shadow-card hover:shadow-md hover:border-primary/30 dark:hover:border-primary/30 transition-all duration-200"
                                x-navigate-skip
                            >
                                {{-- Meal icon placeholder --}}
                                <div class="w-10 h-10 rounded-lg bg-secondary-subtle dark:bg-secondary-subtle flex items-center justify-center shrink-0">
                                    {{-- UtensilsCrossed icon (Lucide, md=20) --}}
                                    <svg class="w-5 h-5 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 2-2.3 2.3a3 3 0 0 0 0 4.2l1.8 1.8a3 3 0 0 0 4.2 0L22 8"></path><path d="M15 15 3.3 3.3a4.2 4.2 0 0 0 0 6l7.3 7.3c.9.9 2.5.9 3.4 0l1-1c.9-.9.9-2.5 0-3.4z"></path><path d="m7 21 3-3"></path></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-on-surface-strong truncate">
                                        {{ $mealData['meal_name'] }}
                                    </p>
                                    <p class="text-xs text-on-surface">
                                        {{ trans_choice(':count order|:count orders', $mealData['order_count'], ['count' => $mealData['order_count']]) }}
                                    </p>
                                </div>
                                {{-- ChevronRight icon (Lucide, sm=16) --}}
                                <svg class="w-4 h-4 text-on-surface/40 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

    @endif
</div>
@endsection
