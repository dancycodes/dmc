{{--
    F-142: Pickup Location Selection
    BR-284: All configured pickup locations for the cook are displayed
    BR-285: Each pickup location shows name, full address (quarter, town), special instructions
    BR-286: Client must select exactly one pickup location
    BR-287: If only one pickup location exists, it is pre-selected automatically
    BR-288: Pickup is always free; "Free" label displayed next to each option
    BR-289: The selected pickup location is stored in the checkout session
    BR-290: All text must be localized via __()
    BR-291: Location names and addresses displayed in user's current language
--}}
@extends('layouts.tenant-public')

@section('title', __('Pickup Location') . ' - ' . ($tenant?->name ?? config('app.name')))

@section('content')
@php
    $locale = app()->getLocale();
    $nameCol = 'name_' . $locale;
    $townNameCol = 'name_' . $locale;
@endphp
<div class="min-h-screen"
    x-data="{
        pickup_location_id: '{{ $currentPickupLocationId ?? '' }}',
        pickupError: '',

        selectLocation(id) {
            this.pickup_location_id = String(id);
            this.pickupError = '';
        },

        submitPickupLocation() {
            this.pickupError = '';

            if (!this.pickup_location_id) {
                this.pickupError = '{{ __('Please select a pickup location.') }}';
                return;
            }

            $action('{{ route('tenant.checkout.save-pickup-location') }}', {
                include: ['pickup_location_id']
            });
        }
    }"
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
                {{ __('Where will you pick up your order?') }}
            </h2>
            @if($pickupLocations->count() === 1)
                <p class="text-sm text-on-surface mt-1">
                    {{ __('Pickup at the location below. Confirm and proceed.') }}
                </p>
            @else
                <p class="text-sm text-on-surface mt-1">
                    {{ __('Select a pickup location from the options below.') }}
                </p>
            @endif
        </div>

        {{-- Error message --}}
        <div x-show="pickupError" x-cloak class="mb-4 flex items-center gap-2 bg-danger-subtle text-danger rounded-lg px-4 py-3 text-sm font-medium">
            <svg class="w-4 h-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg>
            <span x-text="pickupError"></span>
        </div>

        {{-- Pickup location cards --}}
        <div class="space-y-3 mb-6">
            @foreach($pickupLocations as $location)
                @php
                    $locationName = $location->{$nameCol} ?? $location->name_en;
                    $townName = $location->town?->{$townNameCol} ?? $location->town?->name_en ?? '';
                    $quarterName = $location->quarter?->{$townNameCol} ?? $location->quarter?->name_en ?? '';
                    $fullAddress = collect([$location->address, $quarterName, $townName])
                        ->filter()
                        ->implode(', ');
                @endphp
                <button
                    type="button"
                    @click="selectLocation({{ $location->id }})"
                    class="w-full text-left p-4 sm:p-5 rounded-xl border-2 transition-all duration-200"
                    :class="pickup_location_id === '{{ $location->id }}'
                        ? 'border-primary bg-primary-subtle shadow-card'
                        : 'border-outline dark:border-outline bg-surface dark:bg-surface hover:border-primary/50 hover:shadow-card'"
                >
                    <div class="flex items-start gap-3">
                        {{-- Radio indicator --}}
                        <div
                            class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 mt-0.5 transition-all duration-200"
                            :class="pickup_location_id === '{{ $location->id }}'
                                ? 'border-primary bg-primary'
                                : 'border-outline'"
                        >
                            <svg x-show="pickup_location_id === '{{ $location->id }}'" x-cloak class="w-3 h-3 text-on-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                        </div>

                        {{-- Map pin icon --}}
                        <div class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 transition-colors duration-200"
                            :class="pickup_location_id === '{{ $location->id }}'
                                ? 'bg-primary text-on-primary'
                                : 'bg-surface-alt dark:bg-surface-alt text-on-surface'"
                        >
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        </div>

                        {{-- Location details --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-sm font-semibold text-on-surface-strong">{{ $locationName }}</span>
                                {{-- BR-288: Free badge --}}
                                <span class="shrink-0 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-success-subtle text-success">{{ __('Free') }}</span>
                            </div>

                            {{-- Full address --}}
                            @if($fullAddress)
                                <p class="text-xs text-on-surface leading-relaxed">{{ $fullAddress }}</p>
                            @endif

                            {{-- Special instructions (if any) --}}
                            @if($location->address && (mb_strlen($location->address) > 0))
                                {{-- The address field serves as location details / special instructions --}}
                            @endif
                        </div>
                    </div>
                </button>
            @endforeach
        </div>

        {{-- Order summary --}}
        <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-4 shadow-card mb-6">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-on-surface">{{ __('Cart Subtotal') }}</span>
                <span class="text-base font-bold text-primary">{{ number_format($cartSummary['total'], 0, '.', ',') }} XAF</span>
            </div>
            <div class="flex items-center justify-between mt-2">
                <span class="text-sm font-medium text-on-surface">{{ __('Pickup Fee') }}</span>
                <span class="text-sm font-semibold text-success">{{ __('Free') }}</span>
            </div>
        </div>

        {{-- Action buttons --}}
        <div class="flex flex-col sm:flex-row gap-3">
            <a href="{{ url('/checkout/delivery-method') }}" class="flex-1 h-11 inline-flex items-center justify-center gap-2 border border-outline dark:border-outline text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt font-medium rounded-lg transition-all duration-200" x-navigate>
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                {{ __('Back') }}
            </a>
            <button
                @click="submitPickupLocation()"
                class="flex-1 h-11 inline-flex items-center justify-center gap-2 bg-primary hover:bg-primary-hover text-on-primary font-semibold rounded-lg shadow-card transition-all duration-200 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="$fetching() || !pickup_location_id"
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
        x-show="pickup_location_id"
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
                <p class="text-xs font-medium text-on-surface-strong">{{ __('Pickup') }}</p>
                <p class="text-xs text-success font-semibold">{{ __('Free') }}</p>
            </div>
            <button
                @click="submitPickupLocation()"
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
