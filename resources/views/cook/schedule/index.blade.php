{{--
    Cook Day Schedule Management
    ----------------------------
    F-098: Cook Day Schedule Creation
    F-099: Order Time Interval Configuration

    Allows the cook to create schedule entries for specific days of the week.
    Multiple entries per day (e.g., Lunch, Dinner) up to configurable max (default 3).
    Entries grouped by day in a card layout.
    Each available entry can have an order time interval configured.

    Business Rules:
    BR-098: Each schedule entry belongs to a single day of the week (Monday-Sunday)
    BR-099: Entry has an availability flag: available or unavailable
    BR-100: Multiple entries per day up to configurable maximum (default 3)
    BR-101: Unavailable entries cannot have time intervals configured
    BR-102: Schedule entries are tenant-scoped
    BR-103: Only users with can-manage-schedules permission
    BR-104: Schedule creation logged via Spatie Activitylog
    BR-105: Label defaults to "Slot N" based on position when empty
    BR-106: Order interval start = time + day offset (0-7)
    BR-107: Order interval end = time + day offset (0-1)
    BR-108: Start must be chronologically before end
    BR-109: Time format is 24-hour (HH:MM)
    BR-110: Start day offset max 7
    BR-111: End day offset max 1
    BR-112: Only available entries can have intervals configured
    BR-115: Interval config logged via Spatie Activitylog
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
        order_start_time: '06:00',
        order_start_day_offset: '0',
        order_end_time: '10:00',
        order_end_day_offset: '0',

        toggleInterval(entryId, startTime, startOffset, endTime, endOffset) {
            if (this.expandedIntervalId === entryId) {
                this.expandedIntervalId = null;
                return;
            }
            this.expandedIntervalId = entryId;
            this.order_start_time = startTime || '06:00';
            this.order_start_day_offset = String(startOffset ?? 0);
            this.order_end_time = endTime || '10:00';
            this.order_end_day_offset = String(endOffset ?? 0);
        },

        getIntervalPreview() {
            if (!this.order_start_time || !this.order_end_time) return '';
            const startLabel = this.formatDayOffset(parseInt(this.order_start_day_offset));
            const endLabel = this.formatDayOffset(parseInt(this.order_end_day_offset));
            const startFormatted = this.formatTime(this.order_start_time);
            const endFormatted = this.formatTime(this.order_end_time);
            return startFormatted + ' ' + startLabel + ' {{ __('to') }} ' + endFormatted + ' ' + endLabel;
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
    x-sync="['day_of_week', 'is_available', 'label', 'order_start_time', 'order_start_day_offset', 'order_end_time', 'order_end_day_offset']"
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
        <button
            @click="showAddForm = !showAddForm"
            class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary hover:bg-primary-hover text-on-primary rounded-lg text-sm font-medium transition-colors duration-200 shadow-sm"
        >
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
            <span x-text="showAddForm ? '{{ __('Cancel') }}' : '{{ __('Add Schedule') }}'"></span>
        </button>
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
                                                {{-- F-099: Order interval status + config toggle --}}
                                                @if($entry->hasOrderInterval())
                                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-info-subtle text-info hidden sm:inline-flex" title="{{ $entry->order_interval_summary }}">
                                                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                                        {{ __('Orders') }}
                                                    </span>
                                                @endif
                                                <button
                                                    @click="toggleInterval({{ $entry->id }}, '{{ $entry->order_start_time ? substr($entry->order_start_time, 0, 5) : '' }}', '{{ $entry->order_start_day_offset }}', '{{ $entry->order_end_time ? substr($entry->order_end_time, 0, 5) : '' }}', '{{ $entry->order_end_day_offset }}')"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium transition-colors duration-200"
                                                    :class="expandedIntervalId === {{ $entry->id }} ? 'bg-primary text-on-primary' : 'bg-surface dark:bg-surface border border-outline text-on-surface hover:bg-primary-subtle hover:text-primary'"
                                                    title="{{ __('Configure Order Interval') }}"
                                                >
                                                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                                    <span class="hidden sm:inline">{{ $entry->hasOrderInterval() ? __('Edit') : __('Set') }}</span>
                                                </button>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- F-099: Order Interval Summary (shown when interval is set but form is collapsed) --}}
                                    @if($entry->is_available && $entry->hasOrderInterval())
                                        <div
                                            x-show="expandedIntervalId !== {{ $entry->id }}"
                                            class="px-4 sm:px-6 pb-3 -mt-1"
                                        >
                                            <div class="flex items-center gap-2 text-xs text-on-surface/60">
                                                <svg class="w-3.5 h-3.5 text-info shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                                <span>{{ __('Orders') }}: {{ $entry->order_interval_summary }}</span>
                                            </div>
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
