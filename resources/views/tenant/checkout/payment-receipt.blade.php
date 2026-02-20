{{--
    F-154: Payment Receipt & Confirmation Page
    BR-398: Displays order number, item summary, total amount, payment method,
            transaction reference, order status
    BR-399: Order number format: DMC-YYMMDD-NNNN
    BR-403: Download Receipt button
    BR-404: Track Order links to order tracking page
    BR-405: Order status is "Paid" at this point
    BR-406: Accessible only to the order's owner
    BR-408: All text localized via __()
--}}
@extends('layouts.tenant-public')

@section('title', __('Payment Confirmed') . ' - ' . ($tenant?->name ?? config('app.name')))

@section('content')
<div class="min-h-screen"
    x-data="{
        showShareOptions: false,
        shareText: '{{ addslashes($shareText) }}',
        printReceipt() {
            window.print();
        },
        async shareOrder() {
            if (navigator.share) {
                try {
                    await navigator.share({
                        title: '{{ addslashes(__('DancyMeals Order Confirmation')) }}',
                        text: this.shareText,
                        url: window.location.href
                    });
                } catch (e) {
                    /* User cancelled share */
                }
            } else {
                this.showShareOptions = !this.showShareOptions;
            }
        },
        copyLink() {
            navigator.clipboard.writeText(window.location.href);
            this.showShareOptions = false;
        }
    }"
>
    {{-- Top bar --}}
    <div class="bg-surface dark:bg-surface border-b border-outline dark:border-outline print:hidden">
        <div class="max-w-3xl mx-auto px-4 sm:px-6">
            <div class="h-12 flex items-center justify-center">
                <h1 class="text-sm font-semibold text-on-surface-strong dark:text-on-surface-strong">{{ __('Order Confirmation') }}</h1>
            </div>
        </div>
    </div>

    <div class="max-w-lg mx-auto px-4 sm:px-6 py-8 sm:py-12">
        {{-- Success animation --}}
        <div class="flex justify-center mb-6">
            <div class="relative">
                {{-- Green circle with checkmark --}}
                <div class="w-20 h-20 rounded-full bg-success flex items-center justify-center animate-scale-in">
                    <svg class="w-10 h-10 text-on-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </div>
                {{-- Subtle pulse ring --}}
                <div class="absolute inset-0 w-20 h-20 rounded-full bg-success/20 animate-ping" style="animation-duration: 2s; animation-iteration-count: 3;"></div>
            </div>
        </div>

        {{-- Success heading --}}
        <div class="text-center mb-8">
            <h2 class="text-2xl sm:text-3xl font-display font-bold text-on-surface-strong dark:text-on-surface-strong mb-2">
                {{ __('Payment Successful!') }}
            </h2>
            <p class="text-base text-on-surface dark:text-on-surface">
                {{ __('Your order has been confirmed and is being processed.') }}
            </p>
        </div>

        {{-- Receipt card --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-card overflow-hidden mb-6 print:shadow-none print:border-2">
            {{-- Order number header --}}
            <div class="bg-primary/5 dark:bg-primary/10 px-4 sm:px-6 py-4 border-b border-outline dark:border-outline">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-on-surface dark:text-on-surface uppercase tracking-wider">{{ __('Order Number') }}</span>
                    <span class="font-mono font-bold text-primary text-lg">{{ $order->order_number }}</span>
                </div>
            </div>

            {{-- Items summary --}}
            <div class="px-4 sm:px-6 py-4 border-b border-outline dark:border-outline">
                <h3 class="text-sm font-semibold text-on-surface-strong dark:text-on-surface-strong mb-3">{{ __('Items Ordered') }}</h3>

                @foreach($items as $meal)
                    <div class="mb-3 last:mb-0">
                        <p class="text-sm font-medium text-on-surface-strong dark:text-on-surface-strong mb-1">{{ $meal['meal_name'] }}</p>
                        @foreach($meal['components'] as $component)
                            <div class="flex items-center justify-between text-sm py-0.5">
                                <span class="text-on-surface dark:text-on-surface">
                                    {{ $component['quantity'] }}x {{ $component['name'] }}
                                </span>
                                <span class="font-medium text-on-surface-strong dark:text-on-surface-strong">
                                    {{ number_format($component['subtotal'], 0, '.', ',') }} XAF
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>

            {{-- Totals --}}
            <div class="px-4 sm:px-6 py-4 border-b border-outline dark:border-outline">
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-on-surface dark:text-on-surface">{{ __('Subtotal') }}</span>
                        <span class="text-on-surface-strong dark:text-on-surface-strong">{{ number_format($order->subtotal, 0, '.', ',') }} XAF</span>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <span class="text-on-surface dark:text-on-surface">{{ __('Delivery Fee') }}</span>
                        @if($order->delivery_fee > 0)
                            <span class="text-on-surface-strong dark:text-on-surface-strong">{{ number_format($order->delivery_fee, 0, '.', ',') }} XAF</span>
                        @else
                            <span class="text-success font-medium">{{ __('Free') }}</span>
                        @endif
                    </div>

                    @if($order->promo_discount > 0)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-on-surface dark:text-on-surface">{{ __('Promo Discount') }}</span>
                            <span class="text-success font-medium">-{{ number_format($order->promo_discount, 0, '.', ',') }} XAF</span>
                        </div>
                    @endif

                    <div class="border-t border-outline dark:border-outline pt-2 mt-2">
                        <div class="flex items-center justify-between">
                            <span class="text-base font-semibold text-on-surface-strong dark:text-on-surface-strong">{{ __('Total Paid') }}</span>
                            <span class="text-lg font-bold text-primary">{{ $order->formattedGrandTotal() }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Payment details --}}
            <div class="px-4 sm:px-6 py-4 border-b border-outline dark:border-outline">
                <h3 class="text-sm font-semibold text-on-surface-strong dark:text-on-surface-strong mb-3">{{ __('Payment Details') }}</h3>

                <div class="space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-on-surface dark:text-on-surface">{{ __('Payment Method') }}</span>
                        <span class="font-medium text-on-surface-strong dark:text-on-surface-strong">{{ $paymentLabel }}</span>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <span class="text-on-surface dark:text-on-surface">{{ __('Transaction Ref') }}</span>
                        <span class="font-mono text-xs text-on-surface-strong dark:text-on-surface-strong">{{ $transactionReference }}</span>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <span class="text-on-surface dark:text-on-surface">{{ __('Status') }}</span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-success/10 text-success text-xs font-semibold">
                            <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            {{ __('Paid') }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <span class="text-on-surface dark:text-on-surface">{{ __('Date') }}</span>
                        <span class="text-on-surface-strong dark:text-on-surface-strong">{{ $order->paid_at ? $order->paid_at->format('M d, Y H:i') : $order->created_at->format('M d, Y H:i') }}</span>
                    </div>
                </div>
            </div>

            {{-- Delivery info --}}
            <div class="px-4 sm:px-6 py-4">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-on-surface dark:text-on-surface">{{ __('Delivery') }}</span>
                    <span class="font-medium text-on-surface-strong dark:text-on-surface-strong">{{ $deliveryLabel }}</span>
                </div>
                @if($order->phone)
                    <div class="flex items-center justify-between text-sm mt-2">
                        <span class="text-on-surface dark:text-on-surface">{{ __('Contact Phone') }}</span>
                        <span class="font-medium text-on-surface-strong dark:text-on-surface-strong">{{ $order->phone }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Action buttons --}}
        <div class="space-y-3 mb-6 print:hidden">
            {{-- Primary CTA: Track Order --}}
            <a href="{{ $trackOrderUrl }}"
                x-navigate
                class="flex items-center justify-center gap-2 w-full px-6 py-3.5 bg-primary hover:bg-primary-hover text-on-primary font-semibold rounded-xl transition-colors duration-200 shadow-card text-base"
            >
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                {{ __('Track Order') }}
            </a>

            {{-- Secondary actions row --}}
            <div class="flex gap-3">
                {{-- Download Receipt --}}
                <button
                    @click="printReceipt()"
                    class="flex-1 flex items-center justify-center gap-2 px-4 py-3 bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline hover:bg-primary-subtle dark:hover:bg-primary-subtle text-on-surface-strong dark:text-on-surface-strong font-medium rounded-xl transition-colors duration-200 text-sm cursor-pointer"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" x2="12" y1="15" y2="3"></line></svg>
                    {{ __('Download Receipt') }}
                </button>

                {{-- Share --}}
                <div class="relative flex-1">
                    <button
                        @click="shareOrder()"
                        class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline hover:bg-primary-subtle dark:hover:bg-primary-subtle text-on-surface-strong dark:text-on-surface-strong font-medium rounded-xl transition-colors duration-200 text-sm cursor-pointer"
                    >
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" x2="15.42" y1="13.51" y2="17.49"></line><line x1="15.41" x2="8.59" y1="6.51" y2="10.49"></line></svg>
                        {{ __('Share') }}
                    </button>

                    {{-- Fallback share dropdown --}}
                    <div
                        x-show="showShareOptions"
                        x-transition
                        @click.outside="showShareOptions = false"
                        class="absolute bottom-full left-0 right-0 mb-2 bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl shadow-dropdown p-2 z-10"
                    >
                        {{-- WhatsApp --}}
                        <a
                            href="https://wa.me/?text={{ urlencode($shareText) }}"
                            target="_blank"
                            x-navigate-skip
                            class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-surface-alt dark:hover:bg-surface-alt text-sm text-on-surface-strong dark:text-on-surface-strong transition-colors"
                        >
                            <svg class="w-4 h-4 text-[#25D366]" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                            {{ __('WhatsApp') }}
                        </a>
                        {{-- Copy link --}}
                        <button
                            @click="copyLink()"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-surface-alt dark:hover:bg-surface-alt text-sm text-on-surface-strong dark:text-on-surface-strong transition-colors w-full cursor-pointer"
                        >
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"></rect><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"></path></svg>
                            {{ __('Copy Link') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Continue browsing --}}
        <div class="text-center print:hidden">
            <a href="{{ url('/') }}"
                x-navigate
                class="text-sm font-medium text-primary hover:text-primary-hover transition-colors duration-200"
            >
                {{ __('Continue Browsing') }}
            </a>
        </div>
    </div>

    {{-- Mobile sticky bottom bar --}}
    <div class="fixed bottom-0 left-0 right-0 bg-surface dark:bg-surface border-t border-outline dark:border-outline p-4 sm:hidden print:hidden z-30">
        <a href="{{ $trackOrderUrl }}"
            x-navigate
            class="flex items-center justify-center gap-2 w-full px-6 py-3.5 bg-primary hover:bg-primary-hover text-on-primary font-semibold rounded-xl transition-colors duration-200 text-base"
        >
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            {{ __('Track Order') }}
        </a>
    </div>

    {{-- Bottom spacing for mobile sticky bar --}}
    <div class="h-20 sm:hidden print:hidden"></div>
</div>

{{-- Print styles --}}
<style>
    @media print {
        header, nav, footer, .print\\:hidden { display: none !important; }
        .print\\:shadow-none { box-shadow: none !important; }
        .print\\:border-2 { border-width: 2px !important; }
        body { background: white !important; }
        * { color: #000 !important; }
    }
</style>
@endsection
