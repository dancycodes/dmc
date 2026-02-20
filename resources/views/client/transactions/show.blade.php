{{--
    Transaction Detail View (F-165)
    --------------------------------
    Displays full details of a single financial transaction for the client.

    BR-271: Client can only view their own transaction details.
    BR-272: All transaction types display: amount, type, date/time, status.
    BR-273: Payment transactions additionally show: payment method, Flutterwave reference.
    BR-274: Refund transactions additionally show: original order reference, refund reason.
    BR-275: Wallet payment transactions show: wallet as the payment method.
    BR-276: Order reference is a clickable link to the order detail page (F-161).
    BR-277: Failed transactions show the failure reason.
    BR-278: All amounts displayed in XAF format.
    BR-279: All user-facing text uses __() localization.
--}}
@extends(tenant() ? 'layouts.tenant-public' : 'layouts.main-public')

@section('title', __('Transaction Details'))

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8" x-data>

    {{-- Back Navigation --}}
    <a href="{{ url('/my-transactions') }}" class="inline-flex items-center gap-2 text-sm font-medium text-primary hover:text-primary-hover transition-colors mb-6" x-navigate>
        {{-- ArrowLeft icon (Lucide, sm=16) --}}
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
        {{ __('Back to Transaction History') }}
    </a>

    {{-- Transaction Header Card --}}
    <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl shadow-card overflow-hidden mb-6">
        {{-- Header with type badge and status --}}
        <div class="px-4 sm:px-6 py-4 sm:py-5 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-3">
                    {{-- Transaction type icon --}}
                    @if($transaction['type'] === 'payment')
                        <span class="w-10 h-10 rounded-full bg-info-subtle dark:bg-info-subtle flex items-center justify-center shrink-0">
                            {{-- CreditCard icon (Lucide, md=20) --}}
                            <svg class="w-5 h-5 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"></rect><line x1="2" x2="22" y1="10" y2="10"></line></svg>
                        </span>
                    @elseif($transaction['type'] === 'refund')
                        <span class="w-10 h-10 rounded-full bg-success-subtle dark:bg-success-subtle flex items-center justify-center shrink-0">
                            {{-- RefreshCcw icon (Lucide, md=20) --}}
                            <svg class="w-5 h-5 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"></path><path d="M16 16h5v5"></path></svg>
                        </span>
                    @else
                        <span class="w-10 h-10 rounded-full bg-warning-subtle dark:bg-warning-subtle flex items-center justify-center shrink-0">
                            {{-- Wallet icon (Lucide, md=20) --}}
                            <svg class="w-5 h-5 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"></path><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"></path></svg>
                        </span>
                    @endif
                    <div>
                        <h1 class="text-lg sm:text-xl font-display font-bold text-on-surface-strong">
                            {{ $transaction['description'] }}
                        </h1>
                        <p class="text-xs text-on-surface mt-0.5">
                            {{ $transaction['tenant_name'] }}
                        </p>
                    </div>
                </div>
                @include('client.transactions._status-badge', ['status' => $transaction['status']])
            </div>
        </div>

        {{-- Prominent Amount Display --}}
        <div class="px-4 sm:px-6 py-5 sm:py-6 text-center border-b border-outline dark:border-outline">
            <p class="text-xs uppercase tracking-wider text-on-surface mb-1">
                {{ $transaction['debit_credit'] === 'credit' ? __('Amount Credited') : __('Amount Debited') }}
            </p>
            <p class="text-3xl sm:text-4xl font-bold {{ $transaction['debit_credit'] === 'credit' ? 'text-success' : 'text-on-surface-strong' }}">
                {{ $transaction['debit_credit'] === 'credit' ? '+' : '-' }}{{ \App\Services\ClientTransactionService::formatXAF($transaction['amount']) }}
            </p>
            <div class="mt-2">
                @include('client.transactions._type-badge', ['type' => $transaction['type']])
            </div>
        </div>

        {{-- Detail Fields --}}
        <div class="px-4 sm:px-6 py-4 sm:py-5 space-y-4">

            {{-- Date & Time (BR-272) --}}
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-2 text-sm text-on-surface shrink-0">
                    {{-- Calendar icon (Lucide, sm=16) --}}
                    <svg class="w-4 h-4 text-on-surface/60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path></svg>
                    {{ __('Date & Time') }}
                </div>
                <p class="text-sm font-medium text-on-surface-strong text-right">
                    {{ $transaction['date']->format('M d, Y') }}<br>
                    <span class="text-xs text-on-surface">{{ $transaction['date']->format('H:i') }}</span>
                </p>
            </div>

            <div class="border-t border-outline dark:border-outline"></div>

            {{-- Order Reference (BR-276) --}}
            @if($transaction['order_number'])
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-2 text-sm text-on-surface shrink-0">
                        {{-- Package icon (Lucide, sm=16) --}}
                        <svg class="w-4 h-4 text-on-surface/60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"></path><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path><path d="m3.3 7 8.7 5 8.7-5"></path><path d="M12 22V12"></path></svg>
                        {{ __('Order') }}
                    </div>
                    @if($transaction['order_exists'])
                        <a href="{{ url('/my-orders/' . $transaction['order_id']) }}" class="text-sm font-medium text-primary hover:text-primary-hover transition-colors" x-navigate>
                            {{ $transaction['order_number'] }}
                            {{-- ExternalLink icon (Lucide, xs=14) --}}
                            <svg class="w-3.5 h-3.5 inline ml-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"></path><path d="M10 14 21 3"></path><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path></svg>
                        </a>
                    @else
                        <span class="text-sm text-on-surface/60 italic">
                            {{ __('Order details unavailable') }}
                        </span>
                    @endif
                </div>

                <div class="border-t border-outline dark:border-outline"></div>
            @endif

            {{-- Payment Method (BR-273 / BR-275) --}}
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-2 text-sm text-on-surface shrink-0">
                    {{-- Smartphone icon (Lucide, sm=16) --}}
                    <svg class="w-4 h-4 text-on-surface/60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"></rect><path d="M12 18h.01"></path></svg>
                    {{ __('Payment Method') }}
                </div>
                <p class="text-sm font-medium text-on-surface-strong">
                    {{ $transaction['payment_method'] }}
                </p>
            </div>

            {{-- Flutterwave Reference (BR-273) — only for payment transactions --}}
            @if($transaction['flutterwave_reference'] || $transaction['flutterwave_tx_ref'])
                <div class="border-t border-outline dark:border-outline"></div>

                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-2 text-sm text-on-surface shrink-0">
                        {{-- Hash icon (Lucide, sm=16) --}}
                        <svg class="w-4 h-4 text-on-surface/60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="9" y2="9"></line><line x1="4" x2="20" y1="15" y2="15"></line><line x1="10" x2="8" y1="3" y2="21"></line><line x1="16" x2="14" y1="3" y2="21"></line></svg>
                        {{ __('Reference') }}
                    </div>
                    <p class="text-sm font-mono text-on-surface-strong break-all text-right">
                        {{ $transaction['flutterwave_reference'] ?? $transaction['flutterwave_tx_ref'] }}
                    </p>
                </div>
            @endif

            {{-- Refund Reason (BR-274) --}}
            @if($transaction['refund_reason'])
                <div class="border-t border-outline dark:border-outline"></div>

                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-2 text-sm text-on-surface shrink-0">
                        {{-- Info icon (Lucide, sm=16) --}}
                        <svg class="w-4 h-4 text-on-surface/60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                        {{ __('Refund Reason') }}
                    </div>
                    <p class="text-sm text-on-surface-strong text-right">
                        {{ $transaction['refund_reason'] }}
                    </p>
                </div>
            @endif

            {{-- Failure Reason (BR-277) --}}
            @if($transaction['failure_reason'])
                <div class="border-t border-outline dark:border-outline"></div>

                <div class="rounded-lg bg-danger-subtle dark:bg-danger-subtle p-3">
                    <div class="flex items-start gap-2">
                        {{-- AlertTriangle icon (Lucide, sm=16) --}}
                        <svg class="w-4 h-4 text-danger shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                        <div>
                            <p class="text-sm font-medium text-danger">{{ __('Payment Failed') }}</p>
                            <p class="text-sm text-danger/80 mt-0.5">{{ $transaction['failure_reason'] }}</p>
                            @if($transaction['is_pending'] ?? false)
                                <p class="text-xs text-on-surface mt-2">
                                    {{ __('You may retry this payment within the 15-minute retry window.') }}
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- Pending Status Note --}}
            @if($transaction['is_pending'])
                <div class="border-t border-outline dark:border-outline"></div>

                <div class="rounded-lg bg-warning-subtle dark:bg-warning-subtle p-3">
                    <div class="flex items-start gap-2">
                        {{-- Clock icon (Lucide, sm=16) --}}
                        <svg class="w-4 h-4 text-warning shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        <div>
                            <p class="text-sm font-medium text-warning">{{ __('Payment Pending') }}</p>
                            <p class="text-sm text-on-surface mt-0.5">
                                {{ __('This payment is being processed. It may take a few minutes to complete.') }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Wallet Balance Info (for wallet transactions) --}}
            @if(isset($transaction['balance_before']) && $transaction['balance_before'] !== null)
                <div class="border-t border-outline dark:border-outline"></div>

                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-2 text-sm text-on-surface shrink-0">
                        {{-- ArrowDownUp icon (Lucide, sm=16) --}}
                        <svg class="w-4 h-4 text-on-surface/60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 16 4 4 4-4"></path><path d="M7 20V4"></path><path d="m21 8-4-4-4 4"></path><path d="M17 4v16"></path></svg>
                        {{ __('Wallet Balance') }}
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-on-surface">
                            {{ __('Before') }}: {{ \App\Services\ClientTransactionService::formatXAF($transaction['balance_before']) }}
                        </p>
                        <p class="text-xs text-on-surface font-medium">
                            {{ __('After') }}: {{ \App\Services\ClientTransactionService::formatXAF($transaction['balance_after']) }}
                        </p>
                    </div>
                </div>
            @endif

            {{-- Flutterwave Fee (if applicable) --}}
            @if($transaction['flutterwave_fee'])
                <div class="border-t border-outline dark:border-outline"></div>

                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-2 text-sm text-on-surface shrink-0">
                        {{-- Percent icon (Lucide, sm=16) --}}
                        <svg class="w-4 h-4 text-on-surface/60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" x2="5" y1="5" y2="19"></line><circle cx="6.5" cy="6.5" r="2.5"></circle><circle cx="17.5" cy="17.5" r="2.5"></circle></svg>
                        {{ __('Processing Fee') }}
                    </div>
                    <p class="text-sm text-on-surface-strong">
                        {{ \App\Services\ClientTransactionService::formatXAF($transaction['flutterwave_fee']) }}
                    </p>
                </div>
            @endif

        </div>
    </div>

    {{-- Back to Transactions button --}}
    <div class="text-center">
        <a href="{{ url('/my-transactions') }}" class="inline-flex items-center gap-2 h-10 px-6 rounded-lg bg-surface dark:bg-surface border border-outline dark:border-outline text-sm font-medium text-on-surface hover:bg-surface-alt transition-colors shadow-sm" x-navigate>
            {{-- ArrowLeft icon (Lucide, sm=16) --}}
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
            {{ __('Back to Transaction History') }}
        </a>
    </div>

</div>
@endsection
