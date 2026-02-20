{{--
    Cook Order Detail View
    ----------------------
    F-156: Cook Order Detail View
    Displays the full detail of a single order for the cook or manager.

    BR-166: Order detail is tenant-scoped.
    BR-167: Client phone number displayed.
    BR-168: Items with components, quantities, prices.
    BR-169: Delivery orders show town, quarter, landmark, delivery fee.
    BR-170: Pickup orders show pickup location name and address.
    BR-171: Status timeline with timestamps and user info.
    BR-172: Status update button shows next valid status.
    BR-173: Payment info (method, amount, status, reference).
    BR-174: Client notes displayed if present.
    BR-175: Only manage-orders permission.
    BR-176: All amounts in XAF.
    BR-177: All text uses __() localization.
--}}
@extends('layouts.cook-dashboard')

@section('title', __('Order') . ' #' . $order->order_number)
@section('page-title', __('Order Details'))

@section('content')
<div
    class="max-w-4xl mx-auto"
    x-data="{
        showConfirmDialog: false,
        nextStatus: '{{ $nextStatus ?? '' }}',
        nextStatusLabel: '{{ addslashes($nextStatusLabel ?? '') }}',

        confirmStatusUpdate() {
            this.showConfirmDialog = true;
        },
        cancelStatusUpdate() {
            this.showConfirmDialog = false;
        },
        executeStatusUpdate() {
            this.showConfirmDialog = false;
            $action('{{ url('/dashboard/orders/' . $order->id . '/status') }}', {
                method: 'PATCH',
                include: ['nextStatus']
            });
        }
    }"
>
    {{-- Back navigation + Header --}}
    <div class="mb-6">
        {{-- Breadcrumb --}}
        <nav class="flex items-center gap-2 text-sm text-on-surface/60 mb-4" aria-label="{{ __('Breadcrumb') }}">
            <a href="{{ url('/dashboard') }}" class="hover:text-primary transition-colors duration-200" x-navigate>
                {{ __('Dashboard') }}
            </a>
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
            <a href="{{ url('/dashboard/orders') }}" class="hover:text-primary transition-colors duration-200" x-navigate>
                {{ __('Orders') }}
            </a>
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
            <span class="text-on-surface-strong font-medium">#{{ $order->order_number }}</span>
        </nav>

        {{-- Order header with status badge and action --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <h2 class="text-2xl font-display font-bold text-on-surface-strong">
                    #{{ $order->order_number }}
                </h2>
                @include('cook._order-status-badge', ['status' => $order->status])
            </div>

            <div class="flex items-center gap-3">
                {{-- F-188: Message Client button (forward-compatible) --}}
                <a
                    href="{{ url('/dashboard/orders/' . $order->id . '/messages') }}"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium border border-outline dark:border-outline text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors duration-200"
                    x-navigate
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"></path></svg>
                    {{ __('Message Client') }}
                </a>

                {{-- BR-172: Status update button --}}
                @if($nextStatus)
                    <button
                        @click="confirmStatusUpdate()"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold bg-primary text-on-primary hover:bg-primary-hover transition-colors duration-200 shadow-sm"
                    >
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"></path></svg>
                        <span x-text="'{{ __('Mark as') }} ' + nextStatusLabel">
                            {{ __('Mark as') }} {{ $nextStatusLabel }}
                        </span>
                    </button>
                @endif
            </div>
        </div>

        <p class="mt-1 text-sm text-on-surface/60">
            {{ __('Placed on') }} {{ $order->created_at->format('M d, Y') }} {{ __('at') }} {{ $order->created_at->format('H:i') }}
        </p>
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

    {{-- Content cards --}}
    <div class="space-y-6">

        {{-- Client Info Card --}}
        <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline overflow-hidden">
            <div class="px-5 py-3.5 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                <h3 class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    {{ __('Client Information') }}
                </h3>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Name') }}</p>
                        <p class="text-sm text-on-surface-strong font-medium">{{ $order->client?->name ?? __('Guest') }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Phone') }}</p>
                        <div class="flex items-center gap-2">
                            <a href="tel:{{ $order->phone }}" class="text-sm text-primary hover:underline" x-navigate-skip>
                                {{ $order->phone }}
                            </a>
                            @if($order->tenant?->whatsapp)
                                <a
                                    href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $order->phone) }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 text-xs text-success hover:text-success/80 transition-colors duration-200"
                                    x-navigate-skip
                                    title="{{ __('Chat on WhatsApp') }}"
                                >
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                </a>
                            @endif
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Email') }}</p>
                        <p class="text-sm text-on-surface">{{ $order->client?->email ?? __('N/A') }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Order Date') }}</p>
                        <p class="text-sm text-on-surface">{{ $order->created_at->format('M d, Y H:i') }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Order Items Card --}}
        <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline overflow-hidden">
            <div class="px-5 py-3.5 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                <h3 class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"></path><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path><path d="m3.3 7 8.7 5 8.7-5"></path><path d="M12 22V12"></path></svg>
                    {{ __('Order Items') }}
                    <span class="text-xs font-normal text-on-surface/50">({{ count($items) }} {{ trans_choice('item|items', count($items)) }})</span>
                </h3>
            </div>
            <div class="p-5">
                @if(count($items) > 0)
                    <div class="divide-y divide-outline dark:divide-outline {{ count($items) > 10 ? 'max-h-96 overflow-y-auto' : '' }}">
                        @foreach($items as $item)
                            <div class="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-on-surface-strong">{{ $item['meal_name'] }}</p>
                                    @if($item['component_name'])
                                        <p class="text-xs text-on-surface/60 mt-0.5">{{ $item['component_name'] }}</p>
                                    @endif
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-sm text-on-surface/70">
                                        {{ $item['quantity'] }} x {{ \App\Services\CookOrderService::formatXAF($item['unit_price']) }}
                                    </p>
                                    <p class="text-sm font-mono font-medium text-on-surface-strong">
                                        {{ \App\Services\CookOrderService::formatXAF($item['subtotal']) }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Subtotal / Delivery Fee / Promo / Grand Total --}}
                    <div class="mt-4 pt-4 border-t border-outline dark:border-outline space-y-2">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-on-surface/70">{{ __('Subtotal') }}</span>
                            <span class="font-mono text-on-surface">{{ \App\Services\CookOrderService::formatXAF($order->subtotal) }}</span>
                        </div>
                        @if($order->delivery_fee > 0)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-on-surface/70">{{ __('Delivery Fee') }}</span>
                                <span class="font-mono text-on-surface">{{ \App\Services\CookOrderService::formatXAF($order->delivery_fee) }}</span>
                            </div>
                        @endif
                        @if($order->promo_discount > 0)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-success">{{ __('Promo Discount') }}</span>
                                <span class="font-mono text-success">-{{ \App\Services\CookOrderService::formatXAF($order->promo_discount) }}</span>
                            </div>
                        @endif
                        <div class="flex items-center justify-between text-base font-semibold pt-2 border-t border-outline dark:border-outline">
                            <span class="text-on-surface-strong">{{ __('Grand Total') }}</span>
                            <span class="font-mono text-on-surface-strong">{{ \App\Services\CookOrderService::formatXAF($order->grand_total) }}</span>
                        </div>
                    </div>
                @else
                    <p class="text-sm text-on-surface/50 text-center py-4">{{ __('No items found for this order.') }}</p>
                @endif
            </div>
        </div>

        {{-- Delivery / Pickup Card --}}
        <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline overflow-hidden">
            <div class="px-5 py-3.5 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                <h3 class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                    @if($order->delivery_method === \App\Models\Order::METHOD_DELIVERY)
                        <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 18H3c-.6 0-1-.4-1-1V7c0-.6.4-1 1-1h10c.6 0 1 .4 1 1v11"></path><path d="M14 9h4l4 4v4c0 .6-.4 1-1 1h-2"></path><circle cx="7" cy="18" r="2"></circle><path d="M15 18H9"></path><circle cx="17" cy="18" r="2"></circle></svg>
                        {{ __('Delivery Details') }}
                    @else
                        <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        {{ __('Pickup Details') }}
                    @endif
                </h3>
            </div>
            <div class="p-5">
                @if($order->delivery_method === \App\Models\Order::METHOD_DELIVERY)
                    {{-- BR-169: Delivery details --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Town') }}</p>
                            <p class="text-sm text-on-surface-strong">{{ $order->town?->name ?? __('N/A') }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Quarter') }}</p>
                            <p class="text-sm text-on-surface-strong">{{ $order->quarter?->name ?? __('N/A') }}</p>
                        </div>
                        @if($order->neighbourhood)
                            <div class="sm:col-span-2">
                                <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Landmark / Description') }}</p>
                                <p class="text-sm text-on-surface">{{ $order->neighbourhood }}</p>
                            </div>
                        @endif
                        <div>
                            <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Delivery Fee') }}</p>
                            <p class="text-sm font-mono font-medium text-on-surface-strong">{{ \App\Services\CookOrderService::formatXAF($order->delivery_fee) }}</p>
                        </div>
                    </div>
                @else
                    {{-- BR-170: Pickup details --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Pickup Location') }}</p>
                            <p class="text-sm text-on-surface-strong">{{ $order->pickupLocation?->name ?? __('N/A') }}</p>
                        </div>
                        @if($order->pickupLocation?->address)
                            <div>
                                <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Address') }}</p>
                                <p class="text-sm text-on-surface">{{ $order->pickupLocation->address }}</p>
                            </div>
                        @endif
                        @if($order->pickupLocation?->town)
                            <div>
                                <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Town') }}</p>
                                <p class="text-sm text-on-surface">{{ $order->pickupLocation->town->name ?? __('N/A') }}</p>
                            </div>
                        @endif
                        @if($order->pickupLocation?->quarter)
                            <div>
                                <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Quarter') }}</p>
                                <p class="text-sm text-on-surface">{{ $order->pickupLocation->quarter->name ?? __('N/A') }}</p>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- Payment Info Card --}}
        <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline overflow-hidden">
            <div class="px-5 py-3.5 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                <h3 class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="12" x="2" y="6" rx="2"></rect><circle cx="12" cy="12" r="2"></circle><path d="M6 12h.01M18 12h.01"></path></svg>
                    {{ __('Payment Information') }}
                </h3>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Payment Method') }}</p>
                        <p class="text-sm text-on-surface-strong font-medium">{{ $order->getPaymentProviderLabel() }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Amount') }}</p>
                        <p class="text-sm font-mono font-medium text-on-surface-strong">{{ \App\Services\CookOrderService::formatXAF($order->grand_total) }}</p>
                    </div>
                    @if($paymentTransaction)
                        <div>
                            <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Status') }}</p>
                            @php
                                $paymentStatusClasses = match($paymentTransaction->status) {
                                    'successful' => 'bg-success-subtle text-success',
                                    'pending' => 'bg-warning-subtle text-warning',
                                    'failed' => 'bg-danger-subtle text-danger',
                                    'refunded' => 'bg-secondary-subtle text-secondary',
                                    default => 'bg-surface-alt text-on-surface',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $paymentStatusClasses }}">
                                {{ __(ucfirst($paymentTransaction->status)) }}
                            </span>
                        </div>
                        @if($paymentTransaction->flutterwave_reference)
                            <div>
                                <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Reference') }}</p>
                                <p class="text-sm font-mono text-on-surface/70">{{ $paymentTransaction->flutterwave_reference }}</p>
                            </div>
                        @endif
                    @else
                        <div>
                            <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Status') }}</p>
                            @php
                                $orderPaymentStatus = match($order->status) {
                                    'paid', 'confirmed', 'preparing', 'ready', 'out_for_delivery', 'ready_for_pickup', 'delivered', 'picked_up', 'completed' => 'successful',
                                    'payment_failed' => 'failed',
                                    'cancelled' => 'cancelled',
                                    default => 'pending',
                                };
                                $orderPaymentClasses = match($orderPaymentStatus) {
                                    'successful' => 'bg-success-subtle text-success',
                                    'pending' => 'bg-warning-subtle text-warning',
                                    'failed' => 'bg-danger-subtle text-danger',
                                    'cancelled' => 'bg-danger-subtle text-danger',
                                    default => 'bg-surface-alt text-on-surface',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $orderPaymentClasses }}">
                                {{ __(ucfirst($orderPaymentStatus)) }}
                            </span>
                        </div>
                    @endif
                    @if($order->payment_phone)
                        <div>
                            <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Payment Phone') }}</p>
                            <p class="text-sm text-on-surface">{{ $order->payment_phone }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Client Notes Card --}}
        @if($order->notes)
            <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline overflow-hidden">
                <div class="px-5 py-3.5 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                    <h3 class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                        <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" x2="8" y1="13" y2="13"></line><line x1="16" x2="8" y1="17" y2="17"></line><line x1="10" x2="8" y1="9" y2="9"></line></svg>
                        {{ __('Client Notes') }}
                    </h3>
                </div>
                <div class="p-5">
                    <div class="bg-warning-subtle dark:bg-warning-subtle rounded-lg p-4 border border-warning/20">
                        <p class="text-sm text-on-surface whitespace-pre-wrap">{{ $order->notes }}</p>
                    </div>
                </div>
            </div>
        @else
            <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline overflow-hidden">
                <div class="px-5 py-3.5 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                    <h3 class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                        <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" x2="8" y1="13" y2="13"></line><line x1="16" x2="8" y1="17" y2="17"></line><line x1="10" x2="8" y1="9" y2="9"></line></svg>
                        {{ __('Client Notes') }}
                    </h3>
                </div>
                <div class="p-5">
                    <p class="text-sm text-on-surface/50 text-center italic">{{ __('No notes from the client.') }}</p>
                </div>
            </div>
        @endif

        {{-- Status Timeline Card --}}
        <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline overflow-hidden">
            <div class="px-5 py-3.5 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                <h3 class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    {{ __('Status Timeline') }}
                </h3>
            </div>
            <div class="p-5">
                @if(count($statusTimeline) > 0)
                    <div class="relative">
                        {{-- Vertical line --}}
                        <div class="absolute left-3 top-2 bottom-2 w-0.5 bg-outline dark:bg-outline"></div>

                        <div class="space-y-6">
                            @foreach($statusTimeline as $index => $entry)
                                @php
                                    $isCurrentStatus = $entry['status'] === $order->status;
                                    $isLast = $index === count($statusTimeline) - 1;
                                    $dotColor = $isCurrentStatus
                                        ? 'bg-primary ring-4 ring-primary/20'
                                        : 'bg-outline-strong dark:bg-outline-strong';
                                @endphp
                                <div class="relative flex items-start gap-4 pl-1">
                                    {{-- Dot --}}
                                    <div class="relative z-10 w-5 h-5 rounded-full {{ $dotColor }} shrink-0 mt-0.5 flex items-center justify-center">
                                        @if($isCurrentStatus)
                                            <div class="w-2 h-2 rounded-full bg-on-primary"></div>
                                        @endif
                                    </div>

                                    {{-- Content --}}
                                    <div class="flex-1 min-w-0 pb-1">
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                                            <p class="text-sm font-medium {{ $isCurrentStatus ? 'text-primary' : 'text-on-surface-strong' }}">
                                                {{ $entry['label'] }}
                                            </p>
                                            <span class="text-xs text-on-surface/50">{{ $entry['timestamp'] }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-xs text-on-surface/50">{{ __('by') }} {{ $entry['user'] }}</span>
                                            <span class="text-xs text-on-surface/40">{{ $entry['relative_time'] }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <p class="text-sm text-on-surface/50 text-center py-4">{{ __('No status history available.') }}</p>
                @endif
            </div>
        </div>

    </div>

    {{-- Mobile floating status update button --}}
    @if($nextStatus)
        <div class="fixed bottom-6 right-6 sm:hidden z-30">
            <button
                @click="confirmStatusUpdate()"
                class="inline-flex items-center gap-2 px-5 py-3 rounded-full text-sm font-semibold bg-primary text-on-primary hover:bg-primary-hover transition-colors duration-200 shadow-lg"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"></path></svg>
                <span x-text="nextStatusLabel">{{ $nextStatusLabel }}</span>
            </button>
        </div>
    @endif

    {{-- Status Update Confirmation Dialog --}}
    <div
        x-show="showConfirmDialog"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        role="dialog"
        aria-modal="true"
        x-cloak
    >
        <div
            x-show="showConfirmDialog"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="bg-surface dark:bg-surface rounded-xl shadow-lg border border-outline dark:border-outline p-6 w-full max-w-sm"
            @click.away="cancelStatusUpdate()"
            @keydown.escape.window="cancelStatusUpdate()"
        >
            {{-- Icon --}}
            <div class="w-12 h-12 rounded-full bg-primary-subtle flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"></path></svg>
            </div>

            <h3 class="text-base font-semibold text-on-surface-strong text-center mb-2">
                {{ __('Update Order Status') }}
            </h3>
            <p class="text-sm text-on-surface/70 text-center mb-6">
                {{ __('Are you sure you want to mark this order as') }}
                <span class="font-semibold text-on-surface-strong" x-text="nextStatusLabel">{{ $nextStatusLabel }}</span>?
            </p>

            <div class="flex items-center gap-3">
                <button
                    @click="cancelStatusUpdate()"
                    class="flex-1 px-4 py-2.5 rounded-lg text-sm font-medium border border-outline dark:border-outline text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors duration-200"
                >
                    {{ __('Cancel') }}
                </button>
                <button
                    @click="executeStatusUpdate()"
                    class="flex-1 px-4 py-2.5 rounded-lg text-sm font-semibold bg-primary text-on-primary hover:bg-primary-hover transition-colors duration-200"
                    :class="{ 'opacity-50 cursor-wait': $fetching() }"
                    :disabled="$fetching()"
                >
                    <span x-show="!$fetching()">{{ __('Confirm') }}</span>
                    <span x-show="$fetching()" class="flex items-center justify-center gap-2">
                        <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        {{ __('Updating...') }}
                    </span>
                </button>
            </div>
        </div>
    </div>

</div>
@endsection
