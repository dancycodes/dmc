{{--
    Location Search Component (x-location-search)
    -----------------------------------------------
    F-097: OpenStreetMap Neighbourhood Search

    Reusable autocomplete component for neighbourhood-level location search
    using OpenStreetMap Nominatim API (via server-side proxy).

    Props:
    - name (string): Alpine model name to bind the selected value to (required)
    - placeholder (string): Placeholder text for the input (optional)
    - label (string): Label text (optional)
    - required (bool): Whether the field is required (default: false)
    - value (string): Initial value (optional)
    - id (string): Unique component identifier for multiple instances (optional)

    Usage:
    <x-location-search
        name="delivery_address"
        label="Delivery Address"
        placeholder="Search for your neighbourhood..."
        :required="true"
    />

    Business Rules:
    BR-315: Results scoped to Cameroon (countrycodes=cm)
    BR-316: Autocomplete triggers after 3+ characters typed
    BR-317: Input debounced at 400ms to respect Nominatim rate limits
    BR-318: Max 1 req/sec, valid User-Agent (handled server-side)
    BR-319: Results displayed as dropdown list below input
    BR-320: Selected result fills address field; user can still edit manually
    BR-321: Component is reusable across any feature needing location search
    BR-322: API requests made server-side (through Laravel endpoint)
    BR-323: If API unavailable, degrades gracefully to manual text input
    BR-324: No API key required (free tier), rate limits respected
--}}
@props([
    'name' => 'location_search',
    'placeholder' => null,
    'label' => null,
    'required' => false,
    'value' => '',
    'id' => null,
])

@php
    $componentId = $id ?? 'location-search-' . \Illuminate\Support\Str::random(6);
    $placeholderText = $placeholder ?? __('Search for a neighbourhood...');
@endphp

<div
    x-data="{
        query: '{{ addslashes($value) }}',
        results: [],
        isOpen: false,
        isLoading: false,
        errorMessage: '',
        highlightedIndex: -1,
        debounceTimer: null,
        componentId: '{{ $componentId }}',

        /* BR-316: Search only after 3+ characters, BR-317: Debounce at 400ms */
        onInput() {
            clearTimeout(this.debounceTimer);
            this.highlightedIndex = -1;

            if (this.query.trim().length < 3) {
                this.results = [];
                this.isOpen = false;
                this.errorMessage = '';
                this.isLoading = false;
                return;
            }

            this.isLoading = true;
            this.errorMessage = '';

            this.debounceTimer = setTimeout(() => {
                this.performSearch();
            }, 400);
        },

        /* BR-322: Server-side API call */
        async performSearch() {
            try {
                const response = await fetch('{{ url('/location-search') }}?q=' + encodeURIComponent(this.query.trim()), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }

                const data = await response.json();

                this.isLoading = false;

                if (data.success) {
                    this.results = data.results;
                    this.isOpen = this.results.length > 0 || this.query.trim().length >= 3;
                    this.errorMessage = '';
                } else {
                    /* BR-323: API unavailable — graceful degradation */
                    this.results = [];
                    this.isOpen = true;
                    this.errorMessage = data.error || '{{ __('Unable to search locations. Please type your address manually.') }}';
                }
            } catch (error) {
                this.isLoading = false;
                this.results = [];
                this.isOpen = true;
                this.errorMessage = '{{ __('Unable to search locations. Please type your address manually.') }}';
            }
        },

        /* BR-320: Select result and fill field */
        selectResult(result) {
            /* Build a clean display name: name, area, country */
            let parts = [];
            if (result.name) parts.push(result.name);
            if (result.area && result.area !== result.name) parts.push(result.area);
            if (result.country) parts.push(result.country);
            this.query = parts.join(', ');

            this.isOpen = false;
            this.results = [];
            this.highlightedIndex = -1;
            this.errorMessage = '';

            /* Update the bound Alpine model */
            this.$dispatch('location-selected', {
                name: result.name,
                area: result.area,
                country: result.country,
                display_name: this.query,
                lat: result.lat,
                lon: result.lon,
                componentId: this.componentId
            });
        },

        /* Keyboard navigation */
        onKeyDown(event) {
            if (!this.isOpen) return;

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                this.highlightedIndex = Math.min(this.highlightedIndex + 1, this.results.length - 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                this.highlightedIndex = Math.max(this.highlightedIndex - 1, -1);
            } else if (event.key === 'Enter') {
                event.preventDefault();
                if (this.highlightedIndex >= 0 && this.highlightedIndex < this.results.length) {
                    this.selectResult(this.results[this.highlightedIndex]);
                }
            } else if (event.key === 'Escape') {
                this.isOpen = false;
                this.highlightedIndex = -1;
            }
        },

        /* Close dropdown when clicking outside */
        onClickOutside() {
            this.isOpen = false;
            this.highlightedIndex = -1;
        },

        /* Format display text for each suggestion */
        formatSuggestion(result) {
            let parts = [];
            if (result.name) parts.push(result.name);
            if (result.area && result.area !== result.name) parts.push(result.area);
            return parts.join(', ');
        }
    }"
    x-init="
        /* Sync with parent via custom event on every query change */
        $watch('query', value => {
            $dispatch('location-input', {
                value: value,
                componentId: componentId
            });
        });
    "
    @click.outside="onClickOutside()"
    class="relative w-full"
    id="{{ $componentId }}"
>
    {{-- Label --}}
    @if($label)
        <label class="block text-sm font-medium text-on-surface mb-1.5" for="{{ $componentId }}-input">
            {{ $label }}
            @if($required)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif

    {{-- Search Input with Icon --}}
    <div class="relative">
        {{-- Map Pin / Search Icon --}}
        <div class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none">
            <template x-if="!isLoading">
                {{-- Lucide: map-pin --}}
                <svg class="w-4 h-4 text-on-surface/40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
            </template>
            <template x-if="isLoading">
                {{-- Loading spinner --}}
                <svg class="w-4 h-4 text-primary animate-spin" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
            </template>
        </div>

        <input
            type="text"
            id="{{ $componentId }}-input"
            x-model="query"
            @input="onInput()"
            @keydown="onKeyDown($event)"
            @focus="if (results.length > 0) isOpen = true"
            placeholder="{{ $placeholderText }}"
            autocomplete="off"
            @if($required) required @endif
            x-name="{{ $name }}"
            class="w-full pl-9 pr-3 py-2.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200"
            role="combobox"
            aria-autocomplete="list"
            :aria-expanded="isOpen"
            aria-haspopup="listbox"
            aria-label="{{ $label ?? __('Search for a location') }}"
            :aria-activedescendant="highlightedIndex >= 0 ? componentId + '-option-' + highlightedIndex : ''"
        >
    </div>

    {{-- Validation error --}}
    <p x-message="{{ $name }}" class="mt-1 text-xs text-danger"></p>

    {{-- Autocomplete Dropdown (BR-319) --}}
    <div
        x-show="isOpen"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-1"
        class="absolute z-50 w-full mt-1 rounded-lg border border-outline bg-surface shadow-dropdown overflow-hidden"
        role="listbox"
        :id="componentId + '-listbox'"
    >
        {{-- Results List --}}
        <template x-if="results.length > 0">
            <ul class="py-1 max-h-60 overflow-y-auto">
                <template x-for="(result, index) in results" :key="index">
                    <li
                        @click="selectResult(result)"
                        @mouseenter="highlightedIndex = index"
                        :id="componentId + '-option-' + index"
                        class="px-3 py-2.5 cursor-pointer transition-colors duration-100 flex items-start gap-2.5"
                        :class="highlightedIndex === index ? 'bg-primary-subtle dark:bg-primary-subtle/30' : 'hover:bg-surface-alt'"
                        role="option"
                        :aria-selected="highlightedIndex === index"
                    >
                        {{-- Location pin icon --}}
                        <svg class="w-4 h-4 text-primary shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        <div class="flex-1 min-w-0">
                            {{-- Primary name --}}
                            <p class="text-sm font-medium text-on-surface-strong truncate" x-text="result.name"></p>
                            {{-- Area and country --}}
                            <p class="text-xs text-on-surface/60 truncate">
                                <span x-text="result.area"></span>
                                <template x-if="result.area && result.country">
                                    <span>, </span>
                                </template>
                                <span x-text="result.country"></span>
                            </p>
                        </div>
                    </li>
                </template>
            </ul>
        </template>

        {{-- No Results Message --}}
        <template x-if="results.length === 0 && !isLoading && !errorMessage && query.trim().length >= 3">
            <div class="px-3 py-4 text-center">
                <div class="w-8 h-8 rounded-full bg-surface-alt flex items-center justify-center mx-auto mb-2">
                    {{-- Lucide: search-x --}}
                    <svg class="w-4 h-4 text-on-surface/40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m13.5 8.5-5 5"></path><path d="m8.5 8.5 5 5"></path><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                </div>
                <p class="text-sm text-on-surface/60">{{ __('No locations found. Try a different search term.') }}</p>
            </div>
        </template>

        {{-- Error Message (BR-323: Graceful degradation) --}}
        <template x-if="errorMessage">
            <div class="px-3 py-4 text-center">
                <div class="w-8 h-8 rounded-full bg-warning-subtle flex items-center justify-center mx-auto mb-2">
                    {{-- Lucide: wifi-off --}}
                    <svg class="w-4 h-4 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h.01"></path><path d="M8.5 16.429a5 5 0 0 1 7 0"></path><path d="M5 12.859a10 10 0 0 1 5.17-2.69"></path><path d="M19 12.859a10 10 0 0 0-2.007-1.523"></path><path d="M2 8.82a15 15 0 0 1 4.177-2.643"></path><path d="M22 8.82a15 15 0 0 0-11.288-3.764"></path><path d="m2 2 20 20"></path></svg>
                </div>
                <p class="text-sm text-on-surface/60" x-text="errorMessage"></p>
            </div>
        </template>

        {{-- Loading State --}}
        <template x-if="isLoading">
            <div class="px-3 py-4 text-center">
                <svg class="w-5 h-5 text-primary animate-spin mx-auto mb-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                <p class="text-xs text-on-surface/60">{{ __('Searching...') }}</p>
            </div>
        </template>
    </div>
</div>
