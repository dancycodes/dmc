{{--
    Edit Delivery Address (F-035)
    ----------------------------
    Allows users to edit a saved delivery address. The form is pre-populated
    with the current address data. Same fields as the add form.

    BR-135: All fields from F-033 apply with the same validation rules.
    BR-136: Form pre-populated with current address values.
    BR-137: Label uniqueness excludes current address.
    BR-138: Town change resets and repopulates quarter dropdown.
    BR-139: Users can only edit their own addresses.
    BR-140: Save via Gale without page reload.
--}}
@extends(tenant() ? 'layouts.tenant-public' : 'layouts.main-public')

@section('title', __('Edit Delivery Address'))

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    {{-- Back Link --}}
    <div class="mb-6" x-data x-navigate>
        <a href="{{ url('/profile/addresses') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-on-surface hover:text-primary transition-colors">
            {{-- Arrow left icon (Lucide, sm=16px) --}}
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m12 19-7-7 7-7"></path>
                <path d="M19 12H5"></path>
            </svg>
            {{ __('Back to Addresses') }}
        </a>
    </div>

    {{-- Card --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-xl shadow-card border border-outline overflow-hidden">
        {{-- Card Header --}}
        <div class="px-4 sm:px-6 py-5 border-b border-outline">
            <h1 class="text-lg sm:text-xl font-bold text-on-surface-strong font-display">
                {{ __('Edit Delivery Address') }}
            </h1>
            <p class="text-sm text-on-surface mt-1">
                {{ __('Update the details of your saved address.') }}
            </p>
        </div>

        {{-- Address Edit Form --}}
        <div class="px-4 sm:px-6 py-6"
            x-data="{
                label: {{ Js::from($address->label) }},
                town_id: {{ Js::from((string) $address->town_id) }},
                quarter_id: {{ Js::from((string) $address->quarter_id) }},
                quarters: {{ Js::from($quarters) }},
                neighbourhood: {{ Js::from($address->neighbourhood ?? '') }},
                additional_directions: {{ Js::from($address->additional_directions ?? '') }},
                latitude: {{ Js::from($address->latitude) }},
                longitude: {{ Js::from($address->longitude) }},
                directionsCount: {{ Js::from(strlen($address->additional_directions ?? '')) }},
                directionsMax: 500,
                osmSuggestions: [],
                osmLoading: false,
                osmOpen: false,
                osmDebounce: null,

                async fetchQuarters() {
                    this.quarter_id = '';
                    this.quarters = [];
                    if (!this.town_id) return;
                    await $action('{{ route('addresses.quarters') }}', {
                        include: ['town_id']
                    });
                },

                searchNeighbourhood() {
                    clearTimeout(this.osmDebounce);
                    if (this.neighbourhood.length < 3) {
                        this.osmSuggestions = [];
                        this.osmOpen = false;
                        return;
                    }
                    this.osmDebounce = setTimeout(async () => {
                        this.osmLoading = true;
                        try {
                            const response = await fetch(
                                `https://nominatim.openstreetmap.org/search?format=json&countrycodes=cm&limit=5&q=${encodeURIComponent(this.neighbourhood)}`
                            );
                            if (response.ok) {
                                this.osmSuggestions = await response.json();
                                this.osmOpen = this.osmSuggestions.length > 0;
                            }
                        } catch (e) {
                            this.osmSuggestions = [];
                            this.osmOpen = false;
                        }
                        this.osmLoading = false;
                    }, 400);
                },

                selectSuggestion(suggestion) {
                    this.neighbourhood = suggestion.display_name.split(',')[0];
                    this.latitude = parseFloat(suggestion.lat);
                    this.longitude = parseFloat(suggestion.lon);
                    this.osmSuggestions = [];
                    this.osmOpen = false;
                }
            }"
            x-sync="['label', 'town_id', 'quarter_id', 'neighbourhood', 'additional_directions', 'latitude', 'longitude']"
        >
            <form @submit.prevent="$action('{{ route('addresses.update', $address) }}')" class="space-y-5">

                {{-- Label --}}
                <div class="space-y-1.5">
                    <label for="addr-label" class="block text-sm font-medium text-on-surface-strong">
                        {{ __('Address Label') }} <span class="text-danger">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface/50">
                            {{-- Tag icon (Lucide) --}}
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"></path>
                                <circle cx="7.5" cy="7.5" r=".5" fill="currentColor"></circle>
                            </svg>
                        </span>
                        <input
                            id="addr-label"
                            type="text"
                            x-name="label"
                            x-model="label"
                            required
                            maxlength="50"
                            class="w-full h-11 pl-10 pr-3 border border-outline rounded-lg text-sm text-on-surface-strong placeholder:text-on-surface/50 bg-surface dark:bg-surface-alt transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                            placeholder="{{ __('e.g., Home, Office, School') }}"
                        >
                    </div>
                    <p x-message="label" class="text-xs text-danger"></p>
                </div>

                {{-- Town Select --}}
                <div class="space-y-1.5">
                    <label for="addr-town" class="block text-sm font-medium text-on-surface-strong">
                        {{ __('Town') }} <span class="text-danger">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface/50 pointer-events-none">
                            {{-- Building icon (Lucide) --}}
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="16" height="20" x="4" y="2" rx="2" ry="2"></rect>
                                <path d="M9 22v-4h6v4"></path>
                                <path d="M8 6h.01"></path>
                                <path d="M16 6h.01"></path>
                                <path d="M12 6h.01"></path>
                                <path d="M12 10h.01"></path>
                                <path d="M12 14h.01"></path>
                                <path d="M16 10h.01"></path>
                                <path d="M16 14h.01"></path>
                                <path d="M8 10h.01"></path>
                                <path d="M8 14h.01"></path>
                            </svg>
                        </span>
                        <select
                            id="addr-town"
                            x-name="town_id"
                            x-model="town_id"
                            @change="fetchQuarters()"
                            required
                            class="w-full h-11 pl-10 pr-8 border border-outline rounded-lg text-sm text-on-surface-strong bg-surface dark:bg-surface-alt transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary appearance-none cursor-pointer"
                        >
                            <option value="">{{ __('Select a town') }}</option>
                            @foreach($towns as $town)
                                <option value="{{ $town->id }}">{{ $town->{localized('name')} }}</option>
                            @endforeach
                        </select>
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface/50 pointer-events-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m6 9 6 6 6-6"></path>
                            </svg>
                        </span>
                    </div>
                    <p x-message="town_id" class="text-xs text-danger"></p>
                </div>

                {{-- Quarter Select (dynamic, pre-populated with current town's quarters) --}}
                <div class="space-y-1.5">
                    <label for="addr-quarter" class="block text-sm font-medium text-on-surface-strong">
                        {{ __('Quarter') }} <span class="text-danger">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface/50 pointer-events-none">
                            {{-- MapPin icon (Lucide) --}}
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                        </span>
                        <select
                            id="addr-quarter"
                            x-name="quarter_id"
                            x-model="quarter_id"
                            required
                            :disabled="!town_id || quarters.length === 0"
                            class="w-full h-11 pl-10 pr-8 border border-outline rounded-lg text-sm text-on-surface-strong bg-surface dark:bg-surface-alt transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary appearance-none cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <option value="">
                                <span x-text="town_id ? (quarters.length > 0 ? '{{ __('Select a quarter') }}' : '{{ __('Loading quarters...') }}') : '{{ __('Select a town first') }}'"></span>
                            </option>
                            <template x-for="q in quarters" :key="q.id">
                                <option :value="q.id" x-text="q.name"></option>
                            </template>
                        </select>
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface/50 pointer-events-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m6 9 6 6 6-6"></path>
                            </svg>
                        </span>
                    </div>
                    <p x-message="quarter_id" class="text-xs text-danger"></p>
                </div>

                {{-- Neighbourhood (with OpenStreetMap autocomplete) --}}
                <div class="space-y-1.5">
                    <label for="addr-neighbourhood" class="block text-sm font-medium text-on-surface-strong">
                        {{ __('Neighbourhood') }}
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface/50">
                            {{-- Navigation icon (Lucide) --}}
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="3 11 22 2 13 21 11 13 3 11"></polygon>
                            </svg>
                        </span>
                        <input
                            id="addr-neighbourhood"
                            type="text"
                            x-name="neighbourhood"
                            x-model="neighbourhood"
                            @input="searchNeighbourhood()"
                            @click.away="osmOpen = false"
                            maxlength="255"
                            autocomplete="off"
                            class="w-full h-11 pl-10 pr-3 border border-outline rounded-lg text-sm text-on-surface-strong placeholder:text-on-surface/50 bg-surface dark:bg-surface-alt transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                            placeholder="{{ __('Start typing for suggestions...') }}"
                        >
                        {{-- Loading indicator --}}
                        <span
                            x-show="osmLoading"
                            x-cloak
                            class="absolute right-3 top-1/2 -translate-y-1/2"
                        >
                            <svg class="w-4 h-4 animate-spin-slow text-on-surface/50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </span>

                        {{-- Autocomplete dropdown --}}
                        <div
                            x-show="osmOpen && osmSuggestions.length > 0"
                            x-cloak
                            x-transition
                            class="absolute z-20 mt-1 w-full bg-surface border border-outline rounded-lg shadow-dropdown overflow-hidden"
                        >
                            <ul class="max-h-48 overflow-y-auto">
                                <template x-for="(suggestion, idx) in osmSuggestions" :key="idx">
                                    <li
                                        @click="selectSuggestion(suggestion)"
                                        class="px-3 py-2.5 text-sm cursor-pointer hover:bg-primary-subtle hover:text-primary transition-colors flex items-start gap-2"
                                    >
                                        <svg class="w-4 h-4 text-on-surface/50 mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                                            <circle cx="12" cy="10" r="3"></circle>
                                        </svg>
                                        <span class="text-on-surface-strong leading-snug" x-text="suggestion.display_name"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                    <p x-message="neighbourhood" class="text-xs text-danger"></p>
                    <p class="text-xs text-on-surface/60">
                        {{ __('Type at least 3 characters for location suggestions.') }}
                    </p>
                </div>

                {{-- Additional Directions --}}
                <div class="space-y-1.5">
                    <label for="addr-directions" class="block text-sm font-medium text-on-surface-strong">
                        {{ __('Additional Directions') }}
                    </label>
                    <textarea
                        id="addr-directions"
                        x-name="additional_directions"
                        x-model="additional_directions"
                        @input="directionsCount = $el.value.length"
                        :maxlength="directionsMax"
                        rows="3"
                        class="w-full px-3 py-2.5 border border-outline rounded-lg text-sm text-on-surface-strong placeholder:text-on-surface/50 bg-surface dark:bg-surface-alt resize-none transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                        placeholder="{{ __('e.g., Behind the blue pharmacy, 2nd floor') }}"
                    ></textarea>
                    <div class="flex items-center justify-between">
                        <p x-message="additional_directions" class="text-xs text-danger"></p>
                        <p class="text-xs text-on-surface/60">
                            <span x-text="directionsCount"></span>/<span x-text="directionsMax"></span>
                        </p>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-2">
                    <a href="{{ url('/profile/addresses') }}" x-data x-navigate class="inline-flex items-center justify-center h-10 px-5 rounded-lg text-sm font-semibold border border-outline text-on-surface hover:bg-surface dark:hover:bg-surface transition-all duration-200">
                        {{ __('Cancel') }}
                    </a>
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center h-10 px-6 rounded-lg text-sm font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100"
                    >
                        <span x-show="!$fetching()">
                            {{-- Save icon (Lucide) --}}
                            <span class="inline-flex items-center gap-2">
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                                    <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                                    <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                                </svg>
                                {{ __('Update Address') }}
                            </span>
                        </span>
                        <span x-show="$fetching()" x-cloak class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin-slow" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            {{ __('Updating...') }}
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
