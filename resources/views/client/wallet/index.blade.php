{{--
    Client Wallet Dashboard (F-166)
    --------------------------------
    Displays the client's wallet page showing balance, recent transactions,
    and explanatory notes about wallet usage.

    BR-280: Each client has one wallet with a single balance.
    BR-281: Wallet balance displayed in XAF format.
    BR-282: Wallet balance cannot be negative.
    BR-283: Clients cannot withdraw wallet balance.
    BR-284: Recent transactions shows last 10.
    BR-285: Link to full transaction history (F-164).
    BR-286: Explanatory note describes wallet purpose.
    BR-287: If wallet payment disabled, a note indicates this.
    BR-288: Authentication required.
    BR-289: All user-facing text uses __() localization.
--}}
@extends(tenant() ? 'layouts.tenant-public' : 'layouts.main-public')

@section('title', __('My Wallet'))

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8" x-data>

    {{-- Page Header --}}
    <div class="mb-6">
        <h1 class="text-2xl sm:text-3xl font-display font-bold text-on-surface-strong">
            {{ __('My Wallet') }}
        </h1>
        <p class="mt-1 text-sm text-on-surface">
            {{ __('Manage your wallet balance and view recent transactions.') }}
        </p>
    </div>

    {{-- Wallet Balance Card --}}
    {{-- BR-281: Large balance display, prominent, centered --}}
    <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-2xl p-6 sm:p-8 shadow-card mb-6 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary-subtle dark:bg-primary-subtle mb-4">
            {{-- Wallet icon (Lucide, lg=24) --}}
            <svg class="w-8 h-8 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"></path><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"></path></svg>
        </div>

        <p class="text-sm text-on-surface mb-2">{{ __('Current Balance') }}</p>
        <p class="text-4xl sm:text-5xl font-bold text-on-surface-strong font-mono tracking-tight">
            {{ $wallet->formattedBalance() }}
        </p>
    </div>

    {{-- Info Note Card --}}
    {{-- BR-286: Explanatory note describes wallet purpose --}}
    @if($wallet->hasBalance())
        <div class="bg-info-subtle dark:bg-info-subtle border border-info/20 dark:border-info/20 rounded-xl p-4 mb-6">
            <div class="flex items-start gap-3">
                {{-- Info icon (Lucide, md=20) --}}
                <svg class="w-5 h-5 text-info shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                <p class="text-sm text-on-surface">
                    {{ __('Your wallet balance comes from refunds and can be used for future orders.') }}
                </p>
            </div>
        </div>
    @else
        {{-- Scenario 2: Zero balance note --}}
        <div class="bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-xl p-4 mb-6">
            <div class="flex items-start gap-3">
                {{-- Info icon (Lucide, md=20) --}}
                <svg class="w-5 h-5 text-on-surface/50 shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                <p class="text-sm text-on-surface">
                    {{ __('Your wallet balance will appear here when you receive a refund.') }}
                </p>
            </div>
        </div>
    @endif

    {{-- Scenario 4: Wallet payments disabled note --}}
    {{-- BR-287: If wallet payment disabled by admin, note indicates this --}}
    @if(!$walletEnabled)
        <div class="bg-warning-subtle dark:bg-warning-subtle border border-warning/20 dark:border-warning/20 rounded-xl p-4 mb-6">
            <div class="flex items-start gap-3">
                {{-- AlertTriangle icon (Lucide, md=20) --}}
                <svg class="w-5 h-5 text-warning shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                <p class="text-sm text-on-surface">
                    {{ __('Wallet payments are currently not available for orders.') }}
                </p>
            </div>
        </div>
    @endif

    {{-- Recent Transactions Section --}}
    {{-- BR-284: Last 10 wallet-related transactions --}}
    <div class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-on-surface-strong">
                {{ __('Recent Transactions') }}
            </h2>
            @if($transactionCount > 0)
                <span class="text-xs text-on-surface bg-surface-alt dark:bg-surface-alt px-2 py-1 rounded-full">
                    {{ $transactionCount }} {{ __('total') }}
                </span>
            @endif
        </div>

        @if($recentTransactions->isEmpty())
            {{-- Empty transaction state --}}
            <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-8 text-center">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-surface-alt dark:bg-surface-alt mb-4">
                    {{-- Receipt icon (Lucide, lg=24) --}}
                    <svg class="w-6 h-6 text-on-surface/40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"></path><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"></path><path d="M12 17.5v-11"></path></svg>
                </div>
                <p class="text-sm text-on-surface">
                    {{ __('No wallet transactions yet.') }}
                </p>
            </div>
        @else
            {{-- Transaction List --}}
            <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl overflow-hidden shadow-card divide-y divide-outline dark:divide-outline">
                @foreach($recentTransactions as $txn)
                    @php
                        $isCredit = $txn->isCredit();
                        $typeLabel = match($txn->type) {
                            \App\Models\WalletTransaction::TYPE_REFUND => __('Refund'),
                            \App\Models\WalletTransaction::TYPE_PAYMENT_CREDIT => __('Payment Credit'),
                            \App\Models\WalletTransaction::TYPE_COMMISSION => __('Commission'),
                            \App\Models\WalletTransaction::TYPE_WITHDRAWAL => __('Withdrawal'),
                            \App\Models\WalletTransaction::TYPE_REFUND_DEDUCTION => __('Refund Deduction'),
                            default => __('Wallet Payment'),
                        };
                        $description = $txn->description ?? $typeLabel;
                        $orderRef = $txn->order?->order_number;
                    @endphp
                    <div class="flex items-center gap-3 px-4 py-3.5 hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors">
                        {{-- Type indicator icon --}}
                        <div class="shrink-0">
                            @if($isCredit)
                                <span class="w-9 h-9 rounded-full bg-success-subtle dark:bg-success-subtle flex items-center justify-center">
                                    {{-- ArrowDownLeft icon (credit) --}}
                                    <svg class="w-4 h-4 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 7 7 17"></path><path d="M17 17H7V7"></path></svg>
                                </span>
                            @else
                                <span class="w-9 h-9 rounded-full bg-danger-subtle dark:bg-danger-subtle flex items-center justify-center">
                                    {{-- ArrowUpRight icon (debit) --}}
                                    <svg class="w-4 h-4 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7"></path><path d="M7 7h10v10"></path></svg>
                                </span>
                            @endif
                        </div>

                        {{-- Description and date --}}
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-on-surface-strong truncate">
                                {{ $description }}
                            </p>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-xs text-on-surface">
                                    {{ $txn->created_at->format('M d, Y') }}
                                </span>
                                @if($orderRef)
                                    <span class="text-xs text-on-surface/50">&middot;</span>
                                    <span class="text-xs text-on-surface font-mono">{{ $orderRef }}</span>
                                @endif
                            </div>
                        </div>

                        {{-- Amount --}}
                        <div class="shrink-0 text-right">
                            <p class="text-sm font-semibold {{ $isCredit ? 'text-success' : 'text-danger' }}">
                                {{ $isCredit ? '+' : '-' }}{{ $txn->formattedAmount() }}
                            </p>
                            <p class="text-xs text-on-surface mt-0.5">
                                {{ $typeLabel }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- View Full History Link --}}
    {{-- BR-285: Link to full transaction history (F-164) --}}
    <div class="text-center" x-navigate>
        <a
            href="{{ url('/my-transactions') }}"
            class="inline-flex items-center gap-2 h-10 px-6 rounded-lg border border-outline dark:border-outline text-sm font-medium text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors duration-200"
        >
            {{-- ExternalLink icon (Lucide, sm=16) --}}
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"></path><path d="M10 14 21 3"></path><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path></svg>
            {{ __('View full transaction history') }}
        </a>
    </div>
</div>
@endsection
