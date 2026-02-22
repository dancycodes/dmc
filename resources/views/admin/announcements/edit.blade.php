{{--
    Edit Announcement
    -----------------
    F-195: Form to edit a draft or scheduled announcement.
    Only editable announcements (status: draft or scheduled) are shown here.
    The controller redirects back to index if the announcement cannot be edited.

    BR-312: Same target options as create form
    BR-314: Content max 2000 characters
    BR-318: Scheduled time must be minimum 5 minutes from now
--}}
@extends('layouts.admin')

@section('title', __('Edit Announcement'))
@section('page-title', __('Announcements'))

@section('content')
@php
    $minScheduleDateTime = now()->addMinutes(6)->format('Y-m-d\TH:i');
    $existingScheduledAt = $announcement->scheduled_at
        ? $announcement->scheduled_at->format('Y-m-d\TH:i')
        : '';
    $isScheduled = $announcement->status === \App\Models\Announcement::STATUS_SCHEDULED;
@endphp

<script type="application/json" id="edit-announcement-data">{!! json_encode([
    'content' => $announcement->content,
    'target_type' => $announcement->target_type,
    'target_tenant_id' => (string) ($announcement->target_tenant_id ?? ''),
    'scheduled_at' => $existingScheduledAt,
    'isScheduled' => $isScheduled,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}</script>

<div
    x-data="(() => {
        const d = JSON.parse(document.getElementById('edit-announcement-data').textContent);
        return {
            content: d.content,
            target_type: d.target_type,
            target_tenant_id: d.target_tenant_id,
            scheduled_at: d.scheduled_at,
            useSchedule: d.isScheduled,
            charCount: d.content.length,
            maxChars: 2000,

            init() {
                this.$watch('content', (val) => {
                    this.charCount = val.length;
                });
            }
        };
    })()"
    x-sync="['content', 'target_type', 'target_tenant_id', 'scheduled_at']"
    class="max-w-2xl mx-auto space-y-6"
>
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[
        ['label' => __('Announcements'), 'url' => '/vault-entry/announcements'],
        ['label' => __('Edit')]
    ]" />

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-on-surface-strong">{{ __('Edit Announcement') }}</h2>
            <p class="text-sm text-on-surface mt-1">
                @if($announcement->status === \App\Models\Announcement::STATUS_SCHEDULED)
                    {{ __('This announcement is scheduled. Edit it before it dispatches.') }}
                @else
                    {{ __('Update this draft announcement.') }}
                @endif
            </p>
        </div>
        {{-- Status badge --}}
        @if($announcement->status === \App\Models\Announcement::STATUS_SCHEDULED)
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-info-subtle text-info">
                <span class="w-1.5 h-1.5 rounded-full bg-info"></span>
                {{ __('Scheduled') }}
            </span>
        @else
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-warning-subtle text-warning">
                <span class="w-1.5 h-1.5 rounded-full bg-warning"></span>
                {{ __('Draft') }}
            </span>
        @endif
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
                placeholder="{{ __('Write your announcement here.') }}"
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
                    <p class="text-xs text-on-surface mt-0.5">{{ __('Keep this scheduled or change to a draft without a schedule.') }}</p>
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
                <p class="mt-1 text-xs text-on-surface">{{ __('Minimum 5 minutes from now.') }}</p>
                <p x-message="scheduled_at" class="mt-1.5 text-xs text-danger"></p>
            </div>
        </div>

    </div>

    {{-- Actions --}}
    <div class="flex flex-col sm:flex-row gap-3 sm:justify-between">
        <a href="/vault-entry/announcements"
           class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-on-surface bg-surface border border-outline rounded-lg hover:bg-surface-alt transition-colors">
            {{ __('Back') }}
        </a>
        <button
            type="button"
            :disabled="$fetching()"
            @click="$action('{{ url('/vault-entry/announcements/' . $announcement->id) }}', { include: ['content', 'target_type', 'target_tenant_id', 'scheduled_at'] })"
            class="inline-flex items-center justify-center gap-2 px-6 py-2.5 text-sm font-medium text-on-primary bg-primary hover:bg-primary-hover rounded-lg transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
        >
            <span x-show="!$fetching()">{{ __('Save Changes') }}</span>
            <span x-show="$fetching()" class="flex items-center gap-2">
                <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                {{ __('Saving...') }}
            </span>
        </button>
    </div>

</div>
@endsection
