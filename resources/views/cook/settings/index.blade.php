{{--
    Cook Settings Page
    ------------------
    F-212: Cancellation Window Configuration
    F-213: Minimum Order Amount Configuration

    The cook can configure the time window (in minutes) during which clients can
    cancel their orders for a full refund, and the minimum order amount clients
    must meet before they can proceed to checkout.

    BR-494: Default cancellation window is 15 minutes.
    BR-495: Allowed range: 5 to 120 minutes (inclusive).
    BR-496: Value must be a whole number (integer minutes).
    BR-497: Setting applies to all new orders from the moment it is saved.
    BR-503: Only the cook can modify cancellation window (not managers).
    BR-504: All changes are logged via Spatie Activitylog.
    BR-505: All user-facing text must use __() localization.
    BR-506: Gale handles the setting form interaction without page reloads.

    BR-507: Default minimum order amount is 0 XAF (no minimum).
    BR-508: Allowed range: 0 to 100,000 XAF (inclusive).
    BR-509: Value must be a whole number (integer XAF).
    BR-515: Only the cook can modify minimum order amount (not managers).
    BR-517: All changes logged via Spatie Activitylog.
    BR-518: All user-facing text must use __() localization.
--}}
@extends('layouts.cook-dashboard')

@section('title', __('Settings'))

@section('content')
<div class="max-w-3xl mx-auto py-6 sm:py-8 px-4 sm:px-6 lg:px-8">

    {{-- Page Header --}}
    <div class="mb-6 sm:mb-8">
        <h1 class="text-2xl sm:text-3xl font-display font-bold text-on-surface-strong">
            {{ __('Settings') }}
        </h1>
        <p class="mt-1.5 text-sm text-on-surface">
            {{ __('Configure your store preferences.') }}
        </p>
    </div>

    <div class="space-y-6">

        {{-- Cancellation Window Card --}}
        <div
            x-data="{
                cancellation_window_minutes: {{ (int) $cancellationWindowMinutes }},
                cancellation_saving: false,
                cancellation_saved: false,
                error: '',

                increment() {
                    if (this.cancellation_window_minutes < {{ \App\Services\CookSettingsService::MAX_CANCELLATION_WINDOW }}) {
                        this.cancellation_window_minutes = Math.min({{ \App\Services\CookSettingsService::MAX_CANCELLATION_WINDOW }}, this.cancellation_window_minutes + 5);
                    }
                },

                decrement() {
                    if (this.cancellation_window_minutes > {{ \App\Services\CookSettingsService::MIN_CANCELLATION_WINDOW }}) {
                        this.cancellation_window_minutes = Math.max({{ \App\Services\CookSettingsService::MIN_CANCELLATION_WINDOW }}, this.cancellation_window_minutes - 5);
                    }
                },

                save() {
                    this.error = '';
                    this.cancellation_saved = false;
                    $action('{{ route('cook.settings.update-cancellation-window') }}', {
                        include: ['cancellation_window_minutes']
                    });
                }
            }"
            x-sync="['cancellation_window_minutes', 'cancellation_saved']"
            class="bg-surface dark:bg-surface rounded-2xl border border-outline dark:border-outline shadow-card overflow-hidden"
        >
            {{-- Card Header --}}
            <div class="px-5 sm:px-6 py-4 sm:py-5 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                <div class="flex items-center gap-3">
                    <span class="w-9 h-9 rounded-lg bg-primary-subtle flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </span>
                    <div>
                        <h2 class="text-base font-semibold text-on-surface-strong">
                            {{ __('Cancellation Window') }}
                        </h2>
                        <p class="text-xs text-on-surface mt-0.5">
                            {{ __('How long after placing an order can a client cancel for a full refund?') }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Card Body --}}
            <div class="px-5 sm:px-6 py-5 sm:py-6">

                {{-- Validation error --}}
                <div
                    x-show="error !== ''"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="mb-4 flex items-start gap-2 bg-danger-subtle dark:bg-danger-subtle border border-danger/30 rounded-lg px-4 py-3"
                    x-cloak
                >
                    <svg class="w-4 h-4 text-danger mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <p class="text-sm text-danger" x-text="error"></p>
                </div>

                {{-- Gale validation error (x-message pattern) --}}
                <div class="mb-4">
                    <p x-message="cancellation_window_minutes" class="text-sm text-danger"></p>
                </div>

                {{-- Number input with stepper controls --}}
                <div class="flex flex-col sm:flex-row sm:items-end gap-4 sm:gap-6">
                    <div class="flex-1">
                        <label for="cancellation_window_minutes" class="block text-sm font-medium text-on-surface-strong mb-2">
                            {{ __('Window Duration') }}
                        </label>
                        <div class="flex items-center gap-2">
                            {{-- Decrement button --}}
                            <button
                                type="button"
                                @click="decrement()"
                                :disabled="cancellation_window_minutes <= {{ \App\Services\CookSettingsService::MIN_CANCELLATION_WINDOW }}"
                                class="w-10 h-10 flex items-center justify-center rounded-lg border border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt text-on-surface hover:bg-primary-subtle hover:text-primary hover:border-primary/50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors duration-200"
                                :title="'{{ __('Decrease') }}'"
                            >
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                            </button>

                            {{-- Number input --}}
                            <div class="relative flex items-center">
                                <input
                                    type="number"
                                    id="cancellation_window_minutes"
                                    x-model.number="cancellation_window_minutes"
                                    x-name="cancellation_window_minutes"
                                    min="{{ \App\Services\CookSettingsService::MIN_CANCELLATION_WINDOW }}"
                                    max="{{ \App\Services\CookSettingsService::MAX_CANCELLATION_WINDOW }}"
                                    step="1"
                                    class="w-24 h-10 text-center text-lg font-semibold text-on-surface-strong bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors duration-200 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                >
                            </div>

                            {{-- Increment button --}}
                            <button
                                type="button"
                                @click="increment()"
                                :disabled="cancellation_window_minutes >= {{ \App\Services\CookSettingsService::MAX_CANCELLATION_WINDOW }}"
                                class="w-10 h-10 flex items-center justify-center rounded-lg border border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt text-on-surface hover:bg-primary-subtle hover:text-primary hover:border-primary/50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors duration-200"
                                :title="'{{ __('Increase') }}'"
                            >
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                            </button>

                            {{-- Unit label --}}
                            <span class="text-sm text-on-surface font-medium ml-1">
                                {{ __('minutes') }}
                            </span>
                        </div>

                        {{-- Range hint --}}
                        <p class="mt-2 text-xs text-on-surface opacity-70">
                            {{ __('Between :min and :max minutes.', ['min' => \App\Services\CookSettingsService::MIN_CANCELLATION_WINDOW, 'max' => \App\Services\CookSettingsService::MAX_CANCELLATION_WINDOW]) }}
                        </p>
                    </div>

                    {{-- Save button --}}
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            @click="save()"
                            :disabled="$fetching()"
                            class="flex items-center gap-2 px-5 py-2.5 bg-primary text-on-primary font-semibold text-sm rounded-lg hover:bg-primary-hover disabled:opacity-60 disabled:cursor-not-allowed transition-colors duration-200 shadow-sm"
                        >
                            <span x-show="!$fetching()">
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                            </span>
                            <span x-show="$fetching()" x-cloak>
                                <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                            </span>
                            <span x-show="!$fetching()">{{ __('Save') }}</span>
                            <span x-show="$fetching()" x-cloak>{{ __('Saving...') }}</span>
                        </button>

                        {{-- Saved confirmation --}}
                        <span
                            x-show="cancellation_saved"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="flex items-center gap-1.5 text-sm font-medium text-success"
                            x-cloak
                        >
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            {{ __('Saved!') }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Info footer --}}
            <div class="px-5 sm:px-6 py-3 bg-surface-alt dark:bg-surface-alt border-t border-outline dark:border-outline">
                <div class="flex items-start gap-2">
                    <svg class="w-4 h-4 text-info mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                    <p class="text-xs text-on-surface">
                        {{ __('Changes apply to new orders only. Existing orders keep the window that was active when they were placed.') }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Minimum Order Amount Card --}}
        {{-- BR-507: Default 0 XAF (no minimum) --}}
        {{-- BR-508: Range 0–100,000 XAF inclusive --}}
        {{-- BR-509: Integer (whole number) only --}}
        {{-- BR-515: Only cook can modify --}}
        {{-- BR-517: Changes logged via Spatie Activitylog --}}
        <div
            x-data="{
                minimum_order_amount: {{ (int) $minimumOrderAmount }},
                minimum_saved: false,

                save() {
                    this.minimum_saved = false;
                    $action('{{ route('cook.settings.update-minimum-order-amount') }}', {
                        include: ['minimum_order_amount']
                    });
                }
            }"
            x-sync="['minimum_order_amount', 'minimum_saved']"
            class="bg-surface dark:bg-surface rounded-2xl border border-outline dark:border-outline shadow-card overflow-hidden"
        >
            {{-- Card Header --}}
            <div class="px-5 sm:px-6 py-4 sm:py-5 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                <div class="flex items-center gap-3">
                    <span class="w-9 h-9 rounded-lg bg-secondary-subtle flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="1" x2="12" y2="23"></line>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                    </span>
                    <div>
                        <h2 class="text-base font-semibold text-on-surface-strong">
                            {{ __('Minimum Order Amount') }}
                        </h2>
                        <p class="text-xs text-on-surface mt-0.5">
                            {{ __('Set the minimum amount clients must order. Set to 0 for no minimum.') }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Card Body --}}
            <div class="px-5 sm:px-6 py-5 sm:py-6">

                {{-- Gale validation error --}}
                <div class="mb-4">
                    <p x-message="minimum_order_amount" class="text-sm text-danger"></p>
                </div>

                {{-- Input row --}}
                <div class="flex flex-col sm:flex-row sm:items-end gap-4 sm:gap-6">
                    <div class="flex-1">
                        <label for="minimum_order_amount" class="block text-sm font-medium text-on-surface-strong mb-2">
                            {{ __('Minimum Amount') }}
                        </label>
                        <div class="flex items-center gap-2">
                            {{-- Number input --}}
                            <div class="relative flex items-center flex-1 max-w-xs">
                                <input
                                    type="number"
                                    id="minimum_order_amount"
                                    x-model.number="minimum_order_amount"
                                    x-name="minimum_order_amount"
                                    min="{{ \App\Services\CookSettingsService::MIN_ORDER_AMOUNT }}"
                                    max="{{ \App\Services\CookSettingsService::MAX_ORDER_AMOUNT }}"
                                    step="1"
                                    placeholder="0"
                                    class="w-full h-10 px-3 text-left text-base font-semibold text-on-surface-strong bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors duration-200 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                >
                            </div>

                            {{-- Unit label --}}
                            <span class="text-sm text-on-surface font-semibold shrink-0">
                                {{ __('XAF') }}
                            </span>
                        </div>

                        {{-- Range hint --}}
                        <p class="mt-2 text-xs text-on-surface opacity-70">
                            {{ __('Between :min and :max XAF. Use 0 to disable the minimum.', [
                                'min' => number_format(\App\Services\CookSettingsService::MIN_ORDER_AMOUNT),
                                'max' => number_format(\App\Services\CookSettingsService::MAX_ORDER_AMOUNT),
                            ]) }}
                        </p>
                    </div>

                    {{-- Save button --}}
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            @click="save()"
                            :disabled="$fetching()"
                            class="flex items-center gap-2 px-5 py-2.5 bg-primary text-on-primary font-semibold text-sm rounded-lg hover:bg-primary-hover disabled:opacity-60 disabled:cursor-not-allowed transition-colors duration-200 shadow-sm"
                        >
                            <span x-show="!$fetching()">
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                            </span>
                            <span x-show="$fetching()" x-cloak>
                                <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                            </span>
                            <span x-show="!$fetching()">{{ __('Save') }}</span>
                            <span x-show="$fetching()" x-cloak>{{ __('Saving...') }}</span>
                        </button>

                        {{-- Saved confirmation --}}
                        <span
                            x-show="minimum_saved"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="flex items-center gap-1.5 text-sm font-medium text-success"
                            x-cloak
                        >
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            {{ __('Saved!') }}
                        </span>
                    </div>
                </div>

                {{-- Live preview when minimum > 0 --}}
                <template x-if="minimum_order_amount > 0">
                    <div class="mt-4 flex items-center gap-2 bg-info-subtle dark:bg-info-subtle border border-info/30 rounded-lg px-4 py-3">
                        <svg class="w-4 h-4 text-info shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                        <p class="text-xs text-on-surface">
                            {{ __('Clients will need at least') }}
                            <strong class="font-semibold text-on-surface-strong" x-text="new Intl.NumberFormat('en').format(minimum_order_amount) + ' XAF'"></strong>
                            {{ __('in their cart to proceed to checkout.') }}
                        </p>
                    </div>
                </template>
            </div>
        </div>

    </div>

</div>
@endsection
