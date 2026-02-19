{{--
    F-140: Delivery/Pickup Choice Selection
    BR-264: Client must choose delivery or pickup to proceed
    BR-265: Delivery leads to delivery location selection (F-141)
    BR-266: Pickup leads to pickup location selection (F-142)
    BR-267: If no pickup locations, pickup hidden/disabled
    BR-268: If no delivery areas, delivery hidden/disabled
    BR-269: Delivery shows variable delivery fees note
    BR-270: Pickup shows free note
    BR-271: Choice persists via session
    BR-272: Requires authentication
    BR-273: All text localized
--}}
@extends('layouts.tenant-public')

@section('title', __('Checkout') . ' - ' . ($tenant?->name ?? config('app.name')))

@section('content')
<div class="min-h-screen"
    x-data="{
        delivery_method: '{{ $currentMethod ?? '' }}',
        checkoutError: '',
        formatPrice(amount) {
            return new Intl.NumberFormat('en').format(amount) + ' XAF';
        },
        selectMethod(method) {
            this.delivery_method = method;
            this.checkoutError = '';
        },
        submitMethod() {
            if (!this.delivery_method) {
                this.checkoutError = '{{ __('Please select a delivery method.') }}';
                return;
            }
            $action('{{ route('tenant.checkout.save-delivery-method') }}', {
                include: ['delivery_method']
            });
        }
    }"
    x-sync="['checkoutError']"
>
    {{-- Back navigation --}}
    <div class="bg-surface dark:bg-surface border-b border-outline dark:border-outline">
        <div class="max-w-3xl mx-auto px-4 sm:px-6">
            <div class="h-12 flex items-center justify-between">
                <a href="{{ url('/cart') }}" class="flex items-center gap-2 text-sm font-medium text-on-surface hover:text-primary transition-colors duration-200" x-navigate>
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                    {{ __('Back to Cart') }}
                </a>
                <h1 class="text-sm font-semibold text-on-surface-strong">{{ __('Checkout') }}</h1>
            </div>
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
        {{-- Step indicator --}}
        <div class="mb-6">
            <div class="flex items-center gap-2 text-xs font-medium text-on-surface">
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-primary text-on-primary text-xs font-bold">1</span>
                <span class="text-primary font-semibold">{{ __('Delivery Method') }}</span>
                <svg class="w-4 h-4 text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-outline text-on-surface text-xs font-bold">2</span>
                <span class="text-on-surface/50">{{ __('Location') }}</span>
                <svg class="w-4 h-4 text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-outline text-on-surface text-xs font-bold">3</span>
                <span class="text-on-surface/50">{{ __('Payment') }}</span>
            </div>
        </div>

        {{-- Error message --}}
        <div x-show="checkoutError" x-cloak class="mb-4 flex items-center gap-2 bg-danger-subtle text-danger rounded-lg px-4 py-3 text-sm font-medium">
            <svg class="w-4 h-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg>
            <span x-text="checkoutError"></span>
        </div>

        @if ($options['has_delivery'] || $options['has_pickup'])
            {{-- Page heading --}}
            <div class="mb-6">
                <h2 class="text-lg sm:text-xl font-display font-bold text-on-surface-strong">
                    {{ __('How would you like to receive your order?') }}
                </h2>
                <p class="text-sm text-on-surface mt-1">
                    {{ __('Choose your preferred delivery method below.') }}
                </p>
            </div>

            {{-- Delivery method cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
                @if ($options['has_delivery'])
                    {{-- Delivery card --}}
                    <button
                        type="button"
                        @click="selectMethod('delivery')"
                        class="relative flex flex-col items-center text-center p-6 rounded-xl border-2 transition-all duration-200 cursor-pointer group"
                        :class="delivery_method === 'delivery'
                            ? 'border-primary bg-primary-subtle shadow-card'
                            : 'border-outline dark:border-outline bg-surface dark:bg-surface hover:border-primary/50 hover:shadow-card'"
                    >
                        {{-- Selected indicator --}}
                        <div
                            class="absolute top-3 right-3 w-6 h-6 rounded-full border-2 flex items-center justify-center transition-all duration-200"
                            :class="delivery_method === 'delivery'
                                ? 'border-primary bg-primary'
                                : 'border-outline'"
                        >
                            <svg x-show="delivery_method === 'delivery'" class="w-4 h-4 text-on-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                        </div>

                        {{-- Icon --}}
                        <div class="w-14 h-14 rounded-full flex items-center justify-center mb-4 transition-colors duration-200"
                            :class="delivery_method === 'delivery'
                                ? 'bg-primary text-on-primary'
                                : 'bg-surface-alt dark:bg-surface-alt text-on-surface'"
                        >
                            <svg class="w-7 h-7" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"></path><path d="M15 18H9"></path><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path><circle cx="17" cy="18" r="2"></circle><circle cx="7" cy="18" r="2"></circle></svg>
                        </div>

                        <h3 class="text-base font-semibold text-on-surface-strong mb-1">{{ __('Delivery') }}</h3>
                        <p class="text-sm text-on-surface">{{ __('Delivered to your door') }}</p>

                        {{-- BR-269: Fee note --}}
                        <div class="mt-3 flex items-center gap-1.5 text-xs font-medium"
                            :class="delivery_method === 'delivery' ? 'text-primary' : 'text-on-surface/60'"
                        >
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                            {{ __('Delivery fees vary by location') }}
                        </div>
                    </button>
                @endif

                @if ($options['has_pickup'])
                    {{-- Pickup card --}}
                    <button
                        type="button"
                        @click="selectMethod('pickup')"
                        class="relative flex flex-col items-center text-center p-6 rounded-xl border-2 transition-all duration-200 cursor-pointer group"
                        :class="delivery_method === 'pickup'
                            ? 'border-primary bg-primary-subtle shadow-card'
                            : 'border-outline dark:border-outline bg-surface dark:bg-surface hover:border-primary/50 hover:shadow-card'"
                    >
                        {{-- Selected indicator --}}
                        <div
                            class="absolute top-3 right-3 w-6 h-6 rounded-full border-2 flex items-center justify-center transition-all duration-200"
                            :class="delivery_method === 'pickup'
                                ? 'border-primary bg-primary'
                                : 'border-outline'"
                        >
                            <svg x-show="delivery_method === 'pickup'" class="w-4 h-4 text-on-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                        </div>

                        {{-- Icon --}}
                        <div class="w-14 h-14 rounded-full flex items-center justify-center mb-4 transition-colors duration-200"
                            :class="delivery_method === 'pickup'
                                ? 'bg-primary text-on-primary'
                                : 'bg-surface-alt dark:bg-surface-alt text-on-surface'"
                        >
                            <svg class="w-7 h-7" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"></path><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"></path><path d="M2 7h20"></path><path d="M22 7v3a2 2 0 0 1-2 2a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 16 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 12 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 8 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 4 12a2 2 0 0 1-2-2V7"></path></svg>
                        </div>

                        <h3 class="text-base font-semibold text-on-surface-strong mb-1">{{ __('Pickup') }}</h3>
                        <p class="text-sm text-on-surface">{{ __("Pick up at cook's location") }}</p>

                        {{-- BR-270: Free note --}}
                        <div class="mt-3 flex items-center gap-1.5 text-xs font-medium"
                            :class="delivery_method === 'pickup' ? 'text-success' : 'text-on-surface/60'"
                        >
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><path d="m9 11 3 3L22 4"></path></svg>
                            {{ __('Pickup is free!') }}
                        </div>
                    </button>
                @endif
            </div>

            {{-- Order summary --}}
            <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-4 shadow-card mb-6">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-on-surface">{{ __('Cart Subtotal') }}</span>
                    <span class="text-base font-bold text-primary">{{ number_format($cartSummary['total'], 0, '.', ',') }} XAF</span>
                </div>
                <div class="flex items-center justify-between mt-2">
                    <span class="text-sm font-medium text-on-surface">{{ __('Delivery Fee') }}</span>
                    <span class="text-sm text-on-surface" x-text="delivery_method === 'pickup' ? '{{ __('Free') }}' : delivery_method === 'delivery' ? '{{ __('Calculated next') }}' : '—'"></span>
                </div>
            </div>

            {{-- Action buttons --}}
            <div class="flex flex-col sm:flex-row gap-3">
                <a href="{{ url('/cart') }}" class="flex-1 h-11 inline-flex items-center justify-center gap-2 border border-outline dark:border-outline text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt font-medium rounded-lg transition-all duration-200" x-navigate>
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                    {{ __('Back to Cart') }}
                </a>
                <button
                    @click="submitMethod()"
                    class="flex-1 h-11 inline-flex items-center justify-center gap-2 bg-primary hover:bg-primary-hover text-on-primary font-semibold rounded-lg shadow-card transition-all duration-200 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="$fetching() || !delivery_method"
                >
                    <span x-show="!$fetching()">{{ __('Continue') }}</span>
                    <span x-show="$fetching()" x-cloak>{{ __('Processing...') }}</span>
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                </button>
            </div>
        @else
            {{-- Edge case: Neither delivery nor pickup configured --}}
            <div class="text-center py-16">
                <div class="w-20 h-20 mx-auto bg-warning-subtle rounded-full flex items-center justify-center mb-6">
                    <svg class="w-10 h-10 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                </div>
                <h2 class="text-xl font-display font-bold text-on-surface-strong mb-2">{{ __('Ordering Not Available') }}</h2>
                <p class="text-sm text-on-surface mb-6 max-w-md mx-auto">{{ __('This cook has not configured delivery or pickup. Contact them via WhatsApp.') }}</p>
                @php
                    $whatsappNumber = $tenant->whatsapp_number ?? $tenant->phone_number ?? null;
                @endphp
                @if ($whatsappNumber)
                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $whatsappNumber) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 h-11 px-6 bg-success hover:bg-success/90 text-on-success font-semibold rounded-lg shadow-card transition-all duration-200">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.49a.75.75 0 0 0 .921.921l4.456-1.495A11.952 11.952 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22a9.94 9.94 0 0 1-5.39-1.583l-.387-.232-2.646.888.888-2.646-.232-.387A9.94 9.94 0 0 1 2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>
                        {{ __('Contact via WhatsApp') }}
                    </a>
                @endif
                <div class="mt-4">
                    <a href="{{ url('/') }}" class="inline-flex items-center gap-2 text-sm font-medium text-primary hover:text-primary-hover transition-colors duration-200" x-navigate>
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                        {{ __('Back to Menu') }}
                    </a>
                </div>
            </div>
        @endif
    </div>

    {{-- Mobile sticky action bar --}}
    @if ($options['has_delivery'] || $options['has_pickup'])
    <div
        class="sm:hidden fixed bottom-0 left-0 right-0 z-40 bg-surface dark:bg-surface border-t border-outline dark:border-outline px-4 py-3 shadow-dropdown"
        x-show="delivery_method"
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
                <p class="text-xs text-on-surface" x-text="delivery_method === 'delivery' ? '{{ __('Delivery') }}' : '{{ __('Pickup') }}'"></p>
                <p class="text-xs text-on-surface/60" x-text="delivery_method === 'pickup' ? '{{ __('Free') }}' : '{{ __('Fee calculated next') }}'"></p>
            </div>
            <button
                @click="submitMethod()"
                class="h-11 px-6 bg-primary hover:bg-primary-hover text-on-primary font-semibold rounded-lg shadow-card transition-all duration-200 cursor-pointer inline-flex items-center gap-2"
                :disabled="$fetching()"
            >
                <span x-show="!$fetching()">{{ __('Continue') }}</span>
                <span x-show="$fetching()" x-cloak>{{ __('Processing...') }}</span>
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
            </button>
        </div>
    </div>
    @endif
</div>
@endsection
