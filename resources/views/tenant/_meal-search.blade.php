{{--
    F-135: Meal Search Bar
    F-136: Integration with Meal Filters
    BR-214: Searches meal names, descriptions, component names, tag names
    BR-215: Case-insensitive search
    BR-216: 300ms debounce to avoid excessive requests
    BR-217: Filters meals grid via Gale (no page reload)
    BR-218: Clear "X" button appears when text is entered
    BR-219: Clearing search restores full meals grid
    BR-221: Minimum 2 characters to trigger search
    BR-222: All text localized via __()
    BR-232: Combinable with filters (F-136)
--}}
@php
    $hasFilters = ($filterData['hasTags'] ?? false) || ($filterData['hasPriceRange'] ?? false);
    $currentSort = $currentSort ?? 'popular';
@endphp
<div
    x-data="{
        query: '{{ addslashes($searchQuery ?? '') }}',
        hasFilters: {{ $hasFilters ? 'true' : 'false' }},
        /* F-137: Track active sort so search preserves it in the URL */
        currentSort: '{{ $currentSort }}',
        triggerSearch() {
            if (this.hasFilters) {
                /* F-136: Delegate to filter component which builds the full URL */
                $dispatch('apply-filters');
            } else {
                /* F-135: No filters — search directly, preserving sort (F-137 BR-240) */
                let params = new URLSearchParams();
                if (this.query.length >= 2) {
                    params.set('q', this.query);
                }
                if (this.currentSort && this.currentSort !== 'popular') {
                    params.set('sort', this.currentSort);
                }
                const url = '/meals/search' + (params.toString() ? '?' + params.toString() : '');
                $navigate(url, { key: 'meal-search', merge: false, replace: true });
            }
        },
        clearSearch() {
            this.query = '';
            this.triggerSearch();
        }
    }"
    x-on:apply-sort.window="currentSort = $event.detail.sort; if (!hasFilters) { triggerSearch(); }"
    class="mb-8"
>
    <div class="relative max-w-xl mx-auto sm:max-w-2xl">
        {{-- Magnifying glass icon (left) --}}
        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
            <svg class="w-5 h-5 text-on-surface/40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
        </div>

        {{-- Search input --}}
        <input
            type="text"
            x-model="query"
            x-on:input.debounce.300ms="if (query.length >= 2 || query.length === 0) { triggerSearch() }"
            data-meal-search-input
            placeholder="{{ __('Search meals...') }}"
            class="w-full h-12 pl-12 pr-12 bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-lg text-on-surface-strong placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-all duration-200 font-sans text-base"
            maxlength="50"
            aria-label="{{ __('Search meals') }}"
        >

        {{-- Loading spinner (visible during search) --}}
        <div class="absolute inset-y-0 right-10 flex items-center" x-show="query.length >= 2 && $gale.loading" x-cloak>
            <svg class="w-5 h-5 text-primary animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>

        {{-- BR-218: Clear "X" button (appears when text is entered) --}}
        <button
            x-show="query.length > 0"
            x-on:click="clearSearch()"
            x-cloak
            type="button"
            class="absolute inset-y-0 right-0 pr-4 flex items-center text-on-surface/40 hover:text-on-surface-strong transition-colors duration-200 cursor-pointer"
            aria-label="{{ __('Clear search') }}"
        >
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
        </button>
    </div>
</div>
