{{--
    F-150: Flutterwave Payment Initiation - Waiting Page
    BR-360: The UI shows a "Waiting for payment" loading state after initiation
    BR-361: Timeout after 15 minutes if no webhook confirmation received
    F-152: On failure/timeout, redirects to payment-retry page
--}}
@extends('layouts.tenant-public')

@section('title', __('Payment Processing') . ' - ' . ($tenant?->name ?? config('app.name')))

@section('content')
<div class="min-h-screen"
    x-data="{
        paymentStatus: 'pending',
        remainingSeconds: {{ $remainingSeconds ?? 0 }},
        orderId: {{ $order->id }},
        timerInterval: null,

        init() {
            if (this.remainingSeconds > 0) {
                this.startTimer();
            }
        },

        startTimer() {
            this.timerInterval = setInterval(() => {
                if (this.remainingSeconds > 0) {
                    this.remainingSeconds--;
                }
                if (this.remainingSeconds <= 0) {
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

        pollStatus() {
            $action('{{ url('/checkout/payment/check-status') }}/' + this.orderId);
        },

        cancelPayment() {
            if (this.timerInterval) clearInterval(this.timerInterval);
            $action('{{ url('/checkout/payment/cancel') }}/' + this.orderId);
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
</div>
@endsection
