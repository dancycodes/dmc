{{--
    Create System Announcement
    --------------------------
    F-195: Form to create and send/schedule a new platform announcement.

    BR-312: Target options: all_users, all_cooks, all_clients, specific_tenant
    BR-313: Tenant dropdown shown when specific_tenant selected
    BR-314: Content max 2000 characters
    BR-315: All three channels: push + DB + email
    BR-316: Send Now dispatches immediately via queue
    BR-317: Schedule stores for dispatch at specified time
    BR-318: Scheduled time minimum 5 minutes from now
    BR-321: All text uses __() localization
--}}
@extends('layouts.admin')

@section('title', __('Create Announcement'))
@section('page-title', __('Announcements'))

@section('content')
@php
    $minScheduleDateTime = now()->addMinutes(6)->format('Y-m-d\TH:i');
@endphp

<div
    x-data="{
        content: '',
        target_type: 'all_users',
        target_tenant_id: '',
        scheduled_at: '',
        useSchedule: false,
        charCount: 0,
        maxChars: 2000,

        init() {
            this.$watch('content', (val) => {
                this.charCount = val.length;
            });
        }
    }"
    x-sync="['content', 'target_type', 'target_tenant_id', 'scheduled_at']"
    class="max-w-2xl mx-auto space-y-6"
>
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[
        ['label' => __('Announcements'), 'url' => '/vault-entry/announcements'],
        ['label' => __('Create')]
    ]" />

    <div>
        <h2 class="text-lg font-semibold text-on-surface-strong">{{ __('New Announcement') }}</h2>
        <p class="text-sm text-on-surface mt-1">{{ __('Write your message and choose who should receive it.') }}</p>
    </div>

    <div class="bg-surface-alt rounded-xl border border-outline p-6 space-y-6">

        {{-- Content --}}
        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label class="block text-sm font-medium text-on-surface-strong">
                    {{ __('Announcement Content') }}
                    <span class="text-danger">*</span>
                </label>
                <span class="text-xs text-on-surface" :class="{ 'text-danger font-medium': charCount > maxChars }">
                    <span x-text="charCount"></span>/{{ $maxChars ?? 2000 }}
                </span>
            </div>
            <textarea
                x-name="content"
                x-model="content"
                rows="6"
                maxlength="2000"
                placeholder="{{ __('Write your announcement here. Be clear and concise.') }}"
                class="w-full px-3 py-2.5 bg-surface border border-outline rounded-lg text-sm text-on-surface-strong placeholder:text-on-surface/50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary resize-none transition-colors"
            ></textarea>
            <p x-message="content" class="mt-1.5 text-xs text-danger"></p>
        </div>

        {{-- Target Audience --}}
        <div>
            <label class="block text-sm font-medium text-on-surface-strong mb-3">
                {{ __('Target Audience') }}
                <span class="text-danger">*</span>
            </label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach($targetOptions as $value => $label)
                    <label
                        class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer transition-colors"
                        :class="target_type === {{ json_encode($value) }} ? 'border-primary bg-primary-subtle' : 'border-outline bg-surface hover:bg-surface-alt'"
                    >
                        <input
                            type="radio"
                            x-model="target_type"
                            value="{{ $value }}"
                            class="w-4 h-4 text-primary border-outline focus:ring-primary/30"
                        >
                        <span class="text-sm font-medium text-on-surface-strong">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            <p x-message="target_type" class="mt-1.5 text-xs text-danger"></p>
        </div>

        {{-- Specific Tenant Dropdown --}}
        <div x-show="target_type === 'specific_tenant'" x-transition>
            <label class="block text-sm font-medium text-on-surface-strong mb-1.5">
                {{ __('Select Tenant') }}
                <span class="text-danger">*</span>
            </label>
            <select
                x-name="target_tenant_id"
                x-model="target_tenant_id"
                class="w-full px-3 py-2.5 bg-surface border border-outline rounded-lg text-sm text-on-surface-strong focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors"
            >
                <option value="">{{ __('-- Select a tenant --') }}</option>
                @foreach($tenants as $tenant)
                    <option value="{{ $tenant->id }}">{{ $tenant->name }} ({{ $tenant->slug }})</option>
                @endforeach
            </select>
            <p x-message="target_tenant_id" class="mt-1.5 text-xs text-danger"></p>
        </div>

        {{-- Schedule Toggle --}}
        <div class="border-t border-outline pt-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-on-surface-strong">{{ __('Schedule for Later') }}</p>
                    <p class="text-xs text-on-surface mt-0.5">{{ __('Send at a specific date and time instead of immediately.') }}</p>
                </div>
                <button
                    type="button"
                    role="switch"
                    :aria-checked="useSchedule"
                    @click="useSchedule = !useSchedule; if (!useSchedule) { scheduled_at = ''; }"
                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-primary/30"
                    :class="useSchedule ? 'bg-primary' : 'bg-outline-strong'"
                >
                    <span
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                        :class="useSchedule ? 'translate-x-5' : 'translate-x-0'"
                    ></span>
                </button>
            </div>

            <div x-show="useSchedule" x-transition class="mt-4">
                <label class="block text-sm font-medium text-on-surface-strong mb-1.5">
                    {{ __('Schedule Date & Time') }}
                    <span class="text-danger">*</span>
                </label>
                <input
                    type="datetime-local"
                    x-name="scheduled_at"
                    x-model="scheduled_at"
                    min="{{ $minScheduleDateTime }}"
                    class="w-full px-3 py-2.5 bg-surface border border-outline rounded-lg text-sm text-on-surface-strong focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors"
                >
                <p class="mt-1 text-xs text-on-surface">{{ __('Minimum 5 minutes from now. Time is in your local timezone.') }}</p>
                <p x-message="scheduled_at" class="mt-1.5 text-xs text-danger"></p>
            </div>
        </div>

    </div>

    {{-- Info Banner --}}
    <div class="bg-info-subtle border border-info/20 rounded-lg p-4 flex items-start gap-3">
        <svg class="w-5 h-5 text-info shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" x2="12" y1="8" y2="12"></line><line x1="12" x2="12.01" y1="16" y2="16"></line></svg>
        <div>
            <p class="text-sm font-medium text-on-surface-strong">{{ __('About Delivery') }}</p>
            <p class="text-xs text-on-surface mt-0.5">{{ __('Announcements are delivered via push notification, in-app database notification, and email to all targeted users simultaneously.') }}</p>
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex flex-col sm:flex-row gap-3 sm:justify-between">
        <a href="/vault-entry/announcements"
           class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-on-surface bg-surface border border-outline rounded-lg hover:bg-surface-alt transition-colors">
            {{ __('Cancel') }}
        </a>
        <div class="flex flex-col sm:flex-row gap-3">
            <button
                type="button"
                :disabled="$fetching()"
                @click="$action('{{ url('/vault-entry/announcements') }}', { include: ['content', 'target_type', 'target_tenant_id', 'scheduled_at'] })"
                class="inline-flex items-center justify-center gap-2 px-6 py-2.5 text-sm font-medium text-on-primary bg-primary hover:bg-primary-hover rounded-lg transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
            >
                <span x-show="!$fetching()">
                    <span x-show="!useSchedule">{{ __('Send Now') }}</span>
                    <span x-show="useSchedule">{{ __('Schedule') }}</span>
                </span>
                <span x-show="$fetching()" class="flex items-center gap-2">
                    <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    {{ __('Processing...') }}
                </span>
            </button>
        </div>
    </div>

</div>
@endsection
