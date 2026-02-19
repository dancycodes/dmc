{{--
    F-136: Meal Filters
    BR-223: Tag filter multi-select with OR logic
    BR-224: Availability filter: "All" (default) and "Available Now"
    BR-226: Price range filter on starting price (min component price)
    BR-228: AND logic between filter types
    BR-229: Active filter count badge
    BR-230: "Clear filters" resets all filter selections
    BR-231: Filters applied via Gale without page reload
    BR-232: Combinable with search (F-135)
    BR-233: All filter labels localized via __()

    UI/UX: Desktop sidebar, mobile bottom sheet triggered by floating "Filter" button
    Edge case: Tag filter hidden when cook has no tags
    Edge case: Price range filter hidden when all meals have same price
--}}
@php
    $tags = $filterData['tags'] ?? [];
    $priceRange = $filterData['priceRange'] ?? ['min' => 0, 'max' => 0];
    $hasTags = $filterData['hasTags'] ?? false;
    $hasPriceRange = $filterData['hasPriceRange'] ?? false;
    $hasAnyFilter = $hasTags || $hasPriceRange;
@endphp

@if($hasAnyFilter)
<div
    x-data="{
        selectedTags: [],
        availability: 'all',
        priceMin: {{ $priceRange['min'] }},
        priceMax: {{ $priceRange['max'] }},
        priceRangeMin: {{ $priceRange['min'] }},
        priceRangeMax: {{ $priceRange['max'] }},
        mobileOpen: false,

        get activeFilterCount() {
            let count = this.selectedTags.length;
            if (this.availability !== 'all') count++;
            if (this.priceMin > this.priceRangeMin || this.priceMax < this.priceRangeMax) count++;
            return count;
        },

        toggleTag(tagId) {
            const idx = this.selectedTags.indexOf(tagId);
            if (idx === -1) {
                this.selectedTags.push(tagId);
            } else {
                this.selectedTags.splice(idx, 1);
            }
            this.applyFilters();
        },

        isTagSelected(tagId) {
            return this.selectedTags.includes(tagId);
        },

        setAvailability(value) {
            this.availability = value;
            this.applyFilters();
        },

        applyPriceRange() {
            /* Ensure min does not exceed max */
            if (this.priceMin > this.priceMax) {
                this.priceMin = this.priceMax;
            }
            this.applyFilters();
        },

        clearFilters() {
            this.selectedTags = [];
            this.availability = 'all';
            this.priceMin = this.priceRangeMin;
            this.priceMax = this.priceRangeMax;
            this.applyFilters();
        },

        applyFilters() {
            /* Build URL from Alpine state + search query from sibling component */
            const searchInput = document.querySelector('[data-meal-search-input]');
            const searchQuery = searchInput ? searchInput.value : '';

            let params = new URLSearchParams();
            if (searchQuery && searchQuery.length >= 2) {
                params.set('q', searchQuery);
            }
            if (this.selectedTags.length > 0) {
                params.set('tags', this.selectedTags.join(','));
            }
            if (this.availability !== 'all') {
                params.set('availability', this.availability);
            }
            if (this.priceMin > this.priceRangeMin) {
                params.set('price_min', String(this.priceMin));
            }
            if (this.priceMax < this.priceRangeMax) {
                params.set('price_max', String(this.priceMax));
            }

            const url = '/meals/search' + (params.toString() ? '?' + params.toString() : '');
            $navigate(url, { key: 'meal-search', merge: false, replace: true });
        }
    }"
    x-on:apply-filters.window="applyFilters()"
    class="relative"
>
    {{-- ============================= --}}
    {{-- MOBILE: Floating Filter Button --}}
    {{-- ============================= --}}
    <button
        x-on:click="mobileOpen = true"
        class="lg:hidden fixed bottom-20 right-4 z-30 flex items-center gap-2 h-12 px-5 bg-primary hover:bg-primary-hover text-on-primary font-semibold rounded-full shadow-lg transition-all duration-200 cursor-pointer"
        aria-label="{{ __('Open filters') }}"
    >
        {{-- Filter icon (Lucide: SlidersHorizontal) --}}
        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="21" x2="14" y1="4" y2="4"></line><line x1="10" x2="3" y1="4" y2="4"></line><line x1="21" x2="12" y1="12" y2="12"></line><line x1="8" x2="3" y1="12" y2="12"></line><line x1="21" x2="16" y1="20" y2="20"></line><line x1="12" x2="3" y1="20" y2="20"></line><line x1="14" x2="14" y1="2" y2="6"></line><line x1="8" x2="8" y1="10" y2="14"></line><line x1="16" x2="16" y1="18" y2="22"></line></svg>
        {{ __('Filters') }}
        {{-- BR-229: Filter count badge --}}
        <span
            x-show="activeFilterCount > 0"
            x-text="activeFilterCount"
            x-cloak
            class="flex items-center justify-center w-5 h-5 rounded-full bg-on-primary text-primary text-xs font-bold"
        ></span>
    </button>

    {{-- ============================= --}}
    {{-- MOBILE: Bottom Sheet Overlay   --}}
    {{-- ============================= --}}
    <div
        x-show="mobileOpen"
        x-cloak
        class="lg:hidden fixed inset-0 z-50"
    >
        {{-- Backdrop --}}
        <div
            x-show="mobileOpen"
            x-transition:enter="transition-opacity ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:click="mobileOpen = false"
            class="absolute inset-0 bg-black/50"
        ></div>

        {{-- Bottom Sheet Panel --}}
        <div
            x-show="mobileOpen"
            x-transition:enter="transition-transform ease-out duration-300"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="transition-transform ease-in duration-200"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="translate-y-full"
            class="absolute bottom-0 inset-x-0 bg-surface dark:bg-surface rounded-t-2xl shadow-lg max-h-[80vh] overflow-y-auto"
        >
            {{-- Sheet header --}}
            <div class="sticky top-0 bg-surface dark:bg-surface border-b border-outline dark:border-outline px-4 py-3 flex items-center justify-between rounded-t-2xl z-10">
                <div class="flex items-center gap-2">
                    <h3 class="text-lg font-semibold text-on-surface-strong">{{ __('Filters') }}</h3>
                    <span
                        x-show="activeFilterCount > 0"
                        x-text="activeFilterCount"
                        x-cloak
                        class="flex items-center justify-center w-5 h-5 rounded-full bg-primary text-on-primary text-xs font-bold"
                    ></span>
                </div>
                <button
                    x-on:click="mobileOpen = false"
                    class="p-2 text-on-surface/60 hover:text-on-surface-strong transition-colors cursor-pointer"
                    aria-label="{{ __('Close filters') }}"
                >
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                </button>
            </div>

            {{-- Sheet content --}}
            <div class="p-4 space-y-6">
                @include('tenant._meal-filters-content', [
                    'tags' => $tags,
                    'hasTags' => $hasTags,
                    'hasPriceRange' => $hasPriceRange,
                    'priceRange' => $priceRange,
                ])
            </div>
        </div>
    </div>

    {{-- ============================= --}}
    {{-- DESKTOP: Sidebar Filter Panel  --}}
    {{-- ============================= --}}
    <div class="hidden lg:block">
        <div class="bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-lg p-5 shadow-card">
            {{-- Header --}}
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-on-surface-strong" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="21" x2="14" y1="4" y2="4"></line><line x1="10" x2="3" y1="4" y2="4"></line><line x1="21" x2="12" y1="12" y2="12"></line><line x1="8" x2="3" y1="12" y2="12"></line><line x1="21" x2="16" y1="20" y2="20"></line><line x1="12" x2="3" y1="20" y2="20"></line><line x1="14" x2="14" y1="2" y2="6"></line><line x1="8" x2="8" y1="10" y2="14"></line><line x1="16" x2="16" y1="18" y2="22"></line></svg>
                    <h3 class="text-base font-semibold text-on-surface-strong">{{ __('Filters') }}</h3>
                    {{-- BR-229: Desktop filter count badge --}}
                    <span
                        x-show="activeFilterCount > 0"
                        x-text="activeFilterCount"
                        x-cloak
                        class="flex items-center justify-center w-5 h-5 rounded-full bg-primary text-on-primary text-xs font-bold"
                    ></span>
                </div>
            </div>

            <div class="space-y-5">
                @include('tenant._meal-filters-content', [
                    'tags' => $tags,
                    'hasTags' => $hasTags,
                    'hasPriceRange' => $hasPriceRange,
                    'priceRange' => $priceRange,
                ])
            </div>
        </div>
    </div>
</div>
@endif
