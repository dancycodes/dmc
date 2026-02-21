{{--
    Cook Wallet Dashboard (F-169)
    --------------------------------
    Displays the cook's wallet page showing balance split into
    withdrawable/unwithdrawable, recent transactions, earnings summary,
    and an earnings chart.

    BR-311: Total balance split into withdrawable and unwithdrawable.
    BR-312: Withdrawable = cleared funds after hold period.
    BR-313: Unwithdrawable = funds still within hold period or blocked.
    BR-314: Withdraw button active only when withdrawable > 0.
    BR-315: Recent transactions shows last 10.
    BR-316: Earnings summary: total earned, total withdrawn, pending.
    BR-317: Earnings chart shows monthly totals for past 6 months.
    BR-318: All amounts in XAF format.
    BR-319: Only cook or users with manage-finances permission.
    BR-320: Managers can view but not withdraw.
    BR-321: Wallet data is tenant-scoped.
    BR-322: All user-facing text uses __() localization.
--}}
@extends('layouts.cook-dashboard')

@section('page-title', __('Wallet'))

@section('content')
<div class="max-w-5xl mx-auto" x-data="{
    monthlyData: {{ json_encode(collect($monthlyEarnings)->pluck('amount')->toArray()) }},
    monthLabels: {{ json_encode(collect($monthlyEarnings)->pluck('short_label')->toArray()) }},
    maxEarning: {{ json_encode(max(1, (float) collect($monthlyEarnings)->max('amount'))) }},
    barHeight(amount) {
        if (this.maxEarning === 0) return 0;
        return Math.max(4, (amount / this.maxEarning) * 100);
    }
}">

    {{-- Balance Cards Section --}}
    {{-- BR-311: Three cards showing Total, Withdrawable, Unwithdrawable amounts --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        {{-- Total Balance Card --}}
        <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-5 shadow-card">
            <div class="flex items-center gap-3 mb-3">
                <span class="w-10 h-10 rounded-full bg-primary-subtle dark:bg-primary-subtle flex items-center justify-center">
                    {{-- Wallet icon (Lucide, md=20) --}}
                    <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"></path><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"></path></svg>
                </span>
                <p class="text-sm text-on-surface">{{ __('Total Balance') }}</p>
            </div>
            <p class="text-2xl sm:text-3xl font-bold text-on-surface-strong font-mono tracking-tight">
                {{ $wallet->formattedTotalBalance() }}
            </p>
        </div>

        {{-- Withdrawable Balance Card --}}
        <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-5 shadow-card">
            <div class="flex items-center gap-3 mb-3">
                <span class="w-10 h-10 rounded-full bg-success-subtle dark:bg-success-subtle flex items-center justify-center">
                    {{-- CircleCheck icon (Lucide, md=20) --}}
                    <svg class="w-5 h-5 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path></svg>
                </span>
                <p class="text-sm text-on-surface">{{ __('Withdrawable') }}</p>
            </div>
            <p class="text-2xl sm:text-3xl font-bold text-success font-mono tracking-tight">
                {{ $wallet->formattedWithdrawableBalance() }}
            </p>

            {{-- BR-314: Withdraw button (active only when withdrawable > 0) --}}
            {{-- BR-320: Managers can view but not withdraw --}}
            @if($isCook)
                <div class="mt-3" x-navigate>
                    @if($wallet->hasWithdrawableBalance())
                        <a
                            href="{{ url('/dashboard/wallet/withdraw') }}"
                            class="inline-flex items-center gap-2 h-9 px-4 rounded-lg bg-success text-on-success text-sm font-medium hover:opacity-90 transition-opacity duration-200"
                        >
                            {{-- ArrowUpFromLine icon (Lucide, sm=16) --}}
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 9-6-6-6 6"></path><path d="M12 3v14"></path><path d="M5 21h14"></path></svg>
                            {{ __('Withdraw') }}
                        </a>
                    @else
                        <span
                            class="inline-flex items-center gap-2 h-9 px-4 rounded-lg bg-surface-alt dark:bg-surface-alt text-on-surface/40 text-sm font-medium cursor-not-allowed"
                            title="{{ __('No funds available for withdrawal yet.') }}"
                        >
                            {{-- ArrowUpFromLine icon (Lucide, sm=16) --}}
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 9-6-6-6 6"></path><path d="M12 3v14"></path><path d="M5 21h14"></path></svg>
                            {{ __('Withdraw') }}
                        </span>
                    @endif
                </div>
            @endif
        </div>

        {{-- Unwithdrawable Balance Card --}}
        <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-5 shadow-card">
            <div class="flex items-center gap-3 mb-3">
                <span class="w-10 h-10 rounded-full bg-warning-subtle dark:bg-warning-subtle flex items-center justify-center">
                    {{-- Clock icon (Lucide, md=20) --}}
                    <svg class="w-5 h-5 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                </span>
                <p class="text-sm text-on-surface">{{ __('Unwithdrawable') }}</p>
            </div>
            <p class="text-2xl sm:text-3xl font-bold text-warning font-mono tracking-tight">
                {{ $wallet->formattedUnwithdrawableBalance() }}
            </p>
            @if((float) $wallet->unwithdrawable_balance > 0)
                <p class="mt-2 text-xs text-on-surface">
                    {{ __('Clearing soon') }}
                </p>
            @endif
        </div>
    </div>

    {{-- Earnings Summary Section --}}
    {{-- BR-316: Total earned, total withdrawn, pending --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-4 shadow-card">
            <p class="text-xs uppercase tracking-wider text-on-surface/60 font-semibold mb-1">
                {{ __('Total Earned') }}
            </p>
            <p class="text-xl font-bold text-on-surface-strong font-mono">
                {{ \App\Services\CookWalletService::formatXAF($earningsSummary['total_earned']) }}
            </p>
        </div>
        <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-4 shadow-card">
            <p class="text-xs uppercase tracking-wider text-on-surface/60 font-semibold mb-1">
                {{ __('Total Withdrawn') }}
            </p>
            <p class="text-xl font-bold text-on-surface-strong font-mono">
                {{ \App\Services\CookWalletService::formatXAF($earningsSummary['total_withdrawn']) }}
            </p>
        </div>
        <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-4 shadow-card">
            <p class="text-xs uppercase tracking-wider text-on-surface/60 font-semibold mb-1">
                {{ __('Pending') }}
            </p>
            <p class="text-xl font-bold text-warning font-mono">
                {{ \App\Services\CookWalletService::formatXAF($earningsSummary['pending']) }}
            </p>
        </div>
    </div>

    {{-- Earnings Chart Section --}}
    {{-- BR-317: Line/bar chart showing monthly earnings, responsive --}}
    <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-5 shadow-card mb-6">
        <h2 class="text-lg font-semibold text-on-surface-strong mb-4">
            {{ __('Earnings Over Time') }}
        </h2>

        @if(collect($monthlyEarnings)->sum('amount') > 0)
            {{-- Bar Chart using Alpine.js (no external chart library) --}}
            <div class="flex items-end gap-2 sm:gap-4 h-48 sm:h-56 px-2">
                @foreach($monthlyEarnings as $index => $month)
                    <div class="flex-1 flex flex-col items-center gap-1">
                        {{-- Bar --}}
                        <div class="w-full flex flex-col items-center justify-end h-40 sm:h-48">
                            <span
                                class="text-xs font-mono text-on-surface-strong mb-1 hidden sm:block"
                                x-text="monthlyData[{{ $index }}] > 0 ? new Intl.NumberFormat().format(monthlyData[{{ $index }}]) : ''"
                            ></span>
                            <div
                                class="w-full max-w-12 rounded-t-md bg-primary dark:bg-primary transition-all duration-500"
                                :style="'height: ' + barHeight(monthlyData[{{ $index }}]) + '%'"
                            ></div>
                        </div>
                        {{-- Label --}}
                        <span class="text-xs text-on-surface mt-1" x-text="monthLabels[{{ $index }}]"></span>
                    </div>
                @endforeach
            </div>
        @else
            {{-- Edge case: No earnings history for chart --}}
            <div class="flex flex-col items-center justify-center py-12 text-center">
                <div class="w-14 h-14 rounded-full bg-surface-alt dark:bg-surface-alt flex items-center justify-center mb-4">
                    {{-- BarChart3 icon (Lucide, lg=24) --}}
                    <svg class="w-6 h-6 text-on-surface/40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"></path><path d="M7 16v-3"></path><path d="M11 16v-8"></path><path d="M15 16v-5"></path><path d="M19 16v-1"></path></svg>
                </div>
                <p class="text-sm text-on-surface">
                    {{ __('Not enough data to display the chart yet.') }}
                </p>
            </div>
        @endif
    </div>

    {{-- Recent Transactions Section --}}
    {{-- BR-315: Last 10 wallet-related transactions --}}
    <div class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-on-surface-strong">
                {{ __('Recent Transactions') }}
            </h2>
            @if($totalTransactionCount > 0)
                <span class="text-xs text-on-surface bg-surface-alt dark:bg-surface-alt px-2 py-1 rounded-full">
                    {{ $totalTransactionCount }} {{ __('total') }}
                </span>
            @endif
        </div>

        @if($recentTransactions->isEmpty())
            {{-- Edge case: No transactions yet --}}
            <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-8 text-center">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-surface-alt dark:bg-surface-alt mb-4">
                    {{-- Receipt icon (Lucide, lg=24) --}}
                    <svg class="w-6 h-6 text-on-surface/40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"></path><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"></path><path d="M12 17.5v-11"></path></svg>
                </div>
                <p class="text-sm text-on-surface">
                    {{ __('No transactions yet.') }}
                </p>
            </div>
        @else
            {{-- Transaction List --}}
            <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl overflow-hidden shadow-card divide-y divide-outline dark:divide-outline">
                @foreach($recentTransactions as $txn)
                    @php
                        $isCredit = $txn->isCredit();
                        $typeLabel = match($txn->type) {
                            \App\Models\WalletTransaction::TYPE_PAYMENT_CREDIT => __('Payment Credit'),
                            \App\Models\WalletTransaction::TYPE_COMMISSION => __('Commission'),
                            \App\Models\WalletTransaction::TYPE_WITHDRAWAL => __('Withdrawal'),
                            \App\Models\WalletTransaction::TYPE_REFUND_DEDUCTION => __('Refund Deduction'),
                            \App\Models\WalletTransaction::TYPE_REFUND => __('Refund'),
                            default => __('Transaction'),
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

    {{-- View All Transactions Link (F-170 forward-compatible) --}}
    @if($totalTransactionCount > 0)
        <div class="text-center" x-navigate>
            <a
                href="{{ url('/dashboard/wallet/transactions') }}"
                class="inline-flex items-center gap-2 h-10 px-6 rounded-lg border border-outline dark:border-outline text-sm font-medium text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors duration-200"
            >
                {{-- ExternalLink icon (Lucide, sm=16) --}}
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"></path><path d="M10 14 21 3"></path><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path></svg>
                {{ __('View all transactions') }}
            </a>
        </div>
    @endif
</div>
@endsection
