{{--
    F-129: Meal Detail View -- Component Card
    F-138: Meal Component Selection & Cart Add
    BR-158: Shows name, price, unit, availability status
    BR-159: Availability: Available, Low Stock (X left), Sold Out
    BR-160: Requirement rules in plain language
    BR-161: Quantity selector with min/max limits
    BR-162: Add to Cart disabled for sold-out
    BR-243: Quantity min 1, max lesser of stock or cook-defined max
    BR-244: Requirement rules enforced via server + client hints
    BR-248: Same component updates quantity
--}}
@php
    $isDisabled = !$component['isAvailable'];
    $colorMap = [
        'success' => 'bg-success/10 text-success dark:bg-success/20 dark:text-success',
        'warning' => 'bg-warning-subtle text-warning dark:bg-warning-subtle dark:text-warning',
        'danger' => 'bg-danger-subtle text-danger dark:bg-danger-subtle dark:text-danger',
    ];
    $statusClasses = $colorMap[$component['availabilityColor']] ?? $colorMap['success'];
    $dotColorMap = [
        'success' => 'bg-success',
        'warning' => 'bg-warning',
        'danger' => 'bg-danger',
    ];
    $dotClass = $dotColorMap[$component['availabilityColor']] ?? 'bg-success';

    // F-138: Check if component is already in cart
    $inCart = isset($cartComponentsForMeal[$component['id']]);
    $cartQty = $cartComponentsForMeal[$component['id']] ?? 0;

    // F-138: Build requirement rule data for client-side enforcement
    $requiresAnyOf = [];
    $requiresAllOf = [];
    $incompatibleWith = [];
    foreach ($component['requirements'] as $rule) {
        if ($rule['type'] === 'requires_any_of') {
            $requiresAnyOf[] = $rule;
        } elseif ($rule['type'] === 'requires_all_of') {
            $requiresAllOf[] = $rule;
        } elseif ($rule['type'] === 'incompatible_with') {
            $incompatibleWith[] = $rule;
        }
    }
    $hasRequirements = !empty($requiresAnyOf) || !empty($requiresAllOf);
@endphp

<div
    class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 transition-colors duration-200 {{ $isDisabled ? 'opacity-60' : '' }}"
    x-data="{
        quantity: {{ $inCart ? $cartQty : $component['minQuantity'] }},
        min: {{ $component['minQuantity'] }},
        max: {{ $component['maxSelectable'] }},
        disabled: {{ $isDisabled ? 'true' : 'false' }},
        inCart: {{ $inCart ? 'true' : 'false' }},
        adding: false,
        increment() {
            if (this.quantity < this.max) this.quantity++;
        },
        decrement() {
            if (this.quantity > this.min) this.quantity--;
        },
        addToCart() {
            if (this.disabled || this.adding) return;
            this.adding = true;
            /* Use $dispatch to communicate with parent x-data for $action call */
            $dispatch('add-to-cart', {
                id: {{ $component['id'] }},
                name: {{ json_encode($component['name']) }},
                price: {{ $component['price'] }},
                unit: {{ json_encode($component['unit']) }},
                qty: this.quantity
            });
            /* Reset adding state after brief delay */
            setTimeout(() => { this.adding = false; }, 800);
        },
        handleCartUpdated(detail) {
            if (detail.componentId === {{ $component['id'] }}) {
                this.inCart = detail.success;
            }
        }
    }"
    x-on:cart-updated.window="handleCartUpdated($event.detail)"
>
    <div class="flex items-start justify-between gap-3">
        <div class="flex-1 min-w-0">
            {{-- Component Name --}}
            <h3 class="text-base font-semibold text-on-surface-strong">{{ $component['name'] }}</h3>

            {{-- Price & Unit --}}
            <div class="mt-1 flex items-center gap-2">
                <span class="text-sm font-bold {{ $component['isFree'] ? 'text-success' : 'text-primary' }}">
                    {{ $component['formattedPrice'] }}
                </span>
                <span class="text-xs text-on-surface/60">/ {{ $component['unit'] }}</span>
            </div>

            {{-- Availability Status Badge --}}
            <div class="mt-2 inline-flex items-center gap-1.5 text-xs font-medium rounded-full px-2.5 py-1 {{ $statusClasses }}">
                <span class="w-1.5 h-1.5 rounded-full {{ $dotClass }}"></span>
                {{ $component['availabilityStatus'] }}
            </div>

            {{-- In Cart indicator --}}
            <div x-show="inCart" x-cloak class="mt-1.5 inline-flex items-center gap-1 text-xs font-medium text-success">
                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><path d="m9 11 3 3L22 4"></path></svg>
                {{ __('In cart') }}
            </div>

            {{-- Stock limit message --}}
            @if($component['maxSelectable'] < 99 && !$isDisabled && $component['availabilityColor'] === 'warning')
                <p class="mt-1.5 text-xs text-on-surface/60">
                    {{ __('Only :count available', ['count' => $component['maxSelectable']]) }}
                </p>
            @endif

            {{-- BR-160: Requirement Rules --}}
            @if(!empty($component['requirements']))
                <div class="mt-2 space-y-1">
                    @foreach($component['requirements'] as $rule)
                        <p class="text-xs text-on-surface/70 flex items-start gap-1">
                            @if($rule['type'] === 'incompatible_with')
                                <svg class="w-3.5 h-3.5 text-danger shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg>
                            @else
                                <svg class="w-3.5 h-3.5 text-info shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                            @endif
                            <span>{{ $rule['label'] }}: {{ $rule['components'] }}</span>
                        </p>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Quantity Selector + Add to Cart --}}
        <div class="shrink-0 flex flex-col items-end gap-2">
            @if(!$isDisabled)
                {{-- BR-161/BR-243: Quantity selector --}}
                <div class="flex items-center gap-1 bg-surface dark:bg-surface border border-outline dark:border-outline rounded-lg">
                    <button
                        @click="decrement()"
                        :disabled="quantity <= min"
                        class="w-8 h-8 flex items-center justify-center text-on-surface hover:text-on-surface-strong hover:bg-surface-alt rounded-l-lg transition-colors duration-200 disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer"
                        aria-label="{{ __('Decrease quantity') }}"
                    >
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path></svg>
                    </button>
                    <span class="w-8 text-center text-sm font-semibold text-on-surface-strong" x-text="quantity"></span>
                    <button
                        @click="increment()"
                        :disabled="quantity >= max"
                        class="w-8 h-8 flex items-center justify-center text-on-surface hover:text-on-surface-strong hover:bg-surface-alt rounded-r-lg transition-colors duration-200 disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer"
                        aria-label="{{ __('Increase quantity') }}"
                    >
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                    </button>
                </div>

                {{-- Max quantity hint --}}
                <p x-show="quantity >= max && max < 99" x-cloak class="text-xs text-warning font-medium">
                    {{ __('Only :count available', ['count' => $component['maxSelectable']]) }}
                </p>

                {{-- BR-163/BR-251: Add to Cart button (fires Gale $action via parent) --}}
                <button
                    @click="addToCart()"
                    :disabled="adding"
                    class="h-8 px-3 bg-primary hover:bg-primary-hover text-on-primary text-xs font-semibold rounded-lg shadow-sm transition-all duration-200 cursor-pointer inline-flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-wait"
                >
                    <template x-if="!adding">
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                            <span x-show="!inCart">{{ __('Add') }}</span>
                            <span x-show="inCart" x-cloak>{{ __('Update') }}</span>
                        </span>
                    </template>
                    <template x-if="adding">
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 animate-spin-slow" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                            {{ __('Adding...') }}
                        </span>
                    </template>
                </button>
            @else
                {{-- BR-162: Disabled add button for sold-out --}}
                <button
                    disabled
                    class="h-8 px-3 bg-outline/50 text-on-surface/40 text-xs font-semibold rounded-lg cursor-not-allowed inline-flex items-center gap-1.5"
                >
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg>
                    {{ __('Sold Out') }}
                </button>
            @endif
        </div>
    </div>
</div>
