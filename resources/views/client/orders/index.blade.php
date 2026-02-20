{{--
    Client Order List (F-160)
    -------------------------
    Displays the authenticated client's order history across all tenants.
    Active orders are pinned to the top, past orders paginated below.

    BR-212: All orders across all tenants.
    BR-213: Active orders pinned at top.
    BR-214: Past orders below.
    BR-215: Default sort by date descending.
    BR-216: 15 orders per page.
    BR-217: Cook name links to tenant landing page.
    BR-218: Same status badge colors as cook order list.
    BR-219: Accessible from any domain.
    BR-220: Auth required.
    BR-221: All text uses __() localization.
--}}
@extends(tenant() ? 'layouts.tenant-public' : 'layouts.main-public')

@section('title', __('My Orders'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8" x-data="{
    statusFilter: '{{ $statusFilter }}',
    sort: '{{ $sort }}',
    direction: '{{ $direction }}',
    applyFilters() {
        let url = '{{ url('/my-orders') }}';
        let params = new URLSearchParams();
        if (this.statusFilter) params.set('status', this.statusFilter);
        if (this.sort !== 'created_at') params.set('sort', this.sort);
        if (this.direction !== 'desc') params.set('direction', this.direction);
        let qs = params.toString();
        $navigate(url + (qs ? '?' + qs : ''), { key: 'orders', replace: true });
    },
    clearFilters() {
        this.statusFilter = '';
        this.sort = 'created_at';
        this.direction = 'desc';
        $navigate('{{ url('/my-orders') }}', { key: 'orders', replace: true });
    }
}">
    {{-- Page Header --}}
    <div class="mb-6">
        <h1 class="text-2xl sm:text-3xl font-display font-bold text-on-surface-strong">
            {{ __('My Orders') }}
        </h1>
        <p class="mt-1 text-sm text-on-surface">
            {{ __('Track and manage your orders across all cooks.') }}
        </p>
    </div>

    {{-- Status Filter Bar --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center gap-3">
        <div class="flex-1">
            <label for="status-filter" class="sr-only">{{ __('Filter by status') }}</label>
            <select
                id="status-filter"
                x-model="statusFilter"
                x-on:change="applyFilters()"
                class="w-full sm:w-auto min-w-[200px] h-10 px-3 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface text-sm focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
            >
                @foreach($statusOptions as $option)
                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                @endforeach
            </select>
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
                x-show="statusFilter !== '' || sort !== 'created_at' || direction !== 'desc'"
                x-on:click="clearFilters()"
                class="h-10 px-3 rounded-lg text-sm font-medium text-danger hover:bg-danger-subtle transition-colors"
                x-cloak
            >
                {{ __('Clear') }}
            </button>
        </div>
    </div>

    @fragment('orders-content')
    <div id="orders-content">

        @if($activeOrders->isEmpty() && $pastOrders->isEmpty())
            {{-- Scenario 6: Empty State --}}
            <div class="text-center py-16 sm:py-24">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-surface-alt dark:bg-surface-alt mb-6">
                    {{-- ShoppingBag icon (Lucide, xl=32) --}}
                    <svg class="w-8 h-8 text-on-surface/40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"></path><path d="M3 6h18"></path><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                </div>
                <h2 class="text-xl font-semibold text-on-surface-strong mb-2">
                    {{ __("You haven't placed any orders yet.") }}
                </h2>
                <p class="text-on-surface mb-6">
                    {{ __('Browse cooks to get started!') }}
                </p>
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2 h-10 px-6 rounded-lg bg-primary hover:bg-primary-hover text-on-primary font-semibold text-sm transition-colors" x-navigate>
                    {{-- Search icon --}}
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                    {{ __('Discover Cooks') }}
                </a>
            </div>
        @else

            {{-- Active Orders Section (pinned) --}}
            @if($activeOrders->isNotEmpty() && !$isFiltered)
                <div class="mb-8">
                    <div class="flex items-center gap-2 mb-4">
                        <h2 class="text-lg font-semibold text-on-surface-strong">
                            {{ __('Active Orders') }}
                        </h2>
                        <span class="inline-flex items-center justify-center min-w-[22px] h-[22px] rounded-full bg-primary text-on-primary text-xs font-bold px-1.5">
                            {{ $activeOrders->count() }}
                        </span>
                    </div>

                    {{-- Desktop Table --}}
                    <div class="hidden md:block bg-surface dark:bg-surface border border-primary/20 dark:border-primary/20 rounded-xl overflow-hidden shadow-card">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-primary-subtle dark:bg-primary-subtle border-b border-primary/20 dark:border-primary/20">
                                    <th class="text-left px-4 py-3 font-semibold text-on-surface-strong">{{ __('Order') }}</th>
                                    <th class="text-left px-4 py-3 font-semibold text-on-surface-strong">{{ __('Cook') }}</th>
                                    <th class="text-left px-4 py-3 font-semibold text-on-surface-strong">{{ __('Items') }}</th>
                                    <th class="text-right px-4 py-3 font-semibold text-on-surface-strong">{{ __('Total') }}</th>
                                    <th class="text-left px-4 py-3 font-semibold text-on-surface-strong">{{ __('Status') }}</th>
                                    <th class="text-left px-4 py-3 font-semibold text-on-surface-strong">{{ __('Date') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline dark:divide-outline">
                                @foreach($activeOrders as $order)
                                    <tr class="hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors cursor-pointer" x-data x-navigate>
                                        <td class="px-4 py-3">
                                            <a href="{{ url('/my-orders/' . $order->id) }}" class="font-mono text-sm font-medium text-primary hover:text-primary-hover">
                                                {{ $order->order_number }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-3">
                                            <a href="{{ \App\Services\ClientOrderService::getTenantUrl($order->tenant) }}" class="text-sm font-medium text-on-surface hover:text-primary transition-colors" x-navigate-skip>
                                                {{ $order->tenant?->name ?? __('Unknown Cook') }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-3 text-on-surface max-w-[200px] truncate" title="{{ $order->items_summary }}">
                                            {{ $order->items_summary }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-medium text-on-surface-strong whitespace-nowrap">
                                            {{ \App\Services\ClientOrderService::formatXAF($order->grand_total) }}
                                        </td>
                                        <td class="px-4 py-3">
                                            @include('cook._order-status-badge', ['status' => $order->status])
                                        </td>
                                        <td class="px-4 py-3 text-on-surface whitespace-nowrap">
                                            {{ $order->created_at->format('M d, Y') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Mobile Cards --}}
                    <div class="md:hidden space-y-3">
                        @foreach($activeOrders as $order)
                            <a href="{{ url('/my-orders/' . $order->id) }}" class="block bg-surface dark:bg-surface border border-primary/20 dark:border-primary/20 rounded-xl p-4 shadow-card hover:shadow-md transition-shadow" x-data x-navigate>
                                <div class="flex items-start justify-between gap-3 mb-2">
                                    <div>
                                        <span class="font-mono text-sm font-medium text-primary">{{ $order->order_number }}</span>
                                        <p class="text-xs text-on-surface mt-0.5">{{ $order->created_at->format('M d, Y H:i') }}</p>
                                    </div>
                                    @include('cook._order-status-badge', ['status' => $order->status])
                                </div>
                                <div class="flex items-center gap-2 text-sm text-on-surface mb-2">
                                    {{-- ChefHat icon (Lucide, sm=16) --}}
                                    <svg class="w-4 h-4 text-on-surface/50 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21a1 1 0 0 0 1-1v-5.35c0-.457.316-.844.727-1.041a4 4 0 0 0-2.134-7.589 5 5 0 0 0-9.186 0 4 4 0 0 0-2.134 7.588c.411.198.727.585.727 1.041V20a1 1 0 0 0 1 1Z"></path><path d="M6 17h12"></path></svg>
                                    <span class="truncate">{{ $order->tenant?->name ?? __('Unknown Cook') }}</span>
                                </div>
                                <div class="text-sm text-on-surface truncate mb-2">{{ $order->items_summary }}</div>
                                <div class="text-sm font-semibold text-on-surface-strong">
                                    {{ \App\Services\ClientOrderService::formatXAF($order->grand_total) }}
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Past Orders Section / Filtered Results --}}
            @if($pastOrders->isNotEmpty() || $isFiltered)
                <div>
                    @if(!$isFiltered && $activeOrders->isNotEmpty())
                        <h2 class="text-lg font-semibold text-on-surface-strong mb-4">
                            {{ __('Past Orders') }}
                        </h2>
                    @endif

                    @if($pastOrders->isEmpty())
                        <div class="text-center py-12">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-surface-alt dark:bg-surface-alt mb-4">
                                <svg class="w-6 h-6 text-on-surface/40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                            </div>
                            <p class="text-on-surface">{{ __('No orders match your filters.') }}</p>
                            <button
                                type="button"
                                x-on:click="clearFilters()"
                                class="mt-3 text-sm font-medium text-primary hover:text-primary-hover transition-colors"
                            >
                                {{ __('Clear filters') }}
                            </button>
                        </div>
                    @else
                        {{-- Desktop Table --}}
                        <div class="hidden md:block bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl overflow-hidden shadow-card">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-surface-alt dark:bg-surface-alt border-b border-outline dark:border-outline">
                                        <th class="text-left px-4 py-3 font-semibold text-on-surface-strong">{{ __('Order') }}</th>
                                        <th class="text-left px-4 py-3 font-semibold text-on-surface-strong">{{ __('Cook') }}</th>
                                        <th class="text-left px-4 py-3 font-semibold text-on-surface-strong">{{ __('Items') }}</th>
                                        <th class="text-right px-4 py-3 font-semibold text-on-surface-strong">{{ __('Total') }}</th>
                                        <th class="text-left px-4 py-3 font-semibold text-on-surface-strong">{{ __('Status') }}</th>
                                        <th class="text-left px-4 py-3 font-semibold text-on-surface-strong">{{ __('Date') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline dark:divide-outline">
                                    @foreach($pastOrders as $order)
                                        <tr class="hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors cursor-pointer" x-data x-navigate>
                                            <td class="px-4 py-3">
                                                <a href="{{ url('/my-orders/' . $order->id) }}" class="font-mono text-sm font-medium text-primary hover:text-primary-hover">
                                                    {{ $order->order_number }}
                                                </a>
                                            </td>
                                            <td class="px-4 py-3">
                                                <a href="{{ \App\Services\ClientOrderService::getTenantUrl($order->tenant) }}" class="text-sm font-medium text-on-surface hover:text-primary transition-colors" x-navigate-skip>
                                                    {{ $order->tenant?->name ?? __('Unknown Cook') }}
                                                </a>
                                            </td>
                                            <td class="px-4 py-3 text-on-surface max-w-[200px] truncate" title="{{ $order->items_summary }}">
                                                {{ $order->items_summary }}
                                            </td>
                                            <td class="px-4 py-3 text-right font-medium text-on-surface-strong whitespace-nowrap">
                                                {{ \App\Services\ClientOrderService::formatXAF($order->grand_total) }}
                                            </td>
                                            <td class="px-4 py-3">
                                                @include('cook._order-status-badge', ['status' => $order->status])
                                            </td>
                                            <td class="px-4 py-3 text-on-surface whitespace-nowrap">
                                                {{ $order->created_at->format('M d, Y') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Mobile Cards --}}
                        <div class="md:hidden space-y-3">
                            @foreach($pastOrders as $order)
                                <a href="{{ url('/my-orders/' . $order->id) }}" class="block bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-4 shadow-card hover:shadow-md transition-shadow" x-data x-navigate>
                                    <div class="flex items-start justify-between gap-3 mb-2">
                                        <div>
                                            <span class="font-mono text-sm font-medium text-primary">{{ $order->order_number }}</span>
                                            <p class="text-xs text-on-surface mt-0.5">{{ $order->created_at->format('M d, Y H:i') }}</p>
                                        </div>
                                        @include('cook._order-status-badge', ['status' => $order->status])
                                    </div>
                                    <div class="flex items-center gap-2 text-sm text-on-surface mb-2">
                                        <svg class="w-4 h-4 text-on-surface/50 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21a1 1 0 0 0 1-1v-5.35c0-.457.316-.844.727-1.041a4 4 0 0 0-2.134-7.589 5 5 0 0 0-9.186 0 4 4 0 0 0-2.134 7.588c.411.198.727.585.727 1.041V20a1 1 0 0 0 1 1Z"></path><path d="M6 17h12"></path></svg>
                                        <span class="truncate">{{ $order->tenant?->name ?? __('Unknown Cook') }}</span>
                                    </div>
                                    <div class="text-sm text-on-surface truncate mb-2">{{ $order->items_summary }}</div>
                                    <div class="text-sm font-semibold text-on-surface-strong">
                                        {{ \App\Services\ClientOrderService::formatXAF($order->grand_total) }}
                                    </div>
                                </a>
                            @endforeach
                        </div>

                        {{-- Pagination --}}
                        @if($pastOrders->hasPages())
                            <div class="mt-6" x-data x-navigate>
                                {{ $pastOrders->links() }}
                            </div>
                        @endif
                    @endif
                </div>
            @endif

        @endif

    </div>
    @endfragment
</div>
@endsection
