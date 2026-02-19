{{--
    F-141: Delivery Location Selection
    F-145: Delivery Fee Calculation
    BR-274: Town dropdown shows only towns where the cook has delivery areas configured
    BR-275: Quarter dropdown is filtered by the selected town
    BR-276: Neighbourhood is a free-text field with OpenStreetMap Nominatim autocomplete
    BR-277: Nominatim autocomplete is scoped to the selected town in Cameroon
    BR-278: Saved addresses matching cook's delivery areas are offered as quick-select options
    BR-279: If saved address in available area, it is pre-selected by default
    BR-280: The selected quarter determines the delivery fee (F-145)
    BR-281: All fields (town, quarter, neighbourhood) are required for delivery orders
    BR-282: All form labels and text must be localized via __()
    BR-283: Town and quarter names displayed in the user's current language
    BR-307: Delivery fee is determined by the selected quarter
    BR-308: If the quarter belongs to a fee group, the group fee is used
    BR-309: If the quarter has an individual fee (no group), the individual fee is used
    BR-310: A fee of 0 XAF is displayed as "Free delivery"
    BR-311: The fee is displayed in the format: "Delivery to {quarter}: {fee} XAF"
    BR-312: The fee updates reactively when the quarter selection changes
--}}
@extends('layouts.tenant-public')

@section('title', __('Delivery Location') . ' - ' . ($tenant?->name ?? config('app.name')))

@section('content')
@php
    $locale = app()->getLocale();
    $townNameCol = 'name_' . $locale;
@endphp
<div class="min-h-screen"
    x-data="{
        town_id: '{{ $currentLocation['town_id'] ?? '' }}',
        quarter_id: '{{ $currentLocation['quarter_id'] ?? '' }}',
        neighbourhood: '{{ addslashes($currentLocation['neighbourhood'] ?? '') }}',
        quarters: {{ Js::from($quarters->toArray()) }},
        selectedAddressId: '{{ $preSelectedAddress?->id ?? '' }}',
        locationError: '',

        selectSavedAddress(address) {
            this.selectedAddressId = String(address.id);
            this.town_id = String(address.town_id);
            this.quarter_id = '';
            this.neighbourhood = address.neighbourhood || '';

            /* Load quarters for the address town */
            $action('{{ route('tenant.checkout.load-quarters') }}', {
                include: ['town_id']
            });
        },

        onTownChange() {
            this.quarter_id = '';
            this.neighbourhood = '';
            this.selectedAddressId = '';
            this.locationError = '';

            if (this.town_id) {
                $action('{{ route('tenant.checkout.load-quarters') }}', {
                    include: ['town_id']
                });
            } else {
                this.quarters = [];
            }
        },

        onQuarterChange() {
            this.locationError = '';
        },

        getDeliveryFee() {
            if (!this.quarter_id) return null;
            const q = this.quarters.find(q => String(q.id) === String(this.quarter_id));
            return q ? q.delivery_fee : null;
        },

        /* F-145: Get the name of the currently selected quarter */
        getSelectedQuarterName() {
            if (!this.quarter_id) return '';
            const q = this.quarters.find(q => String(q.id) === String(this.quarter_id));
            return q ? q.name : '';
        },

        /* F-145 BR-311: Format the delivery fee display text */
        getDeliveryFeeDisplay() {
            const fee = this.getDeliveryFee();
            if (fee === null) return '';
            const name = this.getSelectedQuarterName();
            if (fee === 0) {
                /* BR-310: Free delivery */
                return '{{ __('Delivery to') }} ' + name + ': {{ __('Free delivery') }}';
            }
            /* BR-311: "Delivery to {quarter}: {fee} XAF" */
            return '{{ __('Delivery to') }} ' + name + ': ' + this.formatPrice(fee);
        },

        formatPrice(amount) {
            return new Intl.NumberFormat('en').format(amount) + ' XAF';
        },

        submitLocation() {
            this.locationError = '';

            if (!this.town_id) {
                this.locationError = '{{ __('Please select a town.') }}';
                return;
            }
            if (!this.quarter_id) {
                this.locationError = '{{ __('Please select a quarter.') }}';
                return;
            }
            if (!this.neighbourhood.trim()) {
                this.locationError = '{{ __('Please enter your neighbourhood or address.') }}';
                return;
            }

            $action('{{ route('tenant.checkout.save-delivery-location') }}', {
                include: ['town_id', 'quarter_id', 'neighbourhood']
            });
        }
    }"
    x-sync="['quarters', 'quarter_id', 'neighbourhood']"
    x-on:location-selected.window="
        if ($event.detail && $event.detail.display_name) {
            neighbourhood = $event.detail.display_name;
        }
    "
    x-on:location-input.window="
        if ($event.detail && $event.detail.value !== undefined) {
            neighbourhood = $event.detail.value;
        }
    "
>
    {{-- Back navigation --}}
    <div class="bg-surface dark:bg-surface border-b border-outline dark:border-outline">
        <div class="max-w-3xl mx-auto px-4 sm:px-6">
            <div class="h-12 flex items-center justify-between">
                <a href="{{ url('/checkout/delivery-method') }}" class="flex items-center gap-2 text-sm font-medium text-on-surface hover:text-primary transition-colors duration-200" x-navigate>
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                    {{ __('Back') }}
                </a>
                <h1 class="text-sm font-semibold text-on-surface-strong">{{ __('Checkout') }}</h1>
            </div>
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
        {{-- Step indicator --}}
        <div class="mb-6">
            <div class="flex items-center gap-2 text-xs font-medium text-on-surface">
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-success text-on-success text-xs font-bold">
                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                </span>
                <span class="text-success font-semibold">{{ __('Delivery Method') }}</span>
                <svg class="w-4 h-4 text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-primary text-on-primary text-xs font-bold">2</span>
                <span class="text-primary font-semibold">{{ __('Location') }}</span>
                <svg class="w-4 h-4 text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-outline text-on-surface text-xs font-bold">3</span>
                <span class="text-on-surface/50">{{ __('Payment') }}</span>
            </div>
        </div>

        {{-- Page heading --}}
        <div class="mb-6">
            <h2 class="text-lg sm:text-xl font-display font-bold text-on-surface-strong">
                {{ __('Where should we deliver your order?') }}
            </h2>
            <p class="text-sm text-on-surface mt-1">
                {{ __('Select your delivery town, quarter, and enter your neighbourhood.') }}
            </p>
        </div>

        {{-- Error message --}}
        <div x-show="locationError" x-cloak class="mb-4 flex items-center gap-2 bg-danger-subtle text-danger rounded-lg px-4 py-3 text-sm font-medium">
            <svg class="w-4 h-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg>
            <span x-text="locationError"></span>
        </div>

        {{-- BR-278: Saved addresses as quick-select cards --}}
        @if($savedAddresses->isNotEmpty())
            <div class="mb-6">
                <h3 class="text-sm font-semibold text-on-surface-strong mb-3">
                    {{ __('Your Saved Addresses') }}
                </h3>
                <div class="space-y-2">
                    @foreach($savedAddresses as $address)
                        <button
                            type="button"
                            @click="selectSavedAddress({{ Js::from([
                                'id' => $address->id,
                                'town_id' => $address->town_id,
                                'quarter_id' => $address->quarter_id,
                                'neighbourhood' => $address->neighbourhood ?? '',
                            ]) }})"
                            class="w-full text-left p-3 sm:p-4 rounded-xl border-2 transition-all duration-200"
                            :class="selectedAddressId === '{{ $address->id }}'
                                ? 'border-primary bg-primary-subtle shadow-card'
                                : 'border-outline dark:border-outline bg-surface dark:bg-surface hover:border-primary/50 hover:shadow-card'"
                        >
                            <div class="flex items-start gap-3">
                                {{-- Selected indicator --}}
                                <div
                                    class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 mt-0.5 transition-all duration-200"
                                    :class="selectedAddressId === '{{ $address->id }}'
                                        ? 'border-primary bg-primary'
                                        : 'border-outline'"
                                >
                                    <svg x-show="selectedAddressId === '{{ $address->id }}'" x-cloak class="w-3 h-3 text-on-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-0.5">
                                        <span class="text-sm font-semibold text-on-surface-strong truncate">{{ $address->label }}</span>
                                        @if($address->is_default)
                                            <span class="shrink-0 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-primary-subtle text-primary">{{ __('Default') }}</span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-on-surface truncate">
                                        {{ $address->quarter?->{$townNameCol} ?? $address->quarter?->name_en }},
                                        {{ $address->town?->{$townNameCol} ?? $address->town?->name_en }}
                                    </p>
                                    @if($address->neighbourhood)
                                        <p class="text-xs text-on-surface/60 truncate mt-0.5">{{ $address->neighbourhood }}</p>
                                    @endif
                                </div>

                                {{-- Map pin icon --}}
                                <svg class="w-4 h-4 shrink-0 text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            </div>
                        </button>
                    @endforeach
                </div>

                {{-- Separator --}}
                <div class="flex items-center gap-3 my-5">
                    <div class="flex-1 h-px bg-outline dark:bg-outline"></div>
                    <span class="text-xs font-medium text-on-surface/50 uppercase tracking-wide">{{ __('or select manually') }}</span>
                    <div class="flex-1 h-px bg-outline dark:bg-outline"></div>
                </div>
            </div>
        @endif

        {{-- Manual location selection form --}}
        <div class="space-y-5">
            {{-- Town dropdown (BR-274) --}}
            <div>
                <label for="town-select" class="block text-sm font-medium text-on-surface mb-1.5">
                    {{ __('Town') }} <span class="text-danger">*</span>
                </label>
                <div class="relative">
                    <select
                        id="town-select"
                        x-model="town_id"
                        x-name="town_id"
                        @change="onTownChange()"
                        class="w-full appearance-none pl-3 pr-9 py-2.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200"
                        required
                    >
                        <option value="">{{ __('Select a town') }}</option>
                        @foreach($towns as $town)
                            <option value="{{ $town->id }}">{{ $town->{$townNameCol} ?? $town->name_en }}</option>
                        @endforeach
                    </select>
                    {{-- Dropdown arrow --}}
                    <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
                        <svg class="w-4 h-4 text-on-surface/40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
                    </div>
                </div>
                <p x-message="town_id" class="mt-1 text-xs text-danger"></p>
            </div>

            {{-- Quarter dropdown (BR-275) --}}
            <div>
                <label for="quarter-select" class="block text-sm font-medium text-on-surface mb-1.5">
                    {{ __('Quarter') }} <span class="text-danger">*</span>
                </label>
                <div class="relative">
                    <select
                        id="quarter-select"
                        x-model="quarter_id"
                        x-name="quarter_id"
                        @change="onQuarterChange()"
                        x-init="$nextTick(() => { if (quarter_id) $el.value = quarter_id })"
                        class="w-full appearance-none pl-3 pr-9 py-2.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="!town_id || quarters.length === 0"
                        required
                    >
                        <option value="">{{ __('Select a quarter') }}</option>
                        <template x-for="quarter in quarters" :key="quarter.id">
                            <option :value="String(quarter.id)" x-text="quarter.name + (quarter.delivery_fee > 0 ? ' (' + formatPrice(quarter.delivery_fee) + ')' : ' ({{ __('Free') }})')"></option>
                        </template>
                    </select>
                    {{-- Dropdown arrow --}}
                    <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
                        <svg class="w-4 h-4 text-on-surface/40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
                    </div>
                </div>
                <p x-message="quarter_id" class="mt-1 text-xs text-danger"></p>

                {{-- Loading state for quarters --}}
                <div x-show="town_id && quarters.length === 0 && $fetching()" x-cloak class="mt-2 flex items-center gap-2 text-xs text-on-surface/60">
                    <svg class="w-3.5 h-3.5 animate-spin text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                    {{ __('Loading quarters...') }}
                </div>

                {{-- No quarters message --}}
                <div x-show="town_id && quarters.length === 0 && !$fetching()" x-cloak class="mt-2 text-xs text-warning">
                    {{ __('No quarters available for delivery in this town.') }}
                </div>

                {{-- F-145: Delivery fee indicator (BR-311, BR-310, BR-312) --}}
                <div
                    x-show="quarter_id && getDeliveryFee() !== null"
                    x-cloak
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="mt-2 flex items-center gap-1.5 py-1.5 px-2.5 rounded-lg"
                    :class="getDeliveryFee() === 0 ? 'bg-success-subtle' : 'bg-primary-subtle'"
                >
                    {{-- Delivery truck icon --}}
                    <svg class="w-3.5 h-3.5 shrink-0" :class="getDeliveryFee() === 0 ? 'text-success' : 'text-primary'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"></path><path d="M15 18H9"></path><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path><circle cx="17" cy="18" r="2"></circle><circle cx="7" cy="18" r="2"></circle></svg>
                    {{-- BR-311: "Delivery to {quarter}: {fee} XAF" / BR-310: "Free delivery" --}}
                    <span
                        class="text-xs font-medium"
                        :class="getDeliveryFee() === 0 ? 'text-success' : 'text-primary'"
                        x-text="getDeliveryFeeDisplay()"
                    ></span>
                </div>
            </div>

            {{-- Neighbourhood with OpenStreetMap autocomplete (BR-276/BR-277) --}}
            <div>
                <x-location-search
                    name="neighbourhood"
                    :label="__('Neighbourhood / Address')"
                    :placeholder="__('Search for your neighbourhood...')"
                    :required="true"
                    :value="$currentLocation['neighbourhood'] ?? ''"
                    id="checkout-location-search"
                />
                <p class="mt-1 text-xs text-on-surface/50">
                    {{ __('Type to search or enter your address manually.') }}
                </p>
            </div>
        </div>

        {{-- F-145: Order summary with delivery fee (BR-313) --}}
        <div class="mt-6 bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-4 shadow-card">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-on-surface">{{ __('Cart Subtotal') }}</span>
                <span class="text-sm font-semibold text-on-surface-strong">{{ number_format($cartSummary['total'], 0, '.', ',') }} XAF</span>
            </div>
            <div class="flex items-center justify-between mt-2">
                <span class="text-sm font-medium text-on-surface">{{ __('Delivery Fee') }}</span>
                <span
                    class="text-sm"
                    :class="quarter_id && getDeliveryFee() !== null ? (getDeliveryFee() === 0 ? 'text-success font-semibold' : 'text-on-surface-strong font-semibold') : 'text-on-surface/50'"
                    x-text="quarter_id && getDeliveryFee() !== null ? (getDeliveryFee() > 0 ? formatPrice(getDeliveryFee()) : '{{ __('Free') }}') : '{{ __('Select quarter to calculate') }}'"
                ></span>
            </div>
            {{-- Total line (BR-313: delivery fee added to order total) --}}
            <div
                x-show="quarter_id && getDeliveryFee() !== null"
                x-cloak
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                class="flex items-center justify-between mt-3 pt-3 border-t border-outline dark:border-outline"
            >
                <span class="text-sm font-semibold text-on-surface-strong">{{ __('Estimated Total') }}</span>
                <span class="text-base font-bold text-primary" x-text="formatPrice({{ $cartSummary['total'] }} + (getDeliveryFee() || 0))"></span>
            </div>
        </div>

        {{-- Action buttons --}}
        <div class="mt-6 flex flex-col sm:flex-row gap-3">
            <a href="{{ url('/checkout/delivery-method') }}" class="flex-1 h-11 inline-flex items-center justify-center gap-2 border border-outline dark:border-outline text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt font-medium rounded-lg transition-all duration-200" x-navigate>
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                {{ __('Back') }}
            </a>
            <button
                @click="submitLocation()"
                class="flex-1 h-11 inline-flex items-center justify-center gap-2 bg-primary hover:bg-primary-hover text-on-primary font-semibold rounded-lg shadow-card transition-all duration-200 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="$fetching() || !town_id || !quarter_id || !neighbourhood.trim()"
            >
                <span x-show="!$fetching()">{{ __('Continue') }}</span>
                <span x-show="$fetching()" x-cloak>{{ __('Processing...') }}</span>
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
            </button>
        </div>
    </div>

    {{-- Mobile sticky action bar --}}
    <div
        class="sm:hidden fixed bottom-0 left-0 right-0 z-40 bg-surface dark:bg-surface border-t border-outline dark:border-outline px-4 py-3 shadow-dropdown"
        x-show="town_id && quarter_id && neighbourhood.trim()"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-y-full"
        x-transition:enter-end="translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-y-0"
        x-transition:leave-end="translate-y-full"
    >
        <div class="flex items-center justify-between">
            <div>
                {{-- F-145 BR-311: Delivery fee display in mobile bar --}}
                <p
                    class="text-xs font-medium"
                    :class="getDeliveryFee() === 0 ? 'text-success' : 'text-on-surface-strong'"
                    x-text="quarter_id && getDeliveryFee() !== null ? getDeliveryFeeDisplay() : ''"
                ></p>
                <p class="text-xs text-on-surface/60 truncate max-w-[160px]" x-text="neighbourhood"></p>
            </div>
            <button
                @click="submitLocation()"
                class="h-11 px-6 bg-primary hover:bg-primary-hover text-on-primary font-semibold rounded-lg shadow-card transition-all duration-200 cursor-pointer inline-flex items-center gap-2"
                :disabled="$fetching()"
            >
                <span x-show="!$fetching()">{{ __('Continue') }}</span>
                <span x-show="$fetching()" x-cloak>{{ __('Processing...') }}</span>
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
            </button>
        </div>
    </div>
</div>
@endsection
