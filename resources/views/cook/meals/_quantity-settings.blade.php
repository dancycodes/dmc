{{--
    Meal Component Quantity Settings
    --------------------------------
    F-124: Meal Component Quantity Settings

    Displays and manages quantity constraints for a meal component:
    - Minimum order quantity (BR-334)
    - Maximum order quantity (BR-335)
    - Available quantity / stock (BR-336)
    - Low stock warning (under 5 remaining)
    - Auto-unavailable when stock depletes (BR-337)

    Business Rules:
    BR-334: Minimum quantity default is 0 (component is optional)
    BR-335: Maximum quantity default is null (unlimited)
    BR-336: Available quantity default is null (unlimited)
    BR-337: Auto-toggle unavailable when available qty reaches 0
    BR-339: Min quantity >= 0
    BR-340: Max quantity >= min quantity when both set
    BR-341: Available quantity >= 0
    BR-345: Quantity changes take immediate effect
    BR-346: Only users with manage-meals permission
    BR-347: Quantity changes logged via Spatie Activitylog
--}}
@php
    $stockStatus = $componentStockStatus[$component->id] ?? ['label' => __('Unlimited'), 'type' => 'unlimited'];
@endphp
<div class="mt-2 pt-2 border-t border-outline/30 dark:border-outline/30">
    {{-- Stock Status Badge + Expand Toggle --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
            {{-- Stock status indicator --}}
            @if($stockStatus['type'] === 'out_of_stock')
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-danger-subtle text-danger">
                    {{-- Lucide: package-x (xs=14) --}}
                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l2-1.14"/><path d="m7.5 4.27 9 5.15"/><path d="M3.29 7 12 12l8.71-5"/><path d="M12 22V12"/><path d="m17 13 5 5m-5 0 5-5"/></svg>
                    {{ $stockStatus['label'] }}
                </span>
            @elseif($stockStatus['type'] === 'low_stock')
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-warning-subtle text-warning">
                    {{-- Lucide: alert-triangle (xs=14) --}}
                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                    {{ $stockStatus['label'] }}
                </span>
            @elseif($stockStatus['type'] === 'in_stock')
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-success-subtle text-success">
                    {{-- Lucide: package-check (xs=14) --}}
                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 16 2 2 4-4"/><path d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l2-1.14"/><path d="m7.5 4.27 9 5.15"/><path d="M3.29 7 12 12l8.71-5"/><path d="M12 22V12"/></svg>
                    {{ $stockStatus['label'] }}
                </span>
            @else
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-info-subtle text-info">
                    {{-- Lucide: infinity (xs=14) --}}
                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 12c-2-2.67-4-4-6-4a4 4 0 1 0 0 8c2 0 4-1.33 6-4Zm0 0c2 2.67 4 4 6 4a4 4 0 0 0 0-8c-2 0-4 1.33-6 4Z"/></svg>
                    {{ $stockStatus['label'] }}
                </span>
            @endif

            {{-- Quantity constraints summary --}}
            <span class="text-[10px] text-on-surface/50">
                @if($component->min_quantity > 0 || !$component->hasUnlimitedMaxQuantity())
                    ({{ $component->min_quantity > 0 ? __('Min :qty', ['qty' => $component->min_quantity]) : '' }}{{ $component->min_quantity > 0 && !$component->hasUnlimitedMaxQuantity() ? ' · ' : '' }}{{ !$component->hasUnlimitedMaxQuantity() ? __('Max :qty', ['qty' => $component->max_quantity]) : '' }})
                @endif
            </span>
        </div>

        {{-- Expand/collapse toggle --}}
        <button
            type="button"
            @click="showQtySettings_{{ $component->id }} = !showQtySettings_{{ $component->id }}"
            class="text-[10px] font-medium text-primary hover:text-primary-hover transition-colors duration-200 flex items-center gap-1"
        >
            <span x-text="showQtySettings_{{ $component->id }} ? '{{ __('Hide') }}' : '{{ __('Quantity') }}'"></span>
            {{-- Lucide: chevron-down (xs=14) --}}
            <svg
                class="w-3 h-3 transition-transform duration-200"
                :class="showQtySettings_{{ $component->id }} ? 'rotate-180' : ''"
                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
            ><path d="m6 9 6 6 6-6"/></svg>
        </button>
    </div>

    {{-- Quantity Settings Form (collapsible) --}}
    <div
        x-show="showQtySettings_{{ $component->id }}"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-1"
        class="mt-3 p-3 rounded-lg border border-outline/50 dark:border-outline/50 bg-surface-alt/50 dark:bg-surface-alt/50"
    >
        <div class="flex items-center gap-2 mb-3">
            {{-- Lucide: settings (sm=16) --}}
            <svg class="w-4 h-4 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
            <h5 class="text-xs font-semibold text-on-surface-strong">{{ __('Quantity Settings') }}</h5>
        </div>

        <form @submit.prevent="$action('{{ url('/dashboard/meals/' . $meal->id . '/components/' . $component->id . '/quantity') }}', { method: 'PATCH' })">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                {{-- Min Quantity --}}
                <div>
                    <label for="qty_min_quantity_{{ $component->id }}" class="block text-[11px] font-medium text-on-surface-strong mb-1">
                        {{ __('Min Order Qty') }}
                    </label>
                    <input
                        type="number"
                        id="qty_min_quantity_{{ $component->id }}"
                        x-model.number="qty_min_quantity_{{ $component->id }}"
                        x-name="qty_min_quantity"
                        min="0"
                        step="1"
                        placeholder="{{ __('0 (optional)') }}"
                        class="w-full px-2.5 py-1.5 rounded-md border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200 text-xs"
                    >
                    <p class="text-[10px] text-on-surface/50 mt-0.5">{{ __('Minimum per order') }}</p>
                    <p x-message="qty_min_quantity" class="text-[10px] text-danger mt-0.5"></p>
                </div>

                {{-- Max Quantity --}}
                <div>
                    <label for="qty_max_quantity_{{ $component->id }}" class="block text-[11px] font-medium text-on-surface-strong mb-1">
                        {{ __('Max Order Qty') }}
                    </label>
                    <input
                        type="number"
                        id="qty_max_quantity_{{ $component->id }}"
                        x-model.number="qty_max_quantity_{{ $component->id }}"
                        x-name="qty_max_quantity"
                        min="1"
                        step="1"
                        placeholder="{{ __('Unlimited') }}"
                        class="w-full px-2.5 py-1.5 rounded-md border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200 text-xs"
                    >
                    <p class="text-[10px] text-on-surface/50 mt-0.5">{{ __('Maximum per order') }}</p>
                    <p x-message="qty_max_quantity" class="text-[10px] text-danger mt-0.5"></p>
                </div>

                {{-- Available Quantity --}}
                <div>
                    <label for="qty_available_quantity_{{ $component->id }}" class="block text-[11px] font-medium text-on-surface-strong mb-1">
                        {{ __('Stock Available') }}
                    </label>
                    <input
                        type="number"
                        id="qty_available_quantity_{{ $component->id }}"
                        x-model.number="qty_available_quantity_{{ $component->id }}"
                        x-name="qty_available_quantity"
                        min="0"
                        step="1"
                        placeholder="{{ __('Unlimited') }}"
                        class="w-full px-2.5 py-1.5 rounded-md border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200 text-xs"
                    >
                    <p class="text-[10px] text-on-surface/50 mt-0.5">{{ __('Total units in stock') }}</p>
                    <p x-message="qty_available_quantity" class="text-[10px] text-danger mt-0.5"></p>
                </div>
            </div>

            {{-- Info note about auto-unavailable --}}
            <p class="text-[10px] text-on-surface/50 mt-2 mb-2">
                {{-- Lucide: info (xs=14) --}}
                <svg class="w-3 h-3 inline-block mr-0.5 -mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                {{ __('Leave fields empty for unlimited. Component becomes unavailable when stock reaches 0.') }}
            </p>

            {{-- Submit button --}}
            <div class="flex items-center justify-end">
                <button
                    type="submit"
                    class="px-3 py-1 rounded-md text-[11px] font-medium bg-primary text-on-primary hover:bg-primary-hover shadow-sm transition-colors duration-200 flex items-center gap-1.5"
                >
                    <span x-show="!$fetching()">
                        {{-- Lucide: save (xs=14) --}}
                        <svg class="w-3.5 h-3.5 inline-block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                        {{ __('Save Quantities') }}
                    </span>
                    <span x-show="$fetching()" x-cloak class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        {{ __('Saving...') }}
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>
