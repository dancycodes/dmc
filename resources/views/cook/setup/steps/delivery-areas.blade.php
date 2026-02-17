{{--
    Delivery Areas Step (Step 3)
    ----------------------------
    F-074: Delivery Areas Step

    Allows the cook to add towns, quarters with delivery fees, and optional pickup locations.

    BR-136: At least 1 town with 1 quarter and a delivery fee is required for minimum setup.
    BR-137: Town name required in both EN and FR.
    BR-138: Town name must be unique within this cook's towns.
    BR-139: Quarter name required in both EN and FR.
    BR-140: Quarter name must be unique within its parent town.
    BR-141: Delivery fee >= 0 XAF (0 = free delivery).
    BR-142: Delivery fee stored as integer in XAF.
    BR-143: Pickup locations are optional.
    BR-144: Same tables as full location management.
    BR-145: No limit on towns/quarters/pickup locations.
--}}
<div
    x-data="{
        deliveryAreas: {{ json_encode($deliveryAreas ?? []) }},
        pickupLocations: {{ json_encode($pickupLocations ?? []) }},
        hasMinimumSetup: {{ ($deliveryAreas ?? []) ? collect($deliveryAreas)->contains(fn($a) => count($a['quarters'] ?? []) > 0) ? 'true' : 'false' : 'false' }},

        /* Town form */
        town_name_en: '',
        town_name_fr: '',
        showTownForm: false,

        /* Quarter form — per expanded town */
        expandedTown: null,
        quarter_name_en: '',
        quarter_name_fr: '',
        delivery_fee: 0,
        showQuarterForm: null,
        feeWarning: '',

        /* Pickup location form */
        pickup_name_en: '',
        pickup_name_fr: '',
        pickup_town_id: '',
        pickup_quarter_id: '',
        pickup_address: '',
        showPickupForm: false,

        /* Helpers */
        toggleTown(id) {
            this.expandedTown = this.expandedTown === id ? null : id;
            this.showQuarterForm = null;
        },
        getQuartersForPickupTown() {
            if (!this.pickup_town_id) return [];
            let area = this.deliveryAreas.find(a => String(a.town_id) === String(this.pickup_town_id));
            return area ? area.quarters : [];
        }
    }"
    x-sync="['town_name_en', 'town_name_fr', 'quarter_name_en', 'quarter_name_fr', 'delivery_fee', 'pickup_name_en', 'pickup_name_fr', 'pickup_town_id', 'pickup_quarter_id', 'pickup_address']"
>
    {{-- Step Header --}}
    <div class="mb-6">
        <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 rounded-full bg-info-subtle flex items-center justify-center shrink-0">
                {{-- Lucide: truck --}}
                <svg class="w-5 h-5 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"></path><path d="M15 18H9"></path><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path><circle cx="17" cy="18" r="2"></circle><circle cx="7" cy="18" r="2"></circle></svg>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-on-surface-strong">{{ __('Delivery Areas') }}</h3>
                <p class="text-sm text-on-surface">{{ __('Add the towns and quarters where you deliver, along with delivery fees.') }}</p>
            </div>
        </div>
    </div>

    {{-- ===== DELIVERY AREAS SECTION ===== --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h4 class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                {{-- Lucide: map-pin --}}
                <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                {{ __('Delivery Towns & Quarters') }}
            </h4>
            <button
                @click="showTownForm = !showTownForm"
                type="button"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-primary text-on-primary rounded-lg hover:bg-primary-hover transition-colors duration-200"
            >
                {{-- Lucide: plus --}}
                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                {{ __('Add Town') }}
            </button>
        </div>

        {{-- Add Town Inline Form --}}
        <div x-show="showTownForm" x-collapse x-cloak class="bg-surface dark:bg-surface rounded-lg border border-outline dark:border-outline p-4 space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-on-surface mb-1">{{ __('Town Name (English)') }} <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        x-model="town_name_en"
                        x-name="town_name_en"
                        placeholder="{{ __('e.g. Douala') }}"
                        class="w-full px-3 py-2 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200"
                    >
                    <p x-message="town_name_en" class="mt-1 text-xs text-danger"></p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-on-surface mb-1">{{ __('Town Name (French)') }} <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        x-model="town_name_fr"
                        x-name="town_name_fr"
                        placeholder="{{ __('e.g. Douala') }}"
                        class="w-full px-3 py-2 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200"
                    >
                    <p x-message="town_name_fr" class="mt-1 text-xs text-danger"></p>
                </div>
            </div>
            <div class="flex items-center gap-2 justify-end">
                <button
                    @click="showTownForm = false; town_name_en = ''; town_name_fr = '';"
                    type="button"
                    class="px-3 py-1.5 text-xs font-medium text-on-surface hover:text-on-surface-strong hover:bg-surface-alt rounded-lg transition-colors duration-200"
                >
                    {{ __('Cancel') }}
                </button>
                <button
                    @click="$action('{{ url('/dashboard/setup/delivery-areas/add-town') }}')"
                    type="button"
                    :disabled="$fetching() || !town_name_en.trim() || !town_name_fr.trim()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-primary text-on-primary rounded-lg hover:bg-primary-hover transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span x-show="!$fetching()">{{ __('Add Town') }}</span>
                    <span x-show="$fetching()" x-cloak>{{ __('Adding...') }}</span>
                </button>
            </div>
        </div>

        {{-- Town List (Accordion) --}}
        <template x-if="deliveryAreas.length === 0 && !showTownForm">
            <div class="text-center py-8 text-on-surface/60">
                <svg class="w-12 h-12 mx-auto mb-3 opacity-40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                <p class="text-sm">{{ __('No delivery towns added yet.') }}</p>
                <p class="text-xs mt-1">{{ __('Click "Add Town" to get started.') }}</p>
            </div>
        </template>

        <div class="space-y-3">
            <template x-for="area in deliveryAreas" :key="area.id">
                <div class="bg-surface dark:bg-surface rounded-lg border border-outline dark:border-outline overflow-hidden">
                    {{-- Town Header (Accordion Trigger) --}}
                    <div
                        @click="toggleTown(area.id)"
                        class="flex items-center justify-between p-3 cursor-pointer hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors duration-200"
                    >
                        <div class="flex items-center gap-2">
                            {{-- Lucide: map-pin --}}
                            <svg class="w-4 h-4 text-primary shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            <div>
                                <span class="text-sm font-semibold text-on-surface-strong" x-text="area.town_name"></span>
                                <span class="text-xs text-on-surface/60 ml-2" x-text="'(' + area.quarters.length + ' ' + (area.quarters.length === 1 ? '{{ __('quarter') }}' : '{{ __('quarters') }}') + ')'"></span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            {{-- Delete Town --}}
                            <button
                                @click.stop="if(confirm('{{ __('Remove this town and all its quarters?') }}')) $action('{{ url('/dashboard/setup/delivery-areas/remove-town') }}/' + area.id, { method: 'DELETE' })"
                                type="button"
                                class="p-1 text-danger/60 hover:text-danger rounded transition-colors duration-200"
                                :title="'{{ __('Remove town') }}'"
                            >
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                            </button>
                            {{-- Chevron --}}
                            <svg
                                class="w-4 h-4 text-on-surface/40 transition-transform duration-200"
                                :class="expandedTown === area.id ? 'rotate-180' : ''"
                                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            ><path d="m6 9 6 6 6-6"></path></svg>
                        </div>
                    </div>

                    {{-- Expanded Quarter List --}}
                    <div x-show="expandedTown === area.id" x-collapse x-cloak class="border-t border-outline dark:border-outline">
                        {{-- Quarters --}}
                        <div class="p-3 space-y-2">
                            <template x-if="area.quarters.length === 0">
                                <p class="text-xs text-on-surface/50 italic py-2 text-center">{{ __('No quarters added yet. Add a quarter with delivery fee below.') }}</p>
                            </template>
                            <template x-for="q in area.quarters" :key="q.id">
                                <div class="flex items-center justify-between px-3 py-2 bg-surface-alt dark:bg-surface-alt rounded-lg">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-3.5 h-3.5 text-on-surface/40 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect></svg>
                                        <span class="text-sm text-on-surface-strong" x-text="q.quarter_name"></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                            :class="q.delivery_fee === 0 ? 'bg-success-subtle text-success' : 'bg-primary-subtle text-primary'"
                                            x-text="q.delivery_fee === 0 ? '{{ __('Free delivery') }}' : q.delivery_fee.toLocaleString() + ' XAF'"
                                        ></span>
                                        <button
                                            @click="$action('{{ url('/dashboard/setup/delivery-areas/remove-quarter') }}/' + q.id, { method: 'DELETE' })"
                                            type="button"
                                            class="p-0.5 text-danger/40 hover:text-danger rounded transition-colors duration-200"
                                            :title="'{{ __('Remove quarter') }}'"
                                        >
                                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- Add Quarter Form --}}
                        <div class="border-t border-outline dark:border-outline p-3 space-y-3">
                            <button
                                x-show="showQuarterForm !== area.id"
                                @click="showQuarterForm = area.id; quarter_name_en = ''; quarter_name_fr = ''; delivery_fee = 0; feeWarning = '';"
                                type="button"
                                class="inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:text-primary-hover transition-colors duration-200"
                            >
                                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                                {{ __('Add Quarter') }}
                            </button>

                            <div x-show="showQuarterForm === area.id" x-collapse x-cloak class="space-y-3">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-on-surface mb-1">{{ __('Quarter Name (English)') }} <span class="text-danger">*</span></label>
                                        <input
                                            type="text"
                                            x-model="quarter_name_en"
                                            x-name="quarter_name_en"
                                            placeholder="{{ __('e.g. Bonaberi') }}"
                                            class="w-full px-3 py-2 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200"
                                        >
                                        <p x-message="quarter_name_en" class="mt-1 text-xs text-danger"></p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-on-surface mb-1">{{ __('Quarter Name (French)') }} <span class="text-danger">*</span></label>
                                        <input
                                            type="text"
                                            x-model="quarter_name_fr"
                                            x-name="quarter_name_fr"
                                            placeholder="{{ __('e.g. Bonaberi') }}"
                                            class="w-full px-3 py-2 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200"
                                        >
                                        <p x-message="quarter_name_fr" class="mt-1 text-xs text-danger"></p>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-on-surface mb-1">{{ __('Delivery Fee') }} <span class="text-danger">*</span></label>
                                    <div class="relative">
                                        <input
                                            type="number"
                                            x-model.number="delivery_fee"
                                            x-name="delivery_fee"
                                            min="0"
                                            step="1"
                                            placeholder="0"
                                            class="w-full px-3 py-2 pr-14 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200"
                                        >
                                        <span class="absolute inset-y-0 right-3 flex items-center text-xs text-on-surface/50 pointer-events-none font-medium">XAF</span>
                                    </div>
                                    <p x-message="delivery_fee" class="mt-1 text-xs text-danger"></p>
                                    <p x-show="delivery_fee === 0" x-cloak class="mt-1 text-xs text-success">{{ __('This quarter will have free delivery.') }}</p>
                                    <p x-show="feeWarning" x-cloak class="mt-1 text-xs text-warning" x-text="feeWarning"></p>
                                </div>
                                <div class="flex items-center gap-2 justify-end">
                                    <button
                                        @click="showQuarterForm = null; quarter_name_en = ''; quarter_name_fr = ''; delivery_fee = 0; feeWarning = '';"
                                        type="button"
                                        class="px-3 py-1.5 text-xs font-medium text-on-surface hover:text-on-surface-strong hover:bg-surface-alt rounded-lg transition-colors duration-200"
                                    >
                                        {{ __('Cancel') }}
                                    </button>
                                    <button
                                        @click="$action('{{ url('/dashboard/setup/delivery-areas') }}/' + area.id + '/add-quarter')"
                                        type="button"
                                        :disabled="$fetching() || !quarter_name_en.trim() || !quarter_name_fr.trim()"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-primary text-on-primary rounded-lg hover:bg-primary-hover transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <span x-show="!$fetching()">{{ __('Add Quarter') }}</span>
                                        <span x-show="$fetching()" x-cloak>{{ __('Adding...') }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- ===== PICKUP LOCATIONS SECTION ===== --}}
    <div class="mt-8 pt-6 border-t border-outline dark:border-outline space-y-4">
        <div class="flex items-center justify-between">
            <h4 class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                {{-- Lucide: map-pin-house --}}
                <svg class="w-4 h-4 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 22a1 1 0 0 1-1-1v-4a1 1 0 0 1 .445-.832l3-2a1 1 0 0 1 1.11 0l3 2A1 1 0 0 1 22 17v4a1 1 0 0 1-1 1z"></path><path d="M18 10a8 8 0 0 0-16 0c0 4.993 5.539 10.193 7.399 11.799a1 1 0 0 0 .601.2"></path><path d="M18 22v-3"></path><circle cx="10" cy="10" r="3"></circle></svg>
                {{ __('Pickup Locations') }}
                <span class="text-xs text-on-surface/60 font-normal">({{ __('optional') }})</span>
            </h4>
            <button
                x-show="deliveryAreas.length > 0"
                @click="showPickupForm = !showPickupForm; pickup_town_id = ''; pickup_quarter_id = '';"
                type="button"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-secondary text-on-secondary rounded-lg hover:bg-secondary-hover transition-colors duration-200"
            >
                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                {{ __('Add Pickup Location') }}
            </button>
        </div>

        {{-- Pickup Form --}}
        <div x-show="showPickupForm" x-collapse x-cloak class="bg-surface dark:bg-surface rounded-lg border border-outline dark:border-outline p-4 space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-on-surface mb-1">{{ __('Location Name (English)') }} <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        x-model="pickup_name_en"
                        x-name="pickup_name_en"
                        placeholder="{{ __('e.g. My Kitchen') }}"
                        class="w-full px-3 py-2 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200"
                    >
                    <p x-message="pickup_name_en" class="mt-1 text-xs text-danger"></p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-on-surface mb-1">{{ __('Location Name (French)') }} <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        x-model="pickup_name_fr"
                        x-name="pickup_name_fr"
                        placeholder="{{ __('e.g. Ma Cuisine') }}"
                        class="w-full px-3 py-2 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200"
                    >
                    <p x-message="pickup_name_fr" class="mt-1 text-xs text-danger"></p>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-on-surface mb-1">{{ __('Town') }} <span class="text-danger">*</span></label>
                    <select
                        x-model="pickup_town_id"
                        x-name="pickup_town_id"
                        @change="pickup_quarter_id = ''"
                        class="w-full px-3 py-2 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200"
                    >
                        <option value="">{{ __('Select a town...') }}</option>
                        <template x-for="area in deliveryAreas" :key="area.id">
                            <option :value="area.town_id" x-text="area.town_name"></option>
                        </template>
                    </select>
                    <p x-message="pickup_town_id" class="mt-1 text-xs text-danger"></p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-on-surface mb-1">{{ __('Quarter') }} <span class="text-danger">*</span></label>
                    <select
                        x-model="pickup_quarter_id"
                        x-name="pickup_quarter_id"
                        :disabled="!pickup_town_id"
                        class="w-full px-3 py-2 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <option value="">{{ __('Select a quarter...') }}</option>
                        <template x-for="q in getQuartersForPickupTown()" :key="q.quarter_id">
                            <option :value="q.quarter_id" x-text="q.quarter_name"></option>
                        </template>
                    </select>
                    <p x-message="pickup_quarter_id" class="mt-1 text-xs text-danger"></p>
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-on-surface mb-1">{{ __('Address / Directions') }} <span class="text-danger">*</span></label>
                <input
                    type="text"
                    x-model="pickup_address"
                    x-name="pickup_address"
                    maxlength="500"
                    placeholder="{{ __('e.g. Behind Akwa Palace Hotel') }}"
                    class="w-full px-3 py-2 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200"
                >
                <p x-message="pickup_address" class="mt-1 text-xs text-danger"></p>
            </div>
            <div class="flex items-center gap-2 justify-end">
                <button
                    @click="showPickupForm = false; pickup_name_en = ''; pickup_name_fr = ''; pickup_town_id = ''; pickup_quarter_id = ''; pickup_address = '';"
                    type="button"
                    class="px-3 py-1.5 text-xs font-medium text-on-surface hover:text-on-surface-strong hover:bg-surface-alt rounded-lg transition-colors duration-200"
                >
                    {{ __('Cancel') }}
                </button>
                <button
                    @click="$action('{{ url('/dashboard/setup/delivery-areas/add-pickup') }}')"
                    type="button"
                    :disabled="$fetching() || !pickup_name_en.trim() || !pickup_name_fr.trim() || !pickup_town_id || !pickup_quarter_id || !pickup_address.trim()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-secondary text-on-secondary rounded-lg hover:bg-secondary-hover transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span x-show="!$fetching()">{{ __('Add Pickup Location') }}</span>
                    <span x-show="$fetching()" x-cloak>{{ __('Adding...') }}</span>
                </button>
            </div>
        </div>

        {{-- Pickup Locations List --}}
        <template x-if="pickupLocations.length === 0 && !showPickupForm">
            <div class="text-center py-4 text-on-surface/50">
                <p class="text-xs">{{ __('No pickup locations added. Customers will use delivery only.') }}</p>
            </div>
        </template>

        <div class="space-y-2">
            <template x-for="loc in pickupLocations" :key="loc.id">
                <div class="flex items-center justify-between px-3 py-2.5 bg-surface dark:bg-surface rounded-lg border border-outline dark:border-outline">
                    <div class="flex items-start gap-2 min-w-0">
                        {{-- Lucide: map-pin --}}
                        <svg class="w-4 h-4 text-secondary shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        <div class="min-w-0">
                            <span class="text-sm font-medium text-on-surface-strong block" x-text="loc.name"></span>
                            <span class="text-xs text-on-surface/60" x-text="loc.town_name + ' — ' + loc.quarter_name"></span>
                            <span class="text-xs text-on-surface/50 block truncate" x-text="loc.address"></span>
                        </div>
                    </div>
                    <button
                        @click="$action('{{ url('/dashboard/setup/delivery-areas/remove-pickup') }}/' + loc.id, { method: 'DELETE' })"
                        type="button"
                        class="p-1 text-danger/40 hover:text-danger rounded transition-colors duration-200 shrink-0"
                        :title="'{{ __('Remove pickup location') }}'"
                    >
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                    </button>
                </div>
            </template>
        </div>
    </div>

    {{-- ===== SAVE & CONTINUE ===== --}}
    <div class="mt-6">
        {{-- Minimum requirement warning --}}
        <div x-show="!hasMinimumSetup" x-cloak class="mb-4 p-3 rounded-lg bg-warning-subtle border border-warning/20 text-warning text-sm flex items-start gap-2">
            <svg class="w-4 h-4 shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
            <span>{{ __('Add at least one town with one quarter and a delivery fee to continue.') }}</span>
        </div>
        <p x-message="delivery_areas" class="mb-3 text-xs text-danger"></p>

        <div class="flex items-center justify-end">
            <button
                @click="$action('{{ url('/dashboard/setup/delivery-areas/save') }}')"
                type="button"
                :disabled="$fetching() || !hasMinimumSetup"
                class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-semibold bg-primary text-on-primary rounded-lg hover:bg-primary-hover transition-colors duration-200 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                :title="!hasMinimumSetup ? '{{ __('Add at least one town with one quarter first') }}' : ''"
            >
                <span x-show="!$fetching()">{{ __('Save & Continue') }}</span>
                <span x-show="$fetching()" x-cloak class="inline-flex items-center gap-2">
                    <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    {{ __('Saving...') }}
                </span>
                <svg x-show="!$fetching()" class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
            </button>
        </div>
    </div>
</div>
