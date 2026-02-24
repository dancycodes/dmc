{{--
    Cook Day Schedule Management
    ----------------------------
    F-098: Cook Day Schedule Creation
    F-099: Order Time Interval Configuration
    F-100: Delivery/Pickup Time Interval Configuration

    Allows the cook to create schedule entries for specific days of the week.
    Multiple entries per day (e.g., Lunch, Dinner) up to configurable max (default 3).
    Entries grouped by day in a card layout.
    Each available entry can have order, delivery, and pickup time intervals configured.

    Business Rules:
    BR-098 to BR-115: Schedule and order interval rules (F-098, F-099)
    BR-116: Delivery/pickup intervals on the open day (day offset 0)
    BR-117: Delivery start >= order interval end time
    BR-118: Pickup start >= order interval end time
    BR-119: Delivery end > delivery start
    BR-120: Pickup end > pickup start
    BR-121: At least one of delivery or pickup must be enabled (if available)
    BR-122: Time format is 24-hour (HH:MM)
    BR-123: Delivery and pickup windows are independent
    BR-124: Order interval must be configured before delivery/pickup
    BR-125: Changes do not affect already-placed orders
    BR-126: Interval configuration logged via Spatie Activitylog
--}}
@extends('layouts.cook-dashboard')

@section('title', __('Schedule'))
@section('page-title', __('Schedule'))

@section('content')
<div
    class="max-w-4xl mx-auto"
    x-data="{
        day_of_week: '',
        is_available: 'true',
        label: '',
        showAddForm: false,
        confirmDeleteId: null,
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
            const startFormatted = this.formatTime(this.order_start_time);
            const endFormatted = this.formatTime(this.order_end_time);
            return startFormatted + ' ' + startLabel + ' {{ __('to') }} ' + endFormatted + ' ' + endLabel;
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
    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm text-on-surface/60 mb-6" aria-label="{{ __('Breadcrumb') }}">
        <a href="{{ url('/dashboard') }}" class="hover:text-primary transition-colors duration-200">
            {{ __('Dashboard') }}
        </a>
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
        <span class="text-on-surface-strong font-medium">{{ __('Schedule') }}</span>
    </nav>

    {{-- Success Toast --}}
    @if(session('success'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 4000)"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-2"
            class="mb-6 p-4 rounded-lg border bg-success-subtle border-success/30 text-success flex items-center gap-3"
            role="alert"
        >
            <svg class="w-5 h-5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><path d="m9 11 3 3L22 4"></path></svg>
            <span class="text-sm font-medium">{{ session('success') }}</span>
        </div>
    @endif

    {{-- Error Toast --}}
    @if(session('error'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 5000)"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-2"
            class="mb-6 p-4 rounded-lg border bg-danger-subtle border-danger/30 text-danger flex items-center gap-3"
            role="alert"
        >
            <svg class="w-5 h-5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" x2="12" y1="8" y2="12"></line><line x1="12" x2="12.01" y1="16" y2="16"></line></svg>
            <span class="text-sm font-medium">{{ session('error') }}</span>
        </div>
    @endif

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        {{-- Total Entries --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 shadow-card">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center">
                    <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path></svg>
                </span>
                <div>
                    <p class="text-2xl font-bold text-on-surface-strong">{{ $summary['total'] }}</p>
                    <p class="text-xs text-on-surface/60">{{ __('Total Entries') }}</p>
                </div>
            </div>
        </div>

        {{-- Available --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 shadow-card">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-success-subtle flex items-center justify-center">
                    <svg class="w-5 h-5 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><path d="m9 11 3 3L22 4"></path></svg>
                </span>
                <div>
                    <p class="text-2xl font-bold text-on-surface-strong">{{ $summary['available'] }}</p>
                    <p class="text-xs text-on-surface/60">{{ __('Available') }}</p>
                </div>
            </div>
        </div>

        {{-- Unavailable --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 shadow-card">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-warning-subtle flex items-center justify-center">
                    <svg class="w-5 h-5 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" x2="19.07" y1="4.93" y2="19.07"></line></svg>
                </span>
                <div>
                    <p class="text-2xl font-bold text-on-surface-strong">{{ $summary['unavailable'] }}</p>
                    <p class="text-xs text-on-surface/60">{{ __('Unavailable') }}</p>
                </div>
            </div>
        </div>

        {{-- Days Covered --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 shadow-card">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-info-subtle flex items-center justify-center">
                    <svg class="w-5 h-5 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M16 2v4"></path><path d="M3 10h18"></path><path d="M8 14h.01"></path><path d="M12 14h.01"></path><path d="M16 14h.01"></path><path d="M8 18h.01"></path><path d="M12 18h.01"></path></svg>
                </span>
                <div>
                    <p class="text-2xl font-bold text-on-surface-strong">{{ $summary['days_covered'] }}<span class="text-sm font-normal text-on-surface/60">/7</span></p>
                    <p class="text-xs text-on-surface/60">{{ __('Days Covered') }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Add Schedule Button --}}
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-on-surface-strong">{{ __('Weekly Schedule') }}</h2>
        <div class="flex items-center gap-2">
            <a
                href="{{ url('/dashboard/schedule/templates') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-surface-alt hover:bg-surface border border-border text-on-surface rounded-lg text-sm font-medium transition-colors duration-200"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/></svg>
                {{ __('Templates') }}
            </a>
            <button
                @click="showAddForm = !showAddForm"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary hover:bg-primary-hover text-on-primary rounded-lg text-sm font-medium transition-colors duration-200 shadow-sm"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                <span x-text="showAddForm ? '{{ __('Cancel') }}' : '{{ __('Add Schedule') }}'"></span>
            </button>
        </div>
    </div>

    {{-- Add Schedule Form --}}
    <div
        x-show="showAddForm"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="mb-6 bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-6 shadow-card"
        x-cloak
    >
        <h3 class="text-base font-semibold text-on-surface-strong mb-4">{{ __('New Schedule Entry') }}</h3>

        <form @submit.prevent="$action('{{ url('/dashboard/schedule') }}')">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                {{-- Day of Week --}}
                <div>
                    <label for="day_of_week" class="block text-sm font-medium text-on-surface mb-1.5">
                        {{ __('Day of the Week') }} <span class="text-danger">*</span>
                    </label>
                    <select
                        id="day_of_week"
                        x-model="day_of_week"
                        x-name="day_of_week"
                        class="w-full rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-colors duration-200"
                    >
                        <option value="">{{ __('Select a day') }}</option>
                        @foreach($daysOfWeek as $day)
                            <option value="{{ $day }}">{{ __($dayLabels[$day]) }}</option>
                        @endforeach
                    </select>
                    <p x-message="day_of_week" class="mt-1 text-sm text-danger"></p>
                </div>

                {{-- Label (Optional) --}}
                <div>
                    <label for="label" class="block text-sm font-medium text-on-surface mb-1.5">
                        {{ __('Label') }} <span class="text-on-surface/40">({{ __('optional') }})</span>
                    </label>
                    <input
                        type="text"
                        id="label"
                        x-model="label"
                        x-name="label"
                        maxlength="100"
                        placeholder="{{ __('e.g., Lunch, Dinner') }}"
                        class="w-full rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface px-3 py-2.5 text-sm placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-colors duration-200"
                    >
                    <p x-message="label" class="mt-1 text-sm text-danger"></p>
                </div>
            </div>

            {{-- Availability Toggle --}}
            <div class="mb-5">
                <label class="block text-sm font-medium text-on-surface mb-2">
                    {{ __('Availability') }} <span class="text-danger">*</span>
                </label>
                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="radio"
                            name="availability"
                            value="true"
                            x-model="is_available"
                            class="w-4 h-4 text-primary border-outline focus:ring-primary/50"
                        >
                        <span class="text-sm text-on-surface flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><path d="m9 11 3 3L22 4"></path></svg>
                            {{ __('Available') }}
                        </span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="radio"
                            name="availability"
                            value="false"
                            x-model="is_available"
                            class="w-4 h-4 text-primary border-outline focus:ring-primary/50"
                        >
                        <span class="text-sm text-on-surface flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" x2="19.07" y1="4.93" y2="19.07"></line></svg>
                            {{ __('Unavailable') }}
                        </span>
                    </label>
                </div>
                <p x-message="is_available" class="mt-1 text-sm text-danger"></p>
            </div>

            {{-- BR-101: Info about unavailable entries --}}
            <div
                x-show="is_available === 'false'"
                x-transition
                class="mb-4 p-3 rounded-lg bg-warning-subtle border border-warning/20 text-sm text-warning"
                x-cloak
            >
                <div class="flex items-start gap-2">
                    <svg class="w-4 h-4 mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                    <span>{{ __('Unavailable entries mark this day as a non-operating day. No order, delivery, or pickup time intervals can be configured for unavailable entries.') }}</span>
                </div>
            </div>

            {{-- Submit Button --}}
            <div class="flex justify-end">
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary hover:bg-primary-hover text-on-primary rounded-lg text-sm font-medium transition-colors duration-200 shadow-sm disabled:opacity-50"
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

    {{-- Schedule List by Day --}}
    @php
        $hasAnySchedules = collect($schedulesByDay)->flatten()->isNotEmpty();
    @endphp

    @if(!$hasAnySchedules)
        {{-- Empty State --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-12 text-center shadow-card">
            <div class="flex justify-center mb-4">
                <span class="w-16 h-16 rounded-full bg-primary-subtle flex items-center justify-center">
                    <svg class="w-8 h-8 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path></svg>
                </span>
            </div>
            <h3 class="text-lg font-semibold text-on-surface-strong mb-2">{{ __('No schedule entries yet') }}</h3>
            <p class="text-sm text-on-surface/60 mb-4 max-w-md mx-auto">
                {{ __('Create your first schedule entry to define which days you are available and set up service windows like Lunch or Dinner.') }}
            </p>
            <button
                @click="showAddForm = true"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary hover:bg-primary-hover text-on-primary rounded-lg text-sm font-medium transition-colors duration-200"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                {{ __('Create First Schedule') }}
            </button>
        </div>
    @else
        {{-- Day-by-day Schedule Cards --}}
        <div class="space-y-4">
            @foreach($daysOfWeek as $day)
                @php
                    $entries = $schedulesByDay[$day] ?? [];
                    $entryCount = count($entries);
                    $isAtLimit = $entryCount >= $maxPerDay;
                @endphp

                <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline shadow-card overflow-hidden">
                    {{-- Day Header --}}
                    <div class="flex items-center justify-between px-4 sm:px-6 py-3 border-b border-outline dark:border-outline bg-surface dark:bg-surface">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-full {{ $entryCount > 0 ? 'bg-primary-subtle text-primary' : 'bg-surface-alt text-on-surface/40' }} flex items-center justify-center text-sm font-bold">
                                {{ mb_strtoupper(mb_substr(__($dayLabels[$day]), 0, 2)) }}
                            </span>
                            <div>
                                <h3 class="text-sm font-semibold text-on-surface-strong">{{ __($dayLabels[$day]) }}</h3>
                                <p class="text-xs text-on-surface/60">
                                    {{ trans_choice(':count entry|:count entries', $entryCount, ['count' => $entryCount]) }}
                                    @if($isAtLimit)
                                        <span class="text-warning">({{ __('limit reached') }})</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Entries --}}
                    @if($entryCount > 0)
                        <div class="divide-y divide-outline dark:divide-outline">
                            @foreach($entries as $entry)
                                <div>
                                    {{-- Entry row --}}
                                    <div class="flex items-center justify-between px-4 sm:px-6 py-3">
                                        <div class="flex items-center gap-3 flex-wrap">
                                            {{-- Availability Badge --}}
                                            @if($entry->is_available)
                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-success-subtle text-success">
                                                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><path d="m9 11 3 3L22 4"></path></svg>
                                                    {{ __('Available') }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-warning-subtle text-warning">
                                                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" x2="19.07" y1="4.93" y2="19.07"></line></svg>
                                                    {{ __('Unavailable') }}
                                                </span>
                                            @endif

                                            {{-- Label --}}
                                            <span class="text-sm font-medium text-on-surface-strong">
                                                {{ $entry->display_label }}
                                            </span>

                                            {{-- Position indicator --}}
                                            <span class="text-xs text-on-surface/40">#{{ $entry->position }}</span>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            @if(!$entry->is_available)
                                                {{-- BR-101/BR-112: No intervals for unavailable entries --}}
                                                <span class="text-xs text-on-surface/40 hidden sm:inline" title="{{ __('No time intervals for unavailable entries') }}">
                                                    {{ __('No intervals') }}
                                                </span>
                                            @else
                                                {{-- F-099: Order interval badge --}}
                                                @if($entry->hasOrderInterval())
                                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-info-subtle text-info hidden sm:inline-flex" title="{{ $entry->order_interval_summary }}">
                                                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                                        {{ __('Orders') }}
                                                    </span>
                                                @endif

                                                {{-- F-100: Delivery/Pickup badges --}}
                                                @if($entry->hasDeliveryInterval())
                                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-primary-subtle text-primary hidden sm:inline-flex" title="{{ $entry->delivery_interval_summary }}">
                                                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"></path><path d="M15 18H9"></path><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path><circle cx="7" cy="18" r="2"></circle><circle cx="17" cy="18" r="2"></circle></svg>
                                                        {{ __('Delivery') }}
                                                    </span>
                                                @endif
                                                @if($entry->hasPickupInterval())
                                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-secondary-subtle text-secondary hidden sm:inline-flex" title="{{ $entry->pickup_interval_summary }}">
                                                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9Z"></path><path d="m3 9 2.45-4.9A2 2 0 0 1 7.24 3h9.52a2 2 0 0 1 1.8 1.1L21 9"></path><path d="M12 3v6"></path></svg>
                                                        {{ __('Pickup') }}
                                                    </span>
                                                @endif

                                                {{-- Order interval config button --}}
                                                <button
                                                    @click="toggleInterval({{ $entry->id }}, '{{ $entry->order_start_time ? substr($entry->order_start_time, 0, 5) : '' }}', '{{ $entry->order_start_day_offset }}', '{{ $entry->order_end_time ? substr($entry->order_end_time, 0, 5) : '' }}', '{{ $entry->order_end_day_offset }}')"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium transition-colors duration-200"
                                                    :class="expandedIntervalId === {{ $entry->id }} ? 'bg-primary text-on-primary' : 'bg-surface dark:bg-surface border border-outline text-on-surface hover:bg-primary-subtle hover:text-primary'"
                                                    title="{{ __('Configure Order Interval') }}"
                                                >
                                                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                                    <span class="hidden sm:inline">{{ $entry->hasOrderInterval() ? __('Edit') : __('Set') }}</span>
                                                </button>

                                                {{-- F-100: Delivery/Pickup config button --}}
                                                @if($entry->hasOrderInterval())
                                                    <button
                                                        @click="toggleDeliveryPickup({{ $entry->id }}, {{ $entry->delivery_enabled ? 'true' : 'false' }}, '{{ $entry->delivery_start_time ? substr($entry->delivery_start_time, 0, 5) : '' }}', '{{ $entry->delivery_end_time ? substr($entry->delivery_end_time, 0, 5) : '' }}', {{ $entry->pickup_enabled ? 'true' : 'false' }}, '{{ $entry->pickup_start_time ? substr($entry->pickup_start_time, 0, 5) : '' }}', '{{ $entry->pickup_end_time ? substr($entry->pickup_end_time, 0, 5) : '' }}')"
                                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium transition-colors duration-200"
                                                        :class="expandedDeliveryPickupId === {{ $entry->id }} ? 'bg-primary text-on-primary' : 'bg-surface dark:bg-surface border border-outline text-on-surface hover:bg-primary-subtle hover:text-primary'"
                                                        title="{{ __('Configure Delivery/Pickup') }}"
                                                    >
                                                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"></path><path d="M15 18H9"></path><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path><circle cx="7" cy="18" r="2"></circle><circle cx="17" cy="18" r="2"></circle></svg>
                                                        <span class="hidden sm:inline">{{ ($entry->hasDeliveryInterval() || $entry->hasPickupInterval()) ? __('Edit') : __('Set') }}</span>
                                                    </button>
                                                @endif
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Interval summaries (shown when forms are collapsed) --}}
                                    @if($entry->is_available)
                                        <div
                                            x-show="expandedIntervalId !== {{ $entry->id }} && expandedDeliveryPickupId !== {{ $entry->id }}"
                                            class="px-4 sm:px-6 pb-3 -mt-1 space-y-1"
                                        >
                                            {{-- F-099: Order Interval Summary --}}
                                            @if($entry->hasOrderInterval())
                                                <div class="flex items-center gap-2 text-xs text-on-surface/60">
                                                    <svg class="w-3.5 h-3.5 text-info shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                                    <span>{{ __('Orders') }}: {{ $entry->order_interval_summary }}</span>
                                                </div>
                                            @endif

                                            {{-- F-100: Delivery Interval Summary --}}
                                            @if($entry->hasDeliveryInterval())
                                                <div class="flex items-center gap-2 text-xs text-on-surface/60">
                                                    <svg class="w-3.5 h-3.5 text-primary shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"></path><path d="M15 18H9"></path><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path><circle cx="7" cy="18" r="2"></circle><circle cx="17" cy="18" r="2"></circle></svg>
                                                    <span>{{ __('Delivery') }}: {{ $entry->delivery_interval_summary }}</span>
                                                </div>
                                            @endif

                                            {{-- F-100: Pickup Interval Summary --}}
                                            @if($entry->hasPickupInterval())
                                                <div class="flex items-center gap-2 text-xs text-on-surface/60">
                                                    <svg class="w-3.5 h-3.5 text-secondary shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9Z"></path><path d="m3 9 2.45-4.9A2 2 0 0 1 7.24 3h9.52a2 2 0 0 1 1.8 1.1L21 9"></path><path d="M12 3v6"></path></svg>
                                                    <span>{{ __('Pickup') }}: {{ $entry->pickup_interval_summary }}</span>
                                                </div>
                                            @endif

                                            {{-- BR-124: No order interval hint --}}
                                            @if(!$entry->hasOrderInterval())
                                                <div class="flex items-center gap-2 text-xs text-on-surface/40">
                                                    <svg class="w-3.5 h-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                                                    <span>{{ __('Set order interval first to enable delivery/pickup configuration.') }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    @endif

                                    {{-- F-099: Order Interval Configuration Form (collapsible) --}}
                                    @if($entry->is_available)
                                        <div
                                            x-show="expandedIntervalId === {{ $entry->id }}"
                                            x-transition:enter="transition ease-out duration-200"
                                            x-transition:enter-start="opacity-0 -translate-y-1"
                                            x-transition:enter-end="opacity-100 translate-y-0"
                                            x-transition:leave="transition ease-in duration-150"
                                            x-transition:leave-start="opacity-100 translate-y-0"
                                            x-transition:leave-end="opacity-0 -translate-y-1"
                                            class="px-4 sm:px-6 pb-4 border-t border-outline/50 dark:border-outline/50 bg-surface/50 dark:bg-surface/30"
                                            x-cloak
                                        >
                                            <div class="pt-4">
                                                <h4 class="text-sm font-semibold text-on-surface-strong mb-3 flex items-center gap-2">
                                                    <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                                    {{ __('Order Time Interval') }}
                                                </h4>

                                                <form @submit.prevent="$action('{{ url('/dashboard/schedule/' . $entry->id . '/order-interval') }}', { method: 'PUT' })">
                                                    {{-- Start Time Row --}}
                                                    <div class="mb-4">
                                                        <label class="block text-sm font-medium text-on-surface mb-1.5">
                                                            {{ __('Start accepting orders') }} <span class="text-danger">*</span>
                                                        </label>
                                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                            {{-- Start Time --}}
                                                            <div>
                                                                <select
                                                                    x-model="order_start_time"
                                                                    x-name="order_start_time"
                                                                    class="w-full rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-colors duration-200"
                                                                >
                                                                    @for($h = 0; $h < 24; $h++)
                                                                        @for($m = 0; $m < 60; $m += 30)
                                                                            @php
                                                                                $timeVal = sprintf('%02d:%02d', $h, $m);
                                                                                $displayTime = date('g:i A', strtotime($timeVal));
                                                                            @endphp
                                                                            <option value="{{ $timeVal }}">{{ $displayTime }}</option>
                                                                        @endfor
                                                                    @endfor
                                                                </select>
                                                                <p x-message="order_start_time" class="mt-1 text-sm text-danger"></p>
                                                            </div>

                                                            {{-- Start Day Offset --}}
                                                            <div>
                                                                <select
                                                                    x-model="order_start_day_offset"
                                                                    x-name="order_start_day_offset"
                                                                    class="w-full rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-colors duration-200"
                                                                >
                                                                    @foreach(\App\Models\CookSchedule::getStartDayOffsetOptions() as $offsetVal => $offsetLabel)
                                                                        <option value="{{ $offsetVal }}">{{ ucfirst($offsetLabel) }}</option>
                                                                    @endforeach
                                                                </select>
                                                                <p x-message="order_start_day_offset" class="mt-1 text-sm text-danger"></p>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {{-- End Time Row --}}
                                                    <div class="mb-4">
                                                        <label class="block text-sm font-medium text-on-surface mb-1.5">
                                                            {{ __('Stop accepting orders') }} <span class="text-danger">*</span>
                                                        </label>
                                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                            {{-- End Time --}}
                                                            <div>
                                                                <select
                                                                    x-model="order_end_time"
                                                                    x-name="order_end_time"
                                                                    class="w-full rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-colors duration-200"
                                                                >
                                                                    @for($h = 0; $h < 24; $h++)
                                                                        @for($m = 0; $m < 60; $m += 30)
                                                                            @php
                                                                                $timeVal = sprintf('%02d:%02d', $h, $m);
                                                                                $displayTime = date('g:i A', strtotime($timeVal));
                                                                            @endphp
                                                                            <option value="{{ $timeVal }}">{{ $displayTime }}</option>
                                                                        @endfor
                                                                    @endfor
                                                                </select>
                                                                <p x-message="order_end_time" class="mt-1 text-sm text-danger"></p>
                                                            </div>

                                                            {{-- End Day Offset --}}
                                                            <div>
                                                                <select
                                                                    x-model="order_end_day_offset"
                                                                    x-name="order_end_day_offset"
                                                                    class="w-full rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-colors duration-200"
                                                                >
                                                                    @foreach(\App\Models\CookSchedule::getEndDayOffsetOptions() as $offsetVal => $offsetLabel)
                                                                        <option value="{{ $offsetVal }}">{{ ucfirst($offsetLabel) }}</option>
                                                                    @endforeach
                                                                </select>
                                                                <p x-message="order_end_day_offset" class="mt-1 text-sm text-danger"></p>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {{-- Live Preview --}}
                                                    <div class="mb-4 p-3 rounded-lg bg-info-subtle border border-info/20">
                                                        <div class="flex items-start gap-2">
                                                            <svg class="w-4 h-4 mt-0.5 shrink-0 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                                                            <div class="text-sm text-info">
                                                                <span class="font-medium">{{ __('Preview') }}:</span>
                                                                <span x-text="getIntervalPreview()"></span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {{-- Action Buttons --}}
                                                    <div class="flex items-center justify-end gap-3">
                                                        <button
                                                            type="button"
                                                            @click="expandedIntervalId = null"
                                                            class="px-4 py-2 rounded-lg text-sm font-medium text-on-surface hover:bg-surface-alt border border-outline transition-colors duration-200"
                                                        >
                                                            {{ __('Cancel') }}
                                                        </button>
                                                        <button
                                                            type="submit"
                                                            class="inline-flex items-center gap-2 px-4 py-2 bg-primary hover:bg-primary-hover text-on-primary rounded-lg text-sm font-medium transition-colors duration-200 shadow-sm disabled:opacity-50"
                                                            :disabled="$fetching()"
                                                        >
                                                            <span x-show="!$fetching()">{{ __('Save Interval') }}</span>
                                                            <span x-show="$fetching()" class="flex items-center gap-2" x-cloak>
                                                                <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                                {{ __('Saving...') }}
                                                            </span>
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    @endif

                                    {{-- F-100: Delivery/Pickup Interval Configuration Form (collapsible) --}}
                                    @if($entry->is_available && $entry->hasOrderInterval())
                                        <div
                                            x-show="expandedDeliveryPickupId === {{ $entry->id }}"
                                            x-transition:enter="transition ease-out duration-200"
                                            x-transition:enter-start="opacity-0 -translate-y-1"
                                            x-transition:enter-end="opacity-100 translate-y-0"
                                            x-transition:leave="transition ease-in duration-150"
                                            x-transition:leave-start="opacity-100 translate-y-0"
                                            x-transition:leave-end="opacity-0 -translate-y-1"
                                            class="px-4 sm:px-6 pb-4 border-t border-outline/50 dark:border-outline/50 bg-surface/50 dark:bg-surface/30"
                                            x-cloak
                                        >
                                            <div class="pt-4">
                                                <h4 class="text-sm font-semibold text-on-surface-strong mb-3 flex items-center gap-2">
                                                    <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"></path><path d="M15 18H9"></path><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path><circle cx="7" cy="18" r="2"></circle><circle cx="17" cy="18" r="2"></circle></svg>
                                                    {{ __('Delivery & Pickup Intervals') }}
                                                </h4>

                                                {{-- BR-117/BR-118: Info about order interval constraint --}}
                                                <div class="mb-4 p-3 rounded-lg bg-info-subtle border border-info/20">
                                                    <div class="flex items-start gap-2">
                                                        <svg class="w-4 h-4 mt-0.5 shrink-0 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                                                        <div class="text-sm text-info">
                                                            {{ __('Delivery and pickup windows must start at or after the order interval end time.') }}
                                                            @if($entry->order_end_day_offset == 0 && $entry->order_end_time)
                                                                <span class="font-medium">({{ date('g:i A', strtotime(substr($entry->order_end_time, 0, 5))) }})</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>

                                                <form @submit.prevent="$action('{{ url('/dashboard/schedule/' . $entry->id . '/delivery-pickup-interval') }}', { method: 'PUT' })">
                                                    {{-- Delivery Section --}}
                                                    <div class="mb-5 p-4 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface">
                                                        <div class="flex items-center justify-between mb-3">
                                                            <label class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                                                                <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"></path><path d="M15 18H9"></path><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path><circle cx="7" cy="18" r="2"></circle><circle cx="17" cy="18" r="2"></circle></svg>
                                                                {{ __('Delivery') }}
                                                            </label>
                                                            <label class="flex items-center gap-2 cursor-pointer">
                                                                <span class="text-xs text-on-surface/60" x-text="delivery_enabled === 'true' ? '{{ __('Enabled') }}' : '{{ __('Disabled') }}'"></span>
                                                                <button
                                                                    type="button"
                                                                    @click="delivery_enabled = delivery_enabled === 'true' ? 'false' : 'true'"
                                                                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary/50"
                                                                    :class="delivery_enabled === 'true' ? 'bg-primary' : 'bg-on-surface/20'"
                                                                    role="switch"
                                                                    :aria-checked="delivery_enabled === 'true'"
                                                                >
                                                                    <span
                                                                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                                                        :class="delivery_enabled === 'true' ? 'translate-x-5' : 'translate-x-0'"
                                                                    ></span>
                                                                </button>
                                                            </label>
                                                        </div>

                                                        <div x-show="delivery_enabled === 'true'" x-transition x-cloak>
                                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                                {{-- Delivery Start Time --}}
                                                                <div>
                                                                    <label class="block text-xs font-medium text-on-surface mb-1">
                                                                        {{ __('Start Time') }} <span class="text-danger">*</span>
                                                                    </label>
                                                                    <select
                                                                        x-model="delivery_start_time"
                                                                        x-name="delivery_start_time"
                                                                        class="w-full rounded-lg border border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt text-on-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-colors duration-200"
                                                                    >
                                                                        @for($h = 0; $h < 24; $h++)
                                                                            @for($m = 0; $m < 60; $m += 30)
                                                                                @php
                                                                                    $timeVal = sprintf('%02d:%02d', $h, $m);
                                                                                    $displayTime = date('g:i A', strtotime($timeVal));
                                                                                @endphp
                                                                                <option value="{{ $timeVal }}">{{ $displayTime }}</option>
                                                                            @endfor
                                                                        @endfor
                                                                    </select>
                                                                    <p x-message="delivery_start_time" class="mt-1 text-xs text-danger"></p>
                                                                </div>

                                                                {{-- Delivery End Time --}}
                                                                <div>
                                                                    <label class="block text-xs font-medium text-on-surface mb-1">
                                                                        {{ __('End Time') }} <span class="text-danger">*</span>
                                                                    </label>
                                                                    <select
                                                                        x-model="delivery_end_time"
                                                                        x-name="delivery_end_time"
                                                                        class="w-full rounded-lg border border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt text-on-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-colors duration-200"
                                                                    >
                                                                        @for($h = 0; $h < 24; $h++)
                                                                            @for($m = 0; $m < 60; $m += 30)
                                                                                @php
                                                                                    $timeVal = sprintf('%02d:%02d', $h, $m);
                                                                                    $displayTime = date('g:i A', strtotime($timeVal));
                                                                                @endphp
                                                                                <option value="{{ $timeVal }}">{{ $displayTime }}</option>
                                                                            @endfor
                                                                        @endfor
                                                                    </select>
                                                                    <p x-message="delivery_end_time" class="mt-1 text-xs text-danger"></p>
                                                                </div>
                                                            </div>

                                                            {{-- Delivery Preview --}}
                                                            <div class="mt-2 text-xs text-on-surface/60" x-show="delivery_start_time && delivery_end_time">
                                                                <span class="font-medium">{{ __('Preview') }}:</span>
                                                                <span x-text="getDeliveryPreview()"></span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {{-- Pickup Section --}}
                                                    <div class="mb-5 p-4 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface">
                                                        <div class="flex items-center justify-between mb-3">
                                                            <label class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                                                                <svg class="w-4 h-4 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9Z"></path><path d="m3 9 2.45-4.9A2 2 0 0 1 7.24 3h9.52a2 2 0 0 1 1.8 1.1L21 9"></path><path d="M12 3v6"></path></svg>
                                                                {{ __('Pickup') }}
                                                            </label>
                                                            <label class="flex items-center gap-2 cursor-pointer">
                                                                <span class="text-xs text-on-surface/60" x-text="pickup_enabled === 'true' ? '{{ __('Enabled') }}' : '{{ __('Disabled') }}'"></span>
                                                                <button
                                                                    type="button"
                                                                    @click="pickup_enabled = pickup_enabled === 'true' ? 'false' : 'true'"
                                                                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary/50"
                                                                    :class="pickup_enabled === 'true' ? 'bg-primary' : 'bg-on-surface/20'"
                                                                    role="switch"
                                                                    :aria-checked="pickup_enabled === 'true'"
                                                                >
                                                                    <span
                                                                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                                                        :class="pickup_enabled === 'true' ? 'translate-x-5' : 'translate-x-0'"
                                                                    ></span>
                                                                </button>
                                                            </label>
                                                        </div>

                                                        <div x-show="pickup_enabled === 'true'" x-transition x-cloak>
                                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                                {{-- Pickup Start Time --}}
                                                                <div>
                                                                    <label class="block text-xs font-medium text-on-surface mb-1">
                                                                        {{ __('Start Time') }} <span class="text-danger">*</span>
                                                                    </label>
                                                                    <select
                                                                        x-model="pickup_start_time"
                                                                        x-name="pickup_start_time"
                                                                        class="w-full rounded-lg border border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt text-on-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-colors duration-200"
                                                                    >
                                                                        @for($h = 0; $h < 24; $h++)
                                                                            @for($m = 0; $m < 60; $m += 30)
                                                                                @php
                                                                                    $timeVal = sprintf('%02d:%02d', $h, $m);
                                                                                    $displayTime = date('g:i A', strtotime($timeVal));
                                                                                @endphp
                                                                                <option value="{{ $timeVal }}">{{ $displayTime }}</option>
                                                                            @endfor
                                                                        @endfor
                                                                    </select>
                                                                    <p x-message="pickup_start_time" class="mt-1 text-xs text-danger"></p>
                                                                </div>

                                                                {{-- Pickup End Time --}}
                                                                <div>
                                                                    <label class="block text-xs font-medium text-on-surface mb-1">
                                                                        {{ __('End Time') }} <span class="text-danger">*</span>
                                                                    </label>
                                                                    <select
                                                                        x-model="pickup_end_time"
                                                                        x-name="pickup_end_time"
                                                                        class="w-full rounded-lg border border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt text-on-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-colors duration-200"
                                                                    >
                                                                        @for($h = 0; $h < 24; $h++)
                                                                            @for($m = 0; $m < 60; $m += 30)
                                                                                @php
                                                                                    $timeVal = sprintf('%02d:%02d', $h, $m);
                                                                                    $displayTime = date('g:i A', strtotime($timeVal));
                                                                                @endphp
                                                                                <option value="{{ $timeVal }}">{{ $displayTime }}</option>
                                                                            @endfor
                                                                        @endfor
                                                                    </select>
                                                                    <p x-message="pickup_end_time" class="mt-1 text-xs text-danger"></p>
                                                                </div>
                                                            </div>

                                                            {{-- Pickup Preview --}}
                                                            <div class="mt-2 text-xs text-on-surface/60" x-show="pickup_start_time && pickup_end_time">
                                                                <span class="font-medium">{{ __('Preview') }}:</span>
                                                                <span x-text="getPickupPreview()"></span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {{-- BR-121: Warning when both disabled --}}
                                                    <div
                                                        x-show="delivery_enabled === 'false' && pickup_enabled === 'false'"
                                                        x-transition
                                                        class="mb-4 p-3 rounded-lg bg-warning-subtle border border-warning/20 text-sm text-warning"
                                                        x-cloak
                                                    >
                                                        <div class="flex items-start gap-2">
                                                            <svg class="w-4 h-4 mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                                                            <span>{{ __('At least one of delivery or pickup must be enabled.') }}</span>
                                                        </div>
                                                    </div>

                                                    <p x-message="delivery_enabled" class="mb-2 text-sm text-danger"></p>

                                                    {{-- Action Buttons --}}
                                                    <div class="flex items-center justify-end gap-3">
                                                        <button
                                                            type="button"
                                                            @click="expandedDeliveryPickupId = null"
                                                            class="px-4 py-2 rounded-lg text-sm font-medium text-on-surface hover:bg-surface-alt border border-outline transition-colors duration-200"
                                                        >
                                                            {{ __('Cancel') }}
                                                        </button>
                                                        <button
                                                            type="submit"
                                                            class="inline-flex items-center gap-2 px-4 py-2 bg-primary hover:bg-primary-hover text-on-primary rounded-lg text-sm font-medium transition-colors duration-200 shadow-sm disabled:opacity-50"
                                                            :disabled="$fetching()"
                                                        >
                                                            <span x-show="!$fetching()">{{ __('Save Intervals') }}</span>
                                                            <span x-show="$fetching()" class="flex items-center gap-2" x-cloak>
                                                                <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                                {{ __('Saving...') }}
                                                            </span>
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        {{-- No entries for this day --}}
                        <div class="px-4 sm:px-6 py-4 text-center">
                            <p class="text-sm text-on-surface/40 italic">{{ __('No schedule entries for this day.') }}</p>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
