{{--
    Payment Transaction Detail
    --------------------------
    F-059: Scenario 4 — Full Flutterwave transaction detail for troubleshooting.

    BR-154: Transaction detail shows raw Flutterwave response data for debugging
    Edge case: Webhook data missing — shows "Webhook data unavailable"
    Edge case: Flutterwave reference null — shows "Awaiting confirmation"
--}}
@extends('layouts.admin')

@section('title', __('Transaction Detail'))
@section('page-title', __('Transaction Detail'))

@section('content')
<div class="space-y-6">
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[
        ['label' => __('Payments'), 'url' => url('/vault-entry/payments')],
        ['label' => $transaction->order_id ? 'ORD-'.$transaction->order_id : '#'.$transaction->id],
    ]" />

    {{-- Back link + Status --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3" x-data x-navigate>
            <a href="{{ url('/vault-entry/payments') }}" class="inline-flex items-center gap-1.5 text-sm text-on-surface hover:text-primary transition-colors">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
                {{ __('Back to Payments') }}
            </a>
        </div>
        <div class="flex items-center gap-3">
            @include('admin.payments._status-badge', ['status' => $transaction->status])
            @if($transaction->isPendingTooLong())
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-warning-subtle text-warning">
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" x2="12" y1="9" y2="13"></line><line x1="12" x2="12.01" y1="17" y2="17"></line></svg>
                    {{ __('Pending over 15 minutes') }}
                </span>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left column: Transaction details --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Transaction Summary --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline overflow-hidden">
                <div class="px-5 py-4 border-b border-outline dark:border-outline">
                    <h3 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wide">{{ __('Transaction Summary') }}</h3>
                </div>
                <div class="p-5 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Order ID --}}
                        <div>
                            <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Order ID') }}</p>
                            <p class="text-sm font-mono font-semibold text-on-surface-strong mt-1">
                                {{ $transaction->order_id ? 'ORD-'.$transaction->order_id : '—' }}
                            </p>
                        </div>

                        {{-- Amount --}}
                        <div>
                            <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Amount') }}</p>
                            <p class="text-xl font-bold text-on-surface-strong mt-1 font-mono">{{ $transaction->formattedAmount() }}</p>
                        </div>

                        {{-- Payment Method --}}
                        <div>
                            <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Payment Method') }}</p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="w-3 h-3 rounded-full {{ $transaction->payment_method === 'mtn_mobile_money' ? 'bg-[#ffcc00]' : 'bg-[#ff6600]' }}"></span>
                                <span class="text-sm font-medium text-on-surface-strong">{{ $transaction->paymentMethodLabel() }}</span>
                            </div>
                        </div>

                        {{-- Date --}}
                        <div>
                            <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Date') }}</p>
                            <p class="text-sm text-on-surface-strong mt-1">{{ $transaction->created_at?->format('M d, Y H:i:s') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Flutterwave Details --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline overflow-hidden">
                <div class="px-5 py-4 border-b border-outline dark:border-outline">
                    <h3 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wide">{{ __('Flutterwave Details') }}</h3>
                </div>
                <div class="p-5 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Flutterwave Reference --}}
                        <div>
                            <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Flutterwave Reference') }}</p>
                            <p class="text-sm font-mono text-on-surface-strong mt-1">
                                {{ $transaction->flutterwave_reference ?? __('Awaiting confirmation') }}
                            </p>
                        </div>

                        {{-- TX Ref --}}
                        <div>
                            <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Transaction Reference') }}</p>
                            <p class="text-sm font-mono text-on-surface-strong mt-1">{{ $transaction->flutterwave_tx_ref ?? '—' }}</p>
                        </div>

                        {{-- Payment Channel --}}
                        <div>
                            <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Payment Channel') }}</p>
                            <p class="text-sm text-on-surface-strong mt-1">
                                @if($transaction->payment_channel)
                                    {{ $transaction->payment_channel }}
                                @else
                                    <span class="text-on-surface/50 italic">{{ __('Webhook data unavailable') }}</span>
                                @endif
                            </p>
                        </div>

                        {{-- Response Code --}}
                        <div>
                            <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Response Code') }}</p>
                            <p class="text-sm font-mono text-on-surface-strong mt-1">{{ $transaction->response_code ?? '—' }}</p>
                        </div>

                        {{-- Flutterwave Fee --}}
                        <div>
                            <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Flutterwave Fee') }}</p>
                            <p class="text-sm font-mono text-on-surface-strong mt-1">
                                @if($transaction->flutterwave_fee !== null)
                                    {{ number_format((float) $transaction->flutterwave_fee, 0, '.', ',') }} XAF
                                @else
                                    <span class="text-on-surface/50 italic">{{ __('Webhook data unavailable') }}</span>
                                @endif
                            </p>
                        </div>

                        {{-- Settlement Amount --}}
                        <div>
                            <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Settlement Amount') }}</p>
                            <p class="text-sm font-mono text-on-surface-strong mt-1">
                                @if($transaction->settlement_amount !== null)
                                    {{ number_format((float) $transaction->settlement_amount, 0, '.', ',') }} XAF
                                @else
                                    <span class="text-on-surface/50 italic">{{ __('Webhook data unavailable') }}</span>
                                @endif
                            </p>
                        </div>

                        {{-- Response Message --}}
                        <div class="sm:col-span-2">
                            <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Response Message') }}</p>
                            <p class="text-sm text-on-surface-strong mt-1">{{ $transaction->response_message ?? '—' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Refund Details (only shown for refunded transactions) --}}
            @if($transaction->status === 'refunded')
                <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-info/30 dark:border-info/30 overflow-hidden">
                    <div class="px-5 py-4 border-b border-info/20 dark:border-info/20 bg-info-subtle/30">
                        <h3 class="text-sm font-semibold text-info uppercase tracking-wide">{{ __('Refund Details') }}</h3>
                    </div>
                    <div class="p-5 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Refund Amount') }}</p>
                                <p class="text-lg font-bold text-info mt-1 font-mono">{{ $transaction->formattedRefundAmount() }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Refund Reason') }}</p>
                                <p class="text-sm text-on-surface-strong mt-1">{{ $transaction->refund_reason ?? '—' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Status History --}}
            @if($transaction->status_history && count($transaction->status_history) > 0)
                <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline overflow-hidden">
                    <div class="px-5 py-4 border-b border-outline dark:border-outline">
                        <h3 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wide">{{ __('Status History') }}</h3>
                    </div>
                    <div class="p-5">
                        <div class="space-y-3">
                            @foreach($transaction->status_history as $entry)
                                <div class="flex items-center gap-3">
                                    @include('admin.payments._status-badge', ['status' => $entry['status'] ?? 'unknown'])
                                    <span class="text-xs text-on-surface/60 font-mono">
                                        {{ isset($entry['timestamp']) ? \Carbon\Carbon::parse($entry['timestamp'])->format('M d, Y H:i:s') : '—' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- Raw Webhook Data (BR-154: collapsible for technical debugging) --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline overflow-hidden"
                 x-data="{ expanded: false }">
                <button
                    @click="expanded = !expanded"
                    class="w-full px-5 py-4 flex items-center justify-between text-left border-b border-outline dark:border-outline hover:bg-surface dark:hover:bg-surface transition-colors"
                >
                    <h3 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wide">{{ __('Raw Webhook Data') }}</h3>
                    <svg
                        class="w-5 h-5 text-on-surface/50 transition-transform duration-200"
                        :class="expanded ? 'rotate-180' : ''"
                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
                </button>
                <div x-show="expanded" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" x-cloak>
                    <div class="p-5">
                        @if($transaction->webhook_payload)
                            <pre class="text-xs font-mono text-on-surface bg-surface dark:bg-surface p-4 rounded-lg overflow-x-auto whitespace-pre-wrap break-words border border-outline dark:border-outline max-h-96">{{ json_encode($transaction->webhook_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        @else
                            <p class="text-sm text-on-surface/50 italic">{{ __('Webhook data unavailable') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Right column: Customer & Cook info --}}
        <div class="space-y-6">
            {{-- Customer Details --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline overflow-hidden">
                <div class="px-5 py-4 border-b border-outline dark:border-outline">
                    <h3 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wide">{{ __('Customer Details') }}</h3>
                </div>
                <div class="p-5 space-y-3">
                    <div>
                        <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Name') }}</p>
                        <p class="text-sm text-on-surface-strong mt-1">{{ $transaction->customer_name ?? $transaction->client?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Email') }}</p>
                        <p class="text-sm text-on-surface-strong mt-1 break-all">{{ $transaction->customer_email ?? $transaction->client?->email ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Phone') }}</p>
                        <p class="text-sm font-mono text-on-surface-strong mt-1">{{ $transaction->customer_phone ?? '—' }}</p>
                    </div>
                </div>
            </div>

            {{-- Cook/Tenant Info --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline overflow-hidden">
                <div class="px-5 py-4 border-b border-outline dark:border-outline">
                    <h3 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wide">{{ __('Cook / Tenant') }}</h3>
                </div>
                <div class="p-5 space-y-3">
                    <div>
                        <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Cook') }}</p>
                        <p class="text-sm text-on-surface-strong mt-1">{{ $transaction->cook?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Tenant') }}</p>
                        <p class="text-sm text-on-surface-strong mt-1">{{ $transaction->tenant?->name ?? '—' }}</p>
                    </div>
                </div>
            </div>

            {{-- Quick Info Card --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline overflow-hidden">
                <div class="px-5 py-4 border-b border-outline dark:border-outline">
                    <h3 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wide">{{ __('Transaction ID') }}</h3>
                </div>
                <div class="p-5">
                    <p class="text-sm font-mono text-on-surface/60">#{{ $transaction->id }}</p>
                    <p class="text-xs text-on-surface/40 mt-2">{{ __('Created') }}: {{ $transaction->created_at?->format('Y-m-d H:i:s') }}</p>
                    <p class="text-xs text-on-surface/40">{{ __('Updated') }}: {{ $transaction->updated_at?->format('Y-m-d H:i:s') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
