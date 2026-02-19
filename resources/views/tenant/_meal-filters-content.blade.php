{{--
    F-136: Meal Filters — Shared filter content
    Used by both desktop sidebar and mobile bottom sheet.
    Must be rendered inside the x-data scope from _meal-filters.blade.php.
--}}

{{-- ============================= --}}
{{-- TAG FILTER (BR-223)           --}}
{{-- Edge case: Hidden if no tags  --}}
{{-- ============================= --}}
@if($hasTags)
<div>
    <h4 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wider mb-3">
        {{ __('Tags') }}
    </h4>
    <div class="flex flex-wrap gap-2">
        @foreach($tags as $tag)
            <button
                x-on:click="toggleTag({{ $tag['id'] }})"
                type="button"
                class="inline-flex items-center h-8 px-3 rounded-full text-sm font-medium border transition-all duration-200 cursor-pointer"
                :class="isTagSelected({{ $tag['id'] }})
                    ? 'bg-primary text-on-primary border-primary'
                    : 'bg-surface dark:bg-surface text-on-surface border-outline dark:border-outline hover:border-primary hover:text-primary'"
                aria-pressed="false"
                :aria-pressed="isTagSelected({{ $tag['id'] }}) ? 'true' : 'false'"
            >
                {{ $tag['name'] }}
            </button>
        @endforeach
    </div>
</div>
@endif

{{-- ============================= --}}
{{-- AVAILABILITY FILTER (BR-224)  --}}
{{-- ============================= --}}
<div>
    <h4 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wider mb-3">
        {{ __('Availability') }}
    </h4>
    <div class="space-y-2">
        {{-- All option (default) --}}
        <label class="flex items-center gap-3 px-3 py-2.5 rounded-lg cursor-pointer transition-colors duration-200 hover:bg-surface dark:hover:bg-surface">
            <input
                type="radio"
                name="availability_filter"
                value="all"
                x-on:change="setAvailability('all')"
                :checked="availability === 'all'"
                class="w-4 h-4 text-primary border-outline focus:ring-primary"
            >
            <span class="text-sm text-on-surface" :class="availability === 'all' ? 'font-semibold text-on-surface-strong' : ''">
                {{ __('All Meals') }}
            </span>
        </label>

        {{-- Available Now option --}}
        <label class="flex items-center gap-3 px-3 py-2.5 rounded-lg cursor-pointer transition-colors duration-200 hover:bg-surface dark:hover:bg-surface">
            <input
                type="radio"
                name="availability_filter"
                value="available_now"
                x-on:change="setAvailability('available_now')"
                :checked="availability === 'available_now'"
                class="w-4 h-4 text-primary border-outline focus:ring-primary"
            >
            <span class="text-sm text-on-surface flex items-center gap-1.5" :class="availability === 'available_now' ? 'font-semibold text-on-surface-strong' : ''">
                {{-- Green dot indicator --}}
                <span class="w-2 h-2 rounded-full bg-success shrink-0"></span>
                {{ __('Available Now') }}
            </span>
        </label>
    </div>
</div>

{{-- ============================= --}}
{{-- PRICE RANGE FILTER (BR-226)   --}}
{{-- Edge case: Hidden if same     --}}
{{-- ============================= --}}
@if($hasPriceRange)
<div>
    <h4 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wider mb-3">
        {{ __('Price Range') }}
    </h4>

    {{-- Min/Max inputs --}}
    <div class="flex items-center gap-3">
        <div class="flex-1">
            <label class="block text-xs text-on-surface/60 mb-1">{{ __('Min') }}</label>
            <div class="relative">
                <input
                    type="number"
                    x-model.number="priceMin"
                    x-on:change="applyPriceRange()"
                    min="{{ $priceRange['min'] }}"
                    :max="priceMax"
                    step="100"
                    class="w-full h-9 pl-3 pr-12 bg-surface dark:bg-surface border border-outline dark:border-outline rounded-lg text-sm text-on-surface-strong focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                    aria-label="{{ __('Minimum price') }}"
                >
                <span class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-on-surface/40 pointer-events-none">XAF</span>
            </div>
        </div>
        <span class="text-on-surface/40 mt-5">—</span>
        <div class="flex-1">
            <label class="block text-xs text-on-surface/60 mb-1">{{ __('Max') }}</label>
            <div class="relative">
                <input
                    type="number"
                    x-model.number="priceMax"
                    x-on:change="applyPriceRange()"
                    :min="priceMin"
                    max="{{ $priceRange['max'] }}"
                    step="100"
                    class="w-full h-9 pl-3 pr-12 bg-surface dark:bg-surface border border-outline dark:border-outline rounded-lg text-sm text-on-surface-strong focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                    aria-label="{{ __('Maximum price') }}"
                >
                <span class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-on-surface/40 pointer-events-none">XAF</span>
            </div>
        </div>
    </div>

    {{-- Price range summary --}}
    <p class="text-xs text-on-surface/40 mt-2">
        {{ __('Range: :min - :max XAF', ['min' => number_format($priceRange['min']), 'max' => number_format($priceRange['max'])]) }}
    </p>
</div>
@endif

{{-- ============================= --}}
{{-- BR-230: CLEAR FILTERS         --}}
{{-- ============================= --}}
<div>
    <button
        x-show="activeFilterCount > 0"
        x-on:click="clearFilters()"
        x-cloak
        type="button"
        class="w-full h-10 flex items-center justify-center gap-2 text-sm font-medium text-danger hover:text-danger/80 border border-outline dark:border-outline rounded-lg hover:bg-danger-subtle transition-all duration-200 cursor-pointer"
    >
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
        {{ __('Clear filters') }}
    </button>
</div>
