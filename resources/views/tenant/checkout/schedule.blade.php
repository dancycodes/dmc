{{--
    F-148: Order Scheduling for Future Date
    BR-335: Scheduling is optional; default is the next available slot
    BR-336: Calendar shows only dates where cook has available schedule entries
    BR-337: Unavailable dates are greyed out and not selectable
    BR-338: Maximum scheduling window is 14 days from today
    BR-339: Past dates and today are not selectable
    BR-340: Selected date determines which order/delivery/pickup time windows apply
    BR-341: Cart items validated against scheduled date's meal availability
    BR-342: Warning shown if a cart item is unavailable on scheduled date
    BR-343: Order stores the scheduled date for cook reference
    BR-344: All text localized via __()
--}}
@extends('layouts.tenant-public')

@section('title', __('Schedule Order') . ' - ' . ($tenant?->name ?? config('app.name')))

@section('content')
@php
    $availableDatesList = array_values(array_filter($availableDates, fn($d) => $d['available']));
    $availableDateKeys = array_keys(array_filter($availableDates, fn($d) => $d['available']));
@endphp

{{-- Safe JSON data island — avoids HTML attribute escaping issues with double quotes --}}
<script id="cart-warnings-data" type="application/json">@json($cartWarnings)</script>

<div class="min-h-screen"
    x-data="{
        scheduleType: @js($currentScheduledDate ? 'scheduled' : 'asap'),
        scheduled_date: @js($currentScheduledDate ?? ''),
        cartWarnings: JSON.parse(document.getElementById('cart-warnings-data').textContent),
        showWarning: {{ !empty($cartWarnings) ? 'true' : 'false' }},
        savedDate: @js($currentScheduledDate ?? ''),
        savedDateFormatted: @js($currentScheduledDateFormatted ?? ''),

        get hasDate() {
            return this.scheduleType === 'scheduled' && this.scheduled_date !== '';
        },

        get canProceed() {
            return this.scheduleType === 'asap' || (this.scheduleType === 'scheduled' && this.scheduled_date !== '');
        },

        selectDate(dateStr) {
            this.scheduled_date = dateStr;
            this.showWarning = false;
            this.cartWarnings = [];
        },

        clearDate() {
            this.scheduled_date = '';
            this.scheduleType = 'asap';
            this.showWarning = false;
            this.cartWarnings = [];
            this.savedDate = '';
            this.savedDateFormatted = '';
        },

        proceedDespiteWarning() {
            this.showWarning = false;
            window.location.href = '/checkout/summary';
        }
    }"
    x-sync="['scheduleType', 'scheduled_date']"
>
    {{-- Back navigation --}}
    <div class="bg-surface dark:bg-surface border-b border-outline dark:border-outline">
        <div class="max-w-3xl mx-auto px-4 sm:px-6">
            <div class="h-12 flex items-center justify-between">
                <a href="{{ url('/checkout/phone') }}" class="flex items-center gap-2 text-sm font-medium text-on-surface hover:text-primary transition-colors duration-200" x-navigate>
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                    {{ __('Back') }}
                </a>
                <h1 class="text-sm font-semibold text-on-surface-strong">{{ __('Checkout') }}</h1>
            </div>
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
        {{-- Step indicator --}}
        <div class="mb-6">
            <div class="flex items-center gap-2 text-xs font-medium text-on-surface flex-wrap">
                {{-- Step 1: Delivery Method (completed) --}}
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-success text-on-success text-xs font-bold shrink-0">
                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                </span>
                <span class="text-success font-semibold">{{ __('Delivery Method') }}</span>
                <svg class="w-4 h-4 text-on-surface/30 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>

                {{-- Step 2: Location (completed) --}}
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-success text-on-success text-xs font-bold shrink-0">
                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                </span>
                <span class="text-success font-semibold">{{ __('Location') }}</span>
                <svg class="w-4 h-4 text-on-surface/30 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>

                {{-- Step 3: Phone (completed) --}}
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-success text-on-success text-xs font-bold shrink-0">
                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                </span>
                <span class="text-success font-semibold">{{ __('Phone') }}</span>
                <svg class="w-4 h-4 text-on-surface/30 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>

                {{-- Step 4: Schedule (current) --}}
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-primary text-on-primary text-xs font-bold shrink-0">4</span>
                <span class="text-primary font-semibold">{{ __('Schedule') }}</span>
                <svg class="w-4 h-4 text-on-surface/30 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>

                {{-- Step 5: Review (upcoming) --}}
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-outline text-on-surface text-xs font-bold shrink-0">5</span>
                <span class="text-on-surface/50">{{ __('Review') }}</span>
                <svg class="w-4 h-4 text-on-surface/30 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>

                {{-- Step 6: Payment (upcoming) --}}
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-outline text-on-surface text-xs font-bold shrink-0">6</span>
                <span class="text-on-surface/50">{{ __('Payment') }}</span>
            </div>
        </div>

        {{-- Page heading --}}
        <div class="mb-6">
            <h2 class="text-lg sm:text-xl font-display font-bold text-on-surface-strong">
                {{ __('When do you want your order?') }}
            </h2>
            <p class="text-sm text-on-surface mt-1">
                {{ __('Choose to order for the next available slot or schedule for a specific date.') }}
            </p>
        </div>

        {{-- Cart incompatibility warning (shown after server-side check) --}}
        <div x-show="showWarning" x-transition class="mb-4 rounded-xl border border-warning bg-warning-subtle px-4 py-4">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-warning shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-warning">{{ __('Some items may not be available on your selected date') }}</p>
                    <ul class="mt-2 space-y-1" x-show="cartWarnings.length > 0">
                        <template x-for="warning in cartWarnings" :key="warning.meal_id">
                            <li class="text-xs text-on-surface" x-text="warning.reason"></li>
                        </template>
                    </ul>
                    <p class="mt-2 text-xs text-on-surface/70">{{ __('You can still proceed or choose a different date.') }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="h-8 px-3 text-xs font-medium rounded-lg bg-primary text-on-primary hover:bg-primary-hover transition-colors duration-200"
                            @click="proceedDespiteWarning()"
                        >
                            {{ __('Proceed Anyway') }}
                        </button>
                        <button
                            type="button"
                            class="h-8 px-3 text-xs font-medium rounded-lg border border-outline text-on-surface hover:bg-surface-alt transition-colors duration-200"
                            @click="showWarning = false; scheduled_date = ''; scheduleType = 'asap';"
                        >
                            {{ __('Change Date') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Scheduling options --}}
        <div class="space-y-3">
            {{-- Option 1: As soon as possible (default) --}}
            <button
                type="button"
                class="w-full text-left rounded-xl border-2 px-5 py-4 transition-all duration-200"
                :class="scheduleType === 'asap'
                    ? 'border-primary bg-primary-subtle dark:bg-primary-subtle'
                    : 'border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt hover:border-primary/50'"
                @click="scheduleType = 'asap'; scheduled_date = ''; showWarning = false; cartWarnings = [];"
            >
                <div class="flex items-start gap-4">
                    <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 mt-0.5 transition-colors duration-200"
                        :class="scheduleType === 'asap' ? 'border-primary bg-primary' : 'border-outline dark:border-outline'"
                    >
                        <div class="w-2 h-2 rounded-full bg-on-primary" x-show="scheduleType === 'asap'"></div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-on-surface-strong">{{ __('As soon as possible') }}</p>
                        <p class="text-xs text-on-surface mt-0.5">
                            @if ($nextAvailableSlot['date'])
                                {{ $nextAvailableSlot['text'] }}
                            @else
                                {{ __('Next available slot based on cook schedule') }}
                            @endif
                        </p>
                    </div>
                    <div class="shrink-0">
                        <svg class="w-5 h-5 text-primary" x-show="scheduleType === 'asap'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                    </div>
                </div>
            </button>

            {{-- Option 2: Schedule for later --}}
            @if ($hasAvailableDates)
                <button
                    type="button"
                    class="w-full text-left rounded-xl border-2 px-5 py-4 transition-all duration-200"
                    :class="scheduleType === 'scheduled'
                        ? 'border-primary bg-primary-subtle dark:bg-primary-subtle'
                        : 'border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt hover:border-primary/50'"
                    @click="scheduleType = 'scheduled'"
                >
                    <div class="flex items-start gap-4">
                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 mt-0.5 transition-colors duration-200"
                            :class="scheduleType === 'scheduled' ? 'border-primary bg-primary' : 'border-outline dark:border-outline'"
                        >
                            <div class="w-2 h-2 rounded-full bg-on-primary" x-show="scheduleType === 'scheduled'"></div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-on-surface-strong">{{ __('Schedule for later') }}</p>
                            <p class="text-xs text-on-surface mt-0.5" x-show="!hasDate">
                                {{ __('Choose a specific date (up to 14 days ahead)') }}
                            </p>
                            <p class="text-xs font-medium text-primary mt-0.5" x-show="hasDate" x-text="savedDateFormatted || ''"></p>
                        </div>
                        <div class="shrink-0">
                            <svg class="w-5 h-5 text-primary" x-show="scheduleType === 'scheduled' && hasDate" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                            <svg class="w-5 h-5 text-on-surface/40" x-show="scheduleType !== 'scheduled'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"></rect><line x1="16" x2="16" y1="2" y2="6"></line><line x1="8" x2="8" y1="2" y2="6"></line><line x1="3" x2="21" y1="10" y2="10"></line></svg>
                        </div>
                    </div>
                </button>

                {{-- Calendar: shown when "Schedule for later" is selected --}}
                <div x-show="scheduleType === 'scheduled'" x-transition class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-card overflow-hidden">
                    {{-- Calendar header --}}
                    <div class="px-5 py-4 border-b border-outline dark:border-outline">
                        <h3 class="text-sm font-semibold text-on-surface-strong">{{ __('Select a date') }}</h3>
                        <p class="text-xs text-on-surface mt-0.5">
                            {{ __('Available dates are highlighted. Greyed out dates are not available.') }}
                        </p>
                    </div>

                    {{-- Date grid --}}
                    <div class="px-5 py-4">
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                            @foreach ($availableDates as $dateKey => $dateInfo)
                                @if ($dateInfo['available'])
                                    {{-- Available date --}}
                                    <button
                                        type="button"
                                        class="rounded-lg px-3 py-3 text-left transition-all duration-150 border-2 focus:outline-none focus:ring-2 focus:ring-primary/50"
                                        :class="scheduled_date === @js($dateKey)
                                            ? 'border-primary bg-primary text-on-primary shadow-card'
                                            : 'border-outline dark:border-outline bg-surface dark:bg-surface hover:border-primary hover:bg-primary-subtle'"
                                        @click="selectDate(@js($dateKey)); $action('/checkout/schedule', { include: ['scheduleType', 'scheduled_date'] })"
                                        x-data="{ localDate: @js($dateKey) }"
                                    >
                                        <span class="text-xs font-medium block leading-tight"
                                            :class="scheduled_date === @js($dateKey) ? 'text-on-primary' : 'text-on-surface'"
                                        >{{ $dateInfo['day_label'] }}</span>
                                        <span class="text-sm font-bold block mt-0.5 leading-tight"
                                            :class="scheduled_date === @js($dateKey) ? 'text-on-primary' : 'text-on-surface-strong'"
                                        >{{ $dateInfo['display_date'] }}</span>
                                        {{-- Selected indicator --}}
                                        <div x-show="scheduled_date === @js($dateKey)" class="mt-1">
                                            <svg class="w-3.5 h-3.5 text-on-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                                        </div>
                                    </button>
                                @else
                                    {{-- Unavailable date (BR-337: greyed out, not selectable) --}}
                                    <div class="rounded-lg px-3 py-3 border border-dashed border-outline/40 dark:border-outline/40 opacity-40 cursor-not-allowed" title="{{ __('Not available') }}">
                                        <span class="text-xs font-medium block leading-tight text-on-surface/50">{{ $dateInfo['day_label'] }}</span>
                                        <span class="text-sm font-bold block mt-0.5 leading-tight text-on-surface/40">{{ $dateInfo['display_date'] }}</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        {{-- Validation message for scheduled_date --}}
                        <p x-message="scheduled_date" class="mt-2 text-xs text-danger"></p>
                    </div>

                    {{-- Clear selection (shown when a date is selected) --}}
                    <div x-show="hasDate" class="px-5 pb-4">
                        <button
                            type="button"
                            class="text-xs text-on-surface/60 hover:text-danger underline transition-colors duration-150"
                            @click="clearDate()"
                        >
                            {{ __('Clear date selection') }}
                        </button>
                    </div>
                </div>
            @else
                {{-- Edge case: No available dates in the next 14 days --}}
                <div class="rounded-xl border border-dashed border-outline/60 dark:border-outline/60 px-5 py-4 bg-surface-alt/50 dark:bg-surface-alt/50">
                    <div class="flex items-center gap-3 text-on-surface/60">
                        <svg class="w-5 h-5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"></rect><line x1="16" x2="16" y1="2" y2="6"></line><line x1="8" x2="8" y1="2" y2="6"></line><line x1="3" x2="21" y1="10" y2="10"></line></svg>
                        <p class="text-sm">{{ __('No available dates for scheduling in the next 14 days.') }}</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- Action buttons (desktop) --}}
        <div class="mt-6 flex flex-col sm:flex-row gap-3 hidden sm:flex">
            <a href="{{ url('/checkout/phone') }}" class="flex-1 h-11 inline-flex items-center justify-center gap-2 border border-outline dark:border-outline text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt font-medium rounded-lg transition-all duration-200" x-navigate>
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                {{ __('Back') }}
            </a>
            <button
                type="button"
                class="flex-1 h-11 inline-flex items-center justify-center gap-2 bg-primary hover:bg-primary-hover text-on-primary font-semibold rounded-lg shadow-card transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="!canProceed || $fetching()"
                @click="canProceed && $action('/checkout/schedule', { include: ['scheduleType', 'scheduled_date'] })"
            >
                <span x-show="!$fetching()">
                    {{ __('Continue to Review') }}
                    <svg class="w-5 h-5 inline-block ml-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                </span>
                <span x-show="$fetching()" class="flex items-center gap-2">
                    <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                    {{ __('Saving...') }}
                </span>
            </button>
        </div>

        {{-- Spacer for mobile sticky bar --}}
        <div class="h-24 sm:hidden"></div>
    </div>

    {{-- Mobile sticky bar --}}
    <div class="sm:hidden fixed bottom-0 left-0 right-0 z-40 bg-surface dark:bg-surface border-t border-outline dark:border-outline px-4 py-3 shadow-dropdown">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-medium text-on-surface truncate" x-show="scheduleType === 'asap'">
                    {{ $nextAvailableSlot['text'] ?? __('Next available slot') }}
                </p>
                <p class="text-xs font-medium text-on-surface truncate" x-show="scheduleType === 'scheduled' && hasDate" x-text="savedDateFormatted || @js(__('Date selected'))"></p>
                <p class="text-xs font-medium text-on-surface truncate" x-show="scheduleType === 'scheduled' && !hasDate">
                    {{ __('Select a date above') }}
                </p>
            </div>
            <button
                type="button"
                class="shrink-0 h-11 px-6 bg-primary hover:bg-primary-hover text-on-primary font-semibold rounded-lg shadow-card transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-2"
                :disabled="!canProceed || $fetching()"
                @click="canProceed && $action('/checkout/schedule', { include: ['scheduleType', 'scheduled_date'] })"
            >
                <span x-show="!$fetching()">{{ __('Continue') }}</span>
                <svg x-show="!$fetching()" class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                <svg x-show="$fetching()" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
            </button>
        </div>
    </div>
</div>
@endsection
