{{--
    F-129: Meal Detail View
    F-138: Meal Component Selection & Cart Add
    BR-156: Displays name, description, images, components, schedule, locations
    BR-157: Image carousel up to 3 images with manual navigation
    BR-158: Each component shows name, price, unit, availability status
    BR-159: Availability statuses: Available, Low Stock (X left), Sold Out
    BR-160: Requirement rules in plain language
    BR-161: Quantity selector respects min/max/stock limits
    BR-162: Add to Cart disabled for sold-out components
    BR-163/BR-251: Cart updates reactively via Gale SSE (server-side session)
    BR-243: Quantity min 1, max lesser of stock or cook-defined max
    BR-244: Requirement rules enforced
    BR-245: Running total updates reactively
    BR-246: Cart state in session
    BR-247: Guest carts work via session
    BR-248: Same component updates quantity (no duplicates)
    BR-249: Cart items grouped by meal
    BR-250: All prices XAF integer
    BR-252: All text localized via __()
--}}
@extends('layouts.tenant-public')

@section('title', $mealData['name'] . ' - ' . ($tenant?->name ?? config('app.name')))

@section('content')
<div class="min-h-screen"
    x-data="{
        component_id: 0,
        meal_id: {{ $meal->id }},
        quantity: 1,
        cartCount: {{ $cart['summary']['count'] }},
        cartTotal: {{ $cart['summary']['total'] }},
        cartItems: {{ json_encode($cart['items']) }},
        cartMeals: {{ json_encode($cart['meals']) }},
        cartError: '',
        cartSuccess: '',
        descExpanded: false,
        toastVisible: false,
        toastTimeout: null,
        formatPrice(amount) {
            return new Intl.NumberFormat('en').format(amount) + ' XAF';
        },
        showToast() {
            if (this.cartSuccess) {
                this.toastVisible = true;
                clearTimeout(this.toastTimeout);
                this.toastTimeout = setTimeout(() => { this.toastVisible = false; }, 3000);
            }
        },
        doAddToCart(id, qty) {
            this.component_id = id;
            this.quantity = qty;
            this.$nextTick(() => {
                $action('{{ route('tenant.cart.add') }}', {
                    include: ['component_id', 'meal_id', 'quantity']
                });
            });
        }
    }"
    x-sync="['cartCount', 'cartTotal', 'cartItems', 'cartMeals', 'cartError', 'cartSuccess']"
    x-effect="showToast()"
    x-on:add-to-cart.window="doAddToCart($event.detail.id, $event.detail.qty)"
>
    {{-- Back navigation --}}
    <div class="bg-surface dark:bg-surface border-b border-outline dark:border-outline">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="h-12 flex items-center justify-between">
                <a href="{{ url('/') }}" class="flex items-center gap-2 text-sm font-medium text-on-surface hover:text-primary transition-colors duration-200" x-navigate>
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                    {{ __('Back to Meals') }}
                </a>

                {{-- Cart indicator --}}
                <div class="flex items-center gap-2" x-show="cartCount > 0" x-cloak>
                    <span class="inline-flex items-center gap-1.5 bg-primary text-on-primary text-xs font-bold rounded-full px-3 py-1">
                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path></svg>
                        <span x-text="cartCount"></span>
                    </span>
                    <span class="text-sm font-semibold text-primary" x-text="formatPrice(cartTotal)"></span>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
        <div class="lg:grid lg:grid-cols-2 lg:gap-10">
            {{-- Left Column: Image carousel --}}
            <div class="mb-6 lg:mb-0">
                @include('tenant._meal-detail-carousel', ['images' => $mealData['images'], 'mealName' => $mealData['name']])
            </div>

            {{-- Right Column: Meal info + Components --}}
            <div>
                {{-- Meal Name --}}
                <h1 class="text-2xl sm:text-3xl font-display font-bold text-on-surface-strong leading-tight">
                    {{ $mealData['name'] }}
                </h1>

                {{-- Tags --}}
                @if(!empty($mealData['tags']))
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach($mealData['tags'] as $tag)
                            <span class="inline-flex items-center text-xs font-medium bg-primary-subtle text-primary rounded-full px-2.5 py-0.5">
                                {{ $tag['name'] }}
                            </span>
                        @endforeach
                    </div>
                @endif

                {{-- Prep time badge --}}
                @if($mealData['prepTime'])
                    <div class="mt-3 inline-flex items-center gap-1.5 bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline text-on-surface text-sm font-medium rounded-full px-3 py-1">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        {{ __('Prep time: :minutes min', ['minutes' => $mealData['prepTime']]) }}
                    </div>
                @endif

                {{-- All unavailable banner --}}
                @if($mealData['allUnavailable'])
                    <div class="mt-4 flex items-center gap-2 bg-danger-subtle text-danger rounded-lg px-4 py-3 text-sm font-semibold">
                        <svg class="w-5 h-5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg>
                        {{ __('Currently Unavailable') }}
                    </div>
                @endif

                {{-- Description --}}
                @if($mealData['description'])
                    <div class="mt-4">
                        @php
                            $descLength = mb_strlen($mealData['description']);
                            $isLong = $descLength > 300;
                        @endphp
                        <div class="text-on-surface leading-relaxed whitespace-pre-line"
                             :class="{ 'line-clamp-4': !descExpanded && {{ $isLong ? 'true' : 'false' }} }">
                            {{ $mealData['description'] }}
                        </div>
                        @if($isLong)
                            <button
                                @click="descExpanded = !descExpanded"
                                class="mt-1 text-sm font-medium text-primary hover:text-primary-hover transition-colors duration-200 cursor-pointer"
                            >
                                <span x-show="!descExpanded">{{ __('Read more') }}</span>
                                <span x-show="descExpanded" x-cloak>{{ __('Show less') }}</span>
                            </button>
                        @endif
                    </div>
                @endif

                {{-- Divider --}}
                <div class="border-t border-outline dark:border-outline my-6"></div>

                {{-- Cart error message --}}
                <div x-show="cartError" x-cloak class="mb-4 flex items-center gap-2 bg-danger-subtle text-danger rounded-lg px-4 py-3 text-sm font-medium">
                    <svg class="w-4 h-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg>
                    <span x-text="cartError"></span>
                </div>

                {{-- Components Section --}}
                <div>
                    <h2 class="text-lg font-semibold text-on-surface-strong mb-4">
                        {{ __('Choose Your Items') }}
                    </h2>

                    @if(!$mealData['hasComponents'])
                        {{-- Edge case: No components --}}
                        <div class="text-center py-8 bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline">
                            <svg class="w-10 h-10 mx-auto text-on-surface/30 mb-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="14" rx="1"></rect><rect width="7" height="7" x="3" y="14" rx="1"></rect></svg>
                            <p class="text-sm text-on-surface/60">{{ __('This meal has no items yet') }}</p>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($components as $index => $component)
                                @include('tenant._meal-detail-component', [
                                    'component' => $component,
                                    'components' => $components,
                                    'cartComponentsForMeal' => $cartComponentsForMeal,
                                    'mealId' => $meal->id,
                                ])
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Schedule Section --}}
        @if($schedule['hasSchedule'])
            <div class="mt-10 border-t border-outline dark:border-outline pt-8">
                <h2 class="text-lg font-semibold text-on-surface-strong mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path></svg>
                    {{ __('Availability Schedule') }}
                </h2>

                <div class="grid gap-2 max-w-2xl">
                    @foreach($schedule['entries'] as $entry)
                        <div class="flex items-start gap-3 p-3 rounded-lg {{ $entry['isToday'] ? 'bg-primary-subtle border border-primary/20' : 'bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline' }}">
                            <div class="shrink-0 w-24">
                                <span class="text-sm font-semibold {{ $entry['isToday'] ? 'text-primary' : 'text-on-surface-strong' }}">
                                    {{ $entry['dayLabel'] }}
                                </span>
                                @if($entry['isToday'])
                                    <span class="block text-xs font-medium text-primary mt-0.5">{{ __('Today') }}</span>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                @if($entry['label'])
                                    <span class="text-xs font-medium text-on-surface/60 block mb-0.5">{{ $entry['label'] }}</span>
                                @endif
                                @if($entry['orderInterval'])
                                    <p class="text-sm text-on-surface">
                                        <span class="font-medium">{{ __('Orders:') }}</span> {{ $entry['orderInterval'] }}
                                    </p>
                                @endif
                                @if($entry['deliveryInterval'])
                                    <p class="text-sm text-on-surface mt-0.5">
                                        <span class="font-medium">{{ __('Delivery:') }}</span> {{ $entry['deliveryInterval'] }}
                                    </p>
                                @endif
                                @if($entry['pickupInterval'])
                                    <p class="text-sm text-on-surface mt-0.5">
                                        <span class="font-medium">{{ __('Pickup:') }}</span> {{ $entry['pickupInterval'] }}
                                    </p>
                                @endif
                                @if(!$entry['orderInterval'] && !$entry['deliveryInterval'] && !$entry['pickupInterval'])
                                    <p class="text-sm text-on-surface/60 italic">{{ __('Available all day') }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Locations Section --}}
        @if($locations['hasLocations'])
            <div class="mt-10 border-t border-outline dark:border-outline pt-8">
                <h2 class="text-lg font-semibold text-on-surface-strong mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    {{ __('Delivery & Pickup') }}
                </h2>

                <div class="grid sm:grid-cols-2 gap-6 max-w-3xl">
                    {{-- Delivery Towns --}}
                    @if(!empty($locations['deliveryTowns']))
                        <div>
                            <h3 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wider mb-3">
                                {{ __('Delivery Areas') }}
                            </h3>
                            <div class="space-y-3">
                                @foreach($locations['deliveryTowns'] as $town)
                                    <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-3">
                                        <h4 class="text-sm font-semibold text-on-surface-strong">{{ $town['name'] }}</h4>
                                        @if(!empty($town['quarters']))
                                            <div class="mt-2 space-y-1">
                                                @foreach($town['quarters'] as $quarter)
                                                    <div class="flex items-center justify-between text-sm">
                                                        <span class="text-on-surface">{{ $quarter['name'] }}</span>
                                                        <span class="text-on-surface-strong font-medium">{{ $quarter['formattedFee'] }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Pickup Locations --}}
                    @if(!empty($locations['pickupLocations']))
                        <div>
                            <h3 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wider mb-3">
                                {{ __('Pickup Locations') }}
                            </h3>
                            <div class="space-y-3">
                                @foreach($locations['pickupLocations'] as $pickup)
                                    <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-3">
                                        <h4 class="text-sm font-semibold text-on-surface-strong">{{ $pickup['name'] }}</h4>
                                        @if($pickup['town'])
                                            <p class="text-xs text-on-surface mt-0.5">{{ $pickup['town'] }}</p>
                                        @endif
                                        @if($pickup['address'])
                                            <p class="text-xs text-on-surface/70 mt-1">{{ $pickup['address'] }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- Cart toast notification --}}
    <div
        x-show="toastVisible"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-2"
        class="fixed bottom-4 right-4 left-4 sm:left-auto sm:w-80 z-50 bg-success text-on-success rounded-lg shadow-dropdown px-4 py-3 flex items-center gap-3"
        x-cloak
    >
        <svg class="w-5 h-5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><path d="m9 11 3 3L22 4"></path></svg>
        <span class="text-sm font-medium" x-text="cartSuccess"></span>
    </div>

    {{-- Mobile sticky "View Cart" bar --}}
    <div
        class="lg:hidden fixed bottom-0 left-0 right-0 z-40 bg-surface dark:bg-surface border-t border-outline dark:border-outline px-4 py-3 shadow-dropdown"
        x-show="cartCount > 0"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-y-full"
        x-transition:enter-end="translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-y-0"
        x-transition:leave-end="translate-y-full"
    >
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-on-surface">
                    <span x-text="cartCount"></span> {{ __('items') }}
                </p>
                <p class="text-lg font-bold text-primary" x-text="formatPrice(cartTotal)"></p>
            </div>
            {{-- F-139 will wire this to the actual cart page --}}
            <button
                class="h-11 px-6 bg-primary hover:bg-primary-hover text-on-primary font-semibold rounded-lg shadow-card transition-all duration-200 cursor-pointer inline-flex items-center gap-2"
                disabled
            >
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path></svg>
                {{ __('View Cart') }}
            </button>
        </div>
    </div>
</div>
@endsection
