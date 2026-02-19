{{--
    F-139: Order Cart Management
    BR-253: Cart items displayed grouped by meal
    BR-254: Each item shows meal name, component name, quantity, unit price, line subtotal
    BR-255: Quantity adjustment respects component stock limits and minimum of 1
    BR-256: Removing the last item from a meal group removes the meal group header
    BR-257: Cart subtotal is the sum of all line subtotals
    BR-258: "Clear Cart" requires confirmation before executing
    BR-259: Cart persists in server-side session across page navigations
    BR-260: "Proceed to Checkout" requires authentication; guests redirected to login
    BR-261: Cart cannot proceed to checkout if empty
    BR-262: All cart interactions use Gale (no page reload)
    BR-263: All text localized via __()
--}}
@extends('layouts.tenant-public')

@section('title', __('Your Cart') . ' - ' . ($tenant?->name ?? config('app.name')))

@section('content')
<div class="min-h-screen"
    x-data="{
        cartCount: {{ $cart['summary']['count'] }},
        cartTotal: {{ $cart['summary']['total'] }},
        cartMeals: {{ json_encode($cart['meals']) }},
        cartError: '',
        cartSuccess: '',
        component_id: 0,
        quantity: 0,
        confirmClear: false,
        formatPrice(amount) {
            return new Intl.NumberFormat('en').format(amount) + ' XAF';
        },
        get isEmpty() {
            return !this.cartMeals || this.cartMeals.length === 0;
        },
        increment(componentId, currentQty, maxQty) {
            if (currentQty >= maxQty) return;
            this.component_id = componentId;
            this.quantity = currentQty + 1;
            this.$nextTick(() => {
                $action('{{ route('tenant.cart.update-quantity') }}', {
                    include: ['component_id', 'quantity']
                });
            });
        },
        decrement(componentId, currentQty) {
            this.component_id = componentId;
            this.quantity = currentQty - 1;
            this.$nextTick(() => {
                $action('{{ route('tenant.cart.update-quantity') }}', {
                    include: ['component_id', 'quantity']
                });
            });
        },
        removeItem(componentId) {
            this.component_id = componentId;
            this.quantity = 0;
            this.$nextTick(() => {
                $action('{{ route('tenant.cart.update-quantity') }}', {
                    include: ['component_id', 'quantity']
                });
            });
        },
        doClearCart() {
            this.confirmClear = false;
            $action('{{ route('tenant.cart.clear') }}');
        },
        doCheckout() {
            $action('{{ route('tenant.cart.checkout') }}');
        }
    }"
    x-sync="['cartCount', 'cartTotal', 'cartMeals', 'cartError', 'cartSuccess']"
>
    {{-- Back navigation --}}
    <div class="bg-surface dark:bg-surface border-b border-outline dark:border-outline">
        <div class="max-w-3xl mx-auto px-4 sm:px-6">
            <div class="h-12 flex items-center justify-between">
                <a href="{{ url('/') }}" class="flex items-center gap-2 text-sm font-medium text-on-surface hover:text-primary transition-colors duration-200" x-navigate>
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                    {{ __('Continue Shopping') }}
                </a>
                <h1 class="text-sm font-semibold text-on-surface-strong">{{ __('Your Cart') }}</h1>
            </div>
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
        {{-- Cart error message --}}
        <div x-show="cartError" x-cloak class="mb-4 flex items-center gap-2 bg-danger-subtle text-danger rounded-lg px-4 py-3 text-sm font-medium">
            <svg class="w-4 h-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg>
            <span x-text="cartError"></span>
        </div>

        {{-- Cart success message --}}
        <div x-show="cartSuccess" x-cloak class="mb-4 flex items-center gap-2 bg-success-subtle text-success rounded-lg px-4 py-3 text-sm font-medium"
             x-effect="if (cartSuccess) { setTimeout(() => cartSuccess = '', 3000) }"
        >
            <svg class="w-4 h-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><path d="m9 11 3 3L22 4"></path></svg>
            <span x-text="cartSuccess"></span>
        </div>

        {{-- Empty cart state --}}
        <template x-if="isEmpty">
            <div class="text-center py-16">
                <div class="w-20 h-20 mx-auto bg-surface-alt dark:bg-surface-alt rounded-full flex items-center justify-center mb-6">
                    <svg class="w-10 h-10 text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path></svg>
                </div>
                <h2 class="text-xl font-display font-bold text-on-surface-strong mb-2">{{ __('Your cart is empty') }}</h2>
                <p class="text-on-surface mb-6">{{ __('Browse meals to get started!') }}</p>
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2 h-11 px-6 bg-primary hover:bg-primary-hover text-on-primary font-semibold rounded-lg shadow-card transition-all duration-200" x-navigate>
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="14" rx="1"></rect><rect width="7" height="7" x="3" y="14" rx="1"></rect></svg>
                    {{ __('Browse Meals') }}
                </a>
            </div>
        </template>

        {{-- Cart with items --}}
        <template x-if="!isEmpty">
            <div>
                {{-- Cart header with clear button --}}
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-lg sm:text-xl font-display font-bold text-on-surface-strong">
                            {{ __('Your Cart') }}
                        </h2>
                        <p class="text-sm text-on-surface mt-0.5">
                            <span x-text="cartCount"></span> {{ __('items') }}
                        </p>
                    </div>
                    {{-- BR-258: Clear Cart with confirmation --}}
                    <button
                        @click="confirmClear = true"
                        class="inline-flex items-center gap-1.5 text-sm font-medium text-danger hover:text-danger/80 transition-colors duration-200 cursor-pointer"
                    >
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                        {{ __('Clear Cart') }}
                    </button>
                </div>

                {{-- BR-253: Cart items grouped by meal --}}
                <div class="space-y-6">
                    <template x-for="(mealGroup, mealIndex) in cartMeals" :key="mealGroup.meal_id">
                        <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl overflow-hidden shadow-card">
                            {{-- Meal group header --}}
                            <div class="px-4 py-3 bg-surface-alt dark:bg-surface-alt border-b border-outline dark:border-outline">
                                <div class="flex items-center justify-between">
                                    <h3 class="font-semibold text-on-surface-strong text-sm sm:text-base" x-text="mealGroup.meal_name"></h3>
                                    <span class="text-sm font-medium text-primary" x-text="formatPrice(mealGroup.subtotal)"></span>
                                </div>
                                {{-- Meal unavailable warning --}}
                                <template x-if="mealGroup.meal_available === false">
                                    <div class="mt-2 flex items-center gap-1.5 text-xs font-medium text-warning">
                                        <svg class="w-3.5 h-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                                        {{ __('This meal is no longer available') }}
                                    </div>
                                </template>
                            </div>

                            {{-- BR-254: Item rows --}}
                            <div class="divide-y divide-outline dark:divide-outline">
                                <template x-for="(item, itemIndex) in mealGroup.items" :key="item.component_id">
                                    <div class="px-4 py-3">
                                        <div class="flex items-start gap-3">
                                            {{-- Item info --}}
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <p class="text-sm font-medium text-on-surface-strong truncate" x-text="item.name"></p>
                                                    {{-- Availability warning badge --}}
                                                    <template x-if="item.warning">
                                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-warning bg-warning-subtle rounded-full px-2 py-0.5 shrink-0">
                                                            <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                                                            <span x-text="item.warning"></span>
                                                        </span>
                                                    </template>
                                                </div>
                                                <p class="text-xs text-on-surface mt-0.5">
                                                    <span x-text="formatPrice(item.unit_price)"></span>
                                                    <template x-if="item.unit">
                                                        <span> / <span x-text="item.unit"></span></span>
                                                    </template>
                                                </p>
                                            </div>

                                            {{-- Quantity controls + subtotal --}}
                                            <div class="flex flex-col items-end gap-2 shrink-0">
                                                {{-- BR-255: Quantity +/- controls --}}
                                                <div class="inline-flex items-center border border-outline dark:border-outline rounded-lg overflow-hidden">
                                                    <button
                                                        @click="decrement(item.component_id, item.quantity)"
                                                        class="w-8 h-8 flex items-center justify-center text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors duration-200 cursor-pointer"
                                                        :disabled="$fetching()"
                                                        :aria-label="'{{ __('Decrease quantity') }}'"
                                                    >
                                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path></svg>
                                                    </button>
                                                    <span class="w-10 h-8 flex items-center justify-center text-sm font-semibold text-on-surface-strong bg-surface-alt dark:bg-surface-alt border-x border-outline dark:border-outline" x-text="item.quantity"></span>
                                                    <button
                                                        @click="increment(item.component_id, item.quantity, item.max_quantity || 50)"
                                                        class="w-8 h-8 flex items-center justify-center text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors duration-200 cursor-pointer"
                                                        :disabled="$fetching() || item.quantity >= (item.max_quantity || 50)"
                                                        :class="item.quantity >= (item.max_quantity || 50) ? 'opacity-30 cursor-not-allowed' : ''"
                                                        :aria-label="'{{ __('Increase quantity') }}'"
                                                    >
                                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                                                    </button>
                                                </div>

                                                {{-- Line subtotal --}}
                                                <p class="text-sm font-semibold text-on-surface-strong" x-text="formatPrice(item.unit_price * item.quantity)"></p>
                                            </div>
                                        </div>

                                        {{-- Remove button --}}
                                        <div class="mt-2 flex justify-end">
                                            <button
                                                @click="removeItem(item.component_id)"
                                                class="inline-flex items-center gap-1 text-xs font-medium text-danger hover:text-danger/80 transition-colors duration-200 cursor-pointer"
                                                :disabled="$fetching()"
                                            >
                                                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                                {{ __('Remove') }}
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- BR-257: Cart subtotal --}}
                <div class="mt-6 bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-4 shadow-card">
                    <div class="flex items-center justify-between">
                        <span class="text-base font-semibold text-on-surface-strong">{{ __('Subtotal') }}</span>
                        <span class="text-xl font-bold text-primary" x-text="formatPrice(cartTotal)"></span>
                    </div>
                    <p class="text-xs text-on-surface mt-1">{{ __('Delivery fees calculated at checkout') }}</p>
                </div>

                {{-- Action buttons --}}
                <div class="mt-6 flex flex-col sm:flex-row gap-3">
                    <a href="{{ url('/') }}" class="flex-1 h-11 inline-flex items-center justify-center gap-2 border border-outline dark:border-outline text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt font-medium rounded-lg transition-all duration-200" x-navigate>
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                        {{ __('Continue Shopping') }}
                    </a>
                    {{-- BR-260/BR-261: Proceed to checkout --}}
                    <button
                        @click="doCheckout()"
                        class="flex-1 h-11 inline-flex items-center justify-center gap-2 bg-primary hover:bg-primary-hover text-on-primary font-semibold rounded-lg shadow-card transition-all duration-200 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="$fetching() || isEmpty"
                    >
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                        <span x-show="!$fetching()">{{ __('Proceed to Checkout') }}</span>
                        <span x-show="$fetching()" x-cloak>{{ __('Processing...') }}</span>
                    </button>
                </div>
            </div>
        </template>
    </div>

    {{-- BR-258: Clear Cart confirmation modal --}}
    <div
        x-show="confirmClear"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        :aria-label="'{{ __('Clear Cart') }}'"
    >
        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-black/50" @click="confirmClear = false"></div>

        {{-- Modal --}}
        <div
            class="relative bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl shadow-dropdown p-6 w-full max-w-sm"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
        >
            <div class="text-center">
                <div class="w-12 h-12 mx-auto bg-danger-subtle rounded-full flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                </div>
                <h3 class="text-lg font-semibold text-on-surface-strong mb-2">{{ __('Clear Cart?') }}</h3>
                <p class="text-sm text-on-surface mb-6">{{ __('This will remove all items from your cart. This action cannot be undone.') }}</p>
            </div>
            <div class="flex gap-3">
                <button
                    @click="confirmClear = false"
                    class="flex-1 h-10 inline-flex items-center justify-center border border-outline dark:border-outline text-on-surface hover:bg-surface-alt rounded-lg font-medium text-sm transition-all duration-200 cursor-pointer"
                >
                    {{ __('Cancel') }}
                </button>
                <button
                    @click="doClearCart()"
                    class="flex-1 h-10 inline-flex items-center justify-center bg-danger hover:bg-danger/90 text-on-danger rounded-lg font-semibold text-sm transition-all duration-200 cursor-pointer"
                    :disabled="$fetching()"
                >
                    {{ __('Clear Cart') }}
                </button>
            </div>
        </div>
    </div>

    {{-- Mobile sticky checkout bar --}}
    <div
        class="sm:hidden fixed bottom-0 left-0 right-0 z-40 bg-surface dark:bg-surface border-t border-outline dark:border-outline px-4 py-3 shadow-dropdown"
        x-show="!isEmpty"
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
                <p class="text-xs text-on-surface">{{ __('Subtotal') }}</p>
                <p class="text-lg font-bold text-primary" x-text="formatPrice(cartTotal)"></p>
            </div>
            <button
                @click="doCheckout()"
                class="h-11 px-6 bg-primary hover:bg-primary-hover text-on-primary font-semibold rounded-lg shadow-card transition-all duration-200 cursor-pointer inline-flex items-center gap-2"
                :disabled="$fetching()"
            >
                <span x-show="!$fetching()">{{ __('Checkout') }}</span>
                <span x-show="$fetching()" x-cloak>{{ __('Processing...') }}</span>
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
            </button>
        </div>
    </div>
</div>
@endsection
