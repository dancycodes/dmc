{{--
    F-146: Order Total Calculation & Summary
    BR-316: Itemized list shows meal name, component name, quantity, unit price, line subtotal
    BR-317: Items are grouped by meal
    BR-318: Subtotal is the sum of all food item line subtotals
    BR-319: Delivery fee is shown as a separate line item; pickup shows Pickup - Free
    BR-320: Promo discount (if applicable) is shown as a negative line item with code name
    BR-321: Grand total = subtotal + delivery fee - promo discount
    BR-322: All amounts displayed in XAF (integer, formatted with thousand separators)
    BR-323: Summary updates reactively via Gale when any input changes
    BR-324: An Edit Cart link allows returning to the cart without losing checkout progress
    BR-325: Proceed to Payment button leads to F-149 (Payment Method Selection)
    BR-326: All text must be localized via __()
--}}
@extends('layouts.tenant-public')

@section('title', __('Order Summary') . ' - ' . ($tenant?->name ?? config('app.name')))

@section('content')
<div class="min-h-screen"
    x-data="{
        formatPrice(amount) {
            return new Intl.NumberFormat('en').format(amount) + ' XAF';
        }
    }"
>
    {{-- Back navigation --}}
    <div class="bg-surface dark:bg-surface border-b border-outline dark:border-outline">
        <div class="max-w-3xl mx-auto px-4 sm:px-6">
            <div class="h-12 flex items-center justify-between">
                <a href="{{ $backUrl }}" class="flex items-center gap-2 text-sm font-medium text-on-surface hover:text-primary transition-colors duration-200" x-navigate>
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                    {{ __('Back') }}
                </a>
                <h1 class="text-sm font-semibold text-on-surface-strong">{{ __('Checkout') }}</h1>
            </div>
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
        {{-- Step indicator --}}
        <div class="mb-6">
            <div class="flex items-center gap-2 text-xs font-medium text-on-surface flex-wrap">
                {{-- Step 1: Delivery Method (completed) --}}
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-success text-on-success text-xs font-bold shrink-0">
                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                </span>
                <span class="text-success font-semibold">{{ __('Delivery Method') }}</span>
                <svg class="w-4 h-4 text-on-surface/30 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>

                {{-- Step 2: Location (completed) --}}
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-success text-on-success text-xs font-bold shrink-0">
                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                </span>
                <span class="text-success font-semibold">{{ __('Location') }}</span>
                <svg class="w-4 h-4 text-on-surface/30 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>

                {{-- Step 3: Phone (completed) --}}
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-success text-on-success text-xs font-bold shrink-0">
                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                </span>
                <span class="text-success font-semibold">{{ __('Phone') }}</span>
                <svg class="w-4 h-4 text-on-surface/30 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>

                {{-- Step 4: Schedule (completed) --}}
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-success text-on-success text-xs font-bold shrink-0">
                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                </span>
                <span class="text-success font-semibold">{{ __('Schedule') }}</span>
                <svg class="w-4 h-4 text-on-surface/30 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>

                {{-- Step 5: Review (current) --}}
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-primary text-on-primary text-xs font-bold shrink-0">5</span>
                <span class="text-primary font-semibold">{{ __('Review') }}</span>
                <svg class="w-4 h-4 text-on-surface/30 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>

                {{-- Step 6: Payment (upcoming) --}}
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-outline text-on-surface text-xs font-bold shrink-0">6</span>
                <span class="text-on-surface/50">{{ __('Payment') }}</span>
            </div>
        </div>

        {{-- Page heading with Edit Cart link --}}
        <div class="flex items-start justify-between mb-6">
            <div>
                <h2 class="text-lg sm:text-xl font-display font-bold text-on-surface-strong">
                    {{ __('Order Summary') }}
                </h2>
                <p class="text-sm text-on-surface mt-1">
                    {{ __('Review your order before proceeding to payment.') }}
                </p>
            </div>
            {{-- BR-324: Edit Cart link --}}
            <a href="{{ url('/cart') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:text-primary-hover transition-colors duration-200 shrink-0 mt-1" x-navigate>
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path></svg>
                {{ __('Edit Cart') }}
            </a>
        </div>

        {{-- Edge case: Price change notification --}}
        @if (!empty($orderSummary['price_changes']))
            <div class="mb-4 flex items-start gap-2 bg-warning-subtle rounded-lg px-4 py-3">
                <svg class="w-4 h-4 text-warning shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                <div>
                    <p class="text-sm font-semibold text-warning">{{ __('Some prices have changed') }}</p>
                    <ul class="mt-1 text-xs text-on-surface space-y-0.5">
                        @foreach ($orderSummary['price_changes'] as $change)
                            <li>
                                {{ $change['name'] }}:
                                <span class="line-through text-on-surface/50">{{ number_format($change['old_price'], 0, '.', ',') }} XAF</span>
                                <span class="font-medium text-on-surface-strong">{{ number_format($change['new_price'], 0, '.', ',') }} XAF</span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-1 text-xs text-on-surface/70">
                        {{ __('The total below reflects the current prices. Please review before continuing.') }}
                    </p>
                </div>
            </div>
        @endif

        {{-- BR-316, BR-317: Itemized list grouped by meal --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-card overflow-hidden">
            {{-- Receipt header --}}
            <div class="px-5 py-4 border-b border-outline dark:border-outline">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center">
                        <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"></path><path d="M14 2v6h6"></path><path d="M16 13H8"></path><path d="M16 17H8"></path><path d="M10 9H8"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-on-surface-strong">{{ __('Order Items') }}</h3>
                        <p class="text-xs text-on-surface">
                            {{ trans_choice(':count item|:count items', $orderSummary['item_count'], ['count' => $orderSummary['item_count']]) }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Meal groups --}}
            <div class="divide-y divide-outline dark:divide-outline">
                @foreach ($orderSummary['meals'] as $mealGroup)
                    <div class="px-5 py-4">
                        {{-- Meal name header (bold) --}}
                        <h4 class="text-sm font-bold text-on-surface-strong mb-3">
                            {{ $mealGroup['meal_name'] }}
                        </h4>

                        {{-- Component line items --}}
                        <div class="space-y-2">
                            @foreach ($mealGroup['items'] as $item)
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex-1 min-w-0">
                                        <span class="text-sm text-on-surface">{{ $item['name'] }}</span>
                                        <span class="text-xs text-on-surface/60 ml-1">
                                            {{ $item['quantity'] }} x {{ number_format($item['unit_price'], 0, '.', ',') }} XAF
                                        </span>
                                    </div>
                                    <span class="text-sm font-medium text-on-surface-strong whitespace-nowrap">
                                        {{ number_format($item['unit_price'] * $item['quantity'], 0, '.', ',') }} XAF
                                    </span>
                                </div>
                            @endforeach
                        </div>

                        {{-- Meal subtotal --}}
                        @if (count($mealGroup['items']) > 1)
                            <div class="mt-2 pt-2 border-t border-outline/50 dark:border-outline/50 flex items-center justify-between">
                                <span class="text-xs font-medium text-on-surface/60">{{ __('Meal subtotal') }}</span>
                                <span class="text-xs font-medium text-on-surface">{{ number_format($mealGroup['subtotal'], 0, '.', ',') }} XAF</span>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Summary totals box --}}
        <div class="mt-4 bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-card overflow-hidden">
            <div class="px-5 py-4 space-y-3">
                {{-- BR-318: Subtotal --}}
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-on-surface">{{ __('Subtotal') }}</span>
                    <span class="text-sm font-medium text-on-surface-strong">{{ number_format($orderSummary['subtotal'], 0, '.', ',') }} XAF</span>
                </div>

                {{-- BR-319: Delivery fee or Pickup - Free --}}
                <div class="flex items-center justify-between">
                    @if ($deliveryMethod === 'pickup')
                        <span class="text-sm font-medium text-on-surface">{{ __('Pickup') }}</span>
                        <span class="text-sm font-medium text-success">{{ __('Free') }}</span>
                    @else
                        <span class="text-sm font-medium text-on-surface">{{ __('Delivery Fee') }}</span>
                        @if ($orderSummary['delivery_fee'] === 0)
                            <span class="text-sm font-medium text-success">{{ __('Free') }}</span>
                        @else
                            <span class="text-sm font-medium text-on-surface-strong">{{ number_format($orderSummary['delivery_fee'], 0, '.', ',') }} XAF</span>
                        @endif
                    @endif
                </div>

                {{-- Delivery location detail --}}
                @if ($deliveryMethod === 'delivery' && $orderSummary['delivery_display']['quarter_name'])
                    <div class="flex items-center gap-1.5 text-xs text-on-surface/60">
                        <svg class="w-3.5 h-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        {{ $orderSummary['delivery_display']['quarter_name'] }}
                    </div>
                @endif

                {{-- BR-320: Promo discount (forward-compatible, shown only if applicable) --}}
                @if ($orderSummary['promo_discount'] > 0)
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-success">
                            {{ __('Promo') }} "{{ $orderSummary['promo_code'] }}"
                        </span>
                        <span class="text-sm font-medium text-success">
                            -{{ number_format($orderSummary['promo_discount'], 0, '.', ',') }} XAF
                        </span>
                    </div>
                @endif

                {{-- Separator --}}
                <div class="border-t border-outline dark:border-outline"></div>

                {{-- BR-321: Grand total (large, bold) --}}
                <div class="flex items-center justify-between">
                    <span class="text-base font-bold text-on-surface-strong">{{ __('Total') }}</span>
                    <span class="text-xl font-bold text-primary">{{ number_format($orderSummary['grand_total'], 0, '.', ',') }} XAF</span>
                </div>
            </div>
        </div>

        {{-- Order details card --}}
        <div class="mt-4 bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-card overflow-hidden">
            <div class="px-5 py-4">
                <h4 class="text-sm font-semibold text-on-surface-strong mb-3">{{ __('Order Details') }}</h4>
                <div class="space-y-2">
                    {{-- Delivery method --}}
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-primary-subtle flex items-center justify-center shrink-0">
                            @if ($deliveryMethod === 'delivery')
                                <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"></path><path d="M15 18H9"></path><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path><circle cx="17" cy="18" r="2"></circle><circle cx="7" cy="18" r="2"></circle></svg>
                            @else
                                <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"></path><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"></path><path d="M2 7h20"></path></svg>
                            @endif
                        </div>
                        <div>
                            <p class="text-sm font-medium text-on-surface-strong">
                                {{ $deliveryMethod === 'delivery' ? __('Delivery') : __('Pickup') }}
                            </p>
                            @if ($deliveryMethod === 'delivery' && $orderSummary['delivery_display']['quarter_name'])
                                <p class="text-xs text-on-surface/60">{{ $orderSummary['delivery_display']['quarter_name'] }}</p>
                            @endif
                        </div>
                    </div>

                    {{-- Phone number --}}
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-primary-subtle flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-on-surface-strong">{{ __('Phone') }}</p>
                            <p class="text-xs text-on-surface/60">{{ $phone }}</p>
                        </div>
                    </div>

                    {{-- F-148: Scheduled date (shown if scheduling was selected) --}}
                    @if (!empty($scheduledDate))
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-secondary-subtle flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"></rect><line x1="16" x2="16" y1="2" y2="6"></line><line x1="8" x2="8" y1="2" y2="6"></line><line x1="3" x2="21" y1="10" y2="10"></line></svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-on-surface-strong">{{ __('Scheduled for') }}</p>
                                <p class="text-xs text-on-surface/60">{{ $scheduledDate }}</p>
                            </div>
                            <a href="{{ route('tenant.checkout.schedule') }}" class="ml-auto text-xs font-medium text-primary hover:text-primary-hover transition-colors duration-200" x-navigate>
                                {{ __('Change') }}
                            </a>
                        </div>
                    @else
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-primary-subtle flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-on-surface-strong">{{ __('Delivery Time') }}</p>
                                <p class="text-xs text-on-surface/60">{{ __('As soon as possible') }}</p>
                            </div>
                            <a href="{{ route('tenant.checkout.schedule') }}" class="ml-auto text-xs font-medium text-primary hover:text-primary-hover transition-colors duration-200" x-navigate>
                                {{ __('Schedule') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Action buttons (hidden on mobile since sticky bar is shown) --}}
        <div class="mt-6 flex flex-col sm:flex-row gap-3 hidden sm:flex">
            <a href="{{ $backUrl }}" class="flex-1 h-11 inline-flex items-center justify-center gap-2 border border-outline dark:border-outline text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt font-medium rounded-lg transition-all duration-200" x-navigate>
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                {{ __('Back') }}
            </a>
            {{-- BR-325: Proceed to Payment --}}
            <a href="{{ url('/checkout/payment') }}" class="flex-1 h-11 inline-flex items-center justify-center gap-2 bg-primary hover:bg-primary-hover text-on-primary font-semibold rounded-lg shadow-card transition-all duration-200" x-navigate>
                {{ __('Proceed to Payment') }}
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
            </a>
        </div>

        {{-- Spacer for mobile sticky bar --}}
        <div class="h-24 sm:hidden"></div>
    </div>

    {{-- Mobile sticky total bar at bottom --}}
    <div class="sm:hidden fixed bottom-0 left-0 right-0 z-40 bg-surface dark:bg-surface border-t border-outline dark:border-outline px-4 py-3 shadow-dropdown">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-on-surface">{{ __('Total') }}</p>
                <p class="text-base font-bold text-primary">{{ number_format($orderSummary['grand_total'], 0, '.', ',') }} XAF</p>
            </div>
            {{-- BR-325: Proceed to Payment --}}
            <a href="{{ url('/checkout/payment') }}" class="h-11 px-6 bg-primary hover:bg-primary-hover text-on-primary font-semibold rounded-lg shadow-card transition-all duration-200 inline-flex items-center gap-2" x-navigate>
                {{ __('Proceed to Payment') }}
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
            </a>
        </div>
    </div>
</div>
@endsection
