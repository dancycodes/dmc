{{--
    F-129: Meal Detail View — Component Card
    BR-158: Shows name, price, unit, availability status
    BR-159: Availability: Available, Low Stock (X left), Sold Out
    BR-160: Requirement rules in plain language
    BR-161: Quantity selector with min/max limits
    BR-162: Add to Cart disabled for sold-out
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
@endphp

<div
    class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 transition-colors duration-200 {{ $isDisabled ? 'opacity-60' : '' }}"
    x-data="{
        quantity: {{ $component['minQuantity'] }},
        min: {{ $component['minQuantity'] }},
        max: {{ $component['maxSelectable'] }},
        disabled: {{ $isDisabled ? 'true' : 'false' }},
        increment() {
            if (this.quantity < this.max) this.quantity++;
        },
        decrement() {
            if (this.quantity > this.min) this.quantity--;
        }
    }"
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
                {{-- BR-161: Quantity selector --}}
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

                {{-- BR-163: Add to Cart button --}}
                <button
                    @click="$root.addToCart({{ $component['id'] }}, {{ json_encode($component['name']) }}, {{ $component['price'] }}, {{ json_encode($component['unit']) }}, quantity)"
                    class="h-8 px-3 bg-primary hover:bg-primary-hover text-on-primary text-xs font-semibold rounded-lg shadow-sm transition-all duration-200 cursor-pointer inline-flex items-center gap-1.5"
                >
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                    {{ __('Add') }}
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
