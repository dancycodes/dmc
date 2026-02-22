{{--
    F-137: Meal Sort Options
    BR-234: Sort options: popular (default), price_asc, price_desc, newest, name_asc
    BR-235: "Most Popular" sorts by total order count descending
    BR-236: Price sort uses meal's starting price (min component price)
    BR-237: "Newest First" sorts by meal creation date descending
    BR-238: "A to Z" sorts alphabetically by meal name in current locale
    BR-239: Sort via Gale without page reload
    BR-240: Sort works in combination with active search and filters
    BR-241: Currently active sort option is visually indicated
    BR-242: All sort option labels localized via __()

    UI/UX: Dropdown select right-aligned above the meals grid
    Mobile: Full-width above the grid
    Integration: Dispatches 'apply-sort' event; filters component listens and builds URL
--}}
@php
    $currentSort = $currentSort ?? 'popular';
    $sortOptions = [
        'popular'    => __('Most Popular'),
        'price_asc'  => __('Price: Low to High'),
        'price_desc' => __('Price: High to Low'),
        'newest'     => __('Newest First'),
        'name_asc'   => __('A to Z'),
    ];
@endphp
<div
    x-data="{
        sort: '{{ $currentSort }}',

        sortLabel(value) {
            const labels = {
                popular:    @js(__('Most Popular')),
                price_asc:  @js(__('Price: Low to High')),
                price_desc: @js(__('Price: High to Low')),
                newest:     @js(__('Newest First')),
                name_asc:   @js(__('A to Z')),
            };
            return labels[value] || @js(__('Most Popular'));
        },

        applySort(value) {
            this.sort = value;
            $dispatch('apply-sort', { sort: value });
        }
    }"
    class="flex items-center justify-between gap-3 mb-4 sm:mb-5"
>
    {{-- Result count / context (visible on desktop) --}}
    <p class="hidden sm:block text-sm text-on-surface/60 shrink-0">
        @if(isset($meals) && $meals->total() > 0)
            {{ trans_choice(':count meal|:count meals', $meals->total(), ['count' => $meals->total()]) }}
        @endif
    </p>

    {{-- Sort dropdown --}}
    <div class="relative w-full sm:w-auto sm:min-w-[200px]">
        {{-- Trigger button —  shows current sort label --}}
        <div class="relative">
            <label for="meal-sort-select" class="sr-only">{{ __('Sort meals by') }}</label>
            <div class="pointer-events-none absolute inset-y-0 left-0 pl-3 flex items-center">
                {{-- Lucide: ArrowUpDown (sort icon) --}}
                <svg class="w-4 h-4 text-on-surface/50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m21 16-4 4-4-4"/>
                    <path d="M17 20V4"/>
                    <path d="m3 8 4-4 4 4"/>
                    <path d="M7 4v16"/>
                </svg>
            </div>
            <select
                id="meal-sort-select"
                x-model="sort"
                x-on:change="applySort($event.target.value)"
                class="w-full h-10 pl-9 pr-8 bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-lg text-sm text-on-surface-strong font-medium font-sans appearance-none focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-all duration-200 cursor-pointer"
                aria-label="{{ __('Sort meals by') }}"
            >
                @foreach($sortOptions as $value => $label)
                    <option value="{{ $value }}" @selected($currentSort === $value)>{{ $label }}</option>
                @endforeach
            </select>
            {{-- Chevron icon --}}
            <div class="pointer-events-none absolute inset-y-0 right-0 pr-2.5 flex items-center">
                <svg class="w-4 h-4 text-on-surface/50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m6 9 6 6 6-6"/>
                </svg>
            </div>
        </div>
    </div>
</div>
