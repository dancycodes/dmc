{{--
    Meal Location Override
    ----------------------
    F-096: Meal-Specific Location Override

    Allows a cook to override the default delivery and pickup locations for a specific meal.
    Toggle between global (default) and custom location settings.

    Variables:
    - $meal — The Meal model
    - $locationData — Array with delivery_areas, pickup_locations, and current_overrides
--}}
@php
    $overrides = $locationData['current_overrides'] ?? ['selected_quarters' => [], 'selected_pickups' => []];
    $deliveryAreas = $locationData['delivery_areas'] ?? [];
    $pickupLocations = $locationData['pickup_locations'] ?? [];

    // Build initial selected quarter map: quarter_id => custom_fee
    $selectedQuarterMap = [];
    foreach ($overrides['selected_quarters'] as $sq) {
        $selectedQuarterMap[$sq['quarter_id']] = $sq['custom_fee'];
    }
    $selectedPickupIds = $overrides['selected_pickups'] ?? [];
@endphp

<div
    x-data="{
        hasCustomLocations: {{ $meal->has_custom_locations ? 'true' : 'false' }},
        expanded: {{ $meal->has_custom_locations ? 'true' : 'false' }},
        errorMessage: '',

        /* State keys used by Gale $action include (separate from UI state) */
        has_custom_locations: {{ $meal->has_custom_locations ? 'true' : 'false' }},
        quarters: [],
        pickup_location_ids: [],

        /* UI state: Delivery quarters { [quarter_id]: { selected: bool, custom_fee: string } } */
        quarterState: {
            @foreach($deliveryAreas as $area)
                @foreach($area['quarters'] as $quarter)
                    {{ $quarter['quarter_id'] }}: {
                        selected: {{ isset($selectedQuarterMap[$quarter['quarter_id']]) ? 'true' : 'false' }},
                        custom_fee: '{{ $selectedQuarterMap[$quarter['quarter_id']] ?? '' }}'
                    },
                @endforeach
            @endforeach
        },

        /* UI state: Pickup locations { [pickup_id]: bool } */
        pickupState: {
            @foreach($pickupLocations as $pickup)
                {{ $pickup['id'] }}: {{ in_array($pickup['id'], $selectedPickupIds) ? 'true' : 'false' }},
            @endforeach
        },

        /* Town expand state */
        expandedTowns: {},

        toggleTown(areaId) {
            this.expandedTowns[areaId] = !this.expandedTowns[areaId];
        },

        toggleAllTownQuarters(quarterIds, checked) {
            quarterIds.forEach(id => {
                if (this.quarterState[id]) {
                    this.quarterState[id].selected = checked;
                    if (!checked) {
                        this.quarterState[id].custom_fee = '';
                    }
                }
            });
        },

        allTownSelected(quarterIds) {
            return quarterIds.every(id => this.quarterState[id]?.selected);
        },

        get selectedQuarterCount() {
            return Object.values(this.quarterState).filter(q => q.selected).length;
        },

        get selectedPickupCount() {
            return Object.values(this.pickupState).filter(v => v).length;
        },

        get totalSelectedCount() {
            return this.selectedQuarterCount + this.selectedPickupCount;
        },

        /* Build Gale-sendable state from UI state just before submission */
        syncGaleState() {
            let qd = [];
            Object.entries(this.quarterState).forEach(([id, q]) => {
                if (q.selected) {
                    qd.push({
                        quarter_id: parseInt(id),
                        custom_fee: q.custom_fee !== '' ? parseInt(q.custom_fee) : null
                    });
                }
            });
            this.quarters = [...qd];

            let pids = [];
            Object.entries(this.pickupState).forEach(([id, selected]) => {
                if (selected) { pids.push(parseInt(id)); }
            });
            this.pickup_location_ids = [...pids];
            this.has_custom_locations = this.hasCustomLocations;
        },

        saveLocations() {
            this.errorMessage = '';

            if (this.hasCustomLocations && this.totalSelectedCount === 0) {
                this.errorMessage = '{{ __('At least one delivery quarter or pickup location must be selected.') }}';
                return;
            }

            this.syncGaleState();
            $action('{{ url('/dashboard/meals/' . $meal->id . '/locations') }}', {
                include: ['has_custom_locations', 'quarters', 'pickup_location_ids'],
            });
        },

        disableCustomLocations() {
            this.hasCustomLocations = false;
            this.expanded = false;

            Object.keys(this.quarterState).forEach(id => {
                this.quarterState[id].selected = false;
                this.quarterState[id].custom_fee = '';
            });
            Object.keys(this.pickupState).forEach(id => {
                this.pickupState[id] = false;
            });

            this.syncGaleState();
            $action('{{ url('/dashboard/meals/' . $meal->id . '/locations') }}', {
                include: ['has_custom_locations', 'quarters', 'pickup_location_ids'],
            });
        }
    }"
    class="bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-xl shadow-card overflow-hidden"
>
    {{-- Section header with toggle --}}
    <div class="p-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-info-subtle flex items-center justify-center">
                    <svg class="w-5 h-5 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path><circle cx="12" cy="10" r="3"></circle></svg>
                </span>
                <div>
                    <h3 class="text-base font-semibold text-on-surface-strong">{{ __('Delivery Locations') }}</h3>
                    <p class="text-sm text-on-surface/60">
                        <span x-show="!hasCustomLocations">{{ __('Using default locations') }}</span>
                        <span x-show="hasCustomLocations" x-cloak>
                            {{ __('Custom locations') }}
                            <span class="text-primary font-medium" x-text="'(' + totalSelectedCount + ')'"></span>
                        </span>
                    </p>
                </div>
            </div>

            {{-- Toggle switch --}}
            <div class="flex items-center gap-3">
                <span class="text-sm text-on-surface/70 hidden sm:inline" x-text="hasCustomLocations ? '{{ __('Custom') }}' : '{{ __('Default') }}'"></span>
                <button
                    type="button"
                    role="switch"
                    :aria-checked="hasCustomLocations"
                    x-on:click="
                        if (hasCustomLocations) {
                            disableCustomLocations();
                        } else {
                            hasCustomLocations = true;
                            expanded = true;
                        }
                    "
                    :class="hasCustomLocations ? 'bg-primary' : 'bg-outline-strong'"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-surface"
                >
                    <span
                        :class="hasCustomLocations ? 'translate-x-5' : 'translate-x-0.5'"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out mt-0.5"
                    ></span>
                </button>
            </div>
        </div>

        {{-- Error message --}}
        <div x-show="errorMessage" x-cloak class="mt-4 p-3 rounded-lg bg-danger-subtle border border-danger/20">
            <p class="text-sm text-danger" x-text="errorMessage"></p>
        </div>
        <p x-message="locations" class="mt-2 text-sm text-danger"></p>
    </div>

    {{-- Custom locations selection panel --}}
    <div
        x-show="hasCustomLocations && expanded"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        class="border-t border-outline dark:border-outline"
    >
        {{-- Delivery quarters section --}}
        @if(count($deliveryAreas) > 0)
        <div class="p-6 pb-4">
            <h4 class="text-sm font-semibold text-on-surface-strong mb-3 flex items-center gap-2">
                <svg class="w-4 h-4 text-on-surface/60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="16" height="16" x="4" y="4" rx="2"></rect><path d="M4 10h16"></path><path d="M10 4v16"></path></svg>
                {{ __('Delivery Quarters') }}
                <span class="text-xs font-normal text-on-surface/50" x-text="'(' + selectedQuarterCount + ' {{ __('selected') }})'"></span>
            </h4>

            <div class="space-y-3">
                @foreach($deliveryAreas as $area)
                <div class="border border-outline dark:border-outline rounded-lg overflow-hidden">
                    {{-- Town header --}}
                    <button
                        type="button"
                        x-on:click="toggleTown({{ $area['id'] }})"
                        class="w-full flex items-center justify-between px-4 py-3 bg-surface dark:bg-surface hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors duration-150"
                    >
                        <div class="flex items-center gap-3">
                            <svg
                                class="w-4 h-4 text-on-surface/50 transition-transform duration-200"
                                :class="expandedTowns[{{ $area['id'] }}] ? 'rotate-90' : ''"
                                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            ><path d="m9 18 6-6-6-6"></path></svg>
                            <span class="text-sm font-medium text-on-surface-strong">{{ $area['town_name'] }}</span>
                            <span class="text-xs text-on-surface/50">({{ count($area['quarters']) }})</span>
                        </div>

                        {{-- Select all for this town --}}
                        @php $quarterIds = array_column($area['quarters'], 'quarter_id'); @endphp
                        <label class="flex items-center gap-2 text-xs text-on-surface/60" x-on:click.stop>
                            <input
                                type="checkbox"
                                :checked="allTownSelected({{ json_encode($quarterIds) }})"
                                x-on:change="toggleAllTownQuarters({{ json_encode($quarterIds) }}, $el.checked)"
                                class="w-4 h-4 rounded border-outline text-primary focus:ring-primary"
                            >
                            <span class="hidden sm:inline">{{ __('All') }}</span>
                        </label>
                    </button>

                    {{-- Quarters list --}}
                    <div
                        x-show="expandedTowns[{{ $area['id'] }}]"
                        x-cloak
                        x-transition
                        class="border-t border-outline dark:border-outline divide-y divide-outline dark:divide-outline"
                    >
                        @foreach($area['quarters'] as $quarter)
                        <div class="px-4 py-3 flex items-center gap-3 flex-wrap sm:flex-nowrap">
                            {{-- Quarter checkbox --}}
                            <label class="flex items-center gap-2 flex-1 min-w-0 cursor-pointer">
                                <input
                                    type="checkbox"
                                    x-model="quarterState[{{ $quarter['quarter_id'] }}].selected"
                                    class="w-4 h-4 rounded border-outline text-primary focus:ring-primary shrink-0"
                                >
                                <span class="text-sm text-on-surface truncate">{{ $quarter['quarter_name'] }}</span>
                                @if($quarter['group_name'])
                                    <span class="shrink-0 px-1.5 py-0.5 rounded text-[10px] font-medium bg-info-subtle text-info">{{ $quarter['group_name'] }}</span>
                                @endif
                            </label>

                            {{-- Default fee display --}}
                            <span class="text-xs text-on-surface/50 shrink-0">
                                {{ __('Default') }}: {{ number_format($quarter['default_fee']) }} {{ __('XAF') }}
                            </span>

                            {{-- Custom fee input (only when quarter is selected) --}}
                            <div
                                x-show="quarterState[{{ $quarter['quarter_id'] }}].selected"
                                x-cloak
                                class="flex items-center gap-1.5 shrink-0"
                            >
                                <label for="custom_fee_{{ $quarter['quarter_id'] }}" class="text-xs text-on-surface/60 whitespace-nowrap">{{ __('Custom fee') }}:</label>
                                <input
                                    id="custom_fee_{{ $quarter['quarter_id'] }}"
                                    type="number"
                                    min="0"
                                    step="1"
                                    placeholder="{{ number_format($quarter['default_fee']) }}"
                                    x-model="quarterState[{{ $quarter['quarter_id'] }}].custom_fee"
                                    class="w-24 px-2 py-1 text-sm rounded-md border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder:text-on-surface/40 focus:border-primary focus:ring-1 focus:ring-primary"
                                >
                                <span class="text-xs text-on-surface/50">{{ __('XAF') }}</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @else
        <div class="p-6 pb-4">
            <p class="text-sm text-on-surface/60 italic">{{ __('No delivery areas configured. Add delivery areas in your location settings first.') }}</p>
        </div>
        @endif

        {{-- Pickup locations section --}}
        @if(count($pickupLocations) > 0)
        <div class="p-6 pt-2 border-t border-outline/50 dark:border-outline/50">
            <h4 class="text-sm font-semibold text-on-surface-strong mb-3 flex items-center gap-2">
                <svg class="w-4 h-4 text-on-surface/60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9Z"></path><path d="m3 9 2.45-4.9A2 2 0 0 1 7.24 3h9.52a2 2 0 0 1 1.8 1.1L21 9"></path><path d="M12 3v6"></path></svg>
                {{ __('Pickup Locations') }}
                <span class="text-xs font-normal text-on-surface/50" x-text="'(' + selectedPickupCount + ' {{ __('selected') }})'"></span>
            </h4>

            <div class="space-y-2">
                @foreach($pickupLocations as $pickup)
                <label class="flex items-start gap-3 p-3 rounded-lg border border-outline dark:border-outline hover:bg-surface dark:hover:bg-surface cursor-pointer transition-colors duration-150">
                    <input
                        type="checkbox"
                        x-model="pickupState[{{ $pickup['id'] }}]"
                        class="w-4 h-4 rounded border-outline text-primary focus:ring-primary mt-0.5 shrink-0"
                    >
                    <div class="min-w-0">
                        <span class="text-sm font-medium text-on-surface-strong block">{{ $pickup['name'] }}</span>
                        <span class="text-xs text-on-surface/60">
                            {{ $pickup['town_name'] }} &middot; {{ $pickup['quarter_name'] }}
                        </span>
                        @if($pickup['address'])
                            <span class="text-xs text-on-surface/50 block mt-0.5 truncate">{{ mb_substr($pickup['address'], 0, 80) }}{{ mb_strlen($pickup['address']) > 80 ? '...' : '' }}</span>
                        @endif
                    </div>
                </label>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Save button --}}
        <div class="px-6 py-4 bg-surface dark:bg-surface border-t border-outline dark:border-outline flex items-center justify-between">
            <p class="text-xs text-on-surface/50">
                {{ __('Changes apply to new orders only.') }}
            </p>
            <button
                type="button"
                x-on:click="saveLocations()"
                :disabled="$fetching()"
                class="px-5 py-2 rounded-lg text-sm font-medium bg-primary text-on-primary hover:bg-primary-hover shadow-sm transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
                <span x-show="!$fetching()">{{ __('Save Locations') }}</span>
                <span x-show="$fetching()" x-cloak class="flex items-center gap-2">
                    <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                    {{ __('Saving...') }}
                </span>
            </button>
        </div>
    </div>
</div>
