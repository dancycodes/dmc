{{--
    Meal List View (Cook Dashboard)
    --------------------------------
    F-116: Full meal list view with search, filters, sort, pagination,
    quick toggles, and responsive table/card layout.

    BR-261: Tenant-scoped — only shows meals for the current tenant.
    BR-262: Soft-deleted meals excluded.
    BR-263: Search matches against both name_en and name_fr.
    BR-264: Status filter: All, Draft, Live.
    BR-265: Availability filter: All, Available, Unavailable.
    BR-266: Sort: Name A-Z, Name Z-A, Newest, Oldest, Most Ordered.
    BR-267: Quick toggles respect F-112 and F-113 business rules.
    BR-268: Only users with manage-meals permission.
    BR-269: Component count and order count per meal.
--}}
@extends('layouts.cook-dashboard')

@section('title', __('Meals'))
@section('page-title', __('Meals'))

@section('content')
<div
    class="max-w-6xl mx-auto"
    x-data="{
        search: '{{ addslashes($search) }}',
        status: '{{ $status }}',
        availability: '{{ $availability }}',
        sort: '{{ $sort }}',
        confirmDeleteId: null,
        confirmDeleteName: '',
        confirmDeleteOrders: 0,

        applyFilters() {
            let url = '{{ url('/dashboard/meals') }}?sort=' + this.sort;
            if (this.search) url += '&search=' + encodeURIComponent(this.search);
            if (this.status) url += '&status=' + this.status;
            if (this.availability) url += '&availability=' + this.availability;
            $navigate(url, { key: 'meal-list', replace: true });
        },
        clearFilters() {
            this.search = '';
            this.status = '';
            this.availability = '';
            this.sort = 'newest';
            $navigate('{{ url('/dashboard/meals') }}', { key: 'meal-list', replace: true });
        },
        confirmDelete(id, name, orders) {
            this.confirmDeleteId = id;
            this.confirmDeleteName = name;
            this.confirmDeleteOrders = orders;
        },
        cancelDelete() {
            this.confirmDeleteId = null;
            this.confirmDeleteName = '';
            this.confirmDeleteOrders = 0;
        },
        executeDelete() {
            if (this.confirmDeleteId) {
                $action('/dashboard/meals/' + this.confirmDeleteId, { method: 'DELETE' });
                this.cancelDelete();
            }
        }
    }"
>
    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm text-on-surface/60 mb-6" aria-label="{{ __('Breadcrumb') }}">
        <a href="{{ url('/dashboard') }}" class="hover:text-primary transition-colors duration-200">
            {{ __('Dashboard') }}
        </a>
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
        <span class="text-on-surface-strong font-medium">{{ __('Meals') }}</span>
    </nav>

    {{-- Page header with Add Meal button --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-display font-bold text-on-surface-strong">{{ __('Meals') }}</h2>
            <p class="mt-1 text-sm text-on-surface/70">{{ __('Manage your food menu.') }}</p>
        </div>
        <a
            href="{{ url('/dashboard/meals/create') }}"
            class="px-4 py-2.5 rounded-lg text-sm font-medium bg-primary text-on-primary hover:bg-primary-hover shadow-sm transition-colors duration-200 inline-flex items-center gap-2 self-start"
        >
            {{-- Lucide: plus (sm=16) --}}
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
            {{ __('Add Meal') }}
        </a>
    </div>

    {{-- Toast notifications --}}
    @if(session('success'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 5000)"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="mb-6 p-4 rounded-lg bg-success-subtle border border-success/20 flex items-center gap-3"
        >
            <svg class="w-5 h-5 text-success shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            <span class="text-sm text-on-surface">{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 7000)"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="mb-6 p-4 rounded-lg bg-danger-subtle border border-danger/20 flex items-center gap-3"
        >
            <svg class="w-5 h-5 text-danger shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            <span class="text-sm text-on-surface">{{ session('error') }}</span>
        </div>
    @endif

    @fragment('meal-list-content')
    <div id="meal-list-content">

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        {{-- Total --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-3 sm:p-4">
            <div class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-full bg-primary-subtle flex items-center justify-center shrink-0">
                    {{-- Lucide: utensils (sm=16) --}}
                    <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"></path><path d="M7 2v20"></path><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path></svg>
                </span>
                <div>
                    <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Total') }}</p>
                    <p class="text-xl font-bold text-on-surface-strong">{{ $totalCount }}</p>
                </div>
            </div>
        </div>

        {{-- Live --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-3 sm:p-4">
            <div class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-full bg-success-subtle flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                </span>
                <div>
                    <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Live') }}</p>
                    <p class="text-xl font-bold text-on-surface-strong">{{ $liveCount }}</p>
                </div>
            </div>
        </div>

        {{-- Draft --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-3 sm:p-4">
            <div class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-full bg-warning-subtle flex items-center justify-center shrink-0">
                    {{-- Lucide: file-edit (sm=16) --}}
                    <svg class="w-4 h-4 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.874a2 2 0 0 1 .506-.852z"></path></svg>
                </span>
                <div>
                    <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Draft') }}</p>
                    <p class="text-xl font-bold text-on-surface-strong">{{ $draftCount }}</p>
                </div>
            </div>
        </div>

        {{-- Unavailable --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-3 sm:p-4">
            <div class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-full bg-danger-subtle flex items-center justify-center shrink-0">
                    {{-- Lucide: eye-off (sm=16) --}}
                    <svg class="w-4 h-4 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"></path><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"></path><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"></path><path d="m2 2 20 20"></path></svg>
                </span>
                <div>
                    <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Unavailable') }}</p>
                    <p class="text-xl font-bold text-on-surface-strong">{{ $unavailableCount }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter / Search / Sort Bar --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-4 mb-6 shadow-card">
        <div class="flex flex-col sm:flex-row gap-3">
            {{-- Search --}}
            <div class="flex-1">
                <label for="meal-search" class="sr-only">{{ __('Search meals') }}</label>
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-on-surface/40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                    <input
                        id="meal-search"
                        type="text"
                        x-model="search"
                        @input.debounce.400ms="applyFilters()"
                        placeholder="{{ __('Search by name...') }}"
                        class="w-full pl-10 pr-3 py-2.5 bg-surface dark:bg-surface text-on-surface border border-outline dark:border-outline rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors duration-200"
                    >
                </div>
            </div>

            {{-- Status filter --}}
            <div class="w-full sm:w-36">
                <label for="meal-status" class="sr-only">{{ __('Status') }}</label>
                <select
                    id="meal-status"
                    x-model="status"
                    @change="applyFilters()"
                    class="w-full px-3 py-2.5 bg-surface dark:bg-surface text-on-surface border border-outline dark:border-outline rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors duration-200"
                >
                    <option value="">{{ __('All Status') }}</option>
                    <option value="draft">{{ __('Draft') }}</option>
                    <option value="live">{{ __('Live') }}</option>
                </select>
            </div>

            {{-- Availability filter --}}
            <div class="w-full sm:w-40">
                <label for="meal-availability" class="sr-only">{{ __('Availability') }}</label>
                <select
                    id="meal-availability"
                    x-model="availability"
                    @change="applyFilters()"
                    class="w-full px-3 py-2.5 bg-surface dark:bg-surface text-on-surface border border-outline dark:border-outline rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors duration-200"
                >
                    <option value="">{{ __('All Availability') }}</option>
                    <option value="available">{{ __('Available') }}</option>
                    <option value="unavailable">{{ __('Unavailable') }}</option>
                </select>
            </div>

            {{-- Sort --}}
            <div class="w-full sm:w-44">
                <label for="meal-sort" class="sr-only">{{ __('Sort') }}</label>
                <select
                    id="meal-sort"
                    x-model="sort"
                    @change="applyFilters()"
                    class="w-full px-3 py-2.5 bg-surface dark:bg-surface text-on-surface border border-outline dark:border-outline rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors duration-200"
                >
                    <option value="newest">{{ __('Newest First') }}</option>
                    <option value="oldest">{{ __('Oldest First') }}</option>
                    <option value="name_asc">{{ __('Name (A-Z)') }}</option>
                    <option value="name_desc">{{ __('Name (Z-A)') }}</option>
                    <option value="most_ordered">{{ __('Most Ordered') }}</option>
                </select>
            </div>
        </div>

        {{-- Active filter indicators + clear --}}
        @if($search || $status || $availability)
            <div class="flex items-center gap-2 mt-3 pt-3 border-t border-outline dark:border-outline">
                <span class="text-xs text-on-surface/60">{{ __('Filters:') }}</span>
                @if($search)
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-primary-subtle text-primary">
                        {{ __('Search') }}: "{{ $search }}"
                    </span>
                @endif
                @if($status)
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-primary-subtle text-primary">
                        {{ $status === 'draft' ? __('Draft') : __('Live') }}
                    </span>
                @endif
                @if($availability)
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-primary-subtle text-primary">
                        {{ $availability === 'available' ? __('Available') : __('Unavailable') }}
                    </span>
                @endif
                <button
                    @click="clearFilters()"
                    class="text-xs text-danger hover:text-danger/80 font-medium transition-colors duration-200"
                >
                    {{ __('Clear All') }}
                </button>
            </div>
        @endif
    </div>

    {{-- Meal list --}}
    @if($meals->isEmpty() && !$search && !$status && !$availability)
        {{-- Empty state — no meals at all --}}
        <div class="bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-xl shadow-card p-12 text-center">
            <div class="w-16 h-16 rounded-full bg-primary-subtle flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"></path><path d="M7 2v20"></path><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path></svg>
            </div>
            <h3 class="text-lg font-semibold text-on-surface-strong mb-2">{{ __('No meals yet') }}</h3>
            <p class="text-sm text-on-surface/70 mb-6">{{ __('Create your first meal to start building your menu.') }}</p>
            <a
                href="{{ url('/dashboard/meals/create') }}"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-medium bg-primary text-on-primary hover:bg-primary-hover shadow-sm transition-colors duration-200"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                {{ __('Add Meal') }}
            </a>
        </div>
    @elseif($meals->isEmpty())
        {{-- No results with active filters --}}
        <div class="bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-xl shadow-card p-12 text-center">
            <div class="w-16 h-16 rounded-full bg-warning-subtle flex items-center justify-center mx-auto mb-4">
                {{-- Lucide: search-x (lg=32) --}}
                <svg class="w-8 h-8 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m13.5 8.5-5 5"></path><path d="m8.5 8.5 5 5"></path><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
            </div>
            <h3 class="text-lg font-semibold text-on-surface-strong mb-2">{{ __('No meals match your filters') }}</h3>
            <p class="text-sm text-on-surface/70 mb-4">{{ __('Try adjusting your search or filter criteria.') }}</p>
            <button
                @click="clearFilters()"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-primary text-on-primary hover:bg-primary-hover transition-colors duration-200"
            >
                {{ __('Clear Filters') }}
            </button>
        </div>
    @else
        {{-- Desktop Table View --}}
        <div class="hidden md:block bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-card overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-outline dark:border-outline">
                        <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-on-surface/60 w-12"></th>
                        <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-on-surface/60">{{ __('Name') }}</th>
                        <th class="px-4 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-on-surface/60">{{ __('Status') }}</th>
                        <th class="px-4 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-on-surface/60">{{ __('Availability') }}</th>
                        <th class="px-4 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-on-surface/60">{{ __('Components') }}</th>
                        <th class="px-4 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-on-surface/60">{{ __('Orders') }}</th>
                        <th class="px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-on-surface/60">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline dark:divide-outline">
                    @foreach($meals as $meal)
                        @php
                            $firstImage = $meal->images->first();
                            $componentCount = $meal->components_count;
                            $orderCount = $meal->orders_count ?? 0;
                            $canDeleteInfo = app(\App\Services\MealService::class)->canDeleteMeal($meal);
                            $completedOrders = app(\App\Services\MealService::class)->getCompletedOrderCount($meal);
                        @endphp
                        <tr class="hover:bg-surface dark:hover:bg-surface transition-colors duration-150">
                            {{-- Thumbnail --}}
                            <td class="px-4 py-3">
                                @if($firstImage)
                                    <img
                                        src="{{ $firstImage->thumbnail_url }}"
                                        alt="{{ $meal->name }}"
                                        class="w-10 h-10 rounded-lg object-cover"
                                        loading="lazy"
                                    >
                                @else
                                    <div class="w-10 h-10 rounded-lg bg-surface dark:bg-surface flex items-center justify-center border border-outline dark:border-outline">
                                        <svg class="w-5 h-5 text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect><circle cx="9" cy="9" r="2"></circle><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path></svg>
                                    </div>
                                @endif
                            </td>

                            {{-- Name --}}
                            <td class="px-4 py-3">
                                <a
                                    href="{{ url('/dashboard/meals/' . $meal->id . '/edit') }}"
                                    class="text-sm font-medium text-on-surface-strong hover:text-primary transition-colors duration-200 truncate block max-w-[200px]"
                                    title="{{ $meal->name }}"
                                >
                                    {{ $meal->name }}
                                </a>
                                <p class="text-xs text-on-surface/50 mt-0.5">{{ $meal->created_at->diffForHumans() }}</p>
                            </td>

                            {{-- Status toggle --}}
                            <td class="px-4 py-3 text-center">
                                <button
                                    type="button"
                                    @click="$action('{{ url('/dashboard/meals/' . $meal->id . '/toggle-status') }}', { method: 'PATCH' })"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition-colors duration-200 cursor-pointer {{ $meal->status === 'live' ? 'bg-success-subtle text-success hover:bg-success/20' : 'bg-warning-subtle text-warning hover:bg-warning/20' }}"
                                    title="{{ $meal->status === 'live' ? __('Click to set as Draft') : __('Click to Go Live') }}"
                                >
                                    @if($meal->status === 'live')
                                        <span class="w-1.5 h-1.5 rounded-full bg-success"></span>
                                        {{ __('Live') }}
                                    @else
                                        <span class="w-1.5 h-1.5 rounded-full bg-warning"></span>
                                        {{ __('Draft') }}
                                    @endif
                                </button>
                            </td>

                            {{-- Availability toggle --}}
                            <td class="px-4 py-3 text-center">
                                <button
                                    type="button"
                                    @click="$action('{{ url('/dashboard/meals/' . $meal->id . '/toggle-availability') }}', { method: 'PATCH' })"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition-colors duration-200 cursor-pointer {{ $meal->is_available ? 'bg-success-subtle text-success hover:bg-success/20' : 'bg-danger-subtle text-danger hover:bg-danger/20' }}"
                                    title="{{ $meal->is_available ? __('Click to mark Unavailable') : __('Click to mark Available') }}"
                                >
                                    @if($meal->is_available)
                                        <span class="w-1.5 h-1.5 rounded-full bg-success"></span>
                                        {{ __('Available') }}
                                    @else
                                        <span class="w-1.5 h-1.5 rounded-full bg-danger"></span>
                                        {{ __('Unavailable') }}
                                    @endif
                                </button>
                            </td>

                            {{-- Components --}}
                            <td class="px-4 py-3 text-center">
                                @if($componentCount === 0)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-warning-subtle text-warning" title="{{ __('No components — cannot go live') }}">
                                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                                        0
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-primary-subtle text-primary">
                                        {{ $componentCount }}
                                    </span>
                                @endif
                            </td>

                            {{-- Orders --}}
                            <td class="px-4 py-3 text-center">
                                <span class="text-sm text-on-surface">{{ $orderCount }}</span>
                            </td>

                            {{-- Actions --}}
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    {{-- Edit --}}
                                    <a
                                        href="{{ url('/dashboard/meals/' . $meal->id . '/edit') }}"
                                        class="p-2 text-on-surface hover:text-primary hover:bg-primary-subtle rounded-lg transition-colors duration-200"
                                        title="{{ __('Edit') }}"
                                    >
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path><path d="m15 5 4 4"></path></svg>
                                    </a>
                                    {{-- Delete --}}
                                    @if($canDeleteInfo['can_delete'])
                                        <button
                                            type="button"
                                            @click="confirmDelete({{ $meal->id }}, {{ json_encode($meal->name) }}, {{ $completedOrders }})"
                                            class="p-2 text-on-surface hover:text-danger hover:bg-danger-subtle rounded-lg transition-colors duration-200"
                                            title="{{ __('Delete') }}"
                                        >
                                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                        </button>
                                    @else
                                        <span
                                            class="p-2 text-on-surface/20 cursor-not-allowed"
                                            title="{{ $canDeleteInfo['reason'] ?? __('Cannot delete this meal') }}"
                                        >
                                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile Card View --}}
        <div class="md:hidden space-y-3">
            @foreach($meals as $meal)
                @php
                    $firstImage = $meal->images->first();
                    $componentCount = $meal->components_count;
                    $orderCount = $meal->orders_count ?? 0;
                    $canDeleteInfo = app(\App\Services\MealService::class)->canDeleteMeal($meal);
                    $completedOrders = app(\App\Services\MealService::class)->getCompletedOrderCount($meal);
                @endphp
                <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-card p-4">
                    <div class="flex gap-3">
                        {{-- Thumbnail --}}
                        <div class="shrink-0">
                            @if($firstImage)
                                <img
                                    src="{{ $firstImage->thumbnail_url }}"
                                    alt="{{ $meal->name }}"
                                    class="w-14 h-14 rounded-lg object-cover"
                                    loading="lazy"
                                >
                            @else
                                <div class="w-14 h-14 rounded-lg bg-surface dark:bg-surface flex items-center justify-center border border-outline dark:border-outline">
                                    <svg class="w-6 h-6 text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect><circle cx="9" cy="9" r="2"></circle><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path></svg>
                                </div>
                            @endif
                        </div>

                        {{-- Info --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <a
                                    href="{{ url('/dashboard/meals/' . $meal->id . '/edit') }}"
                                    class="text-sm font-semibold text-on-surface-strong hover:text-primary transition-colors duration-200 truncate block"
                                >
                                    {{ $meal->name }}
                                </a>
                                <div class="flex items-center gap-1 shrink-0">
                                    {{-- Edit button --}}
                                    <a
                                        href="{{ url('/dashboard/meals/' . $meal->id . '/edit') }}"
                                        class="p-1.5 text-on-surface hover:text-primary hover:bg-primary-subtle rounded-lg transition-colors duration-200"
                                        title="{{ __('Edit') }}"
                                    >
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path><path d="m15 5 4 4"></path></svg>
                                    </a>
                                    {{-- Delete button --}}
                                    @if($canDeleteInfo['can_delete'])
                                        <button
                                            type="button"
                                            @click="confirmDelete({{ $meal->id }}, {{ json_encode($meal->name) }}, {{ $completedOrders }})"
                                            class="p-1.5 text-on-surface hover:text-danger hover:bg-danger-subtle rounded-lg transition-colors duration-200"
                                            title="{{ __('Delete') }}"
                                        >
                                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                        </button>
                                    @endif
                                </div>
                            </div>

                            {{-- Badges --}}
                            <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                                {{-- Status toggle --}}
                                <button
                                    type="button"
                                    @click="$action('{{ url('/dashboard/meals/' . $meal->id . '/toggle-status') }}', { method: 'PATCH' })"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium transition-colors duration-200 {{ $meal->status === 'live' ? 'bg-success-subtle text-success hover:bg-success/20' : 'bg-warning-subtle text-warning hover:bg-warning/20' }}"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full {{ $meal->status === 'live' ? 'bg-success' : 'bg-warning' }}"></span>
                                    {{ $meal->status === 'live' ? __('Live') : __('Draft') }}
                                </button>

                                {{-- Availability toggle --}}
                                <button
                                    type="button"
                                    @click="$action('{{ url('/dashboard/meals/' . $meal->id . '/toggle-availability') }}', { method: 'PATCH' })"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium transition-colors duration-200 {{ $meal->is_available ? 'bg-success-subtle text-success hover:bg-success/20' : 'bg-danger-subtle text-danger hover:bg-danger/20' }}"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full {{ $meal->is_available ? 'bg-success' : 'bg-danger' }}"></span>
                                    {{ $meal->is_available ? __('Available') : __('Unavailable') }}
                                </button>

                                {{-- Components --}}
                                @if($componentCount === 0)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-warning-subtle text-warning" title="{{ __('No components') }}">
                                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                                        {{ __('0 components') }}
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-primary-subtle text-primary">
                                        {{ trans_choice(':count component|:count components', $componentCount, ['count' => $componentCount]) }}
                                    </span>
                                @endif

                                {{-- Orders --}}
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-surface text-on-surface/60">
                                    {{ trans_choice(':count order|:count orders', $orderCount, ['count' => $orderCount]) }}
                                </span>
                            </div>

                            <p class="text-xs text-on-surface/50 mt-1.5">{{ $meal->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($meals->hasPages())
            <div class="mt-6" x-navigate>
                {{ $meals->links() }}
            </div>
        @endif

        {{-- Result count --}}
        <div class="mt-4 text-center">
            <p class="text-xs text-on-surface/60">
                {{ __('Showing :from to :to of :total meals', [
                    'from' => $meals->firstItem() ?? 0,
                    'to' => $meals->lastItem() ?? 0,
                    'total' => $meals->total(),
                ]) }}
            </p>
        </div>
    @endif

    </div>
    @endfragment

    {{-- Delete Confirmation Modal --}}
    <div
        x-show="confirmDeleteId !== null"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        :aria-label="'{{ __('Delete meal') }}'"
    >
        {{-- Backdrop --}}
        <div
            x-show="confirmDeleteId !== null"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="cancelDelete()"
            class="absolute inset-0 bg-black/50"
        ></div>

        {{-- Modal content --}}
        <div
            x-show="confirmDeleteId !== null"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative w-full max-w-md bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-xl shadow-lg p-6"
        >
            {{-- Warning icon --}}
            <div class="flex items-center justify-center w-12 h-12 rounded-full bg-danger-subtle mx-auto mb-4">
                <svg class="w-6 h-6 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            </div>

            <h3 class="text-lg font-semibold text-on-surface-strong text-center mb-2">
                {{ __('Delete Meal') }}
            </h3>

            <p class="text-sm text-on-surface/70 text-center mb-2">
                {{ __('Are you sure you want to delete') }}
                <span class="font-semibold text-on-surface-strong" x-text="confirmDeleteName"></span>?
            </p>

            <p class="text-sm text-on-surface/70 text-center mb-1">
                {{ __('This will remove it from your menu.') }}
            </p>

            {{-- Show completed order count if any --}}
            <template x-if="confirmDeleteOrders > 0">
                <p class="text-sm text-info text-center mb-4">
                    <span x-text="'{{ __('This meal has') }} ' + confirmDeleteOrders + ' {{ __('past orders. Order history will be preserved.') }}'"></span>
                </p>
            </template>

            <template x-if="confirmDeleteOrders === 0">
                <div class="mb-4"></div>
            </template>

            {{-- Action buttons --}}
            <div class="flex items-center justify-end gap-3">
                <button
                    type="button"
                    @click="cancelDelete()"
                    class="px-4 py-2 rounded-lg text-sm font-medium text-on-surface bg-surface dark:bg-surface border border-outline dark:border-outline hover:bg-surface-alt transition-colors duration-200"
                >
                    {{ __('Cancel') }}
                </button>
                <button
                    type="button"
                    @click="executeDelete()"
                    class="px-4 py-2 rounded-lg text-sm font-medium bg-danger text-on-danger hover:bg-danger/90 shadow-sm transition-colors duration-200 flex items-center gap-2"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                    {{ __('Delete') }}
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
