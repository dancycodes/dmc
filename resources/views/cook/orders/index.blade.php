{{--
    Cook Order List View
    --------------------
    F-155: Cook Order List View
    Displays a paginated list of all orders for the cook's tenant.

    BR-155: Orders are tenant-scoped.
    BR-156: Paginated with default 20 per page.
    BR-157: Default sort by date descending (newest first).
    BR-158: Color-coded status badges.
    BR-159: Case-insensitive search by order ID or client name.
    BR-160: Filters and search can be combined.
    BR-161: New orders pushed in real-time via Gale SSE.
    BR-162: Only users with manage-orders permission.
    BR-163: Truncated items summary with quantities.
    BR-164: All amounts in XAF.
    BR-165: All text uses __() localization.
--}}
@extends('layouts.cook-dashboard')

@section('title', __('Orders'))
@section('page-title', __('Orders'))

@section('content')
<div
    class="max-w-6xl mx-auto"
    x-data="{
        search: '{{ addslashes($search) }}',
        status: '{{ $status }}',
        date_from: '{{ $date_from }}',
        date_to: '{{ $date_to }}',
        sort: '{{ $sort }}',
        direction: '{{ $direction }}',
        selectedOrders: [],
        selectAll: false,

        applyFilters() {
            let url = '{{ url('/dashboard/orders') }}?sort=' + this.sort + '&direction=' + this.direction;
            if (this.search) url += '&search=' + encodeURIComponent(this.search);
            if (this.status) url += '&status=' + this.status;
            if (this.date_from) url += '&date_from=' + this.date_from;
            if (this.date_to) url += '&date_to=' + this.date_to;
            this.selectedOrders = [];
            this.selectAll = false;
            $navigate(url, { key: 'order-list', replace: true });
        },
        clearFilters() {
            this.search = '';
            this.status = '';
            this.date_from = '';
            this.date_to = '';
            this.sort = 'created_at';
            this.direction = 'desc';
            this.selectedOrders = [];
            this.selectAll = false;
            $navigate('{{ url('/dashboard/orders') }}', { key: 'order-list', replace: true });
        },
        sortBy(column) {
            if (this.sort === column) {
                this.direction = this.direction === 'asc' ? 'desc' : 'asc';
            } else {
                this.sort = column;
                this.direction = column === 'created_at' ? 'desc' : 'asc';
            }
            this.applyFilters();
        },
        toggleSelectAll() {
            if (this.selectAll) {
                this.selectedOrders = [...document.querySelectorAll('[data-order-id]')].map(el => el.dataset.orderId);
            } else {
                this.selectedOrders = [];
            }
        },
        toggleOrder(orderId) {
            const id = String(orderId);
            const idx = this.selectedOrders.indexOf(id);
            if (idx > -1) {
                this.selectedOrders.splice(idx, 1);
            } else {
                this.selectedOrders.push(id);
            }
            const totalCheckboxes = document.querySelectorAll('[data-order-id]').length;
            this.selectAll = this.selectedOrders.length === totalCheckboxes && totalCheckboxes > 0;
        },
        isSelected(orderId) {
            return this.selectedOrders.includes(String(orderId));
        },
        hasActiveFilters() {
            return this.search || this.status || this.date_from || this.date_to;
        },

        async refreshOrders() {
            let url = '{{ url('/dashboard/orders') }}?sort=' + this.sort + '&direction=' + this.direction;
            if (this.search) url += '&search=' + encodeURIComponent(this.search);
            if (this.status) url += '&status=' + this.status;
            if (this.date_from) url += '&date_from=' + this.date_from;
            if (this.date_to) url += '&date_to=' + this.date_to;
            $navigate(url, { key: 'order-list', replace: true });
        }
    }"
    x-interval.15s.visible="refreshOrders()"
>
    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm text-on-surface/60 mb-6" aria-label="{{ __('Breadcrumb') }}">
        <a href="{{ url('/dashboard') }}" class="hover:text-primary transition-colors duration-200">
            {{ __('Dashboard') }}
        </a>
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
        <span class="text-on-surface-strong font-medium">{{ __('Orders') }}</span>
    </nav>

    {{-- Page header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-display font-bold text-on-surface-strong">{{ __('Orders') }}</h2>
            <p class="mt-1 text-sm text-on-surface/70">{{ __('Manage incoming orders from your customers.') }}</p>
        </div>
    </div>

    {{-- Toast notifications --}}
    @if(session('success'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 5000)"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="mb-6 p-4 rounded-lg bg-success-subtle border border-success/20 flex items-center gap-3"
        >
            <svg class="w-5 h-5 text-success shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            <span class="text-sm text-on-surface">{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 7000)"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="mb-6 p-4 rounded-lg bg-danger-subtle border border-danger/20 flex items-center gap-3"
        >
            <svg class="w-5 h-5 text-danger shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            <span class="text-sm text-on-surface">{{ session('error') }}</span>
        </div>
    @endif

    @fragment('order-list-content')
    <div id="order-list-content">

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        {{-- Total Orders --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-3 sm:p-4">
            <div class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-full bg-primary-subtle flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"></rect><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><path d="M12 11h4"></path><path d="M12 16h4"></path><path d="M8 11h.01"></path><path d="M8 16h.01"></path></svg>
                </span>
                <div>
                    <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Total') }}</p>
                    <p class="text-xl font-bold text-on-surface-strong">{{ $summary['total'] }}</p>
                </div>
            </div>
        </div>

        {{-- Paid --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-3 sm:p-4">
            <div class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-full bg-info-subtle flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="12" x="2" y="6" rx="2"></rect><circle cx="12" cy="12" r="2"></circle><path d="M6 12h.01M18 12h.01"></path></svg>
                </span>
                <div>
                    <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Paid') }}</p>
                    <p class="text-xl font-bold text-on-surface-strong">{{ $summary['paid'] }}</p>
                </div>
            </div>
        </div>

        {{-- Preparing --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-3 sm:p-4">
            <div class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-full bg-warning-subtle flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"></path><path d="M9 18h6"></path><path d="M10 22h4"></path></svg>
                </span>
                <div>
                    <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Preparing') }}</p>
                    <p class="text-xl font-bold text-on-surface-strong">{{ $summary['preparing'] }}</p>
                </div>
            </div>
        </div>

        {{-- Completed --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-3 sm:p-4">
            <div class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-full bg-success-subtle flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                </span>
                <div>
                    <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Completed') }}</p>
                    <p class="text-xl font-bold text-on-surface-strong">{{ $summary['completed'] }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter / Search Bar --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-4 mb-6 shadow-card">
        <div class="flex flex-col gap-3">
            {{-- Top row: Search + Status --}}
            <div class="flex flex-col sm:flex-row gap-3">
                {{-- Search --}}
                <div class="flex-1">
                    <label for="order-search" class="sr-only">{{ __('Search orders') }}</label>
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-on-surface/40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                        <input
                            id="order-search"
                            type="text"
                            x-model="search"
                            @input.debounce.400ms="applyFilters()"
                            placeholder="{{ __('Search by order ID or client name') }}"
                            class="w-full pl-10 pr-3 py-2.5 bg-surface dark:bg-surface text-on-surface border border-outline dark:border-outline rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors duration-200"
                        >
                    </div>
                </div>

                {{-- Status filter --}}
                <div class="w-full sm:w-44">
                    <label for="order-status" class="sr-only">{{ __('Status') }}</label>
                    <select
                        id="order-status"
                        x-model="status"
                        @change="applyFilters()"
                        class="w-full px-3 py-2.5 bg-surface dark:bg-surface text-on-surface border border-outline dark:border-outline rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors duration-200"
                    >
                        @foreach($statusOptions as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Bottom row: Date range + Clear --}}
            <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
                <div class="flex items-center gap-2 flex-1">
                    <label for="date-from" class="text-sm text-on-surface/70 shrink-0">{{ __('From') }}</label>
                    <input
                        id="date-from"
                        type="date"
                        x-model="date_from"
                        @change="applyFilters()"
                        class="flex-1 px-3 py-2 bg-surface dark:bg-surface text-on-surface border border-outline dark:border-outline rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors duration-200"
                    >
                    <label for="date-to" class="text-sm text-on-surface/70 shrink-0">{{ __('To') }}</label>
                    <input
                        id="date-to"
                        type="date"
                        x-model="date_to"
                        @change="applyFilters()"
                        class="flex-1 px-3 py-2 bg-surface dark:bg-surface text-on-surface border border-outline dark:border-outline rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors duration-200"
                    >
                </div>

                {{-- Clear filters --}}
                <button
                    x-show="hasActiveFilters()"
                    @click="clearFilters()"
                    class="text-sm text-danger hover:text-danger/80 font-medium transition-colors duration-200 flex items-center gap-1 shrink-0"
                    x-cloak
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                    {{ __('Clear all filters') }}
                </button>
            </div>
        </div>
    </div>

    {{-- Mass action toolbar (F-158 forward-compatible) --}}
    <div
        x-show="selectedOrders.length > 0"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="mb-4 p-3 rounded-lg bg-primary-subtle border border-primary/20 flex items-center justify-between"
        x-cloak
    >
        <span class="text-sm font-medium text-primary">
            <span x-text="selectedOrders.length"></span> {{ __('orders selected') }}
        </span>
        <div class="flex items-center gap-2">
            {{-- F-158: Mass action buttons will be added here --}}
            <button
                @click="selectedOrders = []; selectAll = false"
                class="text-sm text-on-surface/70 hover:text-on-surface transition-colors duration-200"
            >
                {{ __('Clear selection') }}
            </button>
        </div>
    </div>

    {{-- Orders Table / Cards --}}
    @if($orders->count() > 0)
        {{-- Desktop Table --}}
        <div class="hidden md:block bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-outline dark:border-outline">
                            {{-- Select all checkbox --}}
                            <th class="px-4 py-3 w-10">
                                <input
                                    type="checkbox"
                                    x-model="selectAll"
                                    @change="toggleSelectAll()"
                                    class="w-4 h-4 rounded border-outline text-primary focus:ring-primary/40"
                                    aria-label="{{ __('Select all orders on this page') }}"
                                >
                            </th>
                            {{-- Order ID --}}
                            <th class="px-4 py-3 text-left text-xs font-medium text-on-surface/60 uppercase tracking-wider">
                                {{ __('Order') }}
                            </th>
                            {{-- Client --}}
                            <th class="px-4 py-3 text-left text-xs font-medium text-on-surface/60 uppercase tracking-wider">
                                {{ __('Customer') }}
                            </th>
                            {{-- Items --}}
                            <th class="px-4 py-3 text-left text-xs font-medium text-on-surface/60 uppercase tracking-wider">
                                {{ __('Items') }}
                            </th>
                            {{-- Total --}}
                            <th
                                class="px-4 py-3 text-right text-xs font-medium text-on-surface/60 uppercase tracking-wider cursor-pointer hover:text-on-surface transition-colors duration-200"
                                @click="sortBy('grand_total')"
                            >
                                <span class="inline-flex items-center gap-1">
                                    {{ __('Total') }}
                                    <span x-show="sort === 'grand_total'" x-cloak>
                                        <svg x-show="direction === 'asc'" class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"></path></svg>
                                        <svg x-show="direction === 'desc'" class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
                                    </span>
                                </span>
                            </th>
                            {{-- Status --}}
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-on-surface/60 uppercase tracking-wider cursor-pointer hover:text-on-surface transition-colors duration-200"
                                @click="sortBy('status')"
                            >
                                <span class="inline-flex items-center gap-1">
                                    {{ __('Status') }}
                                    <span x-show="sort === 'status'" x-cloak>
                                        <svg x-show="direction === 'asc'" class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"></path></svg>
                                        <svg x-show="direction === 'desc'" class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
                                    </span>
                                </span>
                            </th>
                            {{-- Date --}}
                            <th
                                class="px-4 py-3 text-right text-xs font-medium text-on-surface/60 uppercase tracking-wider cursor-pointer hover:text-on-surface transition-colors duration-200"
                                @click="sortBy('created_at')"
                            >
                                <span class="inline-flex items-center gap-1">
                                    {{ __('Date') }}
                                    <span x-show="sort === 'created_at'" x-cloak>
                                        <svg x-show="direction === 'asc'" class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"></path></svg>
                                        <svg x-show="direction === 'desc'" class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
                                    </span>
                                </span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline dark:divide-outline">
                        @foreach($orders as $order)
                            <tr
                                class="hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors duration-150 cursor-pointer"
                                data-order-id="{{ $order->id }}"
                            >
                                {{-- Checkbox --}}
                                <td class="px-4 py-3.5" @click.stop>
                                    <input
                                        type="checkbox"
                                        :checked="isSelected({{ $order->id }})"
                                        @change="toggleOrder({{ $order->id }})"
                                        class="w-4 h-4 rounded border-outline text-primary focus:ring-primary/40"
                                        aria-label="{{ __('Select order') }} #{{ $order->order_number }}"
                                    >
                                </td>
                                {{-- Order number (clickable -> detail) --}}
                                <td class="px-4 py-3.5">
                                    <a href="{{ url('/dashboard/orders/' . $order->id) }}" class="text-sm font-medium text-primary hover:underline" x-navigate>
                                        #{{ $order->order_number }}
                                    </a>
                                </td>
                                {{-- Client name --}}
                                <td class="px-4 py-3.5 text-sm text-on-surface">
                                    {{ $order->client?->name ?? __('Guest') }}
                                </td>
                                {{-- Items summary --}}
                                <td class="px-4 py-3.5 text-sm text-on-surface/70 max-w-[200px] truncate" title="{{ $order->items_summary }}">
                                    {{ $order->items_summary }}
                                </td>
                                {{-- Grand total --}}
                                <td class="px-4 py-3.5 text-sm text-on-surface-strong font-mono text-right">
                                    {{ \App\Services\CookOrderService::formatXAF($order->grand_total) }}
                                </td>
                                {{-- Status badge --}}
                                <td class="px-4 py-3.5">
                                    @include('cook._order-status-badge', ['status' => $order->status])
                                </td>
                                {{-- Date --}}
                                <td class="px-4 py-3.5 text-sm text-on-surface/60 text-right whitespace-nowrap">
                                    {{ $order->created_at->format('M d, Y') }}
                                    <span class="block text-xs">{{ $order->created_at->format('H:i') }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Mobile Card Layout --}}
        <div class="md:hidden space-y-3">
            @foreach($orders as $order)
                <div
                    class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline p-4 transition-shadow duration-200 hover:shadow-md"
                    data-order-id="{{ $order->id }}"
                >
                    <div class="flex items-start gap-3">
                        {{-- Checkbox --}}
                        <input
                            type="checkbox"
                            :checked="isSelected({{ $order->id }})"
                            @change="toggleOrder({{ $order->id }})"
                            class="w-4 h-4 mt-1 rounded border-outline text-primary focus:ring-primary/40 shrink-0"
                            aria-label="{{ __('Select order') }} #{{ $order->order_number }}"
                        >
                        {{-- Content --}}
                        <a href="{{ url('/dashboard/orders/' . $order->id) }}" class="flex-1 min-w-0" x-navigate>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-primary">
                                    #{{ $order->order_number }}
                                </span>
                                @include('cook._order-status-badge', ['status' => $order->status])
                            </div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm text-on-surface">
                                    {{ $order->client?->name ?? __('Guest') }}
                                </span>
                                <span class="text-sm font-mono font-medium text-on-surface-strong">
                                    {{ \App\Services\CookOrderService::formatXAF($order->grand_total) }}
                                </span>
                            </div>
                            <p class="text-xs text-on-surface/60 truncate mb-1">{{ $order->items_summary }}</p>
                            <p class="text-xs text-on-surface/50">{{ $order->created_at->diffForHumans() }}</p>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($orders->hasPages())
            <div class="mt-6" x-navigate>
                {{ $orders->links() }}
            </div>
        @endif
    @else
        {{-- Empty State --}}
        <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline p-12 text-center">
            <span class="w-16 h-16 rounded-full bg-surface-alt dark:bg-surface-alt flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"></path><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path><path d="m3.3 7 8.7 5 8.7-5"></path><path d="M12 22V12"></path></svg>
            </span>

            @if($search || $status || $date_from || $date_to)
                <h3 class="text-base font-semibold text-on-surface-strong mb-1">{{ __('No orders match your search') }}</h3>
                <p class="text-sm text-on-surface/60 mb-4">{{ __('Try adjusting your filters or search term.') }}</p>
                <button
                    @click="clearFilters()"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-primary text-on-primary hover:bg-primary-hover transition-colors duration-200"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                    {{ __('Clear all filters') }}
                </button>
            @else
                <h3 class="text-base font-semibold text-on-surface-strong mb-1">{{ __('No orders yet') }}</h3>
                <p class="text-sm text-on-surface/60">{{ __('Share your page link to start receiving orders from customers.') }}</p>
            @endif
        </div>
    @endif

    </div>
    @endfragment
</div>
@endsection
