{{--
    F-149: Payment Method Selection
    BR-345: Available payment methods: MTN Mobile Money, Orange Money, Wallet Balance
    BR-346: Wallet Balance only if admin enabled AND balance >= total
    BR-347: Wallet visible but disabled if balance < total
    BR-348: Mobile money requires phone number, pre-filled from profile or saved methods
    BR-349: Previously used payment methods offered as saved options
    BR-350: Total to pay displayed prominently
    BR-351: Phone number must match Cameroon format
    BR-352: Pay Now triggers F-150 (Flutterwave) or F-153 (wallet)
    BR-353: All text localized via __()
--}}
@extends('layouts.tenant-public')

@section('title', __('Payment') . ' - ' . ($tenant?->name ?? config('app.name')))

@section('content')
<div class="min-h-screen"
    x-data="{
        provider: '{{ addslashes($currentProvider ?? '') }}',
        payment_phone_digits: '{{ addslashes($phoneDigits ?? '') }}',
        phoneError: '',
        savedMethods: {{ Js::from($paymentOptions['saved_methods']->map(fn($m) => ['id' => $m->id, 'provider' => $m->provider, 'phone' => $m->phone, 'label' => $m->label, 'masked_phone' => $m->maskedPhone(), 'provider_label' => $m->providerLabel(), 'is_default' => $m->is_default])) }},

        selectProvider(p) {
            this.provider = p;
            this.phoneError = '';

            if (p === 'wallet') {
                this.payment_phone_digits = '';
                return;
            }

            // Check for a saved method for this provider
            let saved = this.savedMethods.filter(m => m.provider === p);
            if (saved.length > 0) {
                let phone = saved[0].phone;
                if (phone.startsWith('+237')) {
                    phone = phone.substring(4);
                }
                this.payment_phone_digits = phone;
            }
        },

        selectSavedMethod(method) {
            this.provider = method.provider;
            let phone = method.phone;
            if (phone.startsWith('+237')) {
                phone = phone.substring(4);
            }
            this.payment_phone_digits = phone;
            this.phoneError = '';
        },

        formatDisplay() {
            let d = this.payment_phone_digits.replace(/\D/g, '');
            if (d.length >= 6) {
                return d.substring(0, 3) + ' ' + d.substring(3, 6) + ' ' + d.substring(6, 9);
            }
            if (d.length >= 3) {
                return d.substring(0, 3) + ' ' + d.substring(3);
            }
            return d;
        },

        get isMobileMoney() {
            return this.provider === 'mtn_momo' || this.provider === 'orange_money';
        },

        get canSubmit() {
            if (!this.provider) return false;
            if (this.provider === 'wallet') return true;
            return this.payment_phone_digits.replace(/\D/g, '').length >= 9;
        },

        submitPayment() {
            this.phoneError = '';

            if (!this.provider) {
                this.phoneError = '{{ __('Please select a payment method.') }}';
                return;
            }

            if (this.isMobileMoney) {
                let digits = this.payment_phone_digits.replace(/[\s\-()]/g, '');
                if (!digits) {
                    this.phoneError = '{{ __('Phone number is required for mobile money.') }}';
                    return;
                }
                if (digits.length !== 9 || !/^[6]/.test(digits)) {
                    this.phoneError = '{{ __('Please enter a valid Cameroon phone number (+237 followed by 9 digits).') }}';
                    return;
                }
                this.payment_phone_digits = digits;
            }

            $action('{{ route('tenant.checkout.save-payment') }}', {
                include: ['provider', 'payment_phone_digits']
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
            <div class="flex items-center gap-2 text-xs font-medium text-on-surface flex-wrap">
                {{-- Step 1: Delivery Method (completed) --}}
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-success text-on-success text-xs font-bold shrink-0">
                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                </span>
                <span class="text-success font-semibold">{{ __('Delivery Method') }}</span>
                <svg class="w-4 h-4 text-on-surface/30 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>

                {{-- Step 2: Location (completed) --}}
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-success text-on-success text-xs font-bold shrink-0">
                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                </span>
                <span class="text-success font-semibold">{{ __('Location') }}</span>
                <svg class="w-4 h-4 text-on-surface/30 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>

                {{-- Step 3: Phone (completed) --}}
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-success text-on-success text-xs font-bold shrink-0">
                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                </span>
                <span class="text-success font-semibold">{{ __('Phone') }}</span>
                <svg class="w-4 h-4 text-on-surface/30 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>

                {{-- Step 4: Review (completed) --}}
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-success text-on-success text-xs font-bold shrink-0">
                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                </span>
                <span class="text-success font-semibold">{{ __('Review') }}</span>
                <svg class="w-4 h-4 text-on-surface/30 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>

                {{-- Step 5: Payment (current) --}}
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-primary text-on-primary text-xs font-bold shrink-0">5</span>
                <span class="text-primary font-semibold">{{ __('Payment') }}</span>
            </div>
        </div>

        {{-- BR-350: Total displayed prominently --}}
        <div class="mb-6 bg-primary-subtle rounded-xl p-4 sm:p-5 text-center">
            <p class="text-sm font-medium text-on-surface mb-1">{{ __('Total to pay') }}</p>
            <p class="text-2xl sm:text-3xl font-display font-bold text-primary">
                {{ number_format($grandTotal, 0, '.', ',') }} XAF
            </p>
        </div>

        {{-- Page heading --}}
        <div class="mb-6">
            <h2 class="text-lg sm:text-xl font-display font-bold text-on-surface-strong">
                {{ __('Choose your payment method') }}
            </h2>
            <p class="text-sm text-on-surface mt-1">
                {{ __('Select how you want to pay for your order.') }}
            </p>
        </div>

        {{-- Error message --}}
        <div x-show="phoneError" x-cloak class="mb-4 flex items-center gap-2 bg-danger-subtle text-danger rounded-lg px-4 py-3 text-sm font-medium">
            <svg class="w-4 h-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg>
            <span x-text="phoneError"></span>
        </div>

        {{-- Server-side validation errors --}}
        <p x-message="payment_phone_digits" class="mb-4 text-sm text-danger"></p>

        {{-- BR-349: Saved payment methods as quick-select pills --}}
        @if($paymentOptions['saved_methods']->isNotEmpty())
            <div class="mb-6">
                <h3 class="text-sm font-semibold text-on-surface-strong mb-3">{{ __('Saved payment methods') }}</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($paymentOptions['saved_methods'] as $method)
                        <button
                            type="button"
                            @click="selectSavedMethod({{ Js::from(['id' => $method->id, 'provider' => $method->provider, 'phone' => $method->phone, 'label' => $method->label, 'masked_phone' => $method->maskedPhone(), 'provider_label' => $method->providerLabel()]) }})"
                            class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border text-sm font-medium transition-all duration-200 cursor-pointer"
                            :class="provider === '{{ $method->provider }}' && payment_phone_digits.replace(/\D/g, '') === '{{ substr($method->phone, 4) }}'
                                ? 'border-primary bg-primary-subtle text-primary'
                                : 'border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt text-on-surface hover:border-primary/50'"
                        >
                            {{-- Provider dot --}}
                            @if($method->provider === 'mtn_momo')
                                <span class="w-3 h-3 rounded-full shrink-0" style="background-color: #ffcc00;"></span>
                            @else
                                <span class="w-3 h-3 rounded-full shrink-0" style="background-color: #ff6600;"></span>
                            @endif
                            <span>{{ $method->providerLabel() }}</span>
                            <span class="text-on-surface/60">{{ $method->maskedPhone() }}</span>
                            @if($method->label)
                                <span class="text-xs text-on-surface/40">({{ $method->label }})</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- BR-345: Payment method cards --}}
        <div class="space-y-3">
            {{-- MTN Mobile Money card --}}
            @foreach($paymentOptions['providers'] as $providerData)
                <button
                    type="button"
                    @click="selectProvider('{{ $providerData['id'] }}')"
                    class="w-full text-left rounded-xl border-2 p-4 sm:p-5 transition-all duration-200 cursor-pointer"
                    :class="provider === '{{ $providerData['id'] }}'
                        ? 'border-primary bg-primary-subtle/30 shadow-card'
                        : 'border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt hover:border-primary/50'"
                >
                    <div class="flex items-center gap-4">
                        {{-- Provider logo circle --}}
                        <div class="w-12 h-12 rounded-full flex items-center justify-center shrink-0 text-sm font-bold"
                             style="background-color: {{ $providerData['color'] }}; color: {{ $providerData['text_color'] }};">
                            @if($providerData['id'] === 'mtn_momo')
                                MTN
                            @else
                                OM
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-base font-semibold text-on-surface-strong">
                                {{ __($providerData['label']) }}
                            </h3>
                            <p class="text-sm text-on-surface mt-0.5">
                                @if($providerData['id'] === 'mtn_momo')
                                    {{ __('Pay with your MTN Mobile Money account') }}
                                @else
                                    {{ __('Pay with your Orange Money account') }}
                                @endif
                            </p>
                        </div>
                        {{-- Radio indicator --}}
                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0"
                             :class="provider === '{{ $providerData['id'] }}'
                                 ? 'border-primary'
                                 : 'border-outline dark:border-outline'"
                        >
                            <div class="w-2.5 h-2.5 rounded-full bg-primary"
                                 x-show="provider === '{{ $providerData['id'] }}'"
                                 x-transition.scale
                            ></div>
                        </div>
                    </div>
                </button>
            @endforeach

            {{-- BR-346/BR-347: Wallet Balance option --}}
            @if($paymentOptions['wallet']['visible'])
                <button
                    type="button"
                    @if($paymentOptions['wallet']['enabled'])
                        @click="selectProvider('wallet')"
                    @endif
                    class="w-full text-left rounded-xl border-2 p-4 sm:p-5 transition-all duration-200"
                    :class="provider === 'wallet'
                        ? 'border-primary bg-primary-subtle/30 shadow-card cursor-pointer'
                        : '{{ $paymentOptions['wallet']['enabled'] ? 'border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt hover:border-primary/50 cursor-pointer' : 'border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt opacity-60 cursor-not-allowed' }}'"
                >
                    <div class="flex items-center gap-4">
                        {{-- Wallet icon --}}
                        <div class="w-12 h-12 rounded-full bg-success-subtle flex items-center justify-center shrink-0">
                            <svg class="w-6 h-6 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"></path><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"></path></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-base font-semibold text-on-surface-strong">
                                {{ __('Wallet Balance') }}
                            </h3>
                            <p class="text-sm mt-0.5 {{ $paymentOptions['wallet']['sufficient'] ? 'text-success' : 'text-on-surface/60' }}">
                                {{ __('Available:') }} {{ number_format($paymentOptions['wallet']['balance'], 0, '.', ',') }} XAF
                            </p>
                            @if(!$paymentOptions['wallet']['sufficient'])
                                <p class="text-xs text-danger mt-0.5">
                                    {{ __('Insufficient balance') }}
                                </p>
                            @endif
                        </div>
                        {{-- Radio indicator --}}
                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0"
                             :class="provider === 'wallet'
                                 ? 'border-primary'
                                 : 'border-outline dark:border-outline'"
                        >
                            <div class="w-2.5 h-2.5 rounded-full bg-primary"
                                 x-show="provider === 'wallet'"
                                 x-transition.scale
                            ></div>
                        </div>
                    </div>
                </button>
            @endif
        </div>

        {{-- BR-348: Phone number input for mobile money (shown when mobile money selected) --}}
        <div x-show="isMobileMoney" x-cloak x-transition class="mt-6">
            <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-8 h-8 rounded-full bg-primary-subtle flex items-center justify-center">
                        <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-on-surface-strong">
                            <span x-show="provider === 'mtn_momo'">{{ __('MTN MoMo Number') }}</span>
                            <span x-show="provider === 'orange_money'">{{ __('Orange Money Number') }}</span>
                        </h3>
                        <p class="text-xs text-on-surface">{{ __('Enter the number to charge') }}</p>
                    </div>
                </div>

                {{-- Phone input with +237 prefix --}}
                <div>
                    <label for="payment-phone-input" class="block text-sm font-medium text-on-surface mb-1.5">
                        {{ __('Mobile Money Number') }} <span class="text-danger">*</span>
                    </label>
                    <div class="flex">
                        <div class="flex items-center justify-center px-3 py-2.5 rounded-l-lg border border-r-0 border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface font-medium text-sm select-none">
                            +237
                        </div>
                        <input
                            id="payment-phone-input"
                            type="tel"
                            x-model="payment_phone_digits"
                            x-name="payment_phone_digits"
                            maxlength="12"
                            placeholder="6XX XXX XXX"
                            autocomplete="tel"
                            class="flex-1 pl-3 pr-3 py-2.5 rounded-r-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200"
                            @input="phoneError = ''"
                        >
                    </div>

                    {{-- Formatted preview --}}
                    <div x-show="payment_phone_digits.replace(/\D/g, '').length > 0" x-cloak class="mt-2 text-xs text-on-surface/60">
                        {{ __('Number:') }} <span class="font-medium text-on-surface" x-text="'+237 ' + formatDisplay()"></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Action buttons (hidden on mobile since sticky bar is shown) --}}
        <div class="mt-6 flex flex-col sm:flex-row gap-3 hidden sm:flex">
            <a href="{{ $backUrl }}" class="flex-1 h-11 inline-flex items-center justify-center gap-2 border border-outline dark:border-outline text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt font-medium rounded-lg transition-all duration-200" x-navigate>
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                {{ __('Back') }}
            </a>
            <button
                @click="submitPayment()"
                class="flex-1 h-11 inline-flex items-center justify-center gap-2 bg-primary hover:bg-primary-hover text-on-primary font-semibold rounded-lg shadow-card transition-all duration-200 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="$fetching() || !canSubmit"
            >
                <span x-show="!$fetching()">{{ __('Pay Now') }}</span>
                <span x-show="$fetching()" x-cloak>{{ __('Processing...') }}</span>
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"></rect><path d="M2 10h20"></path></svg>
            </button>
        </div>

        {{-- Spacer for mobile sticky bar --}}
        <div class="h-24 sm:hidden"></div>
    </div>

    {{-- Mobile sticky pay bar --}}
    <div
        class="sm:hidden fixed bottom-0 left-0 right-0 z-40 bg-surface dark:bg-surface border-t border-outline dark:border-outline px-4 py-3 shadow-dropdown"
        x-show="canSubmit"
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
                <p class="text-xs font-medium text-on-surface">{{ __('Total') }}</p>
                <p class="text-base font-bold text-primary">{{ number_format($grandTotal, 0, '.', ',') }} XAF</p>
            </div>
            <button
                @click="submitPayment()"
                class="h-11 px-6 bg-primary hover:bg-primary-hover text-on-primary font-semibold rounded-lg shadow-card transition-all duration-200 cursor-pointer inline-flex items-center gap-2"
                :disabled="$fetching()"
            >
                <span x-show="!$fetching()">{{ __('Pay Now') }}</span>
                <span x-show="$fetching()" x-cloak>{{ __('Processing...') }}</span>
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"></rect><path d="M2 10h20"></path></svg>
            </button>
        </div>
    </div>
</div>
@endsection
