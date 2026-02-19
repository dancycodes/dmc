{{--
    F-132: Schedule & Availability Display
    BR-186: All 7 days displayed with availability status
    BR-187: Order, delivery, pickup windows per day/slot
    BR-188: Current day highlighted
    BR-189: "Available Now" badge when within active order window
    BR-190: "Next available" badge when outside order windows
    BR-191: Unavailable days clearly marked
    BR-192: Multiple slots per day with labels
    BR-193: All times in Africa/Douala timezone
    BR-194: All text localized via __()
--}}

@if($scheduleDisplay['hasSchedule'])
    {{-- Availability Badge --}}
    <div class="mb-8 flex justify-center">
        @php
            $badgeColorMap = [
                'success' => 'bg-success-subtle text-success dark:bg-success-subtle dark:text-success border-success/20',
                'warning' => 'bg-warning-subtle text-warning dark:bg-warning-subtle dark:text-warning border-warning/20',
                'danger' => 'bg-danger-subtle text-danger dark:bg-danger-subtle dark:text-danger border-danger/20',
                'info' => 'bg-info-subtle text-info dark:bg-info-subtle dark:text-info border-info/20',
            ];
            $badgeClasses = $badgeColorMap[$scheduleDisplay['availabilityBadge']['color']] ?? $badgeColorMap['info'];
        @endphp

        <div class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border {{ $badgeClasses }} font-semibold text-sm shadow-sm">
            @if($scheduleDisplay['availabilityBadge']['type'] === 'available')
                {{-- Pulsing green dot --}}
                <span class="relative flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-success"></span>
                </span>
            @elseif($scheduleDisplay['availabilityBadge']['type'] === 'closing_soon')
                {{-- Warning clock icon --}}
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            @elseif($scheduleDisplay['availabilityBadge']['type'] === 'next')
                {{-- Calendar icon --}}
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path></svg>
            @else
                {{-- Closed X icon --}}
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg>
            @endif
            {{ $scheduleDisplay['availabilityBadge']['label'] }}
        </div>
    </div>

    {{-- Desktop: 7-column horizontal card layout --}}
    <div class="hidden md:grid md:grid-cols-7 gap-3 max-w-6xl mx-auto">
        @foreach($scheduleDisplay['days'] as $dayData)
            <div class="rounded-lg border p-3 transition-all duration-200
                {{ $dayData['isToday']
                    ? 'border-primary bg-primary-subtle dark:border-primary dark:bg-primary-subtle ring-2 ring-primary/20'
                    : ($dayData['isAvailable']
                        ? 'border-outline dark:border-outline bg-surface dark:bg-surface hover:shadow-card'
                        : 'border-outline/50 dark:border-outline/50 bg-surface-alt/50 dark:bg-surface-alt/50 opacity-60')
                }}">
                {{-- Day header --}}
                <div class="text-center mb-3">
                    <span class="text-xs font-semibold uppercase tracking-wide
                        {{ $dayData['isToday'] ? 'text-primary dark:text-primary' : 'text-on-surface dark:text-on-surface' }}">
                        {{ $dayData['dayShort'] }}
                    </span>
                    @if($dayData['isToday'])
                        <div class="mt-1">
                            <span class="inline-block w-1.5 h-1.5 rounded-full bg-primary"></span>
                        </div>
                    @endif
                </div>

                @if($dayData['isAvailable'])
                    <div class="space-y-2">
                        @foreach($dayData['slots'] as $slot)
                            @if(count($dayData['slots']) > 1)
                                <p class="text-xs font-semibold text-on-surface-strong dark:text-on-surface-strong border-b border-outline/30 dark:border-outline/30 pb-1 mb-1">
                                    {{ $slot['label'] }}
                                </p>
                            @endif

                            {{-- Order window --}}
                            @if($slot['hasOrderInterval'])
                                <div class="flex items-start gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-primary dark:text-primary mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path></svg>
                                    <span class="text-xs text-on-surface dark:text-on-surface leading-tight">{{ $slot['orderInterval'] }}</span>
                                </div>
                            @endif

                            {{-- Delivery window --}}
                            @if($slot['hasDeliveryInterval'])
                                <div class="flex items-start gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-secondary dark:text-secondary mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"></path><path d="M15 18H9"></path><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path><circle cx="7" cy="18" r="2"></circle><circle cx="21" cy="18" r="2"></circle></svg>
                                    <span class="text-xs text-on-surface dark:text-on-surface leading-tight">{{ $slot['deliveryInterval'] }}</span>
                                </div>
                            @endif

                            {{-- Pickup window --}}
                            @if($slot['hasPickupInterval'])
                                <div class="flex items-start gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-info dark:text-info mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"></path><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"></path><path d="M2 7h20"></path><path d="M22 7v3a2 2 0 0 1-2 2a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 16 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 12 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 8 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 4 12a2 2 0 0 1-2-2V7"></path></svg>
                                    <span class="text-xs text-on-surface dark:text-on-surface leading-tight">{{ $slot['pickupInterval'] }}</span>
                                </div>
                            @endif

                            {{-- No intervals configured --}}
                            @if(!$slot['hasOrderInterval'] && !$slot['hasDeliveryInterval'] && !$slot['hasPickupInterval'])
                                <p class="text-xs text-on-surface/60 dark:text-on-surface/60 italic">{{ __('Hours TBD') }}</p>
                            @endif
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-2">
                        <p class="text-xs text-on-surface/50 dark:text-on-surface/50 italic">{{ __('Unavailable') }}</p>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Mobile: Expandable vertical day cards --}}
    <div class="md:hidden space-y-2 max-w-lg mx-auto"
         x-data="{ expandedDay: '{{ collect($scheduleDisplay['days'])->firstWhere('isToday', true)['day'] ?? '' }}' }">
        @foreach($scheduleDisplay['days'] as $dayData)
            <div class="rounded-lg border transition-all duration-200
                {{ $dayData['isToday']
                    ? 'border-primary dark:border-primary ring-2 ring-primary/20'
                    : ($dayData['isAvailable']
                        ? 'border-outline dark:border-outline'
                        : 'border-outline/50 dark:border-outline/50 opacity-60')
                }}">
                {{-- Day header (clickable toggle) --}}
                <button
                    @click="expandedDay = expandedDay === '{{ $dayData['day'] }}' ? '' : '{{ $dayData['day'] }}'"
                    class="w-full flex items-center justify-between px-4 py-3 cursor-pointer
                        {{ $dayData['isToday']
                            ? 'bg-primary-subtle dark:bg-primary-subtle'
                            : ($dayData['isAvailable']
                                ? 'bg-surface dark:bg-surface'
                                : 'bg-surface-alt/50 dark:bg-surface-alt/50')
                        }}
                        rounded-lg"
                    aria-expanded="false"
                    :aria-expanded="expandedDay === '{{ $dayData['day'] }}'"
                >
                    <div class="flex items-center gap-3">
                        <span class="font-semibold text-sm
                            {{ $dayData['isToday'] ? 'text-primary dark:text-primary' : 'text-on-surface-strong dark:text-on-surface-strong' }}">
                            {{ $dayData['dayLabel'] }}
                        </span>
                        @if($dayData['isToday'])
                            <span class="text-xs bg-primary text-on-primary px-2 py-0.5 rounded-full font-medium">
                                {{ __('Today') }}
                            </span>
                        @endif
                    </div>

                    <div class="flex items-center gap-2">
                        @if(!$dayData['isAvailable'])
                            <span class="text-xs text-on-surface/50 dark:text-on-surface/50 italic">{{ __('Unavailable') }}</span>
                        @else
                            <span class="text-xs text-on-surface dark:text-on-surface">
                                {{ trans_choice(':count slot|:count slots', count($dayData['slots']), ['count' => count($dayData['slots'])]) }}
                            </span>
                        @endif
                        {{-- Chevron --}}
                        <svg class="w-4 h-4 text-on-surface/50 dark:text-on-surface/50 transition-transform duration-200"
                             :class="expandedDay === '{{ $dayData['day'] }}' ? 'rotate-180' : ''"
                             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
                    </div>
                </button>

                {{-- Expandable content --}}
                @if($dayData['isAvailable'])
                    <div x-show="expandedDay === '{{ $dayData['day'] }}'"
                         x-transition:enter="transition-all ease-out duration-200"
                         x-transition:enter-start="opacity-0 max-h-0"
                         x-transition:enter-end="opacity-100 max-h-96"
                         x-transition:leave="transition-all ease-in duration-150"
                         x-transition:leave-start="opacity-100 max-h-96"
                         x-transition:leave-end="opacity-0 max-h-0"
                         class="px-4 pb-4 overflow-hidden">
                        <div class="space-y-3 pt-2">
                            @foreach($dayData['slots'] as $slot)
                                @if(count($dayData['slots']) > 1)
                                    <div class="border-b border-outline/30 dark:border-outline/30 pb-1 mb-1">
                                        <p class="text-sm font-semibold text-on-surface-strong dark:text-on-surface-strong">
                                            {{ $slot['label'] }}
                                        </p>
                                    </div>
                                @endif

                                <div class="space-y-2">
                                    {{-- Order window --}}
                                    @if($slot['hasOrderInterval'])
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full bg-primary-subtle dark:bg-primary-subtle flex items-center justify-center shrink-0">
                                                <svg class="w-3.5 h-3.5 text-primary dark:text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path></svg>
                                            </div>
                                            <div>
                                                <p class="text-xs font-medium text-on-surface/70 dark:text-on-surface/70">{{ __('Order') }}</p>
                                                <p class="text-sm text-on-surface-strong dark:text-on-surface-strong">{{ $slot['orderInterval'] }}</p>
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Delivery window --}}
                                    @if($slot['hasDeliveryInterval'])
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full bg-secondary-subtle dark:bg-secondary-subtle flex items-center justify-center shrink-0">
                                                <svg class="w-3.5 h-3.5 text-secondary dark:text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"></path><path d="M15 18H9"></path><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path><circle cx="7" cy="18" r="2"></circle><circle cx="21" cy="18" r="2"></circle></svg>
                                            </div>
                                            <div>
                                                <p class="text-xs font-medium text-on-surface/70 dark:text-on-surface/70">{{ __('Delivery') }}</p>
                                                <p class="text-sm text-on-surface-strong dark:text-on-surface-strong">{{ $slot['deliveryInterval'] }}</p>
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Pickup window --}}
                                    @if($slot['hasPickupInterval'])
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full bg-info-subtle dark:bg-info-subtle flex items-center justify-center shrink-0">
                                                <svg class="w-3.5 h-3.5 text-info dark:text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"></path><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"></path><path d="M2 7h20"></path><path d="M22 7v3a2 2 0 0 1-2 2a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 16 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 12 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 8 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 4 12a2 2 0 0 1-2-2V7"></path></svg>
                                            </div>
                                            <div>
                                                <p class="text-xs font-medium text-on-surface/70 dark:text-on-surface/70">{{ __('Pickup') }}</p>
                                                <p class="text-sm text-on-surface-strong dark:text-on-surface-strong">{{ $slot['pickupInterval'] }}</p>
                                            </div>
                                        </div>
                                    @endif

                                    {{-- No intervals configured --}}
                                    @if(!$slot['hasOrderInterval'] && !$slot['hasDeliveryInterval'] && !$slot['hasPickupInterval'])
                                        <p class="text-sm text-on-surface/60 dark:text-on-surface/60 italic">{{ __('Hours TBD') }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Legend --}}
    <div class="mt-6 flex flex-wrap items-center justify-center gap-4 text-xs text-on-surface/60 dark:text-on-surface/60">
        <div class="flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-primary dark:text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path></svg>
            <span>{{ __('Order') }}</span>
        </div>
        <div class="flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-secondary dark:text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"></path><path d="M15 18H9"></path><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path><circle cx="7" cy="18" r="2"></circle><circle cx="21" cy="18" r="2"></circle></svg>
            <span>{{ __('Delivery') }}</span>
        </div>
        <div class="flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-info dark:text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"></path><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"></path><path d="M2 7h20"></path><path d="M22 7v3a2 2 0 0 1-2 2a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 16 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 12 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 8 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 4 12a2 2 0 0 1-2-2V7"></path></svg>
            <span>{{ __('Pickup') }}</span>
        </div>
    </div>

    {{-- Timezone note --}}
    <p class="mt-3 text-center text-xs text-on-surface/40 dark:text-on-surface/40">
        {{ $scheduleDisplay['timezoneNote'] }}
    </p>
@else
    {{-- Edge case: No schedules configured --}}
    <div class="text-center py-8">
        <div class="w-16 h-16 rounded-full bg-surface-alt dark:bg-surface-alt flex items-center justify-center mx-auto mb-4 border border-outline dark:border-outline">
            <svg class="w-8 h-8 text-on-surface opacity-40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path></svg>
        </div>
        <p class="text-on-surface opacity-60">
            {{ __('Schedule not yet available. Contact the cook for ordering information.') }}
        </p>
    </div>
@endif
