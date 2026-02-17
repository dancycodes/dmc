{{--
    Discovery Page Layout (F-066)
    -----------------------------
    Main domain discovery page showing cook card grid with search, filters, and sort.
    BR-066: Main domain only
    BR-067: Active tenants with cook assigned
    BR-068: No auth required
    BR-069: 12 cards per page
    BR-070: Responsive grid (1/2/3 columns)
    BR-071: All text localized
    BR-072: Light/dark mode
    BR-073: Gale fragment updates
--}}
@extends('layouts.main-public')

@section('title', __('Discover Cooks'))

@section('content')
<div x-data="{
    search: '{{ $search }}',
    sort: '{{ $sort }}',
    direction: '{{ $direction }}',
    filterOpen: false,
    showScrollTop: false
}" x-init="
    window.addEventListener('scroll', () => {
        showScrollTop = window.scrollY > 400;
    });
">
    {{-- Hero Header with Search --}}
    <section class="bg-primary-subtle dark:bg-primary-subtle border-b border-outline dark:border-outline">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14 lg:py-16">
            <div class="text-center max-w-3xl mx-auto">
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-display font-bold text-on-surface-strong">
                    {{ __('Find homemade food near you') }}
                </h1>
                <p class="mt-3 text-base sm:text-lg text-on-surface max-w-xl mx-auto">
                    {{ __('Discover talented cooks in your area and order delicious home-cooked meals.') }}
                </p>

                {{-- Search Bar --}}
                <div class="mt-6 sm:mt-8 max-w-xl mx-auto" x-data x-navigate>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            {{-- Search icon (Lucide) --}}
                            <svg class="w-5 h-5 text-on-surface" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                        </div>
                        <input
                            type="text"
                            x-model="search"
                            @input.debounce.400ms="$navigate('/?search=' + encodeURIComponent(search) + '&sort=' + sort + '&direction=' + direction, { key: 'discovery', replace: true })"
                            placeholder="{{ __('Search cooks by name...') }}"
                            class="w-full h-12 pl-11 pr-10 rounded-xl bg-surface dark:bg-surface text-on-surface-strong placeholder:text-on-surface/50 border border-outline dark:border-outline focus:ring-2 focus:ring-primary focus:border-primary transition-all duration-200 text-base shadow-card"
                            aria-label="{{ __('Search cooks') }}"
                        >
                        <button
                            x-show="search.length > 0"
                            x-cloak
                            @click="search = ''; $navigate('/?sort=' + sort + '&direction=' + direction, { key: 'discovery', replace: true })"
                            class="absolute inset-y-0 right-0 pr-4 flex items-center text-on-surface hover:text-on-surface-strong transition-colors"
                            aria-label="{{ __('Clear search') }}"
                        >
                            {{-- X icon (Lucide) --}}
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Controls Bar: Result count, Sort, Mobile Filter Toggle --}}
    <section class="bg-surface dark:bg-surface border-b border-outline dark:border-outline">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 sm:py-4">
            <div class="flex items-center justify-between gap-3">
                {{-- Result Count --}}
                <p class="text-sm text-on-surface">
                    @if($cooks->total() > 0)
                        {{ trans_choice(':count cook found|:count cooks found', $cooks->total(), ['count' => $cooks->total()]) }}
                    @else
                        {{ __('No cooks found') }}
                    @endif
                </p>

                <div class="flex items-center gap-2">
                    {{-- Sort Dropdown --}}
                    <div x-data="{ sortOpen: false }" class="relative" x-navigate>
                        <button
                            @click="sortOpen = !sortOpen"
                            class="inline-flex items-center gap-2 h-9 px-3 rounded-lg text-sm font-medium border border-outline dark:border-outline text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors duration-200"
                            :aria-expanded="sortOpen"
                        >
                            {{-- ArrowUpDown icon (Lucide) --}}
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 16-4 4-4-4"></path><path d="M17 20V4"></path><path d="m3 8 4-4 4 4"></path><path d="M7 4v16"></path></svg>
                            <span class="hidden sm:inline">{{ __('Sort') }}</span>
                        </button>
                        <div
                            x-show="sortOpen"
                            x-cloak
                            @click.away="sortOpen = false"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute right-0 mt-2 w-48 bg-surface dark:bg-surface border border-outline dark:border-outline rounded-lg shadow-dropdown z-20"
                        >
                            <div class="py-1">
                                <button
                                    @click="sort = 'newest'; direction = 'desc'; sortOpen = false; $navigate('/?search=' + encodeURIComponent(search) + '&sort=newest&direction=desc', { key: 'discovery', replace: true })"
                                    class="w-full text-left px-4 py-2 text-sm hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors"
                                    :class="sort === 'newest' ? 'text-primary font-medium' : 'text-on-surface'"
                                >
                                    {{ __('Newest first') }}
                                </button>
                                <button
                                    @click="sort = 'name'; direction = 'asc'; sortOpen = false; $navigate('/?search=' + encodeURIComponent(search) + '&sort=name&direction=asc', { key: 'discovery', replace: true })"
                                    class="w-full text-left px-4 py-2 text-sm hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors"
                                    :class="sort === 'name' ? 'text-primary font-medium' : 'text-on-surface'"
                                >
                                    {{ __('Name A-Z') }}
                                </button>
                                <button
                                    @click="sort = 'name'; direction = 'desc'; sortOpen = false; $navigate('/?search=' + encodeURIComponent(search) + '&sort=name&direction=desc', { key: 'discovery', replace: true })"
                                    class="w-full text-left px-4 py-2 text-sm hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors"
                                    :class="sort === 'name' && direction === 'desc' ? 'text-primary font-medium' : 'text-on-surface'"
                                >
                                    {{ __('Name Z-A') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Mobile Filter Button (placeholder for F-069) --}}
                    <button
                        @click="filterOpen = !filterOpen"
                        class="lg:hidden inline-flex items-center gap-2 h-9 px-3 rounded-lg text-sm font-medium border border-outline dark:border-outline text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors duration-200"
                        aria-label="{{ __('Filters') }}"
                    >
                        {{-- SlidersHorizontal icon (Lucide) --}}
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="21" x2="14" y1="4" y2="4"></line><line x1="10" x2="3" y1="4" y2="4"></line><line x1="21" x2="12" y1="12" y2="12"></line><line x1="8" x2="3" y1="12" y2="12"></line><line x1="21" x2="16" y1="20" y2="20"></line><line x1="12" x2="3" y1="20" y2="20"></line><line x1="14" x2="14" y1="2" y2="6"></line><line x1="8" x2="8" y1="10" y2="14"></line><line x1="16" x2="16" y1="18" y2="22"></line></svg>
                        <span>{{ __('Filters') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </section>

    {{-- Mobile Filter Bottom Sheet (placeholder for F-069) --}}
    <div
        x-show="filterOpen"
        x-cloak
        class="fixed inset-0 z-50 lg:hidden"
        @keydown.escape.window="filterOpen = false"
    >
        {{-- Backdrop --}}
        <div
            x-show="filterOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute inset-0 bg-black/40"
            @click="filterOpen = false"
        ></div>
        {{-- Bottom Sheet --}}
        <div
            x-show="filterOpen"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="translate-y-full"
            class="absolute bottom-0 inset-x-0 bg-surface dark:bg-surface rounded-t-2xl border-t border-outline dark:border-outline shadow-dropdown max-h-[70vh] overflow-y-auto"
        >
            <div class="px-4 py-4">
                {{-- Handle bar --}}
                <div class="w-10 h-1 bg-outline dark:bg-outline rounded-full mx-auto mb-4"></div>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-on-surface-strong">{{ __('Filters') }}</h2>
                    <button @click="filterOpen = false" class="text-on-surface hover:text-on-surface-strong transition-colors" aria-label="{{ __('Close filters') }}">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                    </button>
                </div>
                {{-- Filter content will be added by F-069 --}}
                <p class="text-sm text-on-surface py-8 text-center">{{ __('Filters coming soon') }}</p>
            </div>
        </div>
    </div>

    {{-- Main Content Area --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
        <div class="flex gap-6 lg:gap-8">
            {{-- Desktop Filter Sidebar (placeholder for F-069) --}}
            <aside class="hidden lg:block w-64 shrink-0">
                <div class="sticky top-20 bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-4">
                    <h2 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wider mb-3">{{ __('Filters') }}</h2>
                    {{-- Filter content will be added by F-069 --}}
                    <p class="text-sm text-on-surface py-4 text-center">{{ __('Filters coming soon') }}</p>
                </div>
            </aside>

            {{-- Cook Card Grid (Fragment for Gale navigate updates) --}}
            @fragment('cook-grid')
            <div id="cook-grid" class="flex-1 min-w-0">
                @if($cooks->total() > 0)
                    {{-- Responsive Grid: 1 col mobile, 2 col tablet, 3 col desktop --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                        @foreach($cooks as $tenant)
                            @include('discovery._cook-card', ['tenant' => $tenant])
                        @endforeach
                    </div>

                    {{-- Pagination --}}
                    @if($cooks->hasPages())
                        <div class="mt-8" x-data x-navigate>
                            <nav class="flex items-center justify-center gap-1" aria-label="{{ __('Pagination') }}">
                                {{-- Previous --}}
                                @if($cooks->onFirstPage())
                                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-on-surface/40 cursor-not-allowed" aria-disabled="true">
                                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                                    </span>
                                @else
                                    <a href="{{ $cooks->previousPageUrl() }}" x-navigate.key.discovery class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors" aria-label="{{ __('Previous page') }}">
                                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                                    </a>
                                @endif

                                {{-- Page Numbers --}}
                                @foreach($cooks->getUrlRange(max(1, $cooks->currentPage() - 2), min($cooks->lastPage(), $cooks->currentPage() + 2)) as $page => $url)
                                    @if($page == $cooks->currentPage())
                                        <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-primary text-on-primary font-semibold text-sm" aria-current="page">
                                            {{ $page }}
                                        </span>
                                    @else
                                        <a href="{{ $url }}" x-navigate.key.discovery class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors text-sm font-medium">
                                            {{ $page }}
                                        </a>
                                    @endif
                                @endforeach

                                {{-- Next --}}
                                @if($cooks->hasMorePages())
                                    <a href="{{ $cooks->nextPageUrl() }}" x-navigate.key.discovery class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors" aria-label="{{ __('Next page') }}">
                                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                                    </a>
                                @else
                                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-on-surface/40 cursor-not-allowed" aria-disabled="true">
                                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                                    </span>
                                @endif
                            </nav>

                            {{-- Page info --}}
                            <p class="text-center text-sm text-on-surface mt-3">
                                {{ __('Page :current of :last', ['current' => $cooks->currentPage(), 'last' => $cooks->lastPage()]) }}
                            </p>
                        </div>
                    @endif
                @else
                    {{-- Empty State --}}
                    <div class="flex flex-col items-center justify-center py-16 sm:py-24 text-center">
                        {{-- ChefHat icon (Lucide) --}}
                        <div class="w-20 h-20 rounded-full bg-primary-subtle dark:bg-primary-subtle flex items-center justify-center mb-6">
                            <svg class="w-10 h-10 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21a1 1 0 0 0 1-1v-5.35c0-.457.316-.844.727-1.041a4 4 0 0 0-2.646-7.544 6 6 0 0 0-11.162 0A4 4 0 0 0 2.919 14.61c.41.196.727.583.727 1.04V20a1 1 0 0 0 1 1z"></path><path d="M6 17h12"></path></svg>
                        </div>
                        <h2 class="text-xl sm:text-2xl font-semibold text-on-surface-strong mb-2">
                            @if(!empty($search))
                                {{ __('No cooks match your search') }}
                            @else
                                {{ __('No cooks available yet') }}
                            @endif
                        </h2>
                        <p class="text-on-surface max-w-md text-base">
                            @if(!empty($search))
                                {{ __('Try adjusting your search terms or browse all available cooks.') }}
                            @else
                                {{ __('Check back soon! Talented cooks are joining DancyMeals every day.') }}
                            @endif
                        </p>
                        @if(!empty($search))
                            <div x-data x-navigate class="mt-6">
                                <a href="/" x-navigate.key.discovery class="h-10 px-6 rounded-lg font-medium bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200 inline-flex items-center gap-2 text-sm">
                                    {{ __('Clear search') }}
                                </a>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
            @endfragment
        </div>
    </section>

    {{-- Scroll to Top Button --}}
    <button
        x-show="showScrollTop"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-2"
        @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
        class="fixed bottom-6 right-6 z-40 w-12 h-12 rounded-full bg-primary hover:bg-primary-hover text-on-primary shadow-dropdown flex items-center justify-center transition-all duration-200"
        aria-label="{{ __('Scroll to top') }}"
    >
        {{-- ChevronUp icon (Lucide) --}}
        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"></path></svg>
    </button>
</div>
@endsection
