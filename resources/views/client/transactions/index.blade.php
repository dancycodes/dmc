{{--
    Client Transaction History (F-164)
    -----------------------------------
    Displays a paginated list of all financial transactions for the authenticated client.
    Transaction types: payments (debit), refunds (credit), wallet payments (debit).

    BR-260: Shows all client transactions across all tenants.
    BR-261: Transaction types: payment, refund, wallet_payment.
    BR-262: Default sort by date descending.
    BR-263: 20 per page.
    BR-264: Each entry shows: date, description, amount, type, status, reference.
    BR-265: Debit shown with red/negative indicator.
    BR-266: Credit shown with green/positive indicator.
    BR-267: Filter by type: All, Payments, Refunds, Wallet Payments.
    BR-268: Clicking navigates to transaction detail (F-165).
    BR-269: Auth required.
    BR-270: All text uses __() localization.
--}}
@extends(tenant() ? 'layouts.tenant-public' : 'layouts.main-public')

@section('title', __('Transaction History'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8" x-data="{
    typeFilter: '{{ $typeFilter }}',
    direction: '{{ $direction }}',
    applyFilters() {
        let url = '{{ url('/my-transactions') }}';
        let params = new URLSearchParams();
        if (this.typeFilter) params.set('type', this.typeFilter);
        if (this.direction !== 'desc') params.set('direction', this.direction);
        let qs = params.toString();
        $navigate(url + (qs ? '?' + qs : ''), { key: 'transactions', replace: true });
    },
    clearFilters() {
        this.typeFilter = '';
        this.direction = 'desc';
        $navigate('{{ url('/my-transactions') }}', { key: 'transactions', replace: true });
    }
}">
    {{-- Page Header --}}
    <div class="mb-6">
        <h1 class="text-2xl sm:text-3xl font-display font-bold text-on-surface-strong">
            {{ __('Transaction History') }}
        </h1>
        <p class="mt-1 text-sm text-on-surface">
            {{ __('View your payment history across all orders.') }}
        </p>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
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

        {{-- Payments --}}
        <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-4 shadow-card">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-info-subtle dark:bg-info-subtle flex items-center justify-center shrink-0">
                    {{-- CreditCard icon (Lucide, md=20) --}}
                    <svg class="w-5 h-5 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"></rect><line x1="2" x2="22" y1="10" y2="10"></line></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs text-on-surface truncate">{{ __('Payments') }}</p>
                    <p class="text-lg font-bold text-on-surface-strong">{{ $summaryCounts['payments'] }}</p>
                </div>
            </div>
        </div>

        {{-- Refunds --}}
        <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-4 shadow-card">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-success-subtle dark:bg-success-subtle flex items-center justify-center shrink-0">
                    {{-- RefreshCcw icon (Lucide, md=20) --}}
                    <svg class="w-5 h-5 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"></path><path d="M16 16h5v5"></path></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs text-on-surface truncate">{{ __('Refunds') }}</p>
                    <p class="text-lg font-bold text-on-surface-strong">{{ $summaryCounts['refunds'] }}</p>
                </div>
            </div>
        </div>

        {{-- Wallet Payments --}}
        <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-4 shadow-card">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-warning-subtle dark:bg-warning-subtle flex items-center justify-center shrink-0">
                    {{-- Wallet icon (Lucide, md=20) --}}
                    <svg class="w-5 h-5 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"></path><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"></path></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs text-on-surface truncate">{{ __('Wallet Payments') }}</p>
                    <p class="text-lg font-bold text-on-surface-strong">{{ $summaryCounts['wallet_payments'] }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Type Filter Pills + Sort --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center gap-3">
        {{-- Type Filter Pills --}}
        <div class="flex-1 flex flex-wrap gap-2">
            @foreach($typeOptions as $option)
                <button
                    type="button"
                    x-on:click="typeFilter = '{{ $option['value'] }}'; applyFilters()"
                    :class="typeFilter === '{{ $option['value'] }}'
                        ? 'bg-primary text-on-primary shadow-sm'
                        : 'bg-surface-alt dark:bg-surface-alt text-on-surface hover:bg-primary-subtle dark:hover:bg-primary-subtle border border-outline dark:border-outline'"
                    class="h-9 px-4 rounded-full text-sm font-medium transition-all duration-200"
                >
                    {{ $option['label'] }}
                </button>
            @endforeach
        </div>

        <div class="flex items-center gap-2">
            {{-- Sort Direction Toggle --}}
            <button
                type="button"
                x-on:click="direction = direction === 'desc' ? 'asc' : 'desc'; applyFilters()"
                class="h-10 px-3 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface hover:bg-surface-alt transition-colors text-sm flex items-center gap-2"
                :title="direction === 'desc' ? '{{ __('Newest first') }}' : '{{ __('Oldest first') }}'"
            >
                {{-- ArrowUpDown icon (Lucide) --}}
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 16-4 4-4-4"></path><path d="M17 20V4"></path><path d="m3 8 4-4 4 4"></path><path d="M7 4v16"></path></svg>
                <span x-text="direction === 'desc' ? '{{ __('Newest') }}' : '{{ __('Oldest') }}'"></span>
            </button>

            {{-- Clear Filters --}}
            <button
                type="button"
                x-show="typeFilter !== '' || direction !== 'desc'"
                x-on:click="clearFilters()"
                class="h-10 px-3 rounded-lg text-sm font-medium text-danger hover:bg-danger-subtle transition-colors"
                x-cloak
            >
                {{ __('Clear') }}
            </button>
        </div>
    </div>

    @fragment('transactions-content')
    <div id="transactions-content">

        @if($transactions->isEmpty())
            {{-- Scenario 5: Empty State --}}
            <div class="text-center py-16 sm:py-24">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-surface-alt dark:bg-surface-alt mb-6">
                    {{-- Receipt icon (Lucide, xl=32) --}}
                    <svg class="w-8 h-8 text-on-surface/40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"></path><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"></path><path d="M12 17.5v-11"></path></svg>
                </div>
                @if($typeFilter)
                    <h2 class="text-xl font-semibold text-on-surface-strong mb-2">
                        {{ __('No transactions match your filter.') }}
                    </h2>
                    <p class="text-on-surface mb-6">
                        {{ __('Try a different filter or view all transactions.') }}
                    </p>
                    <button
                        type="button"
                        x-on:click="clearFilters()"
                        class="inline-flex items-center gap-2 h-10 px-6 rounded-lg bg-primary hover:bg-primary-hover text-on-primary font-semibold text-sm transition-colors"
                    >
                        {{ __('View All Transactions') }}
                    </button>
                @else
                    <h2 class="text-xl font-semibold text-on-surface-strong mb-2">
                        {{ __('No transactions yet.') }}
                    </h2>
                    <p class="text-on-surface mb-6">
                        {{ __('Your payment history will appear here once you place your first order.') }}
                    </p>
                    <a href="{{ url('/') }}" class="inline-flex items-center gap-2 h-10 px-6 rounded-lg bg-primary hover:bg-primary-hover text-on-primary font-semibold text-sm transition-colors" x-data x-navigate>
                        {{-- Search icon --}}
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                        {{ __('Discover Cooks') }}
                    </a>
                @endif
            </div>
        @else
            {{-- Desktop Table --}}
            <div class="hidden md:block bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl overflow-hidden shadow-card">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-surface-alt dark:bg-surface-alt border-b border-outline dark:border-outline">
                            <th class="text-left px-4 py-3 font-semibold text-on-surface-strong">{{ __('Date') }}</th>
                            <th class="text-left px-4 py-3 font-semibold text-on-surface-strong">{{ __('Description') }}</th>
                            <th class="text-right px-4 py-3 font-semibold text-on-surface-strong">{{ __('Amount') }}</th>
                            <th class="text-left px-4 py-3 font-semibold text-on-surface-strong">{{ __('Type') }}</th>
                            <th class="text-left px-4 py-3 font-semibold text-on-surface-strong">{{ __('Status') }}</th>
                            <th class="text-left px-4 py-3 font-semibold text-on-surface-strong">{{ __('Reference') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline dark:divide-outline">
                        @foreach($transactions as $txn)
                            <tr class="hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors cursor-pointer" x-data x-navigate>
                                {{-- BR-264: Date --}}
                                <td class="px-4 py-3 text-on-surface whitespace-nowrap">
                                    {{ $txn['date']->format('M d, Y') }}
                                </td>
                                {{-- BR-264: Description (clickable to F-165) --}}
                                <td class="px-4 py-3">
                                    <a href="{{ url('/my-transactions/' . $txn['source_type'] . '/' . $txn['source_id']) }}" class="text-sm font-medium text-on-surface-strong hover:text-primary transition-colors">
                                        {{ $txn['description'] }}
                                    </a>
                                    @if($txn['tenant_name'])
                                        <p class="text-xs text-on-surface mt-0.5">{{ $txn['tenant_name'] }}</p>
                                    @endif
                                </td>
                                {{-- BR-264: Amount with BR-265/BR-266 debit/credit indicators --}}
                                <td class="px-4 py-3 text-right whitespace-nowrap font-medium {{ $txn['debit_credit'] === 'credit' ? 'text-success' : 'text-danger' }}">
                                    {{ $txn['debit_credit'] === 'credit' ? '+' : '-' }}{{ \App\Services\ClientTransactionService::formatXAF($txn['amount']) }}
                                </td>
                                {{-- BR-264: Type --}}
                                <td class="px-4 py-3">
                                    @include('client.transactions._type-badge', ['type' => $txn['type']])
                                </td>
                                {{-- BR-264: Status --}}
                                <td class="px-4 py-3">
                                    @include('client.transactions._status-badge', ['status' => $txn['status']])
                                </td>
                                {{-- BR-264: Reference --}}
                                <td class="px-4 py-3 text-on-surface font-mono text-xs max-w-[120px] truncate" title="{{ $txn['reference'] }}">
                                    {{ $txn['reference'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile Cards --}}
            <div class="md:hidden space-y-3">
                @foreach($transactions as $txn)
                    <a href="{{ url('/my-transactions/' . $txn['source_type'] . '/' . $txn['source_id']) }}" class="block bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-4 shadow-card hover:shadow-md transition-shadow" x-data x-navigate>
                        <div class="flex items-start justify-between gap-3 mb-2">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-on-surface-strong truncate">{{ $txn['description'] }}</p>
                                <p class="text-xs text-on-surface mt-0.5">{{ $txn['date']->format('M d, Y H:i') }}</p>
                            </div>
                            @include('client.transactions._status-badge', ['status' => $txn['status']])
                        </div>
                        <div class="flex items-center justify-between gap-3 mt-3">
                            <div class="flex items-center gap-2">
                                @include('client.transactions._type-badge', ['type' => $txn['type']])
                                @if($txn['tenant_name'])
                                    <span class="text-xs text-on-surface">{{ $txn['tenant_name'] }}</span>
                                @endif
                            </div>
                            <span class="text-sm font-semibold whitespace-nowrap {{ $txn['debit_credit'] === 'credit' ? 'text-success' : 'text-danger' }}">
                                {{ $txn['debit_credit'] === 'credit' ? '+' : '-' }}{{ \App\Services\ClientTransactionService::formatXAF($txn['amount']) }}
                            </span>
                        </div>
                        @if($txn['reference'] !== '-')
                            <p class="text-xs text-on-surface/60 font-mono mt-2 truncate">{{ __('Ref') }}: {{ $txn['reference'] }}</p>
                        @endif
                    </a>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if($transactions->hasPages())
                <div class="mt-6">
                    {{ $transactions->links() }}
                </div>
            @endif
        @endif
    </div>
    @endfragment
</div>
@endsection
