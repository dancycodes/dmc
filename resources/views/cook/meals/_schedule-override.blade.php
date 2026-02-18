{{--
    Meal Schedule Override
    ----------------------
    F-106: Meal Schedule Override

    Allows cooks to toggle between using the cook's default schedule
    or a custom per-meal schedule. When using cook's schedule, displays
    a read-only summary. When using custom schedule, provides full
    schedule CRUD (same UI as F-098/F-099/F-100 but meal-scoped).

    Business Rules:
    BR-162: Meals inherit cook's schedule by default
    BR-163: Optional custom schedule completely overrides cook's schedule
    BR-164: Binary toggle — cook's schedule or custom schedule
    BR-166: Same rules as cook schedule (F-098/F-099/F-100)
    BR-167: Reverting deletes all custom entries
    BR-168: Confirmation dialog before reverting
    BR-170: Changes logged via Spatie Activitylog
    BR-171: Requires can-manage-meals + can-manage-schedules
--}}
<div
    class="bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-xl shadow-card p-6 mt-6"
    x-data="{
        useCustomSchedule: {{ $scheduleData['hasCustomSchedule'] ? 'true' : 'false' }},
        showRevertConfirm: false,
        showAddForm: false,
        day_of_week: '',
        is_available: 'true',
        label: '',
        expandedIntervalId: null,
        expandedDeliveryPickupId: null,
        order_start_time: '06:00',
        order_start_day_offset: '0',
        order_end_time: '10:00',
        order_end_day_offset: '0',
        delivery_enabled: 'false',
        delivery_start_time: '11:00',
        delivery_end_time: '14:00',
        pickup_enabled: 'false',
        pickup_start_time: '10:30',
        pickup_end_time: '15:00',

        toggleInterval(entryId, startTime, startOffset, endTime, endOffset) {
            if (this.expandedIntervalId === entryId) {
                this.expandedIntervalId = null;
                return;
            }
            this.expandedIntervalId = entryId;
            this.expandedDeliveryPickupId = null;
            this.order_start_time = startTime || '06:00';
            this.order_start_day_offset = String(startOffset ?? 0);
            this.order_end_time = endTime || '10:00';
            this.order_end_day_offset = String(endOffset ?? 0);
        },

        toggleDeliveryPickup(entryId, deliveryEnabled, deliveryStart, deliveryEnd, pickupEnabled, pickupStart, pickupEnd) {
            if (this.expandedDeliveryPickupId === entryId) {
                this.expandedDeliveryPickupId = null;
                return;
            }
            this.expandedDeliveryPickupId = entryId;
            this.expandedIntervalId = null;
            this.delivery_enabled = deliveryEnabled ? 'true' : 'false';
            this.delivery_start_time = deliveryStart || '11:00';
            this.delivery_end_time = deliveryEnd || '14:00';
            this.pickup_enabled = pickupEnabled ? 'true' : 'false';
            this.pickup_start_time = pickupStart || '10:30';
            this.pickup_end_time = pickupEnd || '15:00';
        },

        getIntervalPreview() {
            if (!this.order_start_time || !this.order_end_time) return '';
            const startLabel = this.formatDayOffset(parseInt(this.order_start_day_offset));
            const endLabel = this.formatDayOffset(parseInt(this.order_end_day_offset));
            return this.formatTime(this.order_start_time) + ' ' + startLabel + ' {{ __('to') }} ' + this.formatTime(this.order_end_time) + ' ' + endLabel;
        },

        getDeliveryPreview() {
            if (this.delivery_enabled !== 'true' || !this.delivery_start_time || !this.delivery_end_time) return '';
            return this.formatTime(this.delivery_start_time) + ' {{ __('to') }} ' + this.formatTime(this.delivery_end_time);
        },

        getPickupPreview() {
            if (this.pickup_enabled !== 'true' || !this.pickup_start_time || !this.pickup_end_time) return '';
            return this.formatTime(this.pickup_start_time) + ' {{ __('to') }} ' + this.formatTime(this.pickup_end_time);
        },

        formatTime(timeStr) {
            if (!timeStr) return '';
            const [h, m] = timeStr.split(':').map(Number);
            const ampm = h >= 12 ? 'PM' : 'AM';
            const hour12 = h % 12 || 12;
            return hour12 + ':' + String(m).padStart(2, '0') + ' ' + ampm;
        },

        formatDayOffset(offset) {
            if (offset === 0) return '{{ __('same day') }}';
            if (offset === 1) return '{{ __('day before') }}';
            return offset + ' {{ __('days before') }}';
        }
    }"
    x-sync="['day_of_week', 'is_available', 'label', 'order_start_time', 'order_start_day_offset', 'order_end_time', 'order_end_day_offset', 'delivery_enabled', 'delivery_start_time', 'delivery_end_time', 'pickup_enabled', 'pickup_start_time', 'pickup_end_time']"
>
    {{-- Section Header --}}
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <span class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center">
                <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path></svg>
            </span>
            <div>
                <h3 class="text-base font-semibold text-on-surface-strong">{{ __('Schedule') }}</h3>
                <p class="text-xs text-on-surface/60">
                    {{ $scheduleData['hasCustomSchedule'] ? __('Using custom schedule') : __('Using cook\'s schedule') }}
                </p>
            </div>
        </div>
    </div>

    {{-- Toggle: Use cook's schedule vs Use custom schedule --}}
    <div class="mb-5 p-4 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface">
        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
            <label class="flex items-center gap-3 cursor-pointer flex-1">
                <input
                    type="radio"
                    name="schedule_mode"
                    value="default"
                    {{ !$scheduleData['hasCustomSchedule'] ? 'checked' : '' }}
                    @click="if (useCustomSchedule) { showRevertConfirm = true; } else { useCustomSchedule = false; }"
                    :checked="!useCustomSchedule"
                    class="w-4 h-4 text-primary border-outline focus:ring-primary/50"
                >
                <div>
                    <span class="text-sm font-medium text-on-surface-strong">{{ __('Use cook\'s schedule') }}</span>
                    <p class="text-xs text-on-surface/60">{{ __('This meal follows the cook\'s default schedule.') }}</p>
                </div>
            </label>
            <label class="flex items-center gap-3 cursor-pointer flex-1">
                <input
                    type="radio"
                    name="schedule_mode"
                    value="custom"
                    {{ $scheduleData['hasCustomSchedule'] ? 'checked' : '' }}
                    @click="useCustomSchedule = true"
                    :checked="useCustomSchedule"
                    class="w-4 h-4 text-primary border-outline focus:ring-primary/50"
                >
                <div>
                    <span class="text-sm font-medium text-on-surface-strong">{{ __('Use custom schedule') }}</span>
                    <p class="text-xs text-on-surface/60">{{ __('Configure a schedule specific to this meal.') }}</p>
                </div>
            </label>
        </div>
    </div>

    {{-- BR-168: Revert Confirmation Modal --}}
    <div
        x-show="showRevertConfirm"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        x-cloak
        role="dialog"
        aria-modal="true"
    >
        <div
            x-show="showRevertConfirm"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @click.outside="showRevertConfirm = false"
            class="bg-surface-alt dark:bg-surface-alt rounded-xl shadow-lg max-w-md w-full p-6 border border-outline dark:border-outline"
        >
            <div class="flex items-start gap-4">
                <span class="w-10 h-10 rounded-full bg-danger-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                </span>
                <div class="flex-1">
                    <h4 class="text-base font-semibold text-on-surface-strong mb-1">{{ __('Revert to cook\'s schedule?') }}</h4>
                    <p class="text-sm text-on-surface/70">
                        {{ __('Switching back will delete the custom schedule for this meal. All custom schedule entries will be permanently removed. This action cannot be undone.') }}
                    </p>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 mt-6">
                <button
                    type="button"
                    @click="showRevertConfirm = false"
                    class="px-4 py-2 rounded-lg text-sm font-medium text-on-surface hover:bg-surface border border-outline transition-colors duration-200"
                >
                    {{ __('Cancel') }}
                </button>
                <button
                    type="button"
                    @click="showRevertConfirm = false; $action('{{ url('/dashboard/meals/' . $meal->id . '/schedule/revert') }}', { method: 'DELETE' })"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-danger hover:bg-danger/90 text-on-danger rounded-lg text-sm font-medium transition-colors duration-200 shadow-sm"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                    {{ __('Revert to cook\'s schedule') }}
                </button>
            </div>
        </div>
    </div>

    {{-- DEFAULT: Read-only cook's schedule summary --}}
    <div x-show="!useCustomSchedule" x-transition>
        @php
            $cookSchedulesByDay = $scheduleData['cookSchedulesByDay'] ?? [];
            $cookSummary = $scheduleData['cookSummary'] ?? ['total' => 0, 'available' => 0, 'unavailable' => 0, 'days_covered' => 0];
            $hasCookSchedules = collect($cookSchedulesByDay)->flatten()->isNotEmpty();
        @endphp

        @if(!$hasCookSchedules)
            <div class="p-6 text-center rounded-lg bg-surface dark:bg-surface border border-outline/50">
                <svg class="w-8 h-8 text-on-surface/30 mx-auto mb-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path></svg>
                <p class="text-sm text-on-surface/60">{{ __('The cook has not set up a default schedule yet.') }}</p>
                <p class="text-xs text-on-surface/40 mt-1">{{ __('Go to the Schedule page to configure the cook\'s default schedule.') }}</p>
            </div>
        @else
            {{-- Cook schedule summary --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                <div class="p-3 rounded-lg bg-surface dark:bg-surface border border-outline/50 text-center">
                    <p class="text-lg font-bold text-on-surface-strong">{{ $cookSummary['total'] }}</p>
                    <p class="text-xs text-on-surface/60">{{ __('Entries') }}</p>
                </div>
                <div class="p-3 rounded-lg bg-surface dark:bg-surface border border-outline/50 text-center">
                    <p class="text-lg font-bold text-success">{{ $cookSummary['available'] }}</p>
                    <p class="text-xs text-on-surface/60">{{ __('Available') }}</p>
                </div>
                <div class="p-3 rounded-lg bg-surface dark:bg-surface border border-outline/50 text-center">
                    <p class="text-lg font-bold text-warning">{{ $cookSummary['unavailable'] }}</p>
                    <p class="text-xs text-on-surface/60">{{ __('Unavailable') }}</p>
                </div>
                <div class="p-3 rounded-lg bg-surface dark:bg-surface border border-outline/50 text-center">
                    <p class="text-lg font-bold text-on-surface-strong">{{ $cookSummary['days_covered'] }}<span class="text-sm font-normal text-on-surface/40">/7</span></p>
                    <p class="text-xs text-on-surface/60">{{ __('Days') }}</p>
                </div>
            </div>

            {{-- Day-by-day read-only listing --}}
            <div class="space-y-2">
                @foreach(\App\Models\MealSchedule::DAYS_OF_WEEK as $day)
                    @php $entries = $cookSchedulesByDay[$day] ?? []; @endphp
                    @if(count($entries) > 0)
                        <div class="p-3 rounded-lg border border-outline/50 bg-surface dark:bg-surface">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="w-6 h-6 rounded-full bg-primary-subtle text-primary flex items-center justify-center text-xs font-bold">
                                    {{ mb_strtoupper(mb_substr(__(\App\Models\MealSchedule::DAY_LABELS[$day]), 0, 2)) }}
                                </span>
                                <span class="text-sm font-medium text-on-surface-strong">{{ __(\App\Models\MealSchedule::DAY_LABELS[$day]) }}</span>
                            </div>
                            @foreach($entries as $entry)
                                <div class="flex items-center gap-2 ml-8 mt-1 text-xs text-on-surface/60">
                                    @if($entry->is_available)
                                        <span class="w-1.5 h-1.5 rounded-full bg-success shrink-0"></span>
                                    @else
                                        <span class="w-1.5 h-1.5 rounded-full bg-warning shrink-0"></span>
                                    @endif
                                    <span>{{ $entry->display_label }}</span>
                                    @if($entry->hasOrderInterval())
                                        <span class="text-info">{{ $entry->order_interval_summary }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>

            <p class="mt-3 text-xs text-on-surface/40 italic">
                {{ __('This is a read-only view of the cook\'s schedule. Changes to the cook\'s schedule automatically apply to this meal.') }}
            </p>
        @endif
    </div>

    {{-- CUSTOM: Editable meal-specific schedule --}}
    <div x-show="useCustomSchedule" x-transition>
        @php
            $mealSchedulesByDay = $scheduleData['schedulesByDay'] ?? [];
            $mealSummary = $scheduleData['summary'] ?? ['total' => 0, 'available' => 0, 'unavailable' => 0, 'days_covered' => 0];
            $hasAnyMealSchedules = collect($mealSchedulesByDay)->flatten()->isNotEmpty();
        @endphp

        {{-- Custom schedule summary cards --}}
        @if($hasAnyMealSchedules)
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                <div class="p-3 rounded-lg bg-surface dark:bg-surface border border-outline/50 text-center">
                    <p class="text-lg font-bold text-on-surface-strong">{{ $mealSummary['total'] }}</p>
                    <p class="text-xs text-on-surface/60">{{ __('Entries') }}</p>
                </div>
                <div class="p-3 rounded-lg bg-surface dark:bg-surface border border-outline/50 text-center">
                    <p class="text-lg font-bold text-success">{{ $mealSummary['available'] }}</p>
                    <p class="text-xs text-on-surface/60">{{ __('Available') }}</p>
                </div>
                <div class="p-3 rounded-lg bg-surface dark:bg-surface border border-outline/50 text-center">
                    <p class="text-lg font-bold text-warning">{{ $mealSummary['unavailable'] }}</p>
                    <p class="text-xs text-on-surface/60">{{ __('Unavailable') }}</p>
                </div>
                <div class="p-3 rounded-lg bg-surface dark:bg-surface border border-outline/50 text-center">
                    <p class="text-lg font-bold text-on-surface-strong">{{ $mealSummary['days_covered'] }}<span class="text-sm font-normal text-on-surface/40">/7</span></p>
                    <p class="text-xs text-on-surface/60">{{ __('Days') }}</p>
                </div>
            </div>
        @endif

        {{-- Add Schedule Entry Button --}}
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-sm font-semibold text-on-surface-strong">{{ __('Custom Schedule Entries') }}</h4>
            <button
                @click="showAddForm = !showAddForm"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary hover:bg-primary-hover text-on-primary rounded-lg text-xs font-medium transition-colors duration-200 shadow-sm"
            >
                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                <span x-text="showAddForm ? '{{ __('Cancel') }}' : '{{ __('Add Entry') }}'"></span>
            </button>
        </div>

        {{-- Add Schedule Entry Form --}}
        <div
            x-show="showAddForm"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2"
            class="mb-4 p-4 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface"
            x-cloak
        >
            <form @submit.prevent="$action('{{ url('/dashboard/meals/' . $meal->id . '/schedule') }}')">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                    {{-- Day of Week --}}
                    <div>
                        <label class="block text-xs font-medium text-on-surface mb-1">
                            {{ __('Day of the Week') }} <span class="text-danger">*</span>
                        </label>
                        <select
                            x-model="day_of_week"
                            x-name="day_of_week"
                            class="w-full rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-colors duration-200"
                        >
                            <option value="">{{ __('Select a day') }}</option>
                            @foreach($scheduleData['daysOfWeek'] as $day)
                                <option value="{{ $day }}">{{ __($scheduleData['dayLabels'][$day]) }}</option>
                            @endforeach
                        </select>
                        <p x-message="day_of_week" class="mt-1 text-xs text-danger"></p>
                    </div>

                    {{-- Label --}}
                    <div>
                        <label class="block text-xs font-medium text-on-surface mb-1">
                            {{ __('Label') }} <span class="text-on-surface/40">({{ __('optional') }})</span>
                        </label>
                        <input
                            type="text"
                            x-model="label"
                            x-name="label"
                            maxlength="100"
                            placeholder="{{ __('e.g., Lunch, Dinner') }}"
                            class="w-full rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface px-3 py-2 text-sm placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-colors duration-200"
                        >
                        <p x-message="label" class="mt-1 text-xs text-danger"></p>
                    </div>
                </div>

                {{-- Availability --}}
                <div class="mb-3">
                    <label class="block text-xs font-medium text-on-surface mb-1.5">{{ __('Availability') }}</label>
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="meal_avail" value="true" x-model="is_available" class="w-4 h-4 text-primary border-outline focus:ring-primary/50">
                            <span class="text-sm text-on-surface">{{ __('Available') }}</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="meal_avail" value="false" x-model="is_available" class="w-4 h-4 text-primary border-outline focus:ring-primary/50">
                            <span class="text-sm text-on-surface">{{ __('Unavailable') }}</span>
                        </label>
                    </div>
                    <p x-message="is_available" class="mt-1 text-xs text-danger"></p>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-primary hover:bg-primary-hover text-on-primary rounded-lg text-sm font-medium transition-colors duration-200 shadow-sm disabled:opacity-50"
                        :disabled="$fetching()"
                    >
                        <span x-show="!$fetching()">{{ __('Create Entry') }}</span>
                        <span x-show="$fetching()" class="flex items-center gap-2" x-cloak>
                            <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            {{ __('Creating...') }}
                        </span>
                    </button>
                </div>
            </form>
        </div>

        {{-- Schedule Entries List --}}
        @if(!$hasAnyMealSchedules)
            <div class="p-8 text-center rounded-lg bg-surface dark:bg-surface border border-outline/50">
                <svg class="w-8 h-8 text-on-surface/30 mx-auto mb-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path></svg>
                <p class="text-sm text-on-surface/60 mb-1">{{ __('No custom schedule entries yet') }}</p>
                <p class="text-xs text-on-surface/40">{{ __('Add schedule entries to define when this meal is available.') }}</p>
                <button
                    @click="showAddForm = true"
                    class="mt-3 inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary hover:bg-primary-hover text-on-primary rounded-lg text-xs font-medium transition-colors duration-200"
                >
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                    {{ __('Add First Entry') }}
                </button>
            </div>
        @else
            <div class="space-y-3">
                @foreach($scheduleData['daysOfWeek'] as $day)
                    @php
                        $entries = $mealSchedulesByDay[$day] ?? [];
                        $entryCount = count($entries);
                    @endphp

                    @if($entryCount > 0)
                        <div class="rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface overflow-hidden">
                            {{-- Day Header --}}
                            <div class="flex items-center gap-2 px-4 py-2 border-b border-outline/50 dark:border-outline/50">
                                <span class="w-6 h-6 rounded-full bg-primary-subtle text-primary flex items-center justify-center text-xs font-bold">
                                    {{ mb_strtoupper(mb_substr(__($scheduleData['dayLabels'][$day]), 0, 2)) }}
                                </span>
                                <span class="text-sm font-semibold text-on-surface-strong">{{ __($scheduleData['dayLabels'][$day]) }}</span>
                                <span class="text-xs text-on-surface/40">({{ trans_choice(':count entry|:count entries', $entryCount, ['count' => $entryCount]) }})</span>
                            </div>

                            {{-- Entries --}}
                            <div class="divide-y divide-outline/50 dark:divide-outline/50">
                                @foreach($entries as $entry)
                                    <div>
                                        {{-- Entry Row --}}
                                        <div class="flex items-center justify-between px-4 py-2">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                @if($entry->is_available)
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-medium bg-success-subtle text-success">{{ __('Available') }}</span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-medium bg-warning-subtle text-warning">{{ __('Unavailable') }}</span>
                                                @endif
                                                <span class="text-sm font-medium text-on-surface-strong">{{ $entry->display_label }}</span>
                                            </div>

                                            <div class="flex items-center gap-1.5">
                                                @if($entry->is_available)
                                                    {{-- Order interval button --}}
                                                    <button
                                                        @click="toggleInterval({{ $entry->id }}, '{{ $entry->order_start_time ? substr($entry->order_start_time, 0, 5) : '' }}', '{{ $entry->order_start_day_offset }}', '{{ $entry->order_end_time ? substr($entry->order_end_time, 0, 5) : '' }}', '{{ $entry->order_end_day_offset }}')"
                                                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] font-medium transition-colors duration-200"
                                                        :class="expandedIntervalId === {{ $entry->id }} ? 'bg-primary text-on-primary' : 'bg-surface-alt border border-outline text-on-surface hover:bg-primary-subtle hover:text-primary'"
                                                        title="{{ __('Configure Order Interval') }}"
                                                    >
                                                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                                        <span class="hidden sm:inline">{{ $entry->hasOrderInterval() ? __('Edit') : __('Set') }}</span>
                                                    </button>

                                                    @if($entry->hasOrderInterval())
                                                        {{-- Delivery/Pickup button --}}
                                                        <button
                                                            @click="toggleDeliveryPickup({{ $entry->id }}, {{ $entry->delivery_enabled ? 'true' : 'false' }}, '{{ $entry->delivery_start_time ? substr($entry->delivery_start_time, 0, 5) : '' }}', '{{ $entry->delivery_end_time ? substr($entry->delivery_end_time, 0, 5) : '' }}', {{ $entry->pickup_enabled ? 'true' : 'false' }}, '{{ $entry->pickup_start_time ? substr($entry->pickup_start_time, 0, 5) : '' }}', '{{ $entry->pickup_end_time ? substr($entry->pickup_end_time, 0, 5) : '' }}')"
                                                            class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] font-medium transition-colors duration-200"
                                                            :class="expandedDeliveryPickupId === {{ $entry->id }} ? 'bg-primary text-on-primary' : 'bg-surface-alt border border-outline text-on-surface hover:bg-primary-subtle hover:text-primary'"
                                                            title="{{ __('Configure Delivery/Pickup') }}"
                                                        >
                                                            <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"></path><path d="M15 18H9"></path><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path><circle cx="7" cy="18" r="2"></circle><circle cx="17" cy="18" r="2"></circle></svg>
                                                            <span class="hidden sm:inline">{{ ($entry->hasDeliveryInterval() || $entry->hasPickupInterval()) ? __('Edit') : __('Set') }}</span>
                                                        </button>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Interval summaries --}}
                                        @if($entry->is_available)
                                            <div
                                                x-show="expandedIntervalId !== {{ $entry->id }} && expandedDeliveryPickupId !== {{ $entry->id }}"
                                                class="px-4 pb-2 -mt-1 space-y-0.5"
                                            >
                                                @if($entry->hasOrderInterval())
                                                    <div class="flex items-center gap-1.5 text-[10px] text-on-surface/60">
                                                        <svg class="w-3 h-3 text-info shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                                        <span>{{ __('Orders') }}: {{ $entry->order_interval_summary }}</span>
                                                    </div>
                                                @endif
                                                @if($entry->hasDeliveryInterval())
                                                    <div class="flex items-center gap-1.5 text-[10px] text-on-surface/60">
                                                        <svg class="w-3 h-3 text-primary shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"></path><path d="M15 18H9"></path><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path><circle cx="7" cy="18" r="2"></circle><circle cx="17" cy="18" r="2"></circle></svg>
                                                        <span>{{ __('Delivery') }}: {{ $entry->delivery_interval_summary }}</span>
                                                    </div>
                                                @endif
                                                @if($entry->hasPickupInterval())
                                                    <div class="flex items-center gap-1.5 text-[10px] text-on-surface/60">
                                                        <svg class="w-3 h-3 text-secondary shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9Z"></path><path d="m3 9 2.45-4.9A2 2 0 0 1 7.24 3h9.52a2 2 0 0 1 1.8 1.1L21 9"></path><path d="M12 3v6"></path></svg>
                                                        <span>{{ __('Pickup') }}: {{ $entry->pickup_interval_summary }}</span>
                                                    </div>
                                                @endif
                                                @if(!$entry->hasOrderInterval())
                                                    <div class="flex items-center gap-1.5 text-[10px] text-on-surface/40">
                                                        <svg class="w-3 h-3 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                                                        <span>{{ __('Set order interval to enable delivery/pickup.') }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif

                                        {{-- Order Interval Form --}}
                                        @if($entry->is_available)
                                            <div
                                                x-show="expandedIntervalId === {{ $entry->id }}"
                                                x-transition
                                                class="px-4 pb-3 border-t border-outline/50 bg-surface/50"
                                                x-cloak
                                            >
                                                <div class="pt-3">
                                                    <h5 class="text-xs font-semibold text-on-surface-strong mb-2">{{ __('Order Time Interval') }}</h5>
                                                    <form @submit.prevent="$action('{{ url('/dashboard/meals/' . $meal->id . '/schedule/' . $entry->id . '/order-interval') }}', { method: 'PUT' })">
                                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-2">
                                                            <div>
                                                                <label class="block text-[10px] font-medium text-on-surface mb-0.5">{{ __('Start Time') }}</label>
                                                                <select x-model="order_start_time" x-name="order_start_time" class="w-full rounded-lg border border-outline bg-surface text-on-surface px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-primary/50">
                                                                    @for($h = 0; $h < 24; $h++)
                                                                        @for($m = 0; $m < 60; $m += 30)
                                                                            @php $timeVal = sprintf('%02d:%02d', $h, $m); @endphp
                                                                            <option value="{{ $timeVal }}">{{ date('g:i A', strtotime($timeVal)) }}</option>
                                                                        @endfor
                                                                    @endfor
                                                                </select>
                                                                <p x-message="order_start_time" class="mt-0.5 text-[10px] text-danger"></p>
                                                            </div>
                                                            <div>
                                                                <label class="block text-[10px] font-medium text-on-surface mb-0.5">{{ __('Start Offset') }}</label>
                                                                <select x-model="order_start_day_offset" x-name="order_start_day_offset" class="w-full rounded-lg border border-outline bg-surface text-on-surface px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-primary/50">
                                                                    @foreach(\App\Models\MealSchedule::getStartDayOffsetOptions() as $val => $lbl)
                                                                        <option value="{{ $val }}">{{ ucfirst($lbl) }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-2">
                                                            <div>
                                                                <label class="block text-[10px] font-medium text-on-surface mb-0.5">{{ __('End Time') }}</label>
                                                                <select x-model="order_end_time" x-name="order_end_time" class="w-full rounded-lg border border-outline bg-surface text-on-surface px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-primary/50">
                                                                    @for($h = 0; $h < 24; $h++)
                                                                        @for($m = 0; $m < 60; $m += 30)
                                                                            @php $timeVal = sprintf('%02d:%02d', $h, $m); @endphp
                                                                            <option value="{{ $timeVal }}">{{ date('g:i A', strtotime($timeVal)) }}</option>
                                                                        @endfor
                                                                    @endfor
                                                                </select>
                                                                <p x-message="order_end_time" class="mt-0.5 text-[10px] text-danger"></p>
                                                            </div>
                                                            <div>
                                                                <label class="block text-[10px] font-medium text-on-surface mb-0.5">{{ __('End Offset') }}</label>
                                                                <select x-model="order_end_day_offset" x-name="order_end_day_offset" class="w-full rounded-lg border border-outline bg-surface text-on-surface px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-primary/50">
                                                                    @foreach(\App\Models\MealSchedule::getEndDayOffsetOptions() as $val => $lbl)
                                                                        <option value="{{ $val }}">{{ ucfirst($lbl) }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="mb-2 p-2 rounded bg-info-subtle border border-info/20 text-[10px] text-info">
                                                            <span class="font-medium">{{ __('Preview') }}:</span> <span x-text="getIntervalPreview()"></span>
                                                        </div>
                                                        <div class="flex justify-end gap-2">
                                                            <button type="button" @click="expandedIntervalId = null" class="px-3 py-1.5 rounded-lg text-xs text-on-surface hover:bg-surface-alt border border-outline transition-colors">{{ __('Cancel') }}</button>
                                                            <button type="submit" class="px-3 py-1.5 bg-primary hover:bg-primary-hover text-on-primary rounded-lg text-xs font-medium transition-colors disabled:opacity-50" :disabled="$fetching()">
                                                                <span x-show="!$fetching()">{{ __('Save') }}</span>
                                                                <span x-show="$fetching()" x-cloak>{{ __('Saving...') }}</span>
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        @endif

                                        {{-- Delivery/Pickup Form --}}
                                        @if($entry->is_available && $entry->hasOrderInterval())
                                            <div
                                                x-show="expandedDeliveryPickupId === {{ $entry->id }}"
                                                x-transition
                                                class="px-4 pb-3 border-t border-outline/50 bg-surface/50"
                                                x-cloak
                                            >
                                                <div class="pt-3">
                                                    <h5 class="text-xs font-semibold text-on-surface-strong mb-2">{{ __('Delivery & Pickup') }}</h5>
                                                    <form @submit.prevent="$action('{{ url('/dashboard/meals/' . $meal->id . '/schedule/' . $entry->id . '/delivery-pickup-interval') }}', { method: 'PUT' })">
                                                        {{-- Delivery --}}
                                                        <div class="mb-3 p-3 rounded-lg border border-outline bg-surface-alt">
                                                            <div class="flex items-center justify-between mb-2">
                                                                <span class="text-xs font-semibold text-on-surface-strong">{{ __('Delivery') }}</span>
                                                                <button type="button" @click="delivery_enabled = delivery_enabled === 'true' ? 'false' : 'true'" class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200" :class="delivery_enabled === 'true' ? 'bg-primary' : 'bg-on-surface/20'" role="switch" :aria-checked="delivery_enabled === 'true'">
                                                                    <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-200" :class="delivery_enabled === 'true' ? 'translate-x-4' : 'translate-x-0'"></span>
                                                                </button>
                                                            </div>
                                                            <div x-show="delivery_enabled === 'true'" x-transition x-cloak>
                                                                <div class="grid grid-cols-2 gap-2">
                                                                    <div>
                                                                        <label class="block text-[10px] text-on-surface mb-0.5">{{ __('Start') }}</label>
                                                                        <select x-model="delivery_start_time" x-name="delivery_start_time" class="w-full rounded-lg border border-outline bg-surface text-on-surface px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-primary/50">
                                                                            @for($h = 0; $h < 24; $h++) @for($m = 0; $m < 60; $m += 30) @php $timeVal = sprintf('%02d:%02d', $h, $m); @endphp <option value="{{ $timeVal }}">{{ date('g:i A', strtotime($timeVal)) }}</option> @endfor @endfor
                                                                        </select>
                                                                        <p x-message="delivery_start_time" class="mt-0.5 text-[10px] text-danger"></p>
                                                                    </div>
                                                                    <div>
                                                                        <label class="block text-[10px] text-on-surface mb-0.5">{{ __('End') }}</label>
                                                                        <select x-model="delivery_end_time" x-name="delivery_end_time" class="w-full rounded-lg border border-outline bg-surface text-on-surface px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-primary/50">
                                                                            @for($h = 0; $h < 24; $h++) @for($m = 0; $m < 60; $m += 30) @php $timeVal = sprintf('%02d:%02d', $h, $m); @endphp <option value="{{ $timeVal }}">{{ date('g:i A', strtotime($timeVal)) }}</option> @endfor @endfor
                                                                        </select>
                                                                        <p x-message="delivery_end_time" class="mt-0.5 text-[10px] text-danger"></p>
                                                                    </div>
                                                                </div>
                                                                <div class="mt-1 text-[10px] text-on-surface/60" x-show="delivery_start_time && delivery_end_time"><span class="font-medium">{{ __('Preview') }}:</span> <span x-text="getDeliveryPreview()"></span></div>
                                                            </div>
                                                        </div>

                                                        {{-- Pickup --}}
                                                        <div class="mb-3 p-3 rounded-lg border border-outline bg-surface-alt">
                                                            <div class="flex items-center justify-between mb-2">
                                                                <span class="text-xs font-semibold text-on-surface-strong">{{ __('Pickup') }}</span>
                                                                <button type="button" @click="pickup_enabled = pickup_enabled === 'true' ? 'false' : 'true'" class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200" :class="pickup_enabled === 'true' ? 'bg-primary' : 'bg-on-surface/20'" role="switch" :aria-checked="pickup_enabled === 'true'">
                                                                    <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-200" :class="pickup_enabled === 'true' ? 'translate-x-4' : 'translate-x-0'"></span>
                                                                </button>
                                                            </div>
                                                            <div x-show="pickup_enabled === 'true'" x-transition x-cloak>
                                                                <div class="grid grid-cols-2 gap-2">
                                                                    <div>
                                                                        <label class="block text-[10px] text-on-surface mb-0.5">{{ __('Start') }}</label>
                                                                        <select x-model="pickup_start_time" x-name="pickup_start_time" class="w-full rounded-lg border border-outline bg-surface text-on-surface px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-primary/50">
                                                                            @for($h = 0; $h < 24; $h++) @for($m = 0; $m < 60; $m += 30) @php $timeVal = sprintf('%02d:%02d', $h, $m); @endphp <option value="{{ $timeVal }}">{{ date('g:i A', strtotime($timeVal)) }}</option> @endfor @endfor
                                                                        </select>
                                                                        <p x-message="pickup_start_time" class="mt-0.5 text-[10px] text-danger"></p>
                                                                    </div>
                                                                    <div>
                                                                        <label class="block text-[10px] text-on-surface mb-0.5">{{ __('End') }}</label>
                                                                        <select x-model="pickup_end_time" x-name="pickup_end_time" class="w-full rounded-lg border border-outline bg-surface text-on-surface px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-primary/50">
                                                                            @for($h = 0; $h < 24; $h++) @for($m = 0; $m < 60; $m += 30) @php $timeVal = sprintf('%02d:%02d', $h, $m); @endphp <option value="{{ $timeVal }}">{{ date('g:i A', strtotime($timeVal)) }}</option> @endfor @endfor
                                                                        </select>
                                                                        <p x-message="pickup_end_time" class="mt-0.5 text-[10px] text-danger"></p>
                                                                    </div>
                                                                </div>
                                                                <div class="mt-1 text-[10px] text-on-surface/60" x-show="pickup_start_time && pickup_end_time"><span class="font-medium">{{ __('Preview') }}:</span> <span x-text="getPickupPreview()"></span></div>
                                                            </div>
                                                        </div>

                                                        <div x-show="delivery_enabled === 'false' && pickup_enabled === 'false'" x-cloak class="mb-2 p-2 rounded bg-warning-subtle border border-warning/20 text-[10px] text-warning">
                                                            {{ __('At least one of delivery or pickup must be enabled.') }}
                                                        </div>
                                                        <p x-message="delivery_enabled" class="mb-1 text-[10px] text-danger"></p>

                                                        <div class="flex justify-end gap-2">
                                                            <button type="button" @click="expandedDeliveryPickupId = null" class="px-3 py-1.5 rounded-lg text-xs text-on-surface hover:bg-surface-alt border border-outline transition-colors">{{ __('Cancel') }}</button>
                                                            <button type="submit" class="px-3 py-1.5 bg-primary hover:bg-primary-hover text-on-primary rounded-lg text-xs font-medium transition-colors disabled:opacity-50" :disabled="$fetching()">
                                                                <span x-show="!$fetching()">{{ __('Save') }}</span>
                                                                <span x-show="$fetching()" x-cloak>{{ __('Saving...') }}</span>
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</div>
