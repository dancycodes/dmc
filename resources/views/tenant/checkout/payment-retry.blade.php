{{--
    F-152: Payment Retry with Timeout
    BR-376: On payment failure, order remains in Pending Payment for retry window
    BR-378: A visible countdown timer shows remaining retry time
    BR-379: Maximum 3 retry attempts allowed per order
    BR-383: Failure reason from Flutterwave displayed to client
    BR-384: After retry limit, Retry button is disabled
    BR-386: All text localized via __()
--}}
@extends('layouts.tenant-public')

@section('title', __('Retry Payment') . ' - ' . ($tenant?->name ?? config('app.name')))

@section('content')
@php
    $canRetry = $retryData['can_retry'];
    $isExpired = $retryData['is_expired'];
    $isRetriesExhausted = $retryData['is_retries_exhausted'];
    $failureReason = $retryData['failure_reason'];
    $retryCount = $retryData['retry_count'];
    $maxRetries = $retryData['max_retries'];
    $remainingSeconds = $retryData['remaining_seconds'];
    $isCancelled = $order->status === \App\Models\Order::STATUS_CANCELLED;
@endphp
<div class="min-h-screen"
    x-data="{
        remainingSeconds: {{ $remainingSeconds }},
        retryCount: {{ $retryCount }},
        maxRetries: {{ $maxRetries }},
        canRetry: {{ $canRetry ? 'true' : 'false' }},
        isExpired: {{ $isExpired ? 'true' : 'false' }},
        isRetriesExhausted: {{ $isRetriesExhausted ? 'true' : 'false' }},
        isCancelled: {{ $isCancelled ? 'true' : 'false' }},
        timerInterval: null,
        provider: '{{ addslashes($currentProvider) }}',
        payment_phone_digits: '{{ addslashes($phoneDigits) }}',

        init() {
            if (this.remainingSeconds > 0 && this.canRetry) {
                this.startTimer();
            }
        },

        startTimer() {
            this.timerInterval = setInterval(() => {
                if (this.remainingSeconds > 0) {
                    this.remainingSeconds--;
                }
                if (this.remainingSeconds <= 0) {
                    this.isExpired = true;
                    this.canRetry = false;
                    clearInterval(this.timerInterval);
                }
            }, 1000);
        },

        get minutes() {
            return Math.floor(this.remainingSeconds / 60);
        },

        get seconds() {
            return this.remainingSeconds % 60;
        },

        get formattedTime() {
            return String(this.minutes).padStart(2, '0') + ':' + String(this.seconds).padStart(2, '0');
        },

        get currentAttempt() {
            return this.retryCount + 1;
        },

        get providerLabel() {
            if (this.provider === 'mtn_momo') return 'MTN Mobile Money';
            if (this.provider === 'orange_money') return 'Orange Money';
            return this.provider;
        },

        selectSavedMethod(methodProvider, phone) {
            this.provider = methodProvider;
            /* Strip +237 prefix for the input */
            if (phone.startsWith('+237')) {
                phone = phone.substring(4);
            }
            this.payment_phone_digits = phone;
        },

        retryPayment() {
            if (!this.canRetry) return;
            $action('{{ url('/checkout/payment/retry/' . $order->id) }}', {
                method: 'POST',
                include: ['provider', 'payment_phone_digits']
            });
        },

        cancelOrder() {
            $action('{{ url('/checkout/payment/cancel/' . $order->id) }}');
        }
    }"
    x-init="init()"
    x-sync="['provider', 'payment_phone_digits']"
>
    {{-- Header --}}
    <div class="bg-surface dark:bg-surface border-b border-outline dark:border-outline">
        <div class="max-w-3xl mx-auto px-4 sm:px-6">
            <div class="h-12 flex items-center justify-center">
                <h1 class="text-sm font-semibold text-on-surface-strong">{{ __('Retry Payment') }}</h1>
            </div>
        </div>
    </div>

    <div class="max-w-lg mx-auto px-4 sm:px-6 py-6 sm:py-10">
        {{-- Status indicator: Cancelled / Expired / Retries Exhausted --}}
        <template x-if="isCancelled || isExpired || isRetriesExhausted">
            <div>
                {{-- Error icon --}}
                <div class="text-center mb-6">
                    <div class="w-20 h-20 rounded-full mx-auto mb-4 flex items-center justify-center"
                         :class="isRetriesExhausted ? 'bg-danger-subtle' : 'bg-warning-subtle'">
                        <template x-if="isRetriesExhausted">
                            <svg class="w-10 h-10 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg>
                        </template>
                        <template x-if="!isRetriesExhausted">
                            <svg class="w-10 h-10 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        </template>
                    </div>

                    <h2 class="text-xl sm:text-2xl font-display font-bold text-on-surface-strong mb-2">
                        <template x-if="isRetriesExhausted">
                            <span>{{ __('Maximum retry attempts reached') }}</span>
                        </template>
                        <template x-if="isExpired && !isRetriesExhausted">
                            <span>{{ __('Payment time expired') }}</span>
                        </template>
                        <template x-if="isCancelled && !isExpired && !isRetriesExhausted">
                            <span>{{ __('Order cancelled') }}</span>
                        </template>
                    </h2>

                    <p class="text-base text-on-surface">
                        <template x-if="isRetriesExhausted">
                            <span>{{ __('Maximum retry attempts reached. Your order has been cancelled.') }}</span>
                        </template>
                        <template x-if="isExpired && !isRetriesExhausted">
                            <span>{{ __('Order expired. Payment was not completed within the allowed time.') }}</span>
                        </template>
                        <template x-if="isCancelled && !isExpired && !isRetriesExhausted">
                            <span>{{ __('This order has been cancelled.') }}</span>
                        </template>
                    </p>
                </div>

                {{-- Order info --}}
                <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-4 mb-6">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-on-surface">{{ __('Order Number') }}</span>
                        <span class="font-mono font-semibold text-on-surface-strong">{{ $order->order_number }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm mt-2">
                        <span class="text-on-surface">{{ __('Amount') }}</span>
                        <span class="font-semibold text-on-surface-strong">{{ $order->formattedGrandTotal() }}</span>
                    </div>
                </div>

                {{-- Start new order button --}}
                <a
                    href="{{ url('/') }}"
                    class="w-full h-12 bg-primary hover:bg-primary-hover text-on-primary font-semibold rounded-lg shadow-card transition-all duration-200 inline-flex items-center justify-center gap-2"
                    x-navigate
                >
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
                    {{ __('Start a new order') }}
                </a>
            </div>
        </template>

        {{-- Active retry state --}}
        <template x-if="!isCancelled && !isExpired && !isRetriesExhausted">
            <div>
                {{-- Failure message --}}
                <div class="bg-danger-subtle rounded-xl p-4 mb-6">
                    <div class="flex gap-3">
                        <svg class="w-5 h-5 text-danger shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg>
                        <div>
                            <p class="text-sm font-semibold text-danger mb-1">{{ __('Payment failed') }}</p>
                            @if($failureReason)
                                <p class="text-sm text-on-surface">{{ $failureReason }}</p>
                            @else
                                <p class="text-sm text-on-surface">{{ __('Payment could not be completed. Please try again.') }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Countdown timer + Attempt counter --}}
                <div class="flex items-center justify-between mb-6 gap-4">
                    {{-- Timer --}}
                    <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline px-4 py-3 text-center shadow-card flex-1">
                        <p class="text-xs font-medium text-on-surface uppercase tracking-wider mb-1">{{ __('Time remaining') }}</p>
                        <p class="text-2xl font-mono font-bold text-on-surface-strong" x-text="formattedTime"></p>
                    </div>

                    {{-- Attempt counter --}}
                    <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline px-4 py-3 text-center shadow-card flex-1">
                        <p class="text-xs font-medium text-on-surface uppercase tracking-wider mb-1">{{ __('Attempt') }}</p>
                        <p class="text-2xl font-mono font-bold text-on-surface-strong">
                            <span x-text="currentAttempt"></span><span class="text-on-surface text-base">/{{ $maxRetries }}</span>
                        </p>
                    </div>
                </div>

                {{-- Order info --}}
                <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-4 mb-6">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-on-surface">{{ __('Order Number') }}</span>
                        <span class="font-mono font-semibold text-on-surface-strong">{{ $order->order_number }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm mt-2">
                        <span class="text-on-surface">{{ __('Amount') }}</span>
                        <span class="font-semibold text-primary">{{ $order->formattedGrandTotal() }}</span>
                    </div>
                </div>

                {{-- Payment method selection for retry --}}
                <div class="mb-6">
                    <h3 class="text-sm font-semibold text-on-surface-strong mb-3">{{ __('Payment method') }}</h3>

                    {{-- Saved methods --}}
                    @if($savedMethods->isNotEmpty())
                        <div class="space-y-2 mb-4">
                            @foreach($savedMethods as $method)
                                @php
                                    $methodProvider = $method->provider === 'mtn_momo' ? 'mtn_momo' : 'orange_money';
                                    $methodPhone = $method->phone;
                                @endphp
                                <button
                                    type="button"
                                    @click="selectSavedMethod('{{ $methodProvider }}', '{{ $methodPhone }}')"
                                    class="w-full flex items-center gap-3 p-3 rounded-lg border transition-all duration-200 cursor-pointer"
                                    :class="provider === '{{ $methodProvider }}' && payment_phone_digits === '{{ str_starts_with($methodPhone, '+237') ? substr($methodPhone, 4) : $methodPhone }}'
                                        ? 'border-primary bg-primary-subtle/30 ring-1 ring-primary'
                                        : 'border-outline dark:border-outline hover:border-primary/50'"
                                >
                                    <span class="w-8 h-8 rounded-full flex items-center justify-center shrink-0"
                                          style="background: {{ $method->provider === 'mtn_momo' ? '#ffcc00' : '#ff6600' }}">
                                        <span class="text-xs font-bold {{ $method->provider === 'mtn_momo' ? 'text-black' : 'text-white' }}">
                                            {{ $method->provider === 'mtn_momo' ? 'MTN' : 'OM' }}
                                        </span>
                                    </span>
                                    <div class="text-left flex-1 min-w-0">
                                        <p class="text-sm font-medium text-on-surface-strong">{{ $method->label ?? ($method->provider === 'mtn_momo' ? __('MTN Mobile Money') : __('Orange Money')) }}</p>
                                        <p class="text-xs text-on-surface">{{ $method->maskedPhone() }}</p>
                                    </div>
                                    @if($method->is_default)
                                        <span class="text-xs font-medium text-primary bg-primary-subtle px-2 py-0.5 rounded-full shrink-0">{{ __('Default') }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>

                        <div class="relative flex items-center gap-3 mb-4">
                            <div class="flex-1 h-px bg-outline"></div>
                            <span class="text-xs text-on-surface">{{ __('or enter a different number') }}</span>
                            <div class="flex-1 h-px bg-outline"></div>
                        </div>
                    @endif

                    {{-- Provider selection --}}
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <button
                            type="button"
                            @click="provider = 'mtn_momo'"
                            class="flex items-center justify-center gap-2 p-3 rounded-lg border transition-all duration-200 cursor-pointer"
                            :class="provider === 'mtn_momo'
                                ? 'border-primary bg-primary-subtle/30 ring-1 ring-primary'
                                : 'border-outline dark:border-outline hover:border-primary/50'"
                        >
                            <span class="w-6 h-6 rounded-full flex items-center justify-center shrink-0" style="background: #ffcc00">
                                <span class="text-[10px] font-bold text-black">MTN</span>
                            </span>
                            <span class="text-sm font-medium text-on-surface-strong">{{ __('MTN MoMo') }}</span>
                        </button>

                        <button
                            type="button"
                            @click="provider = 'orange_money'"
                            class="flex items-center justify-center gap-2 p-3 rounded-lg border transition-all duration-200 cursor-pointer"
                            :class="provider === 'orange_money'
                                ? 'border-primary bg-primary-subtle/30 ring-1 ring-primary'
                                : 'border-outline dark:border-outline hover:border-primary/50'"
                        >
                            <span class="w-6 h-6 rounded-full flex items-center justify-center shrink-0" style="background: #ff6600">
                                <span class="text-[10px] font-bold text-white">OM</span>
                            </span>
                            <span class="text-sm font-medium text-on-surface-strong">{{ __('Orange Money') }}</span>
                        </button>
                    </div>

                    {{-- Phone input --}}
                    <div>
                        <label class="block text-sm font-medium text-on-surface-strong mb-1.5">{{ __('Payment phone number') }}</label>
                        <div class="flex">
                            <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt text-sm text-on-surface font-medium">
                                +237
                            </span>
                            <input
                                type="tel"
                                x-name="payment_phone_digits"
                                x-model="payment_phone_digits"
                                placeholder="6XXXXXXXX"
                                maxlength="9"
                                class="flex-1 h-11 px-3 rounded-r-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder:text-on-surface/50 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-all duration-200 text-sm"
                            >
                        </div>
                        <p x-message="payment_phone_digits" class="mt-1 text-sm text-danger"></p>
                    </div>
                </div>

                {{-- Retry error message --}}
                <p x-message="retry_error" class="mb-4 text-sm text-danger text-center"></p>

                {{-- Retry button --}}
                <button
                    @click="retryPayment()"
                    class="w-full h-12 bg-primary hover:bg-primary-hover text-on-primary font-semibold rounded-lg shadow-card transition-all duration-200 cursor-pointer inline-flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="!canRetry || $fetching()"
                >
                    <template x-if="$fetching()">
                        <svg class="w-5 h-5 animate-spin" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                    </template>
                    <template x-if="!$fetching()">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"></path><path d="M16 16h5v5"></path></svg>
                    </template>
                    <span x-show="!$fetching()">{{ __('Retry Payment') }}</span>
                    <span x-show="$fetching()" x-cloak>{{ __('Processing...') }}</span>
                </button>

                {{-- Cancel option --}}
                <div class="text-center mt-4">
                    <button
                        @click="cancelOrder()"
                        class="text-sm font-medium text-danger hover:text-danger/80 transition-colors duration-200 cursor-pointer"
                        :disabled="$fetching()"
                    >
                        {{ __('Cancel order') }}
                    </button>
                </div>
            </div>
        </template>
    </div>
</div>
@endsection
