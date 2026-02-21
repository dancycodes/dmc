{{--
    Cook Dashboard Home
    -------------------
    F-077: Cook Dashboard Home
    Displays at-a-glance overview of the cook's business.

    Features:
    - 4 stat cards: Today's Orders, Week Revenue, Active Meals, Pending Orders (BR-165-168)
    - Recent orders list (BR-169)
    - Recent notifications (BR-171)
    - Real-time updates via Gale polling (BR-170)
    - XAF formatting (BR-172)
    - All text localized (BR-173)
    - Empty states with helpful prompts for new cooks
    - Forward-compatible with orders/notifications tables
--}}
@extends('layouts.cook-dashboard')

@section('title', __('Cook Dashboard'))
@section('page-title', __('Dashboard'))

@section('content')
<div
    x-data="{
        async refreshStats() {
            await $action('{{ route('cook.dashboard.refresh') }}');
        }
    }"
    x-interval.30s.visible="refreshStats()"
    class="space-y-6"
>
    {{-- Stat Cards Grid: 2x2 on tablet, 3-col on desktop for 5 cards, stacked on mobile --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {{-- Today's Orders Card --}}
        <div
            x-data="{
                total: @js(array_sum($todayOrders)),
                pending: @js($todayOrders['pending']),
                confirmed: @js($todayOrders['confirmed']),
                preparing: @js($todayOrders['preparing']),
                ready: @js($todayOrders['ready'])
            }"
            x-component="stat-today-orders"
            x-navigate
            class="bg-surface dark:bg-surface rounded-xl shadow-card p-5 transition-shadow duration-200 hover:shadow-md"
        >
            <a href="{{ url('/dashboard/orders') }}" class="block">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-full bg-info-subtle dark:bg-info-subtle flex items-center justify-center shrink-0">
                        {{-- Lucide: clipboard-list (md=20) --}}
                        <svg class="w-5 h-5 text-info dark:text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"></rect><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><path d="M12 11h4"></path><path d="M12 16h4"></path><path d="M8 11h.01"></path><path d="M8 16h.01"></path></svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-on-surface dark:text-on-surface uppercase tracking-wide">{{ __("Today's Orders") }}</p>
                        <p class="text-2xl font-bold text-on-surface-strong dark:text-on-surface-strong" x-text="total">{{ array_sum($todayOrders) }}</p>
                    </div>
                </div>
                {{-- Status breakdown --}}
                <div class="flex flex-wrap items-center gap-2 mt-3">
                    <span class="inline-flex items-center gap-1 text-xs">
                        <span class="w-2 h-2 rounded-full bg-warning"></span>
                        <span class="text-on-surface dark:text-on-surface" x-text="pending">{{ $todayOrders['pending'] }}</span>
                        <span class="text-on-surface/60 dark:text-on-surface/60">{{ __('Pending') }}</span>
                    </span>
                    <span class="inline-flex items-center gap-1 text-xs">
                        <span class="w-2 h-2 rounded-full bg-info"></span>
                        <span class="text-on-surface dark:text-on-surface" x-text="confirmed">{{ $todayOrders['confirmed'] }}</span>
                        <span class="text-on-surface/60 dark:text-on-surface/60">{{ __('Confirmed') }}</span>
                    </span>
                    <span class="inline-flex items-center gap-1 text-xs">
                        <span class="w-2 h-2 rounded-full bg-secondary"></span>
                        <span class="text-on-surface dark:text-on-surface" x-text="preparing">{{ $todayOrders['preparing'] }}</span>
                        <span class="text-on-surface/60 dark:text-on-surface/60">{{ __('Preparing') }}</span>
                    </span>
                    <span class="inline-flex items-center gap-1 text-xs">
                        <span class="w-2 h-2 rounded-full bg-success"></span>
                        <span class="text-on-surface dark:text-on-surface" x-text="ready">{{ $todayOrders['ready'] }}</span>
                        <span class="text-on-surface/60 dark:text-on-surface/60">{{ __('Ready') }}</span>
                    </span>
                </div>
            </a>
        </div>

        {{-- Week Revenue Card --}}
        <div
            x-data="{
                value: @js($weekRevenue),
                formatted: @js(\App\Services\CookDashboardService::formatXAF($weekRevenue))
            }"
            x-component="stat-week-revenue"
            x-navigate
            class="bg-surface dark:bg-surface rounded-xl shadow-card p-5 transition-shadow duration-200 hover:shadow-md"
        >
            <a href="{{ url('/dashboard/wallet') }}" class="block">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-full bg-success-subtle dark:bg-success-subtle flex items-center justify-center shrink-0">
                        {{-- Lucide: banknote (md=20) --}}
                        <svg class="w-5 h-5 text-success dark:text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="12" x="2" y="6" rx="2"></rect><circle cx="12" cy="12" r="2"></circle><path d="M6 12h.01M18 12h.01"></path></svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-on-surface dark:text-on-surface uppercase tracking-wide">{{ __('This Week Revenue') }}</p>
                        <p class="text-2xl font-bold text-on-surface-strong dark:text-on-surface-strong" x-text="formatted">{{ \App\Services\CookDashboardService::formatXAF($weekRevenue) }}</p>
                    </div>
                </div>
                <div class="flex items-center mt-3">
                    <span class="text-xs text-on-surface/60 dark:text-on-surface/60">{{ __('Completed orders this week') }}</span>
                </div>
            </a>
        </div>

        {{-- Active Meals Card --}}
        <div
            x-data="{
                value: @js($activeMeals)
            }"
            x-component="stat-active-meals"
            x-navigate
            class="bg-surface dark:bg-surface rounded-xl shadow-card p-5 transition-shadow duration-200 hover:shadow-md"
        >
            <a href="{{ url('/dashboard/meals') }}" class="block">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-full bg-primary-subtle dark:bg-primary-subtle flex items-center justify-center shrink-0">
                        {{-- Lucide: utensils-crossed (md=20) --}}
                        <svg class="w-5 h-5 text-primary dark:text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 2-2.3 2.3a3 3 0 0 0 0 4.2l1.8 1.8a3 3 0 0 0 4.2 0L22 8"></path><path d="M15 15 3.3 3.3a4.2 4.2 0 0 0 0 6l7.3 7.3a4.2 4.2 0 0 0 6 0L15 15Zm0 0 7 7"></path><path d="m2.1 21.8 6.4-6.3"></path><path d="m19 5-7 7"></path></svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-on-surface dark:text-on-surface uppercase tracking-wide">{{ __('Active Meals') }}</p>
                        <p class="text-2xl font-bold text-on-surface-strong dark:text-on-surface-strong" x-text="value">{{ $activeMeals }}</p>
                    </div>
                </div>
                <div class="flex items-center mt-3">
                    <span class="text-xs text-on-surface/60 dark:text-on-surface/60">{{ __('Currently listed for customers') }}</span>
                </div>
            </a>
        </div>

        {{-- Pending Orders Card --}}
        <div
            x-data="{
                value: @js($pendingOrders)
            }"
            x-component="stat-pending-orders"
            x-navigate
            class="bg-surface dark:bg-surface rounded-xl shadow-card p-5 transition-shadow duration-200 hover:shadow-md"
        >
            <a href="{{ url('/dashboard/orders') }}" class="block">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-full bg-warning-subtle dark:bg-warning-subtle flex items-center justify-center shrink-0">
                        {{-- Lucide: clock (md=20) --}}
                        <svg class="w-5 h-5 text-warning dark:text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-on-surface dark:text-on-surface uppercase tracking-wide">{{ __('Pending Orders') }}</p>
                        <p class="text-2xl font-bold text-on-surface-strong dark:text-on-surface-strong" x-text="value">{{ $pendingOrders }}</p>
                    </div>
                </div>
                <div class="flex items-center mt-3">
                    <span class="text-xs text-on-surface/60 dark:text-on-surface/60">{{ __('Awaiting confirmation') }}</span>
                </div>
            </a>
        </div>

        {{-- F-179: Cook Overall Rating Card --}}
        {{-- BR-418: X.X/5 format. BR-419: Count includes all ratings. --}}
        {{-- BR-423: "No ratings yet" for zero. Scenario 5: Trend indicator. --}}
        <div
            x-data="{
                average: @js($ratingStats['average']),
                count: @js($ratingStats['count']),
                hasRating: @js($ratingStats['hasRating']),
                trend: @js($ratingStats['trend']),
                get formattedAverage() {
                    return this.hasRating ? Number(this.average).toFixed(1) : '—';
                },
                get trendIcon() {
                    return this.trend;
                }
            }"
            x-component="stat-cook-rating"
            class="bg-surface dark:bg-surface rounded-xl shadow-card p-5 transition-shadow duration-200 hover:shadow-md"
        >
            <div class="block">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-full bg-secondary-subtle dark:bg-secondary-subtle flex items-center justify-center shrink-0">
                        {{-- Lucide: star (md=20) --}}
                        <svg class="w-5 h-5 text-secondary dark:text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-on-surface dark:text-on-surface uppercase tracking-wide">{{ __('Your Rating') }}</p>
                        <div class="flex items-center gap-2">
                            <p class="text-2xl font-bold text-on-surface-strong dark:text-on-surface-strong" x-text="formattedAverage">
                                {{ $ratingStats['hasRating'] ? number_format($ratingStats['average'], 1) : '—' }}
                            </p>
                            <template x-if="hasRating">
                                <span class="text-sm text-on-surface/60 dark:text-on-surface/60">/5</span>
                            </template>
                            {{-- Trend indicator --}}
                            <template x-if="hasRating && trendIcon === 'up'">
                                <span class="inline-flex items-center gap-0.5 text-xs text-success dark:text-success" title="{{ __('Trending up vs. last 30 days') }}">
                                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"></path></svg>
                                </span>
                            </template>
                            <template x-if="hasRating && trendIcon === 'down'">
                                <span class="inline-flex items-center gap-0.5 text-xs text-danger dark:text-danger" title="{{ __('Trending down vs. last 30 days') }}">
                                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
                                </span>
                            </template>
                        </div>
                    </div>
                </div>
                <div class="flex items-center mt-3">
                    <template x-if="hasRating">
                        <span class="text-xs text-on-surface/60 dark:text-on-surface/60" x-text="count + ' {{ __('reviews') }}'">
                            {{ $ratingStats['count'] }} {{ __('reviews') }}
                        </span>
                    </template>
                    <template x-if="!hasRating">
                        <span class="text-xs text-on-surface/60 dark:text-on-surface/60">{{ __('No ratings yet') }}</span>
                    </template>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Orders & Notifications Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Recent Orders (2/3 width on desktop) --}}
        <div class="lg:col-span-2 bg-surface dark:bg-surface rounded-xl shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-outline dark:border-outline flex items-center justify-between">
                <h2 class="text-base font-semibold text-on-surface-strong dark:text-on-surface-strong">{{ __('Recent Orders') }}</h2>
                @if(count($recentOrders) > 0)
                    <a href="{{ url('/dashboard/orders') }}" class="text-sm text-primary dark:text-primary hover:underline" x-navigate>
                        {{ __('View all') }}
                    </a>
                @endif
            </div>

            @if(count($recentOrders) > 0)
                {{-- Desktop table --}}
                <div class="hidden md:block">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-outline dark:border-outline">
                                <th class="px-5 py-3 text-left text-xs font-medium text-on-surface/60 dark:text-on-surface/60 uppercase tracking-wider">{{ __('Order') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-medium text-on-surface/60 dark:text-on-surface/60 uppercase tracking-wider">{{ __('Customer') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-medium text-on-surface/60 dark:text-on-surface/60 uppercase tracking-wider">{{ __('Status') }}</th>
                                <th class="px-5 py-3 text-right text-xs font-medium text-on-surface/60 dark:text-on-surface/60 uppercase tracking-wider">{{ __('Amount') }}</th>
                                <th class="px-5 py-3 text-right text-xs font-medium text-on-surface/60 dark:text-on-surface/60 uppercase tracking-wider">{{ __('Time') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline dark:divide-outline">
                            @foreach($recentOrders as $order)
                                <tr class="hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors duration-150">
                                    <td class="px-5 py-3.5 text-sm font-medium text-on-surface-strong dark:text-on-surface-strong">
                                        #{{ $order->order_number ?? $order->id }}
                                    </td>
                                    <td class="px-5 py-3.5 text-sm text-on-surface dark:text-on-surface">
                                        {{ $order->customer_name ?? __('Guest') }}
                                    </td>
                                    <td class="px-5 py-3.5">
                                        @include('cook._order-status-badge', ['status' => $order->status])
                                    </td>
                                    <td class="px-5 py-3.5 text-sm text-on-surface-strong dark:text-on-surface-strong text-right font-mono">
                                        {{ \App\Services\CookDashboardService::formatXAF($order->total_amount ?? 0) }}
                                    </td>
                                    <td class="px-5 py-3.5 text-xs text-on-surface/60 dark:text-on-surface/60 text-right">
                                        {{ $order->time_ago }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile card layout --}}
                <div class="md:hidden divide-y divide-outline dark:divide-outline">
                    @foreach($recentOrders as $order)
                        <div class="px-5 py-4">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-on-surface-strong dark:text-on-surface-strong">
                                    #{{ $order->order_number ?? $order->id }}
                                </span>
                                @include('cook._order-status-badge', ['status' => $order->status])
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-on-surface dark:text-on-surface">
                                    {{ $order->customer_name ?? __('Guest') }}
                                </span>
                                <span class="text-sm font-mono text-on-surface-strong dark:text-on-surface-strong">
                                    {{ \App\Services\CookDashboardService::formatXAF($order->total_amount ?? 0) }}
                                </span>
                            </div>
                            <p class="text-xs text-on-surface/60 dark:text-on-surface/60 mt-1">{{ $order->time_ago }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                {{-- Empty state --}}
                <div class="px-5 py-12 text-center">
                    <span class="w-12 h-12 rounded-full bg-surface-alt dark:bg-surface-alt flex items-center justify-center mx-auto mb-3">
                        {{-- Lucide: package (lg=24) --}}
                        <svg class="w-6 h-6 text-on-surface/40 dark:text-on-surface/40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"></path><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path><path d="m3.3 7 8.7 5 8.7-5"></path><path d="M12 22V12"></path></svg>
                    </span>
                    <p class="text-sm font-medium text-on-surface-strong dark:text-on-surface-strong mb-1">{{ __('No orders yet') }}</p>
                    <p class="text-xs text-on-surface/60 dark:text-on-surface/60">{{ __('Share your page link to start receiving orders from customers.') }}</p>
                </div>
            @endif
        </div>

        {{-- Recent Notifications (1/3 width on desktop) --}}
        <div class="bg-surface dark:bg-surface rounded-xl shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-outline dark:border-outline">
                <h2 class="text-base font-semibold text-on-surface-strong dark:text-on-surface-strong">{{ __('Recent Notifications') }}</h2>
            </div>

            @if(count($recentNotifications) > 0)
                <div class="divide-y divide-outline dark:divide-outline">
                    @foreach($recentNotifications as $notification)
                        <a href="{{ $notification->url ?? '#' }}" class="block px-5 py-4 hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors duration-150">
                            <div class="flex items-start gap-3">
                                <span class="w-8 h-8 rounded-full bg-primary-subtle dark:bg-primary-subtle flex items-center justify-center shrink-0 mt-0.5">
                                    {{-- Lucide: bell (sm=16) --}}
                                    <svg class="w-4 h-4 text-primary dark:text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path></svg>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-on-surface dark:text-on-surface line-clamp-2">{{ $notification->message }}</p>
                                    <p class="text-xs text-on-surface/60 dark:text-on-surface/60 mt-1">{{ $notification->time_ago }}</p>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                {{-- Empty state --}}
                <div class="px-5 py-12 text-center">
                    <span class="w-12 h-12 rounded-full bg-surface-alt dark:bg-surface-alt flex items-center justify-center mx-auto mb-3">
                        {{-- Lucide: bell-off (lg=24) --}}
                        <svg class="w-6 h-6 text-on-surface/40 dark:text-on-surface/40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.7 3A6 6 0 0 1 18 8a21.3 21.3 0 0 0 .6 5"></path><path d="M17 17H3s3-2 3-9a4.67 4.67 0 0 1 .3-1.7"></path><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path><line x1="2" x2="22" y1="2" y2="22"></line></svg>
                    </span>
                    <p class="text-sm font-medium text-on-surface-strong dark:text-on-surface-strong mb-1">{{ __('No new notifications') }}</p>
                    <p class="text-xs text-on-surface/60 dark:text-on-surface/60">{{ __('You\'re all caught up!') }}</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Onboarding prompt for new cooks --}}
    @if(array_sum($todayOrders) === 0 && $weekRevenue === 0 && $activeMeals === 0 && count($recentOrders) === 0)
        <div class="bg-primary-subtle dark:bg-primary-subtle rounded-xl p-6 border border-primary/20 dark:border-primary/20">
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                <span class="w-12 h-12 rounded-full bg-primary/10 dark:bg-primary/10 flex items-center justify-center shrink-0">
                    {{-- Lucide: rocket (lg=24) --}}
                    <svg class="w-6 h-6 text-primary dark:text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"></path><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"></path><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"></path><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"></path></svg>
                </span>
                <div>
                    <h3 class="text-sm font-semibold text-on-surface-strong dark:text-on-surface-strong mb-1">{{ __('Get started with your business') }}</h3>
                    <p class="text-sm text-on-surface dark:text-on-surface">
                        {{ __('Create meals, set up your delivery areas, and share your page link to start receiving orders.') }}
                    </p>
                </div>
                <a
                    href="{{ url('/dashboard/meals') }}"
                    x-navigate
                    class="shrink-0 inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary text-on-primary text-sm font-medium hover:bg-primary-hover transition-colors duration-200"
                >
                    {{ __('Add Meals') }}
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                </a>
            </div>
        </div>
    @endif
</div>
@endsection
