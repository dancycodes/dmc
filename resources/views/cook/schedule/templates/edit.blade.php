{{--
    Edit Schedule Template
    ----------------------
    F-103: Edit Schedule Template

    Same form layout as template creation (F-101) but pre-populated
    with existing values. Includes a banner noting that changes
    will not affect schedules already using this template.

    Business Rules:
    BR-140: All validation rules from F-099 and F-100 apply
    BR-141: Template name must remain unique within the tenant
    BR-142: Editing does NOT propagate changes to day schedules
    BR-143: To update day schedules, re-apply via F-105
    BR-144: Template edits logged via Spatie Activitylog
    BR-145: Permission-based access (can-manage-schedules)
--}}
@extends('layouts.cook-dashboard')

@section('title', __('Edit Template'))
@section('page-title', __('Edit Template'))

@section('content')
<div
    class="max-w-4xl mx-auto"
    x-data="{
        name: '{{ addslashes($template->name) }}',
        order_start_time: '{{ $template->order_start_time }}',
        order_start_day_offset: '{{ $template->order_start_day_offset }}',
        order_end_time: '{{ $template->order_end_time }}',
        order_end_day_offset: '{{ $template->order_end_day_offset }}',
        delivery_enabled: '{{ $template->delivery_enabled ? 'true' : 'false' }}',
        delivery_start_time: '{{ $template->delivery_start_time ?? '11:00' }}',
        delivery_end_time: '{{ $template->delivery_end_time ?? '14:00' }}',
        pickup_enabled: '{{ $template->pickup_enabled ? 'true' : 'false' }}',
        pickup_start_time: '{{ $template->pickup_start_time ?? '10:30' }}',
        pickup_end_time: '{{ $template->pickup_end_time ?? '15:00' }}',

        getOrderPreview() {
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
    x-sync="['name', 'order_start_time', 'order_start_day_offset', 'order_end_time', 'order_end_day_offset', 'delivery_enabled', 'delivery_start_time', 'delivery_end_time', 'pickup_enabled', 'pickup_start_time', 'pickup_end_time']"
>
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
        <span class="text-on-surface-strong font-medium">{{ __('Edit') }}</span>
    </nav>

    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-on-surface-strong">{{ __('Edit Template') }}</h2>
            <p class="text-sm text-on-surface/60 mt-1">
                {{ __('Modify the schedule template settings.') }}
            </p>
        </div>
        <a
            href="{{ url('/dashboard/schedule/templates') }}"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium text-on-surface hover:bg-surface-alt border border-outline transition-colors duration-200"
        >
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
            {{ __('Back to Templates') }}
        </a>
    </div>

    {{-- BR-142/BR-143: Non-propagation info banner --}}
    <div class="mb-6 p-4 rounded-lg border bg-info-subtle border-info/20 text-sm text-info flex items-start gap-3">
        <svg class="w-5 h-5 shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
        <span>{{ __('Changes will not affect schedules already using this template. Re-apply to update them.') }}</span>
    </div>

    {{-- Template Edit Form --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline shadow-card overflow-hidden">
        <form @submit.prevent="$action('{{ url('/dashboard/schedule/templates/' . $template->id) }}', { method: 'PUT' })">
            <div class="p-6 space-y-6">
                {{-- Template Name --}}
                <div>
                    <label for="template_name" class="block text-sm font-medium text-on-surface mb-1.5">
                        {{ __('Template Name') }} <span class="text-danger">*</span>
                    </label>
                    <input
                        type="text"
                        id="template_name"
                        x-model="name"
                        x-name="name"
                        maxlength="100"
                        placeholder="{{ __('e.g., Lunch Service, Dinner Service') }}"
                        class="w-full rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface px-3 py-2.5 text-sm placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-colors duration-200"
                    >
                    <p x-message="name" class="mt-1 text-sm text-danger"></p>
                    <p class="mt-1 text-xs text-on-surface/40">{{ __('Must be unique within your templates.') }}</p>
                </div>

                {{-- Divider --}}
                <hr class="border-outline dark:border-outline">

                {{-- Order Interval Section --}}
                <div>
                    <h3 class="text-base font-semibold text-on-surface-strong mb-1 flex items-center gap-2">
                        <svg class="w-5 h-5 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        {{ __('Order Interval') }}
                        <span class="text-danger text-sm">*</span>
                    </h3>
                    <p class="text-sm text-on-surface/60 mb-4">{{ __('When clients can place orders relative to the open day.') }}</p>

                    {{-- Start Time Row --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-on-surface mb-1.5">
                            {{ __('Start accepting orders') }} <span class="text-danger">*</span>
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
                            <div>
                                <select
                                    x-model="order_start_day_offset"
                                    x-name="order_start_day_offset"
                                    class="w-full rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-colors duration-200"
                                >
                                    @foreach($startDayOffsetOptions as $offsetVal => $offsetLabel)
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
                            <div>
                                <select
                                    x-model="order_end_day_offset"
                                    x-name="order_end_day_offset"
                                    class="w-full rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-colors duration-200"
                                >
                                    @foreach($endDayOffsetOptions as $offsetVal => $offsetLabel)
                                        <option value="{{ $offsetVal }}">{{ ucfirst($offsetLabel) }}</option>
                                    @endforeach
                                </select>
                                <p x-message="order_end_day_offset" class="mt-1 text-sm text-danger"></p>
                            </div>
                        </div>
                    </div>

                    {{-- Order Interval Preview --}}
                    <div class="p-3 rounded-lg bg-info-subtle border border-info/20">
                        <div class="flex items-start gap-2">
                            <svg class="w-4 h-4 mt-0.5 shrink-0 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                            <div class="text-sm text-info">
                                <span class="font-medium">{{ __('Preview') }}:</span>
                                <span x-text="getOrderPreview()"></span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Divider --}}
                <hr class="border-outline dark:border-outline">

                {{-- Delivery Section --}}
                <div class="p-4 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface">
                    <div class="flex items-center justify-between mb-3">
                        <label class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                            <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"></path><path d="M15 18H9"></path><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path><circle cx="7" cy="18" r="2"></circle><circle cx="17" cy="18" r="2"></circle></svg>
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
                <div class="p-4 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface">
                    <div class="flex items-center justify-between mb-3">
                        <label class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                            <svg class="w-5 h-5 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9Z"></path><path d="m3 9 2.45-4.9A2 2 0 0 1 7.24 3h9.52a2 2 0 0 1 1.8 1.1L21 9"></path><path d="M12 3v6"></path></svg>
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

                {{-- BR-130: Warning when both disabled --}}
                <div
                    x-show="delivery_enabled === 'false' && pickup_enabled === 'false'"
                    x-transition
                    class="p-3 rounded-lg bg-warning-subtle border border-warning/20 text-sm text-warning"
                    x-cloak
                >
                    <div class="flex items-start gap-2">
                        <svg class="w-4 h-4 mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                        <span>{{ __('At least one of delivery or pickup must be enabled.') }}</span>
                    </div>
                </div>

                <p x-message="delivery_enabled" class="text-sm text-danger"></p>

                {{-- Divider --}}
                <hr class="border-outline dark:border-outline">

                {{-- Preview Summary --}}
                <div class="p-4 rounded-lg bg-surface dark:bg-surface border border-outline dark:border-outline">
                    <h4 class="text-sm font-semibold text-on-surface-strong mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        {{ __('Template Summary') }}
                    </h4>
                    <div class="space-y-2">
                        {{-- Template Name --}}
                        <div class="flex items-center gap-2 text-sm">
                            <span class="font-medium text-on-surface w-20">{{ __('Name') }}:</span>
                            <span class="text-on-surface/80" x-text="name || '{{ __('Not set') }}'"></span>
                        </div>

                        {{-- Order Interval --}}
                        <div class="flex items-center gap-2 text-sm">
                            <span class="font-medium text-on-surface w-20">{{ __('Orders') }}:</span>
                            <span class="text-on-surface/80 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-info shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                <span x-text="getOrderPreview()"></span>
                            </span>
                        </div>

                        {{-- Delivery --}}
                        <div class="flex items-center gap-2 text-sm">
                            <span class="font-medium text-on-surface w-20">{{ __('Delivery') }}:</span>
                            <span class="text-on-surface/80 flex items-center gap-1.5">
                                <template x-if="delivery_enabled === 'true'">
                                    <span class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-primary shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"></path><path d="M15 18H9"></path><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path><circle cx="7" cy="18" r="2"></circle><circle cx="17" cy="18" r="2"></circle></svg>
                                        <span x-text="getDeliveryPreview() || '{{ __('Enabled') }}'"></span>
                                    </span>
                                </template>
                                <template x-if="delivery_enabled !== 'true'">
                                    <span class="text-on-surface/40 italic">{{ __('Disabled') }}</span>
                                </template>
                            </span>
                        </div>

                        {{-- Pickup --}}
                        <div class="flex items-center gap-2 text-sm">
                            <span class="font-medium text-on-surface w-20">{{ __('Pickup') }}:</span>
                            <span class="text-on-surface/80 flex items-center gap-1.5">
                                <template x-if="pickup_enabled === 'true'">
                                    <span class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-secondary shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9Z"></path><path d="m3 9 2.45-4.9A2 2 0 0 1 7.24 3h9.52a2 2 0 0 1 1.8 1.1L21 9"></path><path d="M12 3v6"></path></svg>
                                        <span x-text="getPickupPreview() || '{{ __('Enabled') }}'"></span>
                                    </span>
                                </template>
                                <template x-if="pickup_enabled !== 'true'">
                                    <span class="text-on-surface/40 italic">{{ __('Disabled') }}</span>
                                </template>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Form Actions --}}
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-outline dark:border-outline bg-surface/50 dark:bg-surface/30">
                <a
                    href="{{ url('/dashboard/schedule/templates') }}"
                    class="px-4 py-2.5 rounded-lg text-sm font-medium text-on-surface hover:bg-surface-alt border border-outline transition-colors duration-200"
                >
                    {{ __('Cancel') }}
                </a>
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary hover:bg-primary-hover text-on-primary rounded-lg text-sm font-medium transition-colors duration-200 shadow-sm disabled:opacity-50"
                    :disabled="$fetching()"
                >
                    <span x-show="!$fetching()">{{ __('Save Changes') }}</span>
                    <span x-show="$fetching()" class="flex items-center gap-2" x-cloak>
                        <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        {{ __('Saving...') }}
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
