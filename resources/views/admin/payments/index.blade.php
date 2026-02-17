{{--
    Payment Monitoring View
    -----------------------
    F-059: Comprehensive list of all payment transactions across the platform.

    BR-151: Payment statuses: successful, failed, pending, refunded
    BR-155: Search covers: order ID, client name, client email, Flutterwave reference
    BR-156: Default sort: date descending (most recent first)
    BR-157: Pagination: 20 items per page

    UI/UX:
    - Summary bar: Total Transactions, Successful Amount, Failed Count, Pending Count
    - Status badges: green (successful), red (failed), yellow (pending), blue (refunded)
    - Search bar + status filter dropdown above the table
    - Mobile: card layout with key fields
--}}
@extends('layouts.admin')

@section('title', __('Payments'))
@section('page-title', __('Payments'))

@section('content')
<div class="space-y-6">
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[['label' => __('Payments')]]" />

    {{-- Header --}}
    <div>
        <h2 class="text-lg font-semibold text-on-surface-strong">{{ __('Payment Monitoring') }}</h2>
        <p class="text-sm text-on-surface mt-1">{{ __('Monitor all payment transactions across the platform.') }}</p>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        {{-- Total Transactions --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"></rect><line x1="2" x2="22" y1="10" y2="10"></line></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-on-surface uppercase tracking-wide truncate">{{ __('Total') }}</p>
                    <p class="text-2xl font-bold text-on-surface-strong">{{ number_format($totalCount) }}</p>
                </div>
            </div>
        </div>

        {{-- Successful Amount --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-success-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-on-surface uppercase tracking-wide truncate">{{ __('Successful') }}</p>
                    <p class="text-xl sm:text-2xl font-bold text-success truncate">{{ number_format($successfulAmount, 0, '.', ',') }} <span class="text-sm font-normal">XAF</span></p>
                </div>
            </div>
        </div>

        {{-- Failed Count --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-danger-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" x2="9" y1="9" y2="15"></line><line x1="9" x2="15" y1="9" y2="15"></line></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-on-surface uppercase tracking-wide truncate">{{ __('Failed') }}</p>
                    <p class="text-2xl font-bold text-danger">{{ number_format($failedCount) }}</p>
                </div>
            </div>
        </div>

        {{-- Pending Count --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-warning-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-on-surface uppercase tracking-wide truncate">{{ __('Pending') }}</p>
                    <p class="text-2xl font-bold text-warning">{{ number_format($pendingCount) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Main content area with search/filter/table --}}
    @fragment('payment-list-content')
    <div id="payment-list-content"
         x-data="{
             search: '{{ addslashes($search ?? '') }}',
             status: '{{ $status ?? '' }}',
             sortBy: '{{ $sortBy ?? 'created_at' }}',
             sortDir: '{{ $sortDir ?? 'desc' }}',
             baseUrl: '{{ url('/vault-entry/payments') }}',
             buildUrl() {
                 let params = new URLSearchParams();
                 if (this.search) params.set('search', this.search);
                 if (this.status) params.set('status', this.status);
                 if (this.sortBy !== 'created_at' || this.sortDir !== 'desc') {
                     params.set('sort', this.sortBy);
                     params.set('direction', this.sortDir);
                 }
                 let qs = params.toString();
                 return this.baseUrl + (qs ? '?' + qs : '');
             },
             doSearch() {
                 $navigate(this.buildUrl(), { key: 'payment-list', replace: true });
             },
             setSort(column) {
                 if (this.sortBy === column) {
                     this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                 } else {
                     this.sortBy = column;
                     this.sortDir = column === 'created_at' ? 'desc' : 'asc';
                 }
                 $navigate(this.buildUrl(), { key: 'payment-list', replace: true });
             },
             setStatus(val) {
                 this.status = val;
                 $navigate(this.buildUrl(), { key: 'payment-list', replace: true });
             },
             clearSearch() {
                 this.search = '';
                 $navigate(this.buildUrl(), { key: 'payment-list', replace: true });
             }
         }"
    >
        {{-- Search and Filters bar --}}
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
            {{-- Search input --}}
            <div class="relative flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-on-surface/50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                <input
                    type="text"
                    x-model="search"
                    @input.debounce.300ms="doSearch()"
                    placeholder="{{ __('Search by order ID, client, email, or reference...') }}"
                    class="w-full h-10 pl-10 pr-9 border border-outline dark:border-outline rounded-lg text-sm text-on-surface-strong placeholder:text-on-surface/50 bg-surface dark:bg-surface transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                />
                <button
                    x-show="search.length > 0"
                    @click="clearSearch()"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface/50 hover:text-on-surface transition-colors"
                    x-cloak
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                </button>
            </div>

            {{-- Status filter dropdown --}}
            <div class="relative">
                <select
                    x-model="status"
                    @change="setStatus($event.target.value)"
                    class="h-10 pl-3 pr-8 border border-outline dark:border-outline rounded-lg text-sm text-on-surface-strong bg-surface dark:bg-surface transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary appearance-none cursor-pointer"
                >
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="successful">{{ __('Successful') }}</option>
                    <option value="failed">{{ __('Failed') }}</option>
                    <option value="pending">{{ __('Pending') }}</option>
                    <option value="refunded">{{ __('Refunded') }}</option>
                </select>
                <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-on-surface/50 pointer-events-none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
            </div>
        </div>

        {{-- Table (Desktop) --}}
        @if($transactions->count() > 0)
            <div class="hidden md:block bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-outline dark:border-outline">
                                @php
                                    $columns = [
                                        ['key' => 'order_id', 'label' => __('Order ID'), 'align' => 'left'],
                                        ['key' => 'client', 'label' => __('Client'), 'align' => 'left', 'sortable' => false],
                                        ['key' => 'cook', 'label' => __('Cook'), 'align' => 'left', 'sortable' => false],
                                        ['key' => 'amount', 'label' => __('Amount'), 'align' => 'right'],
                                        ['key' => 'payment_method', 'label' => __('Method'), 'align' => 'center'],
                                        ['key' => 'status', 'label' => __('Status'), 'align' => 'center'],
                                        ['key' => 'created_at', 'label' => __('Date'), 'align' => 'left'],
                                    ];
                                @endphp
                                @foreach($columns as $col)
                                    @php $isSortable = $col['sortable'] ?? true; @endphp
                                    <th class="{{ $col['align'] === 'center' ? 'text-center' : ($col['align'] === 'right' ? 'text-right' : 'text-left') }} text-xs font-semibold uppercase tracking-wider text-on-surface/60 px-4 py-3 whitespace-nowrap {{ $isSortable ? 'cursor-pointer select-none hover:text-on-surface-strong transition-colors' : '' }}"
                                        @if($isSortable) @click="setSort('{{ $col['key'] }}')" @endif
                                    >
                                        <span class="inline-flex items-center gap-1 {{ $col['align'] === 'right' ? 'justify-end' : '' }}">
                                            {{ $col['label'] }}
                                            @if($isSortable)
                                                <span class="inline-flex flex-col" :class="sortBy === '{{ $col['key'] }}' ? 'text-primary' : 'text-on-surface/30'">
                                                    <svg x-show="!(sortBy === '{{ $col['key'] }}' && sortDir === 'desc')" class="w-3 h-3 -mb-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"></path></svg>
                                                    <svg x-show="!(sortBy === '{{ $col['key'] }}' && sortDir === 'asc')" class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
                                                </span>
                                            @endif
                                        </span>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline dark:divide-outline" x-data x-navigate>
                            @foreach($transactions as $txn)
                                <tr class="hover:bg-surface dark:hover:bg-surface transition-colors cursor-pointer group {{ $txn->isPendingTooLong() ? 'bg-warning-subtle/30 dark:bg-warning-subtle/20' : '' }}">
                                    {{-- Order ID --}}
                                    <td class="px-4 py-3">
                                        <a href="{{ url('/vault-entry/payments/'.$txn->id) }}" class="block">
                                            <span class="text-sm font-mono font-medium text-on-surface-strong group-hover:text-primary transition-colors">
                                                {{ $txn->order_id ? 'ORD-'.$txn->order_id : '—' }}
                                            </span>
                                            @if($txn->isPendingTooLong())
                                                <svg class="inline-block w-4 h-4 text-warning ml-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" title="{{ __('Pending for over 15 minutes') }}"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" x2="12" y1="9" y2="13"></line><line x1="12" x2="12.01" y1="17" y2="17"></line></svg>
                                            @endif
                                        </a>
                                    </td>

                                    {{-- Client --}}
                                    <td class="px-4 py-3">
                                        <a href="{{ url('/vault-entry/payments/'.$txn->id) }}" class="block">
                                            <p class="text-sm text-on-surface-strong truncate max-w-[150px]">{{ $txn->client?->name ?? $txn->customer_name ?? '—' }}</p>
                                            <p class="text-xs text-on-surface/60 truncate max-w-[150px]">{{ $txn->client?->email ?? $txn->customer_email ?? '' }}</p>
                                        </a>
                                    </td>

                                    {{-- Cook --}}
                                    <td class="px-4 py-3">
                                        <a href="{{ url('/vault-entry/payments/'.$txn->id) }}" class="block">
                                            <span class="text-sm text-on-surface truncate max-w-[120px] block">{{ $txn->cook?->name ?? '—' }}</span>
                                        </a>
                                    </td>

                                    {{-- Amount --}}
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ url('/vault-entry/payments/'.$txn->id) }}" class="block">
                                            <span class="text-sm font-semibold text-on-surface-strong font-mono">{{ $txn->formattedAmount() }}</span>
                                        </a>
                                    </td>

                                    {{-- Payment Method --}}
                                    <td class="px-4 py-3 text-center">
                                        <a href="{{ url('/vault-entry/payments/'.$txn->id) }}" class="block">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold
                                                {{ $txn->payment_method === 'mtn_mobile_money' ? 'bg-[#ffcc00]/20 text-[#996600] dark:text-[#ffcc00]' : 'bg-[#ff6600]/20 text-[#cc5200] dark:text-[#ff9944]' }}">
                                                <span class="w-2 h-2 rounded-full {{ $txn->payment_method === 'mtn_mobile_money' ? 'bg-[#ffcc00]' : 'bg-[#ff6600]' }}"></span>
                                                {{ $txn->payment_method === 'mtn_mobile_money' ? 'MTN' : 'Orange' }}
                                            </span>
                                        </a>
                                    </td>

                                    {{-- Status --}}
                                    <td class="px-4 py-3 text-center">
                                        <a href="{{ url('/vault-entry/payments/'.$txn->id) }}" class="block">
                                            @include('admin.payments._status-badge', ['status' => $txn->status])
                                        </a>
                                    </td>

                                    {{-- Date --}}
                                    <td class="px-4 py-3">
                                        <a href="{{ url('/vault-entry/payments/'.$txn->id) }}" class="block">
                                            <span class="text-sm text-on-surface" title="{{ $txn->created_at?->format('Y-m-d H:i:s') }}">{{ $txn->created_at?->format('M d, Y') }}</span>
                                            <span class="text-xs text-on-surface/60 block">{{ $txn->created_at?->format('H:i') }}</span>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Mobile card view --}}
            <div class="md:hidden space-y-3" x-data x-navigate>
                @foreach($transactions as $txn)
                    <a href="{{ url('/vault-entry/payments/'.$txn->id) }}" class="block bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 hover:border-primary/30 transition-colors {{ $txn->isPendingTooLong() ? 'border-warning/50 bg-warning-subtle/20' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-mono font-semibold text-on-surface-strong">{{ $txn->order_id ? 'ORD-'.$txn->order_id : '—' }}</span>
                                    @if($txn->isPendingTooLong())
                                        <svg class="w-4 h-4 text-warning shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" x2="12" y1="9" y2="13"></line><line x1="12" x2="12.01" y1="17" y2="17"></line></svg>
                                    @endif
                                </div>
                                <p class="text-xs text-on-surface/60 mt-0.5 truncate">{{ $txn->client?->name ?? $txn->customer_name ?? '—' }}</p>
                            </div>
                            @include('admin.payments._status-badge', ['status' => $txn->status])
                        </div>
                        <div class="mt-3 flex items-center justify-between">
                            <span class="text-sm font-semibold text-on-surface-strong font-mono">{{ $txn->formattedAmount() }}</span>
                            <div class="flex items-center gap-3 text-xs text-on-surface/60">
                                <span class="inline-flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $txn->payment_method === 'mtn_mobile_money' ? 'bg-[#ffcc00]' : 'bg-[#ff6600]' }}"></span>
                                    {{ $txn->payment_method === 'mtn_mobile_money' ? 'MTN' : 'Orange' }}
                                </span>
                                <span>{{ $txn->created_at?->format('M d, H:i') }}</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if($transactions->hasPages())
                <div class="mt-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <p class="text-sm text-on-surface/60">
                        {{ __('Showing :from-:to of :total transactions', [
                            'from' => $transactions->firstItem(),
                            'to' => $transactions->lastItem(),
                            'total' => $transactions->total(),
                        ]) }}
                    </p>
                    <div x-data x-navigate>
                        {{ $transactions->links() }}
                    </div>
                </div>
            @else
                <p class="mt-4 text-sm text-on-surface/60">
                    {{ __('Showing :from-:to of :total transactions', [
                        'from' => $transactions->firstItem() ?? 0,
                        'to' => $transactions->lastItem() ?? 0,
                        'total' => $transactions->total(),
                    ]) }}
                </p>
            @endif

        @elseif(!empty($search) || !empty($status))
            {{-- No results from search/filter --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-8 sm:p-12 text-center">
                <svg class="w-12 h-12 mx-auto text-on-surface/30 mb-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                <p class="text-on-surface font-medium">{{ __('No transactions match your search.') }}</p>
                <p class="text-sm text-on-surface/60 mt-1">{{ __('Try adjusting your search or filter criteria.') }}</p>
                <button
                    @click="search = ''; status = ''; $navigate(baseUrl, { key: 'payment-list', replace: true })"
                    class="mt-4 inline-flex items-center gap-2 h-10 px-5 text-sm rounded-lg font-semibold border border-outline text-on-surface hover:bg-surface-alt transition-all duration-200"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                    {{ __('Clear Filters') }}
                </button>
            </div>
        @else
            {{-- Empty state: no transactions exist --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-8 sm:p-12 text-center">
                <svg class="w-12 h-12 mx-auto text-on-surface/30 mb-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"></rect><line x1="2" x2="22" y1="10" y2="10"></line></svg>
                <p class="text-on-surface font-medium">{{ __('No payment transactions recorded yet.') }}</p>
                <p class="text-sm text-on-surface/60 mt-1">{{ __('Payment transactions will appear here when customers make purchases.') }}</p>
            </div>
        @endif
    </div>
    @endfragment
</div>
@endsection
