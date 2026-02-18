{{--
    Schedule Template Application to Days
    --------------------------------------
    F-105: Schedule Template Application to Days

    Allows a cook to apply a schedule template to one or more days of the week.
    Copies the template's interval configurations to create or overwrite
    schedule entries for the selected days.

    Business Rules:
    BR-153: Applying copies values (not a live link)
    BR-154: At least one day must be selected
    BR-155: Confirmation dialog warns about overwriting existing schedules
    BR-156: Overwrites all interval values on existing entries
    BR-157: Sets template_id reference for tracking
    BR-158: Availability set to true for applied entries
    BR-159: Multiple entries per day replaced with one template-based entry
    BR-160: Logged via Spatie Activitylog
    BR-161: Permission-gated (can-manage-schedules)

    UI/UX Notes:
    - Day selection via checkboxes (Mon through Sun)
    - Days with existing schedules marked with warning icon
    - Confirmation dialog lists days that will be overwritten
    - Success toast after application
    - Mobile-friendly checkbox layout
--}}
@extends('layouts.cook-dashboard')

@section('title', __('Apply Template'))
@section('page-title', __('Apply Template'))

@section('content')
<div class="max-w-3xl mx-auto" x-data="{
    days: [],
    showConfirmModal: false,

    daysWithSchedules: @js($daysWithSchedules),
    dayLabels: @js(collect($dayLabels)->mapWithKeys(fn ($label, $day) => [$day => __($label)])->all()),

    get selectedDaysWithExisting() {
        return this.days.filter(d => this.daysWithSchedules.includes(d));
    },

    get hasConflicts() {
        return this.selectedDaysWithExisting.length > 0;
    },

    toggleDay(day) {
        const idx = this.days.indexOf(day);
        if (idx > -1) {
            this.days.splice(idx, 1);
        } else {
            this.days.push(day);
        }
    },

    selectAll() {
        this.days = [...@js($daysOfWeek)];
    },

    deselectAll() {
        this.days = [];
    },

    submitApply() {
        if (this.days.length === 0) return;
        if (this.hasConflicts) {
            this.showConfirmModal = true;
        } else {
            this.executeApply();
        }
    },

    cancelConfirm() {
        this.showConfirmModal = false;
    },

    executeApply() {
        this.showConfirmModal = false;
        $action('/dashboard/schedule/templates/{{ $template->id }}/apply', {
            include: ['days']
        });
    }
}">
    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm text-on-surface/60 mb-6" aria-label="{{ __('Breadcrumb') }}">
        <a href="{{ url('/dashboard') }}" class="hover:text-primary transition-colors duration-200">
            {{ __('Dashboard') }}
        </a>
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
        <a href="{{ url('/dashboard/schedule') }}" class="hover:text-primary transition-colors duration-200">
            {{ __('Schedule') }}
        </a>
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
        <a href="{{ url('/dashboard/schedule/templates') }}" class="hover:text-primary transition-colors duration-200">
            {{ __('Templates') }}
        </a>
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
        <span class="text-on-surface-strong font-medium">{{ __('Apply') }}</span>
    </nav>

    {{-- Template Summary Card --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline shadow-card p-5 mb-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-info-subtle flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"></rect><line x1="16" x2="16" y1="2" y2="6"></line><line x1="8" x2="8" y1="2" y2="6"></line><line x1="3" x2="21" y1="10" y2="10"></line></svg>
            </div>
            <div>
                <h2 class="text-lg font-semibold text-on-surface-strong">{{ __('Apply Template') }}</h2>
                <p class="text-sm text-on-surface/60">{{ $template->name }}</p>
            </div>
        </div>

        {{-- Template Interval Summary --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            {{-- Order Window --}}
            <div class="p-3 bg-surface dark:bg-surface rounded-lg border border-outline/50">
                <div class="flex items-center gap-1.5 mb-1">
                    <svg class="w-3.5 h-3.5 text-info shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    <span class="text-xs font-medium text-on-surface/60">{{ __('Orders') }}</span>
                </div>
                <p class="text-xs text-on-surface/80">{{ $template->order_interval_summary }}</p>
            </div>

            {{-- Delivery --}}
            <div class="p-3 bg-surface dark:bg-surface rounded-lg border border-outline/50">
                <div class="flex items-center gap-1.5 mb-1">
                    <svg class="w-3.5 h-3.5 text-primary shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"></path><path d="M15 18H9"></path><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path><circle cx="7" cy="18" r="2"></circle><circle cx="17" cy="18" r="2"></circle></svg>
                    <span class="text-xs font-medium text-on-surface/60">{{ __('Delivery') }}</span>
                </div>
                @if($template->delivery_enabled && $template->delivery_interval_summary)
                    <p class="text-xs text-on-surface/80">{{ $template->delivery_interval_summary }}</p>
                @else
                    <p class="text-xs text-on-surface/40 italic">{{ __('Disabled') }}</p>
                @endif
            </div>

            {{-- Pickup --}}
            <div class="p-3 bg-surface dark:bg-surface rounded-lg border border-outline/50">
                <div class="flex items-center gap-1.5 mb-1">
                    <svg class="w-3.5 h-3.5 text-secondary shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9Z"></path><path d="m3 9 2.45-4.9A2 2 0 0 1 7.24 3h9.52a2 2 0 0 1 1.8 1.1L21 9"></path><path d="M12 3v6"></path></svg>
                    <span class="text-xs font-medium text-on-surface/60">{{ __('Pickup') }}</span>
                </div>
                @if($template->pickup_enabled && $template->pickup_interval_summary)
                    <p class="text-xs text-on-surface/80">{{ $template->pickup_interval_summary }}</p>
                @else
                    <p class="text-xs text-on-surface/40 italic">{{ __('Disabled') }}</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Day Selection Form --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline shadow-card p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-on-surface-strong">{{ __('Select Days') }}</h3>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    x-on:click="selectAll()"
                    class="text-xs font-medium text-primary hover:text-primary-hover transition-colors duration-200"
                >
                    {{ __('Select All') }}
                </button>
                <span class="text-on-surface/30">|</span>
                <button
                    type="button"
                    x-on:click="deselectAll()"
                    class="text-xs font-medium text-on-surface/60 hover:text-on-surface transition-colors duration-200"
                >
                    {{ __('Deselect All') }}
                </button>
            </div>
        </div>

        <p class="text-sm text-on-surface/60 mb-4">
            {{ __('Choose the days you want to apply this template to. Each selected day will receive the template\'s time intervals.') }}
        </p>

        {{-- Validation Error --}}
        <div x-message="days" class="mb-4 p-3 rounded-lg bg-danger-subtle border border-danger/20 text-danger text-sm hidden"></div>

        {{-- Day Checkboxes --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6">
            @foreach($daysOfWeek as $day)
                <label
                    class="relative flex items-center gap-3 p-3.5 rounded-lg border cursor-pointer transition-all duration-200"
                    x-bind:class="days.includes('{{ $day }}')
                        ? 'bg-primary-subtle border-primary ring-1 ring-primary/30'
                        : 'bg-surface dark:bg-surface border-outline hover:border-primary/50'"
                >
                    {{-- Checkbox --}}
                    <input
                        type="checkbox"
                        value="{{ $day }}"
                        x-on:change="toggleDay('{{ $day }}')"
                        x-bind:checked="days.includes('{{ $day }}')"
                        class="w-4.5 h-4.5 rounded border-outline text-primary focus:ring-primary/30 focus:ring-offset-0 shrink-0"
                    />

                    {{-- Day Name --}}
                    <span class="text-sm font-medium text-on-surface-strong flex-1">
                        {{ __(\App\Models\CookSchedule::DAY_LABELS[$day]) }}
                    </span>

                    {{-- Warning Icon for existing schedules --}}
                    @if(in_array($day, $daysWithSchedules))
                        <span
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-warning-subtle text-warning shrink-0"
                            title="{{ __('Has existing schedule') }}"
                        >
                            <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                            {{ __('Existing') }}
                        </span>
                    @endif
                </label>
            @endforeach
        </div>

        {{-- Selection Summary --}}
        <div class="flex items-center justify-between gap-4 pt-4 border-t border-outline dark:border-outline">
            <div class="text-sm text-on-surface/60">
                <span x-show="days.length === 0">{{ __('No days selected') }}</span>
                <span x-show="days.length > 0" x-cloak>
                    <span x-text="days.length"></span> {{ __('day(s) selected') }}
                    <template x-if="selectedDaysWithExisting.length > 0">
                        <span class="text-warning font-medium">
                            &mdash; <span x-text="selectedDaysWithExisting.length"></span> {{ __('will be overwritten') }}
                        </span>
                    </template>
                </span>
            </div>
            <div class="flex items-center gap-3">
                <a
                    href="{{ url('/dashboard/schedule/templates') }}"
                    class="px-4 py-2.5 rounded-lg text-sm font-medium text-on-surface hover:bg-surface border border-outline transition-colors duration-200"
                >
                    {{ __('Cancel') }}
                </a>
                <button
                    type="button"
                    x-on:click="submitApply()"
                    x-bind:disabled="days.length === 0"
                    class="px-5 py-2.5 rounded-lg text-sm font-medium bg-primary hover:bg-primary-hover text-on-primary transition-colors duration-200 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-2"
                >
                    <span x-show="!$fetching()">
                        <svg class="w-4 h-4 inline-block -mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><path d="m9 11 3 3L22 4"></path></svg>
                        {{ __('Apply Template') }}
                    </span>
                    <span x-show="$fetching()" x-cloak class="inline-flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                        {{ __('Applying...') }}
                    </span>
                </button>
            </div>
        </div>
    </div>

    {{-- BR-155: Overwrite Confirmation Modal --}}
    <div
        x-show="showConfirmModal"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        x-cloak
    >
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/50 dark:bg-black/70" x-on:click="cancelConfirm()"></div>

        {{-- Modal Content --}}
        <div
            x-show="showConfirmModal"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-dropdown max-w-md w-full p-6"
            x-on:keydown.escape.window="cancelConfirm()"
        >
            {{-- Warning Icon --}}
            <div class="flex justify-center mb-4">
                <div class="w-12 h-12 rounded-full bg-warning-subtle flex items-center justify-center">
                    <svg class="w-6 h-6 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                </div>
            </div>

            {{-- Title --}}
            <h3 class="text-lg font-semibold text-on-surface-strong text-center mb-2">
                {{ __('Overwrite Existing Schedules?') }}
            </h3>

            {{-- Description --}}
            <p class="text-sm text-on-surface/70 text-center mb-3">
                {{ __('The following days already have schedules that will be overwritten:') }}
            </p>

            {{-- Days List --}}
            <div class="p-3 rounded-lg bg-warning-subtle/50 border border-warning/20 mb-4">
                <ul class="space-y-1">
                    <template x-for="day in selectedDaysWithExisting" x-bind:key="day">
                        <li class="flex items-center gap-2 text-sm text-on-surface/80">
                            <svg class="w-3.5 h-3.5 text-warning shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                            <span x-text="dayLabels[day]"></span>
                        </li>
                    </template>
                </ul>
            </div>

            <p class="text-xs text-on-surface/50 text-center mb-4">
                {{ __('Existing schedule entries for these days will be replaced with the template\'s configuration. This cannot be undone.') }}
            </p>

            {{-- Action Buttons --}}
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    x-on:click="cancelConfirm()"
                    class="flex-1 px-4 py-2.5 rounded-lg text-sm font-medium text-on-surface hover:bg-surface border border-outline transition-colors duration-200"
                >
                    {{ __('Cancel') }}
                </button>
                <button
                    type="button"
                    x-on:click="executeApply()"
                    class="flex-1 px-4 py-2.5 rounded-lg text-sm font-medium bg-warning hover:bg-warning/90 text-on-warning transition-colors duration-200"
                >
                    {{ __('Overwrite & Apply') }}
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
