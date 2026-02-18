{{--
    Pickup Locations
    ----------------
    F-092: Add Pickup Location
    F-093: Pickup Location List View (stub for future feature)

    Allows the cook to view existing pickup locations and add new ones.
    Each pickup location is scoped to the tenant and references a town and quarter
    from the cook's delivery areas.

    BR-281: Location name is required in both English and French
    BR-282: Town selection is required (from cook's existing towns)
    BR-283: Quarter selection is required (from quarters within the selected town)
    BR-284: Address/description is required (free text, max 500 characters)
    BR-285: Pickup locations have no delivery fee (fee is always 0/N/A)
    BR-286: Pickup location is scoped to the current tenant
    BR-287: Save via Gale; location appears in list without page reload
    BR-288: Only users with location management permission can add pickup locations
--}}
@extends('layouts.cook-dashboard')

@section('title', __('Pickup Locations'))
@section('page-title', __('Pickup Locations'))

@section('content')
<div class="max-w-4xl mx-auto">
    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm text-on-surface/60 mb-6" aria-label="{{ __('Breadcrumb') }}">
        <a href="{{ url('/dashboard') }}" class="hover:text-primary transition-colors duration-200">
            {{ __('Dashboard') }}
        </a>
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
        <a href="{{ url('/dashboard/locations') }}" class="hover:text-primary transition-colors duration-200">
            {{ __('Locations') }}
        </a>
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
        <span class="text-on-surface-strong font-medium">{{ __('Pickup Locations') }}</span>
    </nav>

    {{-- Success Toast --}}
    @if(session('success'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 4000)"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-2"
            class="mb-6 p-4 rounded-lg border bg-success-subtle border-success/30 text-success flex items-center gap-3"
            role="alert"
        >
            {{-- Lucide: check-circle --}}
            <svg class="w-5 h-5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><path d="m9 11 3 3L22 4"></path></svg>
            <p class="text-sm font-medium">{{ session('success') }}</p>
        </div>
    @endif

    {{-- Error Toast --}}
    @if(session('error'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 6000)"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-2"
            class="mb-6 p-4 rounded-lg border bg-danger-subtle border-danger/30 text-danger flex items-center gap-3"
            role="alert"
        >
            {{-- Lucide: alert-circle --}}
            <svg class="w-5 h-5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" x2="12" y1="8" y2="12"></line><line x1="12" x2="12.01" y1="16" y2="16"></line></svg>
            <p class="text-sm font-medium">{{ session('error') }}</p>
        </div>
    @endif

    {{-- Pickup Locations Content --}}
    <div
        x-data="{
            showAddForm: false,
            pickup_name_en: '',
            pickup_name_fr: '',
            pickup_town_id: '',
            pickup_quarter_id: '',
            pickup_address: '',
            deliveryAreas: @js($deliveryAreas),

            /* Get quarters for selected town from delivery areas data */
            getQuartersForTown() {
                if (!this.pickup_town_id) return [];
                let area = this.deliveryAreas.find(a => String(a.town_id) === String(this.pickup_town_id));
                return area ? area.quarters : [];
            },

            /* Reset form fields */
            resetForm() {
                this.pickup_name_en = '';
                this.pickup_name_fr = '';
                this.pickup_town_id = '';
                this.pickup_quarter_id = '';
                this.pickup_address = '';
                this.showAddForm = false;
            },

            /* Character count for address */
            get addressCharCount() {
                return this.pickup_address.length;
            }
        }"
        x-sync="['pickup_name_en', 'pickup_name_fr', 'pickup_town_id', 'pickup_quarter_id', 'pickup_address']"
    >
        {{-- Section Header with Add Button --}}
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                {{-- Lucide: map-pin (pin icon for pickup locations) --}}
                <div class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-on-surface-strong">{{ __('Pickup Locations') }}</h2>
                    <p class="text-sm text-on-surface/60">{{ __('Manage locations where clients can pick up their orders.') }}</p>
                </div>
            </div>
            @if(count($deliveryAreas) > 0)
                <button
                    x-show="!showAddForm"
                    @click="showAddForm = true"
                    type="button"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold bg-primary text-on-primary rounded-lg hover:bg-primary-hover transition-colors duration-200 shadow-card"
                >
                    {{-- Lucide: plus --}}
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                    {{ __('Add Pickup Location') }}
                </button>
            @endif
        </div>

        {{-- No Towns Warning (Scenario 3) --}}
        @if(count($deliveryAreas) === 0)
            <div class="p-6 rounded-xl border border-outline bg-surface-alt text-center">
                <div class="w-12 h-12 rounded-full bg-warning-subtle flex items-center justify-center mx-auto mb-3">
                    {{-- Lucide: alert-triangle --}}
                    <svg class="w-6 h-6 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                </div>
                <h3 class="text-base font-semibold text-on-surface-strong mb-1">{{ __('No towns available') }}</h3>
                <p class="text-sm text-on-surface/60 mb-4">{{ __('Add a town first before creating pickup locations.') }}</p>
                <a
                    href="{{ url('/dashboard/locations') }}"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold bg-primary text-on-primary rounded-lg hover:bg-primary-hover transition-colors duration-200"
                    x-navigate
                >
                    {{-- Lucide: map --}}
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"></polygon><line x1="9" x2="9" y1="3" y2="18"></line><line x1="15" x2="15" y1="6" y2="21"></line></svg>
                    {{ __('Go to Locations') }}
                </a>
            </div>
        @endif

        {{-- Add Pickup Location Form --}}
        <div
            x-show="showAddForm"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2"
            class="mb-6 p-5 rounded-xl border border-primary/30 bg-primary-subtle/30 dark:bg-primary-subtle/10"
        >
            <h3 class="text-base font-semibold text-on-surface-strong mb-4 flex items-center gap-2">
                {{-- Lucide: map-pin-plus --}}
                <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.5 10c0 4.5-6.5 10-6.5 10S5.5 14.5 5.5 10a6.5 6.5 0 0 1 13 0Z"></path><circle cx="12" cy="10" r="2.5"></circle><path d="M20 16h4"></path><path d="M22 14v4"></path></svg>
                {{ __('Add Pickup Location') }}
            </h3>

            {{-- Name Fields --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1.5">
                        {{ __('Location Name (English)') }} <span class="text-danger">*</span>
                    </label>
                    <input
                        type="text"
                        x-model="pickup_name_en"
                        x-name="pickup_name_en"
                        placeholder="{{ __('e.g., My Kitchen') }}"
                        maxlength="255"
                        class="w-full px-3 py-2.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200"
                    >
                    <p x-message="pickup_name_en" class="mt-1 text-xs text-danger"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1.5">
                        {{ __('Location Name (French)') }} <span class="text-danger">*</span>
                    </label>
                    <input
                        type="text"
                        x-model="pickup_name_fr"
                        x-name="pickup_name_fr"
                        placeholder="{{ __('e.g., Ma Cuisine') }}"
                        maxlength="255"
                        class="w-full px-3 py-2.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200"
                    >
                    <p x-message="pickup_name_fr" class="mt-1 text-xs text-danger"></p>
                </div>
            </div>

            {{-- Town and Quarter Dropdowns (Cascading) --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1.5">
                        {{ __('Town') }} <span class="text-danger">*</span>
                    </label>
                    <select
                        x-model="pickup_town_id"
                        x-name="pickup_town_id"
                        @change="pickup_quarter_id = ''"
                        class="w-full px-3 py-2.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200"
                    >
                        <option value="">{{ __('Select a town...') }}</option>
                        <template x-for="area in deliveryAreas" :key="area.id">
                            <option :value="area.town_id" x-text="area.town_name"></option>
                        </template>
                    </select>
                    <p x-message="pickup_town_id" class="mt-1 text-xs text-danger"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1.5">
                        {{ __('Quarter') }} <span class="text-danger">*</span>
                    </label>
                    <select
                        x-model="pickup_quarter_id"
                        x-name="pickup_quarter_id"
                        :disabled="!pickup_town_id"
                        class="w-full px-3 py-2.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <option value="">{{ __('Select a quarter...') }}</option>
                        <template x-for="q in getQuartersForTown()" :key="q.quarter_id">
                            <option :value="q.quarter_id" x-text="q.quarter_name"></option>
                        </template>
                    </select>
                    <p x-message="pickup_quarter_id" class="mt-1 text-xs text-danger"></p>

                    {{-- No quarters available message --}}
                    <p
                        x-show="pickup_town_id && getQuartersForTown().length === 0"
                        x-cloak
                        class="mt-1 text-xs text-warning"
                    >
                        {{ __('No quarters available for this town. Add quarters first.') }}
                    </p>
                </div>
            </div>

            {{-- Address Field --}}
            <div class="mb-4">
                <label class="block text-sm font-medium text-on-surface mb-1.5">
                    {{ __('Address / Directions') }} <span class="text-danger">*</span>
                </label>
                <textarea
                    x-model="pickup_address"
                    x-name="pickup_address"
                    rows="3"
                    maxlength="500"
                    placeholder="{{ __('Near [landmark], [street name]') }}"
                    class="w-full px-3 py-2.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200 resize-none"
                ></textarea>
                <div class="flex items-center justify-between mt-1">
                    <p x-message="pickup_address" class="text-xs text-danger"></p>
                    <span
                        class="text-xs"
                        :class="addressCharCount > 450 ? 'text-warning' : 'text-on-surface/40'"
                        x-text="addressCharCount + ' / 500'"
                    ></span>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="flex items-center gap-3 justify-end">
                <button
                    @click="resetForm()"
                    type="button"
                    class="px-4 py-2 text-sm font-medium text-on-surface hover:text-on-surface-strong hover:bg-surface-alt rounded-lg transition-colors duration-200"
                >
                    {{ __('Cancel') }}
                </button>
                <button
                    @click="$action('{{ url('/dashboard/locations/pickup') }}')"
                    type="button"
                    :disabled="$fetching() || !pickup_name_en.trim() || !pickup_name_fr.trim() || !pickup_town_id || !pickup_quarter_id || !pickup_address.trim()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold bg-primary text-on-primary rounded-lg hover:bg-primary-hover transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed shadow-card"
                >
                    <span x-show="!$fetching()">
                        {{-- Lucide: check --}}
                        <svg class="w-4 h-4 inline-block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                        {{ __('Save') }}
                    </span>
                    <span x-show="$fetching()" x-cloak>
                        <svg class="w-4 h-4 inline-block animate-spin" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                        {{ __('Saving...') }}
                    </span>
                </button>
            </div>
        </div>

        {{-- Pickup Locations List --}}
        @if(count($pickupLocations) > 0)
            <div class="space-y-3">
                @foreach($pickupLocations as $location)
                    <div class="p-4 rounded-xl border border-outline bg-surface-alt dark:bg-surface-alt shadow-card hover:shadow-md transition-shadow duration-200">
                        <div class="flex items-start gap-3">
                            {{-- Pin Icon --}}
                            <div class="w-9 h-9 rounded-full bg-secondary-subtle flex items-center justify-center shrink-0 mt-0.5">
                                {{-- Lucide: map-pin --}}
                                <svg class="w-4 h-4 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="text-sm font-semibold text-on-surface-strong">
                                    {{ $location['name'] }}
                                </h4>
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1">
                                    <span class="text-xs text-on-surface/60 flex items-center gap-1">
                                        {{-- Lucide: building-2 --}}
                                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"></path><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"></path><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"></path><path d="M10 6h4"></path><path d="M10 10h4"></path><path d="M10 14h4"></path><path d="M10 18h4"></path></svg>
                                        {{ $location['town_name'] }}
                                    </span>
                                    <span class="text-xs text-on-surface/60 flex items-center gap-1">
                                        {{-- Lucide: navigation --}}
                                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 11 22 2 13 21 11 13 3 11"></polygon></svg>
                                        {{ $location['quarter_name'] }}
                                    </span>
                                </div>
                                <p class="text-xs text-on-surface/50 mt-2 leading-relaxed">
                                    {{ $location['address'] }}
                                </p>
                            </div>
                            {{-- Free badge (BR-285) --}}
                            <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full bg-success-subtle text-success shrink-0">
                                {{-- Lucide: tag --}}
                                <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"></path><path d="M7 7h.01"></path></svg>
                                {{ __('Free') }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        @elseif(count($deliveryAreas) > 0)
            {{-- Empty State --}}
            <div class="p-8 rounded-xl border border-outline bg-surface-alt text-center">
                <div class="w-14 h-14 rounded-full bg-primary-subtle flex items-center justify-center mx-auto mb-4">
                    {{-- Lucide: map-pin --}}
                    <svg class="w-7 h-7 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                </div>
                <h3 class="text-base font-semibold text-on-surface-strong mb-1">{{ __('No pickup locations yet') }}</h3>
                <p class="text-sm text-on-surface/60 mb-4">{{ __('Add pickup locations so clients can collect their orders from you directly.') }}</p>
                <button
                    @click="showAddForm = true"
                    type="button"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold bg-primary text-on-primary rounded-lg hover:bg-primary-hover transition-colors duration-200 shadow-card"
                >
                    {{-- Lucide: plus --}}
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                    {{ __('Add your first pickup location') }}
                </button>
            </div>
        @endif

        {{-- Back to Locations link --}}
        <div class="mt-6">
            <a
                href="{{ url('/dashboard/locations') }}"
                class="inline-flex items-center gap-1.5 text-sm text-primary hover:text-primary-hover transition-colors duration-200"
                x-navigate
            >
                {{-- Lucide: arrow-left --}}
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
                {{ __('Back to Locations') }}
            </a>
        </div>
    </div>
</div>
@endsection
