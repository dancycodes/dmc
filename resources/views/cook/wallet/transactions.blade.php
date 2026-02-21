{{--
    Cook Wallet Transaction History (F-170)
    ----------------------------------------
    Displays a paginated, filterable list of all wallet transactions
    for the cook's tenant.

    BR-323: Transaction types: payment_credit, commission, withdrawal, refund_deduction, refund.
    BR-324: Default sort by date descending (newest first).
    BR-325: Paginated with 20 per page.
    BR-326: Each transaction shows: date, description, amount, type, order reference.
    BR-327: Filter by type: All, Order Payments, Commissions, Withdrawals, Auto-Deductions, Clearances.
    BR-328: All amounts in XAF format.
    BR-329: Credit in green; debit in red.
    BR-330: Tenant-scoped.
    BR-331: Only users with manage-finances permission.
    BR-332: All text uses __() localization.
--}}
@extends('layouts.cook-dashboard')

@section('page-title', __('Transaction History'))

@section('content')
<div class="max-w-5xl mx-auto" x-data="{
    typeFilter: '{{ $typeFilter }}',
    direction: '{{ $direction }}',
    applyFilters() {
        let url = '{{ url('/dashboard/wallet/transactions') }}';
        let params = new URLSearchParams();
        if (this.typeFilter) params.set('type', this.typeFilter);
        if (this.direction !== 'desc') params.set('direction', this.direction);
        let qs = params.toString();
        $navigate(url + (qs ? '?' + qs : ''), { key: 'cook-transactions', replace: true });
    },
    clearFilters() {
        this.typeFilter = '';
        this.direction = 'desc';
        $navigate('{{ url('/dashboard/wallet/transactions') }}', { key: 'cook-transactions', replace: true });
    }
}">

    {{-- Back Navigation --}}
    <div class="mb-6" x-navigate>
        <a
            href="{{ url('/dashboard/wallet') }}"
            class="inline-flex items-center gap-2 text-sm font-medium text-on-surface hover:text-primary transition-colors duration-200"
        >
            {{-- ArrowLeft icon (Lucide, sm=16) --}}
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
            {{ __('Back to Wallet') }}
        </a>
    </div>

    {{-- Page Header --}}
    <div class="mb-6">
        <h1 class="text-2xl sm:text-3xl font-display font-bold text-on-surface-strong">
            {{ __('Transaction History') }}
        </h1>
        <p class="mt-1 text-sm text-on-surface">
            {{ __('View your complete wallet transaction history.') }}
        </p>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 mb-6">
        {{-- Total Transactions --}}
        <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-4 shadow-card">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-primary-subtle dark:bg-primary-subtle flex items-center justify-center shrink-0">
                    {{-- Receipt icon (Lucide, md=20) --}}
                    <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"></path><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"></path><path d="M12 17.5v-11"></path></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs text-on-surface truncate">{{ __('Total') }}</p>
                    <p class="text-lg font-bold text-on-surface-strong">{{ $summaryCounts['total'] }}</p>
                </div>
            </div>
        </div>

        {{-- Order Payments --}}
        <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-4 shadow-card">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-success-subtle dark:bg-success-subtle flex items-center justify-center shrink-0">
                    {{-- ArrowDownLeft icon (Lucide, md=20) --}}
                    <svg class="w-5 h-5 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 7 7 17"></path><path d="M17 17H7V7"></path></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs text-on-surface truncate">{{ __('Order Payments') }}</p>
                    <p class="text-lg font-bold text-on-surface-strong">{{ $summaryCounts['order_payments'] }}</p>
                </div>
            </div>
        </div>

        {{-- Commissions --}}
        <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-4 shadow-card">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-info-subtle dark:bg-info-subtle flex items-center justify-center shrink-0">
                    {{-- Percent icon (Lucide, md=20) --}}
                    <svg class="w-5 h-5 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="5" x2="5" y2="19"></line><circle cx="6.5" cy="6.5" r="2.5"></circle><circle cx="17.5" cy="17.5" r="2.5"></circle></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs text-on-surface truncate">{{ __('Commissions') }}</p>
                    <p class="text-lg font-bold text-on-surface-strong">{{ $summaryCounts['commissions'] }}</p>
                </div>
            </div>
        </div>

        {{-- Withdrawals --}}
        <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-4 shadow-card">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-warning-subtle dark:bg-warning-subtle flex items-center justify-center shrink-0">
                    {{-- ArrowUpFromLine icon (Lucide, md=20) --}}
                    <svg class="w-5 h-5 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 9-6-6-6 6"></path><path d="M12 3v14"></path><path d="M5 21h14"></path></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs text-on-surface truncate">{{ __('Withdrawals') }}</p>
                    <p class="text-lg font-bold text-on-surface-strong">{{ $summaryCounts['withdrawals'] }}</p>
                </div>
            </div>
        </div>

        {{-- Auto-Deductions --}}
        <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-4 shadow-card">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-danger-subtle dark:bg-danger-subtle flex items-center justify-center shrink-0">
                    {{-- ArrowUpRight icon (Lucide, md=20) --}}
                    <svg class="w-5 h-5 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7"></path><path d="M7 7h10v10"></path></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs text-on-surface truncate">{{ __('Auto-Deductions') }}</p>
                    <p class="text-lg font-bold text-on-surface-strong">{{ $summaryCounts['auto_deductions'] }}</p>
                </div>
            </div>
        </div>

        {{-- Clearances --}}
        <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-4 shadow-card">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-secondary-subtle dark:bg-secondary-subtle flex items-center justify-center shrink-0">
                    {{-- RefreshCw icon (Lucide, md=20) --}}
                    <svg class="w-5 h-5 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path><path d="M21 3v5h-5"></path><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path><path d="M8 16H3v5"></path></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs text-on-surface truncate">{{ __('Clearances') }}</p>
                    <p class="text-lg font-bold text-on-surface-strong">{{ $summaryCounts['clearances'] }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Type Filter Pills --}}
    <div class="mb-6">
        <div class="flex flex-wrap items-center gap-2">
            {{-- All pill --}}
            <button
                type="button"
                x-on:click="typeFilter = ''; applyFilters()"
                :class="typeFilter === '' ? 'bg-primary text-on-primary' : 'bg-surface-alt dark:bg-surface-alt text-on-surface hover:bg-primary-subtle dark:hover:bg-primary-subtle'"
                class="h-8 px-4 rounded-full text-sm font-medium border border-outline dark:border-outline transition-colors duration-200"
            >
                {{ __('All') }}
            </button>

            {{-- Type pills --}}
            @foreach($typeOptions as $option)
                <button
                    type="button"
                    x-on:click="typeFilter = '{{ $option['value'] }}'; applyFilters()"
                    :class="typeFilter === '{{ $option['value'] }}' ? 'bg-primary text-on-primary' : 'bg-surface-alt dark:bg-surface-alt text-on-surface hover:bg-primary-subtle dark:hover:bg-primary-subtle'"
                    class="h-8 px-4 rounded-full text-sm font-medium border border-outline dark:border-outline transition-colors duration-200"
                >
                    {{ $option['label'] }}
                </button>
            @endforeach

            {{-- Sort direction toggle --}}
            <button
                type="button"
                x-on:click="direction = direction === 'desc' ? 'asc' : 'desc'; applyFilters()"
                class="ml-auto h-8 px-3 rounded-full bg-surface-alt dark:bg-surface-alt text-on-surface border border-outline dark:border-outline text-sm font-medium hover:bg-primary-subtle dark:hover:bg-primary-subtle transition-colors duration-200 flex items-center gap-1.5"
                :title="direction === 'desc' ? '{{ __('Newest first') }}' : '{{ __('Oldest first') }}'"
            >
                {{-- ArrowUpDown icon (Lucide, sm=16) --}}
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 16-4 4-4-4"></path><path d="M17 20V4"></path><path d="m3 8 4-4 4 4"></path><path d="M7 4v16"></path></svg>
                <span x-text="direction === 'desc' ? '{{ __('Newest') }}' : '{{ __('Oldest') }}'"></span>
            </button>
        </div>
    </div>

    {{-- Transaction List (Fragment for Gale navigate partial updates) --}}
    @fragment('transactions-content')
    <div id="transactions-content">
        @if($transactions->isEmpty())
            {{-- Empty State --}}
            <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-8 text-center">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-surface-alt dark:bg-surface-alt mb-4">
                    {{-- Receipt icon (Lucide, lg=24) --}}
                    <svg class="w-6 h-6 text-on-surface/40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"></path><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"></path><path d="M12 17.5v-11"></path></svg>
                </div>
                @if($typeFilter)
                    <p class="text-sm text-on-surface mb-3">
                        {{ __('No matching transactions found.') }}
                    </p>
                    <button
                        type="button"
                        x-on:click="clearFilters()"
                        class="inline-flex items-center gap-2 h-9 px-4 rounded-lg border border-outline dark:border-outline text-sm font-medium text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors duration-200"
                    >
                        {{ __('Clear filters') }}
                    </button>
                @else
                    <p class="text-sm text-on-surface">
                        {{ __('No transactions yet.') }}
                    </p>
                @endif
            </div>
        @else
            {{-- Desktop Table (hidden on mobile) --}}
            <div class="hidden md:block bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl overflow-hidden shadow-card mb-6">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-outline dark:border-outline">
                            <th class="text-left text-xs font-semibold uppercase tracking-wider text-on-surface/60 px-4 py-3">
                                {{ __('Date') }}
                            </th>
                            <th class="text-left text-xs font-semibold uppercase tracking-wider text-on-surface/60 px-4 py-3">
                                {{ __('Description') }}
                            </th>
                            <th class="text-left text-xs font-semibold uppercase tracking-wider text-on-surface/60 px-4 py-3">
                                {{ __('Type') }}
                            </th>
                            <th class="text-left text-xs font-semibold uppercase tracking-wider text-on-surface/60 px-4 py-3">
                                {{ __('Order') }}
                            </th>
                            <th class="text-right text-xs font-semibold uppercase tracking-wider text-on-surface/60 px-4 py-3">
                                {{ __('Amount') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline dark:divide-outline">
                        @foreach($transactions as $txn)
                            @php
                                $isCredit = $txn->isCredit();
                                $typeLabel = \App\Services\CookWalletService::getTransactionTypeLabel($txn->type);
                                $description = $txn->description ?? $typeLabel;
                                $orderRef = $txn->order?->order_number;
                            @endphp
                            <tr class="hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors">
                                <td class="px-4 py-3.5 text-sm text-on-surface whitespace-nowrap">
                                    {{ $txn->created_at->format('M d, Y') }}
                                    <span class="block text-xs text-on-surface/60">{{ $txn->created_at->format('H:i') }}</span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-3">
                                        {{-- Credit/Debit indicator icon --}}
                                        @if($isCredit)
                                            <span class="w-8 h-8 rounded-full bg-success-subtle dark:bg-success-subtle flex items-center justify-center shrink-0">
                                                <svg class="w-4 h-4 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 7 7 17"></path><path d="M17 17H7V7"></path></svg>
                                            </span>
                                        @else
                                            <span class="w-8 h-8 rounded-full bg-danger-subtle dark:bg-danger-subtle flex items-center justify-center shrink-0">
                                                <svg class="w-4 h-4 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7"></path><path d="M7 7h10v10"></path></svg>
                                            </span>
                                        @endif
                                        <span class="text-sm font-medium text-on-surface-strong truncate">
                                            {{ $description }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center h-6 px-2.5 rounded-full text-xs font-medium
                                        {{ $isCredit ? 'bg-success-subtle dark:bg-success-subtle text-success' : 'bg-danger-subtle dark:bg-danger-subtle text-danger' }}">
                                        {{ $typeLabel }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-sm" x-navigate>
                                    @if($orderRef)
                                        <a
                                            href="{{ url('/dashboard/orders/' . $txn->order_id) }}"
                                            class="font-mono text-primary hover:underline"
                                        >{{ $orderRef }}</a>
                                    @else
                                        <span class="text-on-surface/40">&mdash;</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                    <span class="text-sm font-semibold font-mono {{ $isCredit ? 'text-success' : 'text-danger' }}">
                                        {{ $isCredit ? '+' : '-' }}{{ $txn->formattedAmount() }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile Card Layout (hidden on desktop) --}}
            <div class="md:hidden space-y-3 mb-6">
                @foreach($transactions as $txn)
                    @php
                        $isCredit = $txn->isCredit();
                        $typeLabel = \App\Services\CookWalletService::getTransactionTypeLabel($txn->type);
                        $description = $txn->description ?? $typeLabel;
                        $orderRef = $txn->order?->order_number;
                    @endphp
                    <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-4 shadow-card">
                        <div class="flex items-start gap-3">
                            {{-- Credit/Debit indicator icon --}}
                            <div class="shrink-0 mt-0.5">
                                @if($isCredit)
                                    <span class="w-9 h-9 rounded-full bg-success-subtle dark:bg-success-subtle flex items-center justify-center">
                                        <svg class="w-4 h-4 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 7 7 17"></path><path d="M17 17H7V7"></path></svg>
                                    </span>
                                @else
                                    <span class="w-9 h-9 rounded-full bg-danger-subtle dark:bg-danger-subtle flex items-center justify-center">
                                        <svg class="w-4 h-4 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7"></path><path d="M7 7h10v10"></path></svg>
                                    </span>
                                @endif
                            </div>

                            {{-- Content --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-sm font-medium text-on-surface-strong truncate">
                                        {{ $description }}
                                    </p>
                                    <p class="text-sm font-semibold font-mono shrink-0 {{ $isCredit ? 'text-success' : 'text-danger' }}">
                                        {{ $isCredit ? '+' : '-' }}{{ $txn->formattedAmount() }}
                                    </p>
                                </div>

                                <div class="flex items-center flex-wrap gap-x-3 gap-y-1 mt-1.5">
                                    <span class="text-xs text-on-surface">
                                        {{ $txn->created_at->format('M d, Y H:i') }}
                                    </span>
                                    <span class="inline-flex items-center h-5 px-2 rounded-full text-xs font-medium
                                        {{ $isCredit ? 'bg-success-subtle dark:bg-success-subtle text-success' : 'bg-danger-subtle dark:bg-danger-subtle text-danger' }}">
                                        {{ $typeLabel }}
                                    </span>
                                </div>

                                @if($orderRef)
                                    <div class="mt-2" x-navigate>
                                        <a
                                            href="{{ url('/dashboard/orders/' . $txn->order_id) }}"
                                            class="text-xs font-mono text-primary hover:underline"
                                        >{{ $orderRef }}</a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if($transactions->hasPages())
                <div class="mt-6" x-navigate>
                    {{ $transactions->links() }}
                </div>
            @endif
        @endif
    </div>
    @endfragment

</div>
@endsection
