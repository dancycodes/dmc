{{--
    F-150: Flutterwave Payment Initiation - Waiting Page
    BR-360: The UI shows a "Waiting for payment" loading state after initiation
    BR-361: Timeout after 15 minutes if no webhook confirmation received
    BR-362: Initiation errors are displayed to the client with actionable messages
    BR-363: All text must be localized via __()
--}}
@extends('layouts.tenant-public')

@section('title', __('Payment Processing') . ' - ' . ($tenant?->name ?? config('app.name')))

@section('content')
<div class="min-h-screen"
    x-data="{
        paymentStatus: '{{ $paymentStatus['status'] ?? 'pending' }}',
        remainingSeconds: {{ $remainingSeconds ?? 0 }},
        errorMessage: '{{ addslashes($errorMessage ?? '') }}',
        orderId: {{ $order->id }},
        timerInterval: null,

        init() {
            if (this.paymentStatus === 'pending' && this.remainingSeconds > 0) {
                this.startTimer();
            }
        },

        startTimer() {
            this.timerInterval = setInterval(() => {
                if (this.remainingSeconds > 0) {
                    this.remainingSeconds--;
                }
                if (this.remainingSeconds <= 0 && this.paymentStatus === 'pending') {
                    this.paymentStatus = 'timed_out';
                    this.errorMessage = '{{ __('Payment timed out.') }}';
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

        get isPending() {
            return this.paymentStatus === 'pending';
        },

        get isTimedOut() {
            return this.paymentStatus === 'timed_out';
        },

        get isFailed() {
            return this.paymentStatus === 'failed' || this.paymentStatus === 'timed_out';
        },

        pollStatus() {
            if (!this.isPending) return;
            $action('{{ url('/checkout/payment/check-status') }}/' + this.orderId);
        },

        cancelPayment() {
            if (this.timerInterval) clearInterval(this.timerInterval);
            $action('{{ url('/checkout/payment/cancel') }}/' + this.orderId);
        },

        retryPayment() {
            window.location.href = '{{ url('/checkout/payment') }}';
        }
    }"
    x-init="init()"
>
    {{-- Back navigation --}}
    <div class="bg-surface dark:bg-surface border-b border-outline dark:border-outline">
        <div class="max-w-3xl mx-auto px-4 sm:px-6">
            <div class="h-12 flex items-center justify-center">
                <h1 class="text-sm font-semibold text-on-surface-strong">{{ __('Payment Processing') }}</h1>
            </div>
        </div>
    </div>

    <div class="max-w-lg mx-auto px-4 sm:px-6 py-8 sm:py-12">
        {{-- Pending state: Waiting for payment --}}
        <div x-show="isPending" x-cloak>
            {{-- Phone illustration --}}
            <div class="flex justify-center mb-8">
                <div class="relative">
                    {{-- Phone outline --}}
                    <div class="w-24 h-40 rounded-2xl border-3 border-primary bg-primary-subtle/30 flex items-center justify-center">
                        {{-- Screen content --}}
                        <div class="text-center">
                            <svg class="w-8 h-8 text-primary mx-auto animate-spin-slow" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                        </div>
                    </div>
                    {{-- Notification badge --}}
                    <div class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-warning flex items-center justify-center animate-bounce">
                        <svg class="w-3.5 h-3.5 text-on-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path></svg>
                    </div>
                </div>
            </div>

            {{-- Main message --}}
            <div class="text-center mb-6">
                <h2 class="text-xl sm:text-2xl font-display font-bold text-on-surface-strong mb-2">
                    {{ __('Check your phone') }}
                </h2>
                <p class="text-base text-on-surface">
                    {{ __('A payment prompt has been sent to your phone. Please authorize the payment of') }}
                    <span class="font-bold text-primary">{{ $order->formattedGrandTotal() }}</span>.
                </p>
            </div>

            {{-- Countdown timer --}}
            <div class="flex justify-center mb-8">
                <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline px-6 py-4 text-center shadow-card">
                    <p class="text-xs font-medium text-on-surface uppercase tracking-wider mb-1">{{ __('Time remaining') }}</p>
                    <p class="text-3xl font-mono font-bold text-on-surface-strong" x-text="formattedTime"></p>
                </div>
            </div>

            {{-- Polling indicator --}}
            <div
                x-show="isPending"
                x-interval.5s.visible="pollStatus()"
                class="flex items-center justify-center gap-2 text-sm text-on-surface mb-6"
            >
                <span class="relative flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-success"></span>
                </span>
                {{ __('Waiting for payment confirmation...') }}
            </div>

            {{-- Instructions --}}
            <div class="bg-info-subtle rounded-xl p-4 mb-6">
                <div class="flex gap-3">
                    <svg class="w-5 h-5 text-info shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                    <div>
                        <p class="text-sm font-medium text-on-surface-strong mb-1">{{ __('How to complete your payment') }}</p>
                        <ol class="text-sm text-on-surface space-y-1 list-decimal list-inside">
                            <li>{{ __('You will receive a USSD prompt on your phone') }}</li>
                            <li>{{ __('Enter your mobile money PIN to authorize') }}</li>
                            <li>{{ __('Wait for confirmation - do not close this page') }}</li>
                        </ol>
                    </div>
                </div>
            </div>

            {{-- Order reference --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-4 mb-6">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-on-surface">{{ __('Order Number') }}</span>
                    <span class="font-mono font-semibold text-on-surface-strong">{{ $order->order_number }}</span>
                </div>
                <div class="flex items-center justify-between text-sm mt-2">
                    <span class="text-on-surface">{{ __('Amount') }}</span>
                    <span class="font-semibold text-primary">{{ $order->formattedGrandTotal() }}</span>
                </div>
                <div class="flex items-center justify-between text-sm mt-2">
                    <span class="text-on-surface">{{ __('Payment method') }}</span>
                    <span class="font-medium text-on-surface-strong">
                        @if($order->payment_provider === 'mtn_momo')
                            {{ __('MTN Mobile Money') }}
                        @else
                            {{ __('Orange Money') }}
                        @endif
                    </span>
                </div>
            </div>

            {{-- Cancel button --}}
            <div class="text-center">
                <button
                    @click="cancelPayment()"
                    class="text-sm font-medium text-danger hover:text-danger/80 transition-colors duration-200 cursor-pointer"
                    :disabled="$fetching()"
                >
                    {{ __('Cancel payment') }}
                </button>
            </div>
        </div>

        {{-- Timed out / Failed state --}}
        <div x-show="isFailed" x-cloak>
            <div class="text-center mb-8">
                {{-- Error icon --}}
                <div class="w-20 h-20 rounded-full mx-auto mb-6 flex items-center justify-center"
                     :class="isTimedOut ? 'bg-warning-subtle' : 'bg-danger-subtle'">
                    <svg x-show="isTimedOut" class="w-10 h-10 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    <svg x-show="!isTimedOut" x-cloak class="w-10 h-10 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg>
                </div>

                <h2 class="text-xl sm:text-2xl font-display font-bold text-on-surface-strong mb-2">
                    <span x-show="isTimedOut">{{ __('Payment timed out') }}</span>
                    <span x-show="!isTimedOut" x-cloak>{{ __('Payment failed') }}</span>
                </h2>
                <p class="text-base text-on-surface" x-text="errorMessage"></p>
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

            {{-- Action buttons --}}
            <div class="flex flex-col gap-3">
                <button
                    @click="retryPayment()"
                    class="w-full h-12 bg-primary hover:bg-primary-hover text-on-primary font-semibold rounded-lg shadow-card transition-all duration-200 cursor-pointer inline-flex items-center justify-center gap-2"
                >
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"></path><path d="M16 16h5v5"></path></svg>
                    {{ __('Try again') }}
                </button>
                <a
                    href="{{ url('/') }}"
                    class="w-full h-12 border border-outline dark:border-outline text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt font-medium rounded-lg transition-all duration-200 inline-flex items-center justify-center gap-2"
                    x-navigate
                >
                    {{ __('Return to menu') }}
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
