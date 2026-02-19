{{--
    F-143: Order Phone Number
    BR-292: Phone number is pre-filled from the authenticated user's profile phone number
    BR-293: Client can override the phone number per order
    BR-294: Overriding the phone number does NOT update the user's profile
    BR-295: Phone must match Cameroon format: +237 followed by 9 digits (starting with 6, 7, or 2)
    BR-296: Phone number is a required field
    BR-297: Validation error messages must be localized via __()
    BR-298: Phone number is stored with the order for delivery/communication purposes
--}}
@extends('layouts.tenant-public')

@section('title', __('Phone Number') . ' - ' . ($tenant?->name ?? config('app.name')))

@section('content')
@php
    $profileDigits = $profilePhone;
    if (str_starts_with($profileDigits, '+237')) {
        $profileDigits = substr($profileDigits, 4);
    }
@endphp
<div class="min-h-screen"
    x-data="{
        phone_digits: '{{ addslashes($phoneDigits) }}',
        phoneError: '',
        profileDigits: '{{ addslashes($profileDigits) }}',

        get isChanged() {
            return this.profileDigits && this.phone_digits !== this.profileDigits;
        },

        resetToProfile() {
            this.phone_digits = this.profileDigits;
            this.phoneError = '';
        },

        formatDisplay() {
            let d = this.phone_digits.replace(/\D/g, '');
            if (d.length >= 6) {
                return d.substring(0, 3) + ' ' + d.substring(3, 6) + ' ' + d.substring(6, 9);
            }
            if (d.length >= 3) {
                return d.substring(0, 3) + ' ' + d.substring(3);
            }
            return d;
        },

        submitPhone() {
            this.phoneError = '';

            let digits = this.phone_digits.replace(/[\s\-()]/g, '');

            if (!digits) {
                this.phoneError = '{{ __('Phone number is required.') }}';
                return;
            }

            if (digits.length !== 9 || !/^[672]/.test(digits)) {
                this.phoneError = '{{ __('Please enter a valid Cameroon phone number (+237 followed by 9 digits).') }}';
                return;
            }

            this.phone_digits = digits;
            $action('{{ route('tenant.checkout.save-phone') }}', {
                include: ['phone_digits']
            });
        }
    }"
>
    {{-- Back navigation --}}
    <div class="bg-surface dark:bg-surface border-b border-outline dark:border-outline">
        <div class="max-w-3xl mx-auto px-4 sm:px-6">
            <div class="h-12 flex items-center justify-between">
                <a href="{{ $backUrl }}" class="flex items-center gap-2 text-sm font-medium text-on-surface hover:text-primary transition-colors duration-200" x-navigate>
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
                {{-- Step 1: Delivery Method (completed) --}}
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-success text-on-success text-xs font-bold">
                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                </span>
                <span class="text-success font-semibold">{{ __('Delivery Method') }}</span>
                <svg class="w-4 h-4 text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>

                {{-- Step 2: Location (completed) --}}
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-success text-on-success text-xs font-bold">
                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                </span>
                <span class="text-success font-semibold">{{ __('Location') }}</span>
                <svg class="w-4 h-4 text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>

                {{-- Step 3: Phone (current) --}}
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-primary text-on-primary text-xs font-bold">3</span>
                <span class="text-primary font-semibold">{{ __('Phone') }}</span>
                <svg class="w-4 h-4 text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>

                {{-- Step 4: Payment (upcoming) --}}
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-outline text-on-surface text-xs font-bold">4</span>
                <span class="text-on-surface/50">{{ __('Payment') }}</span>
            </div>
        </div>

        {{-- Page heading --}}
        <div class="mb-6">
            <h2 class="text-lg sm:text-xl font-display font-bold text-on-surface-strong">
                {{ __('Your contact phone number') }}
            </h2>
            <p class="text-sm text-on-surface mt-1">
                {{ __('This number will be used by the cook to contact you about your order.') }}
            </p>
        </div>

        {{-- Error message --}}
        <div x-show="phoneError" x-cloak class="mb-4 flex items-center gap-2 bg-danger-subtle text-danger rounded-lg px-4 py-3 text-sm font-medium">
            <svg class="w-4 h-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg>
            <span x-text="phoneError"></span>
        </div>

        {{-- Phone input card --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 sm:p-6 shadow-card">
            {{-- Phone icon header --}}
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center">
                    <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-on-surface-strong">{{ __('Phone Number') }}</h3>
                    <p class="text-xs text-on-surface">{{ __('Cameroon mobile number') }}</p>
                </div>
            </div>

            {{-- Phone input with +237 prefix --}}
            <div>
                <label for="phone-input" class="block text-sm font-medium text-on-surface mb-1.5">
                    {{ __('Phone Number') }} <span class="text-danger">*</span>
                </label>
                <div class="flex">
                    {{-- Country code prefix (non-editable) --}}
                    <div class="flex items-center justify-center px-3 py-2.5 rounded-l-lg border border-r-0 border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface font-medium text-sm select-none">
                        +237
                    </div>
                    {{-- Phone digits input --}}
                    <input
                        id="phone-input"
                        type="tel"
                        x-model="phone_digits"
                        x-name="phone_digits"
                        maxlength="12"
                        placeholder="6XX XXX XXX"
                        autocomplete="tel"
                        class="flex-1 pl-3 pr-3 py-2.5 rounded-r-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200"
                        @input="phoneError = ''"
                    >
                </div>
                <p x-message="phone_digits" class="mt-1 text-xs text-danger"></p>

                {{-- Formatted preview --}}
                <div x-show="phone_digits.replace(/\D/g, '').length > 0" x-cloak class="mt-2 text-xs text-on-surface/60">
                    {{ __('Number:') }} <span class="font-medium text-on-surface" x-text="'+237 ' + formatDisplay()"></span>
                </div>
            </div>

            {{-- BR-293: "Same as my profile" reset button (shown only when phone is changed) --}}
            @if($profilePhone)
                <div x-show="isChanged" x-cloak class="mt-4">
                    <button
                        type="button"
                        @click="resetToProfile()"
                        class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-primary hover:text-primary-hover bg-primary-subtle hover:bg-primary-subtle/80 rounded-lg transition-all duration-200 cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path><path d="M21 3v5h-5"></path><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path><path d="M8 16H3v5"></path></svg>
                        {{ __('Same as my profile') }}
                    </button>
                </div>
            @endif

            {{-- Privacy notice --}}
            <div class="mt-4 flex items-start gap-2 p-3 rounded-lg bg-info-subtle">
                <svg class="w-4 h-4 text-info shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                <p class="text-xs text-info leading-relaxed">
                    {{ __('Changing the number here will not update your profile. This number is used for this order only.') }}
                </p>
            </div>
        </div>

        {{-- Order summary --}}
        <div class="mt-6 bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-4 shadow-card">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-on-surface">{{ __('Cart Subtotal') }}</span>
                <span class="text-base font-bold text-primary">{{ number_format($cartSummary['total'], 0, '.', ',') }} XAF</span>
            </div>
        </div>

        {{-- Action buttons --}}
        <div class="mt-6 flex flex-col sm:flex-row gap-3">
            <a href="{{ $backUrl }}" class="flex-1 h-11 inline-flex items-center justify-center gap-2 border border-outline dark:border-outline text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt font-medium rounded-lg transition-all duration-200" x-navigate>
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                {{ __('Back') }}
            </a>
            <button
                @click="submitPhone()"
                class="flex-1 h-11 inline-flex items-center justify-center gap-2 bg-primary hover:bg-primary-hover text-on-primary font-semibold rounded-lg shadow-card transition-all duration-200 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="$fetching() || !phone_digits.replace(/\D/g, '').length"
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
        x-show="phone_digits.replace(/\D/g, '').length >= 9"
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
                <p class="text-xs font-medium text-on-surface-strong">{{ __('Phone') }}</p>
                <p class="text-xs text-on-surface/60" x-text="'+237 ' + formatDisplay()"></p>
            </div>
            <button
                @click="submitPhone()"
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
