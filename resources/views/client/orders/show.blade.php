{{--
    Client Order Detail & Status Tracking (F-161)
    -----------------------------------------------
    Displays full order detail with real-time status tracking.

    BR-222: Client can only view their own orders.
    BR-223: Status updates pushed in real-time via Gale SSE.
    BR-224: Visual timeline shows all status transitions with timestamps.
    BR-225: Cancel button visible when order is Paid/Confirmed AND within cancellation window.
    BR-226: Countdown timer shows remaining cancellation time.
    BR-227: Report a Problem link for delivered/picked up/completed statuses.
    BR-228: Rating prompt for completed, unrated orders.
    BR-229: Cook WhatsApp number displayed for urgent contact.
    BR-230: Payment details show method, amount, reference, status.
    BR-231: Items show meal name, components, quantities, unit prices, line totals.
    BR-232: Delivery orders show town, quarter, landmark, delivery fee.
    BR-233: Pickup orders show pickup location name and address.
    BR-234: All amounts in XAF format.
    BR-235: All text uses __() localization.
--}}
@extends(tenant() ? 'layouts.tenant-public' : 'layouts.main-public')

@section('title', __('Order') . ' #' . $order->order_number)

@section('content')
<div
    class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8"
    x-data="{
        currentStatus: '{{ $order->status }}',
        currentStatusLabel: '{{ addslashes(\App\Models\Order::getStatusLabel($order->status)) }}',
        canCancel: {{ $canCancel ? 'true' : 'false' }},
        cancelSecondsRemaining: {{ $cancellationSecondsRemaining }},
        canReport: {{ $canReport ? 'true' : 'false' }},
        canRate: {{ $canRate ? 'true' : 'false' }},
        rated: {{ ($existingRating !== null) ? 'true' : 'false' }},
        submittedStars: {{ $existingRating?->stars ?? 0 }},
        hoverStars: 0,
        selectedStars: 0,
        stars: 0,
        previousStatus: '{{ $order->status }}',
        showStatusToast: false,
        statusToastMessage: '',

        formatCountdown(seconds) {
            if (seconds <= 0) return '0:00';
            let m = Math.floor(seconds / 60);
            let s = seconds % 60;
            return m + ':' + (s < 10 ? '0' : '') + s;
        },

        startCountdown() {
            if (this.cancelSecondsRemaining > 0) {
                setInterval(() => {
                    if (this.cancelSecondsRemaining > 0) {
                        this.cancelSecondsRemaining--;
                        if (this.cancelSecondsRemaining <= 0) {
                            this.canCancel = false;
                        }
                    }
                }, 1000);
            }
        },

        statusBadgeClasses: {
            'pending_payment': 'bg-surface-alt text-on-surface/70 dark:bg-surface-alt dark:text-on-surface/70',
            'payment_failed': 'bg-danger-subtle text-danger dark:bg-danger-subtle dark:text-danger',
            'paid': 'bg-info-subtle text-info dark:bg-info-subtle dark:text-info',
            'confirmed': 'bg-[oklch(0.93_0.05_270)] text-[oklch(0.45_0.15_270)] dark:bg-[oklch(0.25_0.06_270)] dark:text-[oklch(0.75_0.12_270)]',
            'preparing': 'bg-warning-subtle text-warning dark:bg-warning-subtle dark:text-warning',
            'ready': 'bg-[oklch(0.93_0.05_175)] text-[oklch(0.45_0.1_175)] dark:bg-[oklch(0.25_0.04_175)] dark:text-[oklch(0.75_0.1_175)]',
            'out_for_delivery': 'bg-[oklch(0.93_0.05_300)] text-[oklch(0.45_0.15_300)] dark:bg-[oklch(0.25_0.06_300)] dark:text-[oklch(0.75_0.12_300)]',
            'ready_for_pickup': 'bg-[oklch(0.93_0.05_300)] text-[oklch(0.45_0.15_300)] dark:bg-[oklch(0.25_0.06_300)] dark:text-[oklch(0.75_0.12_300)]',
            'delivered': 'bg-success-subtle text-success dark:bg-success-subtle dark:text-success',
            'picked_up': 'bg-success-subtle text-success dark:bg-success-subtle dark:text-success',
            'completed': 'bg-success-subtle text-success dark:bg-success-subtle dark:text-success',
            'cancelled': 'bg-danger-subtle text-danger dark:bg-danger-subtle dark:text-danger',
            'refunded': 'bg-secondary-subtle text-secondary dark:bg-secondary-subtle dark:text-secondary',
        },

        getBadgeClass() {
            return this.statusBadgeClasses[this.currentStatus] || 'bg-surface-alt text-on-surface dark:bg-surface-alt dark:text-on-surface';
        },

        checkStatusChange() {
            if (this.currentStatus !== this.previousStatus) {
                this.statusToastMessage = '{{ __('Your order is now') }} ' + this.currentStatusLabel + '!';
                this.showStatusToast = true;
                this.previousStatus = this.currentStatus;
                setTimeout(() => { this.showStatusToast = false; }, 5000);
            }
        }
    }"
    x-component="order-tracker"
    x-init="startCountdown(); $watch('currentStatus', () => checkStatusChange())"
    x-interval.10s.visible="$action('{{ url('/my-orders/' . $order->id . '/refresh-status') }}')"
>

    {{-- Real-time status toast notification (BR-223, Scenario 2) --}}
    <div
        x-show="showStatusToast"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed top-4 right-4 z-50 max-w-sm"
        x-cloak
    >
        <div class="bg-success-subtle border border-success/20 rounded-lg p-4 shadow-lg flex items-center gap-3">
            {{-- CheckCircle icon (Lucide, md=20) --}}
            <svg class="w-5 h-5 text-success shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            <span class="text-sm text-on-surface font-medium" x-text="statusToastMessage"></span>
        </div>
    </div>

    @fragment('order-detail-content')
    <div id="order-detail-content">

        {{-- Back Navigation --}}
        <nav class="flex items-center gap-2 text-sm text-on-surface/60 mb-6" aria-label="{{ __('Breadcrumb') }}">
            <a href="{{ url('/my-orders') }}" class="hover:text-primary transition-colors duration-200 flex items-center gap-1" x-navigate>
                {{-- ArrowLeft icon (Lucide, sm=16) --}}
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
                {{ __('My Orders') }}
            </a>
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
            <span class="text-on-surface-strong font-medium">#{{ $order->order_number }}</span>
        </nav>

        {{-- Order Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-display font-bold text-on-surface-strong">
                    #{{ $order->order_number }}
                </h1>
                <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                    :class="getBadgeClass()"
                    x-text="currentStatusLabel"
                ></span>
            </div>
            <p class="text-sm text-on-surface/60">
                {{ __('Placed on') }} {{ $order->created_at->format('M d, Y') }} {{ __('at') }} {{ $order->created_at->format('H:i') }}
            </p>
        </div>

        {{-- Cook Info Bar --}}
        <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline p-4 mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-3">
                {{-- ChefHat icon (Lucide, md=20) --}}
                <div class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21a1 1 0 0 0 1-1v-5.35c0-.457.316-.844.727-1.041a4 4 0 0 0-2.134-7.589 5 5 0 0 0-9.186 0 4 4 0 0 0-2.134 7.588c.411.198.727.585.727 1.041V20a1 1 0 0 0 1 1Z"></path><path d="M6 17h12"></path></svg>
                </div>
                <div>
                    <a href="{{ $tenantUrl }}" class="text-sm font-semibold text-on-surface-strong hover:text-primary transition-colors" x-navigate-skip>
                        {{ $cookName }}
                    </a>
                    @if(!$tenantActive)
                        <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-warning-subtle text-warning">
                            {{ __('Inactive') }}
                        </span>
                    @endif
                </div>
            </div>

            {{-- Contact Buttons --}}
            <div class="flex items-center gap-2">
                {{-- F-188: Message Cook (forward-compatible) --}}
                <a
                    href="{{ url('/my-orders/' . $order->id . '/messages') }}"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium border border-outline dark:border-outline text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors"
                    x-navigate
                >
                    {{-- MessageCircle icon (Lucide, sm=16) --}}
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"></path></svg>
                    {{ __('Message Cook') }}
                </a>

                {{-- BR-229: WhatsApp button --}}
                @if($cookWhatsapp)
                    <a
                        href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $cookWhatsapp) }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium bg-[#25D366] text-white hover:bg-[#20BD5A] transition-colors"
                        x-navigate-skip
                        title="{{ __('Contact on WhatsApp') }}"
                    >
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        {{ __('WhatsApp') }}
                    </a>
                @endif
            </div>
        </div>

        {{-- Cancellation Banner (BR-225, BR-226) --}}
        <div x-show="canCancel" x-cloak class="mb-6">
            <div class="bg-warning-subtle border border-warning/20 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-3">
                    {{-- Clock icon (Lucide, md=20) --}}
                    <svg class="w-5 h-5 text-warning shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    <div>
                        <p class="text-sm font-medium text-on-surface-strong">{{ __('You can still cancel this order') }}</p>
                        <p class="text-xs text-on-surface/60">
                            {{ __('Time remaining:') }}
                            <span class="font-mono font-semibold text-warning" x-text="formatCountdown(cancelSecondsRemaining)"></span>
                        </p>
                    </div>
                </div>
                {{-- F-162: Cancel Order button (forward-compatible) --}}
                <a
                    href="{{ url('/my-orders/' . $order->id . '/cancel') }}"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium border-2 border-danger text-danger hover:bg-danger hover:text-on-danger transition-colors"
                    x-navigate
                >
                    {{-- XCircle icon (Lucide, sm=16) --}}
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg>
                    {{ __('Cancel Order') }}
                    <span class="font-mono text-xs" x-text="'(' + formatCountdown(cancelSecondsRemaining) + ')'"></span>
                </a>
            </div>
        </div>

        {{-- Pending Payment Notice (Edge case) --}}
        @if($order->status === \App\Models\Order::STATUS_PENDING_PAYMENT)
            <div class="mb-6 bg-warning-subtle border border-warning/20 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-warning shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <p class="text-sm font-medium text-on-surface-strong">{{ __('Awaiting payment') }}</p>
                </div>
                {{-- F-152: Link to retry payment --}}
                <a
                    href="{{ url('/checkout/payment-retry/' . $order->id) }}"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium bg-primary text-on-primary hover:bg-primary-hover transition-colors"
                    x-navigate
                >
                    {{ __('Complete Payment') }}
                </a>
            </div>
        @endif

        {{-- Cancelled Status Notice (Edge case) --}}
        @if($order->status === \App\Models\Order::STATUS_CANCELLED)
            <div class="mb-6 bg-danger-subtle border border-danger/20 rounded-xl p-4 flex items-center gap-3">
                <svg class="w-5 h-5 text-danger shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg>
                <div>
                    <p class="text-sm font-medium text-danger">{{ __('This order has been cancelled.') }}</p>
                    @if($order->cancelled_at)
                        <p class="text-xs text-on-surface/60 mt-0.5">{{ __('Cancelled on') }} {{ $order->cancelled_at->format('M d, Y H:i') }}</p>
                    @endif
                </div>
            </div>
        @endif

        {{-- Content Cards --}}
        <div class="space-y-6">

            {{-- Order Items Card (BR-231) --}}
            <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline overflow-hidden">
                <div class="px-5 py-3.5 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                    <h2 class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                        {{-- Package icon (Lucide, sm=16) --}}
                        <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"></path><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path><path d="m3.3 7 8.7 5 8.7-5"></path><path d="M12 22V12"></path></svg>
                        {{ __('Order Items') }}
                        <span class="text-xs font-normal text-on-surface/50">({{ count($items) }} {{ trans_choice('item|items', count($items)) }})</span>
                    </h2>
                </div>
                <div class="p-5">
                    @if(count($items) > 0)
                        <div class="divide-y divide-outline dark:divide-outline">
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
                                            {{ $item['quantity'] }} x {{ \App\Services\ClientOrderService::formatXAF($item['unit_price']) }}
                                        </p>
                                        <p class="text-sm font-mono font-medium text-on-surface-strong">
                                            {{ \App\Services\ClientOrderService::formatXAF($item['subtotal']) }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Totals (BR-234) --}}
                        <div class="mt-4 pt-4 border-t border-outline dark:border-outline space-y-2">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-on-surface/70">{{ __('Subtotal') }}</span>
                                <span class="font-mono text-on-surface">{{ \App\Services\ClientOrderService::formatXAF($order->subtotal) }}</span>
                            </div>
                            @if($order->delivery_fee > 0)
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-on-surface/70">{{ __('Delivery Fee') }}</span>
                                    <span class="font-mono text-on-surface">{{ \App\Services\ClientOrderService::formatXAF($order->delivery_fee) }}</span>
                                </div>
                            @endif
                            @if($order->promo_discount > 0)
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-success">{{ __('Promo Discount') }}</span>
                                    <span class="font-mono text-success">-{{ \App\Services\ClientOrderService::formatXAF($order->promo_discount) }}</span>
                                </div>
                            @endif
                            <div class="flex items-center justify-between text-base font-semibold pt-2 border-t border-outline dark:border-outline">
                                <span class="text-on-surface-strong">{{ __('Grand Total') }}</span>
                                <span class="font-mono text-on-surface-strong">{{ \App\Services\ClientOrderService::formatXAF($order->grand_total) }}</span>
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-on-surface/50 text-center py-4">{{ __('No items found for this order.') }}</p>
                    @endif
                </div>
            </div>

            {{-- Delivery / Pickup Card (BR-232, BR-233) --}}
            <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline overflow-hidden">
                <div class="px-5 py-3.5 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                    <h2 class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                        @if($order->delivery_method === \App\Models\Order::METHOD_DELIVERY)
                            {{-- Truck icon (Lucide, sm=16) --}}
                            <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 18H3c-.6 0-1-.4-1-1V7c0-.6.4-1 1-1h10c.6 0 1 .4 1 1v11"></path><path d="M14 9h4l4 4v4c0 .6-.4 1-1 1h-2"></path><circle cx="7" cy="18" r="2"></circle><path d="M15 18H9"></path><circle cx="17" cy="18" r="2"></circle></svg>
                            {{ __('Delivery Details') }}
                        @else
                            {{-- MapPin icon (Lucide, sm=16) --}}
                            <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            {{ __('Pickup Details') }}
                        @endif
                    </h2>
                </div>
                <div class="p-5">
                    @if($order->delivery_method === \App\Models\Order::METHOD_DELIVERY)
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
                                <p class="text-sm font-mono font-medium text-on-surface-strong">{{ \App\Services\ClientOrderService::formatXAF($order->delivery_fee) }}</p>
                            </div>
                        </div>
                    @else
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

            {{-- Payment Info Card (BR-230) --}}
            <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline overflow-hidden">
                <div class="px-5 py-3.5 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                    <h2 class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                        {{-- CreditCard icon (Lucide, sm=16) --}}
                        <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="12" x="2" y="6" rx="2"></rect><circle cx="12" cy="12" r="2"></circle><path d="M6 12h.01M18 12h.01"></path></svg>
                        {{ __('Payment Information') }}
                    </h2>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Payment Method') }}</p>
                            <p class="text-sm text-on-surface-strong font-medium">{{ $order->getPaymentProviderLabel() }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Amount') }}</p>
                            <p class="text-sm font-mono font-medium text-on-surface-strong">{{ \App\Services\ClientOrderService::formatXAF($order->grand_total) }}</p>
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

            {{-- Status Timeline Card (BR-224) --}}
            @fragment('status-timeline-section')
            <div id="status-timeline-section" class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline overflow-hidden">
                <div class="px-5 py-3.5 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                    <h2 class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                        {{-- Clock icon (Lucide, sm=16) --}}
                        <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        {{ __('Status Timeline') }}
                    </h2>
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
                                            <span class="text-xs text-on-surface/40">{{ $entry['relative_time'] }}</span>
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
            @endfragment

            {{-- Rating Section (F-176) --}}
            {{-- BR-228: Rating prompt for completed, unrated orders --}}
            {{-- BR-392: Rating prompt appears on the order detail page --}}
            {{-- Scenario 3: Already rated - show submitted rating --}}
            <div x-show="rated" x-cloak>
                <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                        <h2 class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                            {{-- Star icon (Lucide, sm=16) --}}
                            <svg class="w-4 h-4 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                            {{ __('Your Rating') }}
                        </h2>
                    </div>
                    <div class="p-5">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-1" aria-label="{{ __('Your rating') }}">
                                <template x-for="star in 5" :key="star">
                                    <svg
                                        class="w-6 h-6 sm:w-7 sm:h-7 transition-colors"
                                        :class="star <= submittedStars ? 'text-warning fill-warning' : 'text-outline dark:text-outline'"
                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                    ><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                </template>
                            </div>
                            <span class="text-sm font-medium text-on-surface" x-text="submittedStars + '/5 {{ __('stars') }}'"></span>
                        </div>
                        @if($existingRating?->review)
                            <p class="mt-3 text-sm text-on-surface/70 italic">{{ $existingRating->review }}</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Scenario 1: Rate after completion - interactive stars --}}
            <div x-show="canRate && !rated" x-cloak>
                <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-outline dark:border-outline bg-secondary-subtle dark:bg-secondary-subtle">
                        <h2 class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                            {{-- Star icon (Lucide, sm=16) --}}
                            <svg class="w-4 h-4 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                            {{ __('How was your order?') }}
                        </h2>
                    </div>
                    <div class="p-5">
                        <p class="text-sm text-on-surface/60 mb-4">{{ __('Rate your experience to help other customers.') }}</p>

                        {{-- Interactive Star Selector --}}
                        <div class="flex items-center gap-2 mb-4" role="radiogroup" aria-label="{{ __('Star rating') }}">
                            <template x-for="star in 5" :key="star">
                                <button
                                    type="button"
                                    class="focus:outline-none focus-visible:ring-2 focus-visible:ring-primary rounded transition-transform hover:scale-110"
                                    x-on:click="selectedStars = star"
                                    x-on:mouseenter="hoverStars = star"
                                    x-on:mouseleave="hoverStars = 0"
                                    :aria-label="star + ' {{ __('star') }}'"
                                    :aria-checked="selectedStars === star"
                                    role="radio"
                                >
                                    <svg
                                        class="w-8 h-8 sm:w-10 sm:h-10 transition-colors cursor-pointer"
                                        :class="star <= (hoverStars || selectedStars) ? 'text-warning fill-warning' : 'text-outline dark:text-outline hover:text-warning/50'"
                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                    ><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                </button>
                            </template>
                            <span
                                class="text-sm font-medium text-on-surface/70 ml-2"
                                x-show="selectedStars > 0"
                                x-text="selectedStars + '/5'"
                            ></span>
                        </div>

                        {{-- Validation error --}}
                        <p x-message="stars" class="text-sm text-danger mb-3"></p>

                        {{-- Submit Button --}}
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium bg-secondary text-on-secondary hover:bg-secondary-hover transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="selectedStars === 0 || $fetching()"
                            x-on:click="stars = selectedStars; $action('{{ url('/my-orders/' . $order->id . '/rate') }}', { include: ['stars'] })"
                        >
                            <span x-show="!$fetching()">
                                {{-- Star icon (Lucide, sm=16) --}}
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                            </span>
                            <span x-show="$fetching()" class="animate-spin-slow">
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                            </span>
                            <span x-show="!$fetching()">{{ __('Submit Rating') }}</span>
                            <span x-show="$fetching()">{{ __('Submitting...') }}</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Report a Problem (BR-227) --}}
            <div x-show="canReport" x-cloak>
                <div class="text-center py-2">
                    <a
                        href="{{ url('/my-orders/' . $order->id . '/complaint') }}"
                        class="text-sm text-on-surface/50 hover:text-danger transition-colors underline decoration-dotted underline-offset-4"
                        x-navigate
                    >
                        {{ __('Report a Problem') }}
                    </a>
                </div>
            </div>

        </div>
    </div>
    @endfragment

</div>
@endsection
