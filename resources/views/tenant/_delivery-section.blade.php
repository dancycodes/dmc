{{--
    F-133: Delivery Areas & Fees Display
    BR-195: Delivery areas organized hierarchically: town > quarters with fees
    BR-196: Fee of 0 shows "Free delivery"
    BR-197: Quarters with same group fee visually grouped
    BR-198: Pickup locations listed separately with full address
    BR-199: Pickup always labeled "Free"
    BR-200: Fallback message for unlisted areas with WhatsApp contact
    BR-201: Towns expandable/collapsible (all expanded on desktop)
    BR-202: All text localized via __()
    BR-203: Town/quarter names in user's current language
--}}

@if($deliveryDisplay['hasDeliveryAreas'])
    <div class="max-w-3xl mx-auto space-y-6">
        {{-- Delivery Towns --}}
        <div>
            <h3 class="text-lg font-semibold text-on-surface-strong dark:text-on-surface-strong mb-4 flex items-center gap-2">
                {{-- Truck icon (Lucide) --}}
                <svg class="w-5 h-5 text-primary dark:text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"></path><path d="M15 18H9"></path><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path><circle cx="7" cy="18" r="2"></circle><circle cx="21" cy="18" r="2"></circle></svg>
                {{ __('Delivery Areas') }}
            </h3>

            {{-- Desktop: All towns expanded --}}
            <div class="hidden md:block space-y-4">
                @foreach($deliveryDisplay['towns'] as $town)
                    <div class="bg-surface dark:bg-surface rounded-lg border border-outline dark:border-outline shadow-card overflow-hidden">
                        {{-- Town header --}}
                        <div class="px-5 py-3 bg-surface-alt dark:bg-surface-alt border-b border-outline dark:border-outline">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    {{-- Map pin icon --}}
                                    <svg class="w-4 h-4 text-primary dark:text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                    <h4 class="font-semibold text-on-surface-strong dark:text-on-surface-strong">
                                        {{ $town['name'] }}
                                    </h4>
                                </div>
                                <span class="text-xs text-on-surface dark:text-on-surface bg-surface dark:bg-surface px-2 py-1 rounded-full border border-outline dark:border-outline">
                                    {{ trans_choice(':count quarter|:count quarters', $town['quarterCount'], ['count' => $town['quarterCount']]) }}
                                </span>
                            </div>
                        </div>

                        {{-- Quarter list --}}
                        <div class="divide-y divide-outline/50 dark:divide-outline/50">
                            @php
                                $groupedQuarters = collect($town['quarters'])->groupBy('groupId');
                            @endphp

                            @foreach($groupedQuarters as $groupId => $quartersInGroup)
                                @if($groupId && $quartersInGroup->count() > 1)
                                    {{-- BR-197: Grouped quarters visual --}}
                                    <div class="px-5 py-3">
                                        <div class="flex items-center gap-2 mb-2">
                                            {{-- Group icon --}}
                                            <svg class="w-3.5 h-3.5 text-secondary dark:text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"></rect><path d="M3 9h18"></path><path d="M9 21V9"></path></svg>
                                            <span class="text-xs font-medium text-secondary dark:text-secondary">
                                                {{ $quartersInGroup->first()['groupName'] }}
                                            </span>
                                            <span class="text-xs font-bold
                                                {{ $quartersInGroup->first()['isFree'] ? 'text-success dark:text-success' : 'text-on-surface-strong dark:text-on-surface-strong' }}">
                                                {{ $quartersInGroup->first()['formattedFee'] }}
                                            </span>
                                        </div>
                                        <div class="flex flex-wrap gap-2 pl-5">
                                            @foreach($quartersInGroup as $quarter)
                                                <span class="text-sm text-on-surface dark:text-on-surface bg-surface-alt dark:bg-surface-alt px-2.5 py-1 rounded-md border border-outline/50 dark:border-outline/50">
                                                    {{ $quarter['name'] }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    {{-- Individual quarter rows --}}
                                    @foreach($quartersInGroup as $quarter)
                                        <div class="flex items-center justify-between px-5 py-3">
                                            <span class="text-sm text-on-surface dark:text-on-surface truncate max-w-[60%]">
                                                {{ $quarter['name'] }}
                                            </span>
                                            <span class="text-sm font-bold shrink-0
                                                {{ $quarter['isFree'] ? 'text-success dark:text-success' : 'text-on-surface-strong dark:text-on-surface-strong' }}">
                                                {{ $quarter['formattedFee'] }}
                                            </span>
                                        </div>
                                    @endforeach
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Mobile: Collapsible accordion --}}
            <div class="md:hidden space-y-2"
                 x-data="{ expandedTown: {{ count($deliveryDisplay['towns']) === 1 ? "'" . addslashes($deliveryDisplay['towns'][0]['name']) . "'" : "''" }} }">
                @foreach($deliveryDisplay['towns'] as $townIndex => $town)
                    <div class="rounded-lg border border-outline dark:border-outline overflow-hidden">
                        {{-- Town toggle button --}}
                        <button
                            x-on:click="expandedTown = expandedTown === '{{ addslashes($town['name']) }}' ? '' : '{{ addslashes($town['name']) }}'"
                            class="w-full flex items-center justify-between px-4 py-3 bg-surface dark:bg-surface hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors duration-200 cursor-pointer"
                            aria-expanded="false"
                            :aria-expanded="expandedTown === '{{ addslashes($town['name']) }}'"
                        >
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-primary dark:text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                <span class="font-semibold text-sm text-on-surface-strong dark:text-on-surface-strong">
                                    {{ $town['name'] }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-on-surface dark:text-on-surface">
                                    {{ trans_choice(':count quarter|:count quarters', $town['quarterCount'], ['count' => $town['quarterCount']]) }}
                                </span>
                                <svg class="w-4 h-4 text-on-surface/50 dark:text-on-surface/50 transition-transform duration-200"
                                     :class="expandedTown === '{{ addslashes($town['name']) }}' ? 'rotate-180' : ''"
                                     xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
                            </div>
                        </button>

                        {{-- Expandable quarter list --}}
                        <div x-show="expandedTown === '{{ addslashes($town['name']) }}'"
                             x-transition:enter="transition-all ease-out duration-200"
                             x-transition:enter-start="opacity-0 max-h-0"
                             x-transition:enter-end="opacity-100 max-h-[500px]"
                             x-transition:leave="transition-all ease-in duration-150"
                             x-transition:leave-start="opacity-100 max-h-[500px]"
                             x-transition:leave-end="opacity-0 max-h-0"
                             class="overflow-hidden border-t border-outline dark:border-outline">
                            <div class="divide-y divide-outline/50 dark:divide-outline/50">
                                @php
                                    $groupedQuarters = collect($town['quarters'])->groupBy('groupId');
                                @endphp

                                @foreach($groupedQuarters as $groupId => $quartersInGroup)
                                    @if($groupId && $quartersInGroup->count() > 1)
                                        <div class="px-4 py-3">
                                            <div class="flex items-center gap-2 mb-2">
                                                <svg class="w-3.5 h-3.5 text-secondary dark:text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"></rect><path d="M3 9h18"></path><path d="M9 21V9"></path></svg>
                                                <span class="text-xs font-medium text-secondary dark:text-secondary">
                                                    {{ $quartersInGroup->first()['groupName'] }}
                                                </span>
                                                <span class="text-xs font-bold
                                                    {{ $quartersInGroup->first()['isFree'] ? 'text-success dark:text-success' : 'text-on-surface-strong dark:text-on-surface-strong' }}">
                                                    {{ $quartersInGroup->first()['formattedFee'] }}
                                                </span>
                                            </div>
                                            <div class="flex flex-wrap gap-1.5 pl-5">
                                                @foreach($quartersInGroup as $quarter)
                                                    <span class="text-xs text-on-surface dark:text-on-surface bg-surface-alt dark:bg-surface-alt px-2 py-0.5 rounded border border-outline/50 dark:border-outline/50">
                                                        {{ $quarter['name'] }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @else
                                        @foreach($quartersInGroup as $quarter)
                                            <div class="flex items-center justify-between px-4 py-3">
                                                <span class="text-sm text-on-surface dark:text-on-surface truncate max-w-[60%]">
                                                    {{ $quarter['name'] }}
                                                </span>
                                                <span class="text-sm font-bold shrink-0
                                                    {{ $quarter['isFree'] ? 'text-success dark:text-success' : 'text-on-surface-strong dark:text-on-surface-strong' }}">
                                                    {{ $quarter['formattedFee'] }}
                                                </span>
                                            </div>
                                        @endforeach
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Pickup Locations --}}
        @if($deliveryDisplay['hasPickupLocations'])
            <div class="mt-8">
                <h3 class="text-lg font-semibold text-on-surface-strong dark:text-on-surface-strong mb-4 flex items-center gap-2">
                    {{-- Store/building icon (Lucide) --}}
                    <svg class="w-5 h-5 text-info dark:text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"></path><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"></path><path d="M2 7h20"></path><path d="M22 7v3a2 2 0 0 1-2 2a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 16 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 12 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 8 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 4 12a2 2 0 0 1-2-2V7"></path></svg>
                    {{ __('Pickup Locations') }}
                </h3>

                <div class="space-y-3">
                    @foreach($deliveryDisplay['pickupLocations'] as $pickup)
                        <div class="bg-surface dark:bg-surface rounded-lg border border-outline dark:border-outline p-4 flex items-start gap-3 shadow-sm">
                            {{-- Map pin icon --}}
                            <div class="w-9 h-9 rounded-full bg-info-subtle dark:bg-info-subtle flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="w-4 h-4 text-info dark:text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-on-surface-strong dark:text-on-surface-strong">
                                    {{ $pickup['name'] }}
                                </p>
                                <p class="text-xs text-on-surface dark:text-on-surface mt-1 truncate">
                                    {{ $pickup['fullAddress'] }}
                                </p>
                            </div>
                            {{-- BR-199: Pickup is always free --}}
                            <span class="text-xs font-bold text-success dark:text-success bg-success-subtle dark:bg-success-subtle px-2.5 py-1 rounded-full shrink-0">
                                {{ __('Free') }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- BR-200: Fallback message for unlisted areas --}}
        <div class="mt-6 bg-surface dark:bg-surface rounded-lg border border-outline dark:border-outline p-4 text-center">
            <p class="text-sm text-on-surface dark:text-on-surface">
                {{ __("Don't see your area? Contact the cook to ask about delivery to your location.") }}
            </p>
            @if($deliveryDisplay['whatsappLink'])
                <a href="{{ $deliveryDisplay['whatsappLink'] }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="inline-flex items-center gap-2 mt-3 px-4 py-2 bg-success hover:bg-success/90 text-on-success rounded-lg text-sm font-medium transition-colors duration-200">
                    {{-- WhatsApp / phone icon --}}
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                    {{ __('Contact via WhatsApp') }}
                </a>
            @endif
        </div>
    </div>
@else
    {{-- Edge case: No delivery areas configured --}}
    <div class="text-center py-8">
        <div class="w-16 h-16 rounded-full bg-surface dark:bg-surface flex items-center justify-center mx-auto mb-4 border border-outline dark:border-outline">
            <svg class="w-8 h-8 text-on-surface opacity-40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
        </div>
        <p class="text-on-surface opacity-60">
            {{ __('Delivery areas not yet configured. Contact the cook.') }}
        </p>
        @if($deliveryDisplay['whatsappLink'])
            <a href="{{ $deliveryDisplay['whatsappLink'] }}"
               target="_blank"
               rel="noopener noreferrer"
               class="inline-flex items-center gap-2 mt-3 px-4 py-2 bg-success hover:bg-success/90 text-on-success rounded-lg text-sm font-medium transition-colors duration-200">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                {{ __('Contact via WhatsApp') }}
            </a>
        @endif
    </div>
@endif
