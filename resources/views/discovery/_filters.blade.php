{{--
    Discovery Filters Partial (F-069)
    ----------------------------------
    Shared filter controls used in both desktop sidebar and mobile bottom sheet.
    BR-090: Filter categories: town, availability, tags, min_rating.
    BR-091: AND logic between filter categories.
    BR-092: OR logic within tag filter.
    BR-093: Active filter count badge.
    BR-094: "Clear all" resets all filters.
    BR-095: Filter changes update grid via Gale without page reload.
    BR-097: Towns populated from active cook delivery areas.
    BR-098/BR-099: Availability checks cook schedules.

    Expected variables from parent scope (Alpine x-data):
    - selectedTown, selectedAvailability, selectedTags, selectedMinRating
    - applyFilters(), clearFilters()
    - filterTowns (from PHP), filterTags (from PHP)
--}}

{{-- Town Filter (BR-090: single-select dropdown) --}}
@if($filterTowns->count() > 0)
<div class="mb-5">
    <label class="block text-xs font-semibold text-on-surface-strong uppercase tracking-wider mb-2">
        {{-- MapPin icon (Lucide, xs=14) --}}
        <span class="inline-flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path><circle cx="12" cy="10" r="3"></circle></svg>
            {{ __('Town') }}
        </span>
    </label>
    <select
        x-model="selectedTown"
        @change="applyFilters()"
        class="w-full h-9 px-3 rounded-lg text-sm bg-surface dark:bg-surface border border-outline dark:border-outline text-on-surface-strong focus:ring-2 focus:ring-primary focus:border-primary transition-all duration-200"
    >
        <option value="">{{ __('All towns') }}</option>
        @foreach($filterTowns as $town)
            <option value="{{ $town->id }}">{{ $town->name }}</option>
        @endforeach
    </select>
</div>
@endif

{{-- Availability Filter (BR-090: radio options) --}}
<div class="mb-5">
    <span class="block text-xs font-semibold text-on-surface-strong uppercase tracking-wider mb-2">
        {{-- Clock icon (Lucide, xs=14) --}}
        <span class="inline-flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            {{ __('Availability') }}
        </span>
    </span>
    <div class="space-y-1.5">
        <label class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg cursor-pointer hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors duration-150"
               :class="selectedAvailability === 'all' && 'bg-primary-subtle dark:bg-primary-subtle'"
        >
            <input type="radio" name="availability" value="all"
                   x-model="selectedAvailability" @change="applyFilters()"
                   class="w-4 h-4 text-primary focus:ring-primary border-outline"
            >
            <span class="text-sm text-on-surface" :class="selectedAvailability === 'all' && 'text-on-surface-strong font-medium'">{{ __('All cooks') }}</span>
        </label>
        <label class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg cursor-pointer hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors duration-150"
               :class="selectedAvailability === 'now' && 'bg-primary-subtle dark:bg-primary-subtle'"
        >
            <input type="radio" name="availability" value="now"
                   x-model="selectedAvailability" @change="applyFilters()"
                   class="w-4 h-4 text-primary focus:ring-primary border-outline"
            >
            <span class="text-sm text-on-surface" :class="selectedAvailability === 'now' && 'text-on-surface-strong font-medium'">{{ __('Available now') }}</span>
        </label>
        <label class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg cursor-pointer hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors duration-150"
               :class="selectedAvailability === 'today' && 'bg-primary-subtle dark:bg-primary-subtle'"
        >
            <input type="radio" name="availability" value="today"
                   x-model="selectedAvailability" @change="applyFilters()"
                   class="w-4 h-4 text-primary focus:ring-primary border-outline"
            >
            <span class="text-sm text-on-surface" :class="selectedAvailability === 'today' && 'text-on-surface-strong font-medium'">{{ __('Available today') }}</span>
        </label>
    </div>
</div>

{{-- Minimum Rating Filter (BR-090: star-row selector) --}}
<div class="mb-5">
    <span class="block text-xs font-semibold text-on-surface-strong uppercase tracking-wider mb-2">
        {{-- Star icon (Lucide, xs=14) --}}
        <span class="inline-flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
            {{ __('Minimum Rating') }}
        </span>
    </span>
    <div class="flex items-center gap-1">
        <template x-for="star in [1, 2, 3, 4, 5]" :key="star">
            <button
                type="button"
                @click="selectedMinRating = (selectedMinRating === star ? null : star); applyFilters()"
                class="p-0.5 transition-all duration-150"
                :aria-label="'{{ __('Minimum :stars stars') }}'.replace(':stars', star)"
            >
                {{-- Filled star when <= selected rating --}}
                <svg x-show="selectedMinRating && star <= selectedMinRating" class="w-6 h-6 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                {{-- Outlined star otherwise --}}
                <svg x-show="!selectedMinRating || star > selectedMinRating" class="w-6 h-6 text-on-surface/30 hover:text-secondary/60 transition-colors" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
            </button>
        </template>
        {{-- Label showing selected value --}}
        <span x-show="selectedMinRating" x-cloak class="ml-2 text-xs text-on-surface font-medium">
            <span x-text="selectedMinRating"></span>{{ __('+ stars') }}
        </span>
    </div>
</div>

{{-- Tags Filter (BR-090: multi-select chips, BR-092: OR logic) --}}
@if($filterTags->count() > 0)
<div class="mb-5">
    <span class="block text-xs font-semibold text-on-surface-strong uppercase tracking-wider mb-2">
        {{-- Tag icon (Lucide, xs=14) --}}
        <span class="inline-flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"></path><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"></circle></svg>
            {{ __('Meal Tags') }}
        </span>
    </span>
    <div class="flex flex-wrap gap-2 max-h-40 overflow-y-auto pr-1">
        @foreach($filterTags as $tag)
            <button
                type="button"
                @click="toggleTag({{ $tag->id }}); applyFilters()"
                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium border transition-all duration-150"
                :class="selectedTags.includes({{ $tag->id }})
                    ? 'bg-primary text-on-primary border-primary'
                    : 'bg-surface dark:bg-surface text-on-surface border-outline dark:border-outline hover:border-primary hover:text-primary'"
            >
                @php
                    $locale = app()->getLocale();
                    $tagName = $locale === 'fr' ? ($tag->name_fr ?? $tag->name_en) : $tag->name_en;
                @endphp
                {{ $tagName }}
                {{-- X icon when selected --}}
                <svg x-show="selectedTags.includes({{ $tag->id }})" x-cloak class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
            </button>
        @endforeach
    </div>
</div>
@endif
