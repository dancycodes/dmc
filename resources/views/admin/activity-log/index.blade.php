{{--
    Activity Log Viewer
    -------------------
    F-064: Searchable, filterable view of all Spatie Activitylog entries in the admin panel.

    BR-192: Read-only — no edits or deletes
    BR-193: All Spatie Activitylog entries displayed (system + user actions)
    BR-194: "System" shown as causer for automated actions (null causer)
    BR-195: Before/after values stored for model attribute changes
    BR-196: 25 entries per page
    BR-197: Default sort: newest first
    BR-198: Search covers description, causer name, subject type

    UI/UX:
    - Summary cards: Total, Today, Active Causers
    - Filter bar: User dropdown, Subject Type, Event Type, Date Range, Search
    - Expandable rows: accordion diff view for before/after attribute changes
    - Mobile: simplified card layout with tap-to-expand
    - "System" causer shown with gear icon
    - Timestamp relative with exact on hover
--}}
@extends('layouts.admin')

@section('title', __('Activity Log'))
@section('page-title', __('Activity Log'))

@section('content')
<div
    class="space-y-6"
    x-data="{
        search: {{ json_encode($search) }},
        causerUserId: {{ json_encode($causerUserId) }},
        subjectType: {{ json_encode($subjectType) }},
        event: {{ json_encode($event) }},
        dateFrom: {{ json_encode($dateFrom) }},
        dateTo: {{ json_encode($dateTo) }},
        expandedRows: {},
        toggleRow(id) {
            this.expandedRows[id] = !this.expandedRows[id];
        },
        isExpanded(id) {
            return !!this.expandedRows[id];
        },
        applyFilters() {
            const params = new URLSearchParams();
            if (this.search) params.set('search', this.search);
            if (this.causerUserId) params.set('causer_user_id', this.causerUserId);
            if (this.subjectType) params.set('subject_type', this.subjectType);
            if (this.event) params.set('event', this.event);
            if (this.dateFrom) params.set('date_from', this.dateFrom);
            if (this.dateTo) params.set('date_to', this.dateTo);
            $navigate('/vault-entry/activity-log?' + params.toString(), { key: 'activity-log', replace: true });
        },
        clearFilters() {
            this.search = '';
            this.causerUserId = '';
            this.subjectType = '';
            this.event = '';
            this.dateFrom = '';
            this.dateTo = '';
            $navigate('/vault-entry/activity-log', { key: 'activity-log', replace: true });
        }
    }"
>
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[['label' => __('Activity Log')]]" />

    {{-- Header --}}
    <div>
        <h2 class="text-lg font-semibold text-on-surface-strong">{{ __('Activity Log') }}</h2>
        <p class="text-sm text-on-surface mt-1">{{ __('Audit trail of all actions performed across the platform.') }}</p>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
        {{-- Total Entries --}}
        <div class="bg-surface-alt rounded-lg border border-outline p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"></path><path d="M14 2v6h6"></path><path d="M16 13H8"></path><path d="M16 17H8"></path><path d="M10 9H8"></path></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-on-surface uppercase tracking-wide truncate">{{ __('Total Entries') }}</p>
                    <p class="text-2xl font-bold text-on-surface-strong">{{ number_format($totalCount) }}</p>
                </div>
            </div>
        </div>

        {{-- Today's Events --}}
        <div class="bg-surface-alt rounded-lg border border-outline p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-info-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"></rect><line x1="16" x2="16" y1="2" y2="6"></line><line x1="8" x2="8" y1="2" y2="6"></line><line x1="3" x2="21" y1="10" y2="10"></line></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-on-surface uppercase tracking-wide truncate">{{ __('Today') }}</p>
                    <p class="text-2xl font-bold text-on-surface-strong">{{ number_format($todayCount) }}</p>
                </div>
            </div>
        </div>

        {{-- Unique Causers --}}
        <div class="bg-surface-alt rounded-lg border border-outline p-4 sm:p-5 col-span-2 sm:col-span-1">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-secondary-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-on-surface uppercase tracking-wide truncate">{{ __('Active Users') }}</p>
                    <p class="text-2xl font-bold text-on-surface-strong">{{ number_format($uniqueCausers) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-surface-alt rounded-lg border border-outline p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3">
            {{-- Search --}}
            <div class="xl:col-span-2">
                <label class="block text-xs font-medium text-on-surface mb-1">{{ __('Search') }}</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        <svg class="w-4 h-4 text-on-surface" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                    </span>
                    <input
                        type="text"
                        x-model="search"
                        @input.debounce.400ms="applyFilters()"
                        placeholder="{{ __('Description, user, model...') }}"
                        class="w-full pl-9 pr-3 py-2 text-sm bg-surface border border-outline rounded-lg text-on-surface placeholder-on-surface/50 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-surface dark:border-outline dark:text-on-surface"
                    />
                </div>
            </div>

            {{-- User filter --}}
            <div>
                <label class="block text-xs font-medium text-on-surface mb-1">{{ __('User') }}</label>
                <select
                    x-model="causerUserId"
                    @change="applyFilters()"
                    class="w-full px-3 py-2 text-sm bg-surface border border-outline rounded-lg text-on-surface focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-surface dark:border-outline dark:text-on-surface"
                >
                    <option value="">{{ __('All Users') }}</option>
                    @foreach ($adminUsers as $adminUser)
                        <option value="{{ $adminUser->id }}">{{ $adminUser->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Subject Type filter --}}
            <div>
                <label class="block text-xs font-medium text-on-surface mb-1">{{ __('Model Type') }}</label>
                <select
                    x-model="subjectType"
                    @change="applyFilters()"
                    class="w-full px-3 py-2 text-sm bg-surface border border-outline rounded-lg text-on-surface focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-surface dark:border-outline dark:text-on-surface"
                >
                    <option value="">{{ __('All Models') }}</option>
                    @foreach ($subjectTypes as $fqn => $shortName)
                        <option value="{{ $fqn }}">{{ $shortName }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Event Type filter --}}
            <div>
                <label class="block text-xs font-medium text-on-surface mb-1">{{ __('Event') }}</label>
                <select
                    x-model="event"
                    @change="applyFilters()"
                    class="w-full px-3 py-2 text-sm bg-surface border border-outline rounded-lg text-on-surface focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-surface dark:border-outline dark:text-on-surface"
                >
                    <option value="">{{ __('All Events') }}</option>
                    @foreach ($eventTypes as $eventType)
                        <option value="{{ $eventType }}">{{ ucfirst($eventType) }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Clear Filters --}}
            <div class="flex items-end">
                <button
                    @click="clearFilters()"
                    class="w-full px-3 py-2 text-sm font-medium text-on-surface border border-outline rounded-lg hover:bg-surface transition-colors duration-150 dark:border-outline dark:text-on-surface dark:hover:bg-surface"
                >
                    {{ __('Clear Filters') }}
                </button>
            </div>
        </div>

        {{-- Date Range --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
            <div>
                <label class="block text-xs font-medium text-on-surface mb-1">{{ __('Date From') }}</label>
                <input
                    type="date"
                    x-model="dateFrom"
                    @change="applyFilters()"
                    class="w-full px-3 py-2 text-sm bg-surface border border-outline rounded-lg text-on-surface focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-surface dark:border-outline dark:text-on-surface"
                />
            </div>
            <div>
                <label class="block text-xs font-medium text-on-surface mb-1">{{ __('Date To') }}</label>
                <input
                    type="date"
                    x-model="dateTo"
                    @change="applyFilters()"
                    class="w-full px-3 py-2 text-sm bg-surface border border-outline rounded-lg text-on-surface focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-surface dark:border-outline dark:text-on-surface"
                />
            </div>
        </div>
    </div>

    {{-- Fragment: Activity Log Content (updated on filter/pagination) --}}
    @fragment('activity-log-content')
    <div id="activity-log-content">

        @if ($activities->isEmpty())
            {{-- Empty State --}}
            <div class="bg-surface-alt rounded-lg border border-outline p-12 text-center">
                <span class="w-16 h-16 rounded-full bg-primary-subtle flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"></path><path d="M14 2v6h6"></path><path d="M16 13H8"></path><path d="M16 17H8"></path><path d="M10 9H8"></path></svg>
                </span>
                <h3 class="text-base font-semibold text-on-surface-strong mb-1">{{ __('No activity found') }}</h3>
                <p class="text-sm text-on-surface">
                    @if ($search || $causerUserId || $subjectType || $event || $dateFrom || $dateTo)
                        {{ __('No activity matches your current filters. Try adjusting them.') }}
                    @else
                        {{ __('No activity has been recorded yet.') }}
                    @endif
                </p>
            </div>
        @else
            {{-- Result count --}}
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm text-on-surface">
                    {{ __(':total entries found', ['total' => number_format($activities->total())]) }}
                </p>
                <p class="text-xs text-on-surface">
                    {{ __('Page :current of :last', ['current' => $activities->currentPage(), 'last' => $activities->lastPage()]) }}
                </p>
            </div>

            {{-- Desktop Table --}}
            <div class="hidden md:block bg-surface-alt rounded-lg border border-outline overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-surface border-b border-outline">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-on-surface uppercase tracking-wide w-36">{{ __('Timestamp') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-on-surface uppercase tracking-wide w-40">{{ __('User') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-on-surface uppercase tracking-wide">{{ __('Action') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-on-surface uppercase tracking-wide w-28">{{ __('Model') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-on-surface uppercase tracking-wide w-24">{{ __('Event') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-on-surface uppercase tracking-wide w-16">{{ __('Details') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline">
                        @foreach ($activities as $activity)
                            @php
                                $hasProperties = $activity->properties &&
                                    ($activity->properties->get('old') || $activity->properties->get('attributes'));
                                $shortSubjectType = $activity->subject_type
                                    ? \App\Http\Controllers\Admin\ActivityLogController::getShortModelName($activity->subject_type)
                                    : null;
                            @endphp
                            <tr class="hover:bg-surface transition-colors duration-100">
                                {{-- Timestamp --}}
                                <td class="px-4 py-3 text-xs text-on-surface whitespace-nowrap">
                                    <span title="{{ $activity->created_at->format('Y-m-d H:i:s') }}" class="cursor-help">
                                        {{ $activity->created_at->diffForHumans() }}
                                    </span>
                                </td>

                                {{-- Causer --}}
                                <td class="px-4 py-3">
                                    @if ($activity->causer)
                                        <div class="flex items-center gap-2">
                                            @if ($activity->causer->profile_photo_path)
                                                <img src="{{ Storage::url($activity->causer->profile_photo_path) }}" alt="" class="w-6 h-6 rounded-full object-cover shrink-0" />
                                            @else
                                                <span class="w-6 h-6 rounded-full bg-primary-subtle text-primary flex items-center justify-center text-xs font-semibold shrink-0">
                                                    {{ mb_substr($activity->causer->name, 0, 1) }}
                                                </span>
                                            @endif
                                            <span class="text-xs font-medium text-on-surface-strong truncate max-w-28">{{ $activity->causer->name }}</span>
                                        </div>
                                    @else
                                        {{-- BR-194: System causer --}}
                                        <div class="flex items-center gap-2">
                                            <span class="w-6 h-6 rounded-full bg-surface border border-outline flex items-center justify-center shrink-0">
                                                <svg class="w-3.5 h-3.5 text-on-surface" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 1 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                            </span>
                                            <span class="text-xs font-medium text-on-surface italic">{{ __('System') }}</span>
                                        </div>
                                    @endif
                                </td>

                                {{-- Description (Action) --}}
                                <td class="px-4 py-3 text-xs text-on-surface-strong">
                                    {{ $activity->description }}
                                </td>

                                {{-- Subject Model --}}
                                <td class="px-4 py-3 text-xs text-on-surface">
                                    @if ($shortSubjectType)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-surface border border-outline text-on-surface">
                                            {{ $shortSubjectType }}
                                            @if ($activity->subject_id)
                                                <span class="ml-1 text-on-surface/50">#{{ $activity->subject_id }}</span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="text-on-surface/40">—</span>
                                    @endif
                                </td>

                                {{-- Event badge --}}
                                <td class="px-4 py-3">
                                    @if ($activity->event)
                                        @php
                                            $eventColorClass = match($activity->event) {
                                                'created', 'registered' => 'bg-success-subtle text-success',
                                                'deleted' => 'bg-danger-subtle text-danger',
                                                'updated' => 'bg-info-subtle text-info',
                                                'login' => 'bg-primary-subtle text-primary',
                                                'logged_out' => 'bg-surface text-on-surface border border-outline',
                                                default => 'bg-warning-subtle text-warning',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $eventColorClass }}">
                                            {{ ucfirst($activity->event) }}
                                        </span>
                                    @else
                                        <span class="text-on-surface/40 text-xs">—</span>
                                    @endif
                                </td>

                                {{-- Expand toggle --}}
                                <td class="px-4 py-3 text-center">
                                    @if ($hasProperties)
                                        <button
                                            @click="toggleRow({{ $activity->id }})"
                                            class="inline-flex items-center justify-center w-7 h-7 rounded-md hover:bg-primary-subtle text-on-surface hover:text-primary transition-colors duration-150"
                                            :aria-expanded="isExpanded({{ $activity->id }}) ? 'true' : 'false'"
                                            :title="isExpanded({{ $activity->id }}) ? @js(__('Collapse')) : @js(__('Expand'))"
                                        >
                                            <svg
                                                class="w-4 h-4 transition-transform duration-200"
                                                :class="{ 'rotate-180': isExpanded({{ $activity->id }}) }"
                                                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            ><path d="m6 9 6 6 6-6"/></svg>
                                        </button>
                                    @else
                                        <span class="text-on-surface/30 text-xs">—</span>
                                    @endif
                                </td>
                            </tr>

                            {{-- Expanded Detail Row --}}
                            @if ($hasProperties)
                                <tr x-show="isExpanded({{ $activity->id }})" x-cloak class="bg-surface">
                                    <td colspan="6" class="px-4 py-0">
                                        <div class="border border-outline rounded-lg my-2 overflow-hidden">
                                            @include('admin.activity-log._diff-panel', ['activity' => $activity])
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile Card Layout --}}
            <div class="md:hidden space-y-3">
                @foreach ($activities as $activity)
                    @php
                        $hasProperties = $activity->properties &&
                            ($activity->properties->get('old') || $activity->properties->get('attributes'));
                        $shortSubjectType = $activity->subject_type
                            ? \App\Http\Controllers\Admin\ActivityLogController::getShortModelName($activity->subject_type)
                            : null;
                    @endphp
                    <div class="bg-surface-alt rounded-lg border border-outline overflow-hidden">
                        <div class="p-4">
                            {{-- Top: Causer + Time + Event --}}
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    @if ($activity->causer)
                                        @if ($activity->causer->profile_photo_path)
                                            <img src="{{ Storage::url($activity->causer->profile_photo_path) }}" alt="" class="w-7 h-7 rounded-full object-cover shrink-0" />
                                        @else
                                            <span class="w-7 h-7 rounded-full bg-primary-subtle text-primary flex items-center justify-center text-xs font-semibold shrink-0">
                                                {{ mb_substr($activity->causer->name, 0, 1) }}
                                            </span>
                                        @endif
                                        <span class="text-sm font-medium text-on-surface-strong truncate">{{ $activity->causer->name }}</span>
                                    @else
                                        <span class="w-7 h-7 rounded-full bg-surface border border-outline flex items-center justify-center shrink-0">
                                            <svg class="w-4 h-4 text-on-surface" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 1 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        </span>
                                        <span class="text-sm font-medium text-on-surface italic">{{ __('System') }}</span>
                                    @endif
                                </div>
                                @if ($activity->event)
                                    @php
                                        $eventColorClass = match($activity->event) {
                                            'created', 'registered' => 'bg-success-subtle text-success',
                                            'deleted' => 'bg-danger-subtle text-danger',
                                            'updated' => 'bg-info-subtle text-info',
                                            'login' => 'bg-primary-subtle text-primary',
                                            'logged_out' => 'bg-surface text-on-surface border border-outline',
                                            default => 'bg-warning-subtle text-warning',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $eventColorClass }} shrink-0">
                                        {{ ucfirst($activity->event) }}
                                    </span>
                                @endif
                            </div>

                            {{-- Description --}}
                            <p class="text-sm text-on-surface-strong mb-2">{{ $activity->description }}</p>

                            {{-- Subject + Time --}}
                            <div class="flex items-center justify-between gap-2">
                                @if ($shortSubjectType)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-surface border border-outline text-on-surface">
                                        {{ $shortSubjectType }}
                                        @if ($activity->subject_id)
                                            <span class="ml-1 text-on-surface/50">#{{ $activity->subject_id }}</span>
                                        @endif
                                    </span>
                                @else
                                    <span></span>
                                @endif
                                <span class="text-xs text-on-surface" title="{{ $activity->created_at->format('Y-m-d H:i:s') }}">
                                    {{ $activity->created_at->diffForHumans() }}
                                </span>
                            </div>
                        </div>

                        {{-- Expand button for mobile --}}
                        @if ($hasProperties)
                            <div class="border-t border-outline">
                                <button
                                    @click="toggleRow({{ $activity->id }})"
                                    class="w-full flex items-center justify-between px-4 py-2.5 text-xs font-medium text-primary hover:bg-primary-subtle transition-colors duration-150"
                                    :aria-expanded="isExpanded({{ $activity->id }}) ? 'true' : 'false'"
                                >
                                    <span x-text="isExpanded({{ $activity->id }}) ? @js(__('Hide Changes')) : @js(__('View Changes'))"></span>
                                    <svg
                                        class="w-4 h-4 transition-transform duration-200"
                                        :class="{ 'rotate-180': isExpanded({{ $activity->id }}) }"
                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                    ><path d="m6 9 6 6 6-6"/></svg>
                                </button>
                            </div>
                            <div x-show="isExpanded({{ $activity->id }})" x-cloak class="border-t border-outline">
                                @include('admin.activity-log._diff-panel', ['activity' => $activity])
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if ($activities->hasPages())
                <div class="mt-4" x-navigate>
                    {{ $activities->links() }}
                </div>
            @endif
        @endif

    </div>
    @endfragment

</div>
@endsection
