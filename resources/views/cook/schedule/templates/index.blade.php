{{--
    Schedule Template List View
    ---------------------------
    F-102: Schedule Template List View
    F-104: Delete Schedule Template

    Displays all schedule templates created by the cook within their tenant.
    Each template shows its name, order/delivery/pickup window summaries,
    and how many day schedules currently use values copied from this template.

    Business Rules:
    BR-136: Tenant-scoped — only shows templates belonging to current tenant
    BR-137: "Applied to" count reflects how many schedule entries reference this template
    BR-138: Only users with manage-schedules permission can view
    BR-139: Alphabetical order by name
    BR-147: Confirmation dialog before deletion
    BR-148: Confirmation includes applied-to count if > 0

    UI/UX Notes:
    - Card-based layout on mobile, table layout on desktop
    - Action buttons: Edit (pencil), Delete (trash), Apply (calendar)
    - Empty state with illustration and CTA
    - All navigation via Gale (no page reloads)
    - Delete uses Alpine.js confirmation modal with $action DELETE
--}}
@extends('layouts.cook-dashboard')

@section('title', __('Schedule Templates'))
@section('page-title', __('Schedule Templates'))

@section('content')
<div class="max-w-5xl mx-auto" x-data="{
    deleteTemplateId: null,
    deleteTemplateName: '',
    deleteTemplateAppliedCount: 0,
    showDeleteModal: false,

    confirmDelete(id, name, appliedCount) {
        this.deleteTemplateId = id;
        this.deleteTemplateName = name;
        this.deleteTemplateAppliedCount = appliedCount;
        this.showDeleteModal = true;
    },

    cancelDelete() {
        this.showDeleteModal = false;
        this.deleteTemplateId = null;
        this.deleteTemplateName = '';
        this.deleteTemplateAppliedCount = 0;
    },

    executeDelete() {
        if (!this.deleteTemplateId) return;
        $action('/dashboard/schedule/templates/' + this.deleteTemplateId, { method: 'DELETE' });
        this.showDeleteModal = false;
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
        <span class="text-on-surface-strong font-medium">{{ __('Templates') }}</span>
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

    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="text-lg font-semibold text-on-surface-strong">{{ __('Schedule Templates') }}</h2>
            <p class="text-sm text-on-surface/60 mt-1">
                {{ __('Manage reusable schedule templates to quickly configure multiple days.') }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a
                href="{{ url('/dashboard/schedule') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium text-on-surface hover:bg-surface-alt border border-outline transition-colors duration-200"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                {{ __('Back to Schedule') }}
            </a>
            <a
                href="{{ url('/dashboard/schedule/templates/create') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary hover:bg-primary-hover text-on-primary rounded-lg text-sm font-medium transition-colors duration-200 shadow-sm"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                {{ __('Create Template') }}
            </a>
        </div>
    </div>

    @if($templates->isEmpty())
        {{-- Empty State --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline shadow-card p-8 sm:p-12 text-center">
            <div class="flex justify-center mb-4">
                <div class="w-16 h-16 rounded-full bg-primary-subtle flex items-center justify-center">
                    <svg class="w-8 h-8 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                </div>
            </div>
            <h3 class="text-lg font-semibold text-on-surface-strong mb-2">
                {{ __('No templates yet') }}
            </h3>
            <p class="text-sm text-on-surface/60 mb-6 max-w-sm mx-auto">
                {{ __('Create schedule templates to quickly apply consistent time intervals across multiple days.') }}
            </p>
            <a
                href="{{ url('/dashboard/schedule/templates/create') }}"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary hover:bg-primary-hover text-on-primary rounded-lg text-sm font-medium transition-colors duration-200 shadow-sm"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                {{ __('Create Your First Template') }}
            </a>
        </div>
    @else
        {{-- Template Count Summary --}}
        <p class="text-sm text-on-surface/60 mb-4">
            {{ trans_choice(':count template|:count templates', $templates->count(), ['count' => $templates->count()]) }}
        </p>

        {{-- Desktop Table View --}}
        <div class="hidden md:block bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline shadow-card overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-outline dark:border-outline bg-surface/50 dark:bg-surface/30">
                        <th class="text-left px-4 py-3 font-medium text-on-surface/70">{{ __('Name') }}</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface/70">{{ __('Order Window') }}</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface/70">{{ __('Delivery') }}</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface/70">{{ __('Pickup') }}</th>
                        <th class="text-center px-4 py-3 font-medium text-on-surface/70">{{ __('Applied') }}</th>
                        <th class="text-right px-4 py-3 font-medium text-on-surface/70">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline dark:divide-outline">
                    @foreach($templates as $template)
                        <tr class="hover:bg-surface/50 dark:hover:bg-surface/20 transition-colors duration-150">
                            {{-- Name --}}
                            <td class="px-4 py-3">
                                <span class="font-medium text-on-surface-strong truncate block max-w-[200px]" title="{{ $template->name }}">
                                    {{ $template->name }}
                                </span>
                            </td>

                            {{-- Order Window --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5 text-on-surface/80">
                                    <svg class="w-3.5 h-3.5 text-info shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                    <span class="text-xs">{{ $template->order_interval_summary }}</span>
                                </div>
                            </td>

                            {{-- Delivery --}}
                            <td class="px-4 py-3">
                                @if($template->delivery_enabled && $template->delivery_interval_summary)
                                    <div class="flex items-center gap-1.5 text-on-surface/80">
                                        <svg class="w-3.5 h-3.5 text-primary shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"></path><path d="M15 18H9"></path><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path><circle cx="7" cy="18" r="2"></circle><circle cx="17" cy="18" r="2"></circle></svg>
                                        <span class="text-xs">{{ $template->delivery_interval_summary }}</span>
                                    </div>
                                @else
                                    <span class="text-xs text-on-surface/40 italic">&mdash;</span>
                                @endif
                            </td>

                            {{-- Pickup --}}
                            <td class="px-4 py-3">
                                @if($template->pickup_enabled && $template->pickup_interval_summary)
                                    <div class="flex items-center gap-1.5 text-on-surface/80">
                                        <svg class="w-3.5 h-3.5 text-secondary shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9Z"></path><path d="m3 9 2.45-4.9A2 2 0 0 1 7.24 3h9.52a2 2 0 0 1 1.8 1.1L21 9"></path><path d="M12 3v6"></path></svg>
                                        <span class="text-xs">{{ $template->pickup_interval_summary }}</span>
                                    </div>
                                @else
                                    <span class="text-xs text-on-surface/40 italic">&mdash;</span>
                                @endif
                            </td>

                            {{-- Applied Count Badge --}}
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {{ $template->cook_schedules_count > 0 ? 'bg-info-subtle text-info' : 'bg-surface text-on-surface/40' }}">
                                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"></rect><line x1="16" x2="16" y1="2" y2="6"></line><line x1="8" x2="8" y1="2" y2="6"></line><line x1="3" x2="21" y1="10" y2="10"></line></svg>
                                    {{ trans_choice(':count day|:count days', $template->cook_schedules_count, ['count' => $template->cook_schedules_count]) }}
                                </span>
                            </td>

                            {{-- Actions --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    {{-- Edit (F-103) --}}
                                    <a
                                        href="{{ url('/dashboard/schedule/templates/' . $template->id . '/edit') }}"
                                        class="p-2 rounded-lg text-on-surface/60 hover:text-primary hover:bg-primary-subtle transition-colors duration-200"
                                        title="{{ __('Edit') }}"
                                    >
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path><path d="m15 5 4 4"></path></svg>
                                    </a>

                                    {{-- Apply to Days (F-105) --}}
                                    <a
                                        href="{{ url('/dashboard/schedule/templates/' . $template->id . '/apply') }}"
                                        class="p-2 rounded-lg text-on-surface/60 hover:text-info hover:bg-info-subtle transition-colors duration-200"
                                        title="{{ __('Apply to Days') }}"
                                    >
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"></rect><line x1="16" x2="16" y1="2" y2="6"></line><line x1="8" x2="8" y1="2" y2="6"></line><line x1="3" x2="21" y1="10" y2="10"></line></svg>
                                    </a>

                                    {{-- Delete (F-104) --}}
                                    <button
                                        type="button"
                                        x-on:click="confirmDelete({{ $template->id }}, '{{ addslashes($template->name) }}', {{ $template->cook_schedules_count }})"
                                        class="p-2 rounded-lg text-on-surface/60 hover:text-danger hover:bg-danger-subtle transition-colors duration-200"
                                        title="{{ __('Delete') }}"
                                    >
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" x2="10" y1="11" y2="17"></line><line x1="14" x2="14" y1="11" y2="17"></line></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile Card View --}}
        <div class="md:hidden space-y-3">
            @foreach($templates as $template)
                <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 shadow-card">
                    {{-- Card Header --}}
                    <div class="flex items-start justify-between mb-3">
                        <h4 class="text-sm font-semibold text-on-surface-strong truncate max-w-[200px]" title="{{ $template->name }}">
                            {{ $template->name }}
                        </h4>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium shrink-0 {{ $template->cook_schedules_count > 0 ? 'bg-info-subtle text-info' : 'bg-surface text-on-surface/40' }}">
                            <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"></rect><line x1="16" x2="16" y1="2" y2="6"></line><line x1="8" x2="8" y1="2" y2="6"></line><line x1="3" x2="21" y1="10" y2="10"></line></svg>
                            {{ trans_choice(':count day|:count days', $template->cook_schedules_count, ['count' => $template->cook_schedules_count]) }}
                        </span>
                    </div>

                    {{-- Interval Details --}}
                    <div class="space-y-1.5 mb-3">
                        {{-- Order Window --}}
                        <div class="flex items-center gap-2 text-xs text-on-surface/70">
                            <svg class="w-3.5 h-3.5 text-info shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            <span class="font-medium text-on-surface/50 w-14 shrink-0">{{ __('Orders') }}</span>
                            <span>{{ $template->order_interval_summary }}</span>
                        </div>

                        {{-- Delivery --}}
                        <div class="flex items-center gap-2 text-xs text-on-surface/70">
                            <svg class="w-3.5 h-3.5 text-primary shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"></path><path d="M15 18H9"></path><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path><circle cx="7" cy="18" r="2"></circle><circle cx="17" cy="18" r="2"></circle></svg>
                            <span class="font-medium text-on-surface/50 w-14 shrink-0">{{ __('Delivery') }}</span>
                            @if($template->delivery_enabled && $template->delivery_interval_summary)
                                <span>{{ $template->delivery_interval_summary }}</span>
                            @else
                                <span class="italic text-on-surface/40">{{ __('Disabled') }}</span>
                            @endif
                        </div>

                        {{-- Pickup --}}
                        <div class="flex items-center gap-2 text-xs text-on-surface/70">
                            <svg class="w-3.5 h-3.5 text-secondary shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9Z"></path><path d="m3 9 2.45-4.9A2 2 0 0 1 7.24 3h9.52a2 2 0 0 1 1.8 1.1L21 9"></path><path d="M12 3v6"></path></svg>
                            <span class="font-medium text-on-surface/50 w-14 shrink-0">{{ __('Pickup') }}</span>
                            @if($template->pickup_enabled && $template->pickup_interval_summary)
                                <span>{{ $template->pickup_interval_summary }}</span>
                            @else
                                <span class="italic text-on-surface/40">{{ __('Disabled') }}</span>
                            @endif
                        </div>
                    </div>

                    {{-- Mode Badges --}}
                    <div class="flex items-center gap-1.5 mb-3">
                        @if($template->delivery_enabled)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-primary-subtle text-primary">
                                <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"></path><path d="M15 18H9"></path><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path><circle cx="7" cy="18" r="2"></circle><circle cx="17" cy="18" r="2"></circle></svg>
                                {{ __('Delivery') }}
                            </span>
                        @endif
                        @if($template->pickup_enabled)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-secondary-subtle text-secondary">
                                <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9Z"></path><path d="m3 9 2.45-4.9A2 2 0 0 1 7.24 3h9.52a2 2 0 0 1 1.8 1.1L21 9"></path><path d="M12 3v6"></path></svg>
                                {{ __('Pickup') }}
                            </span>
                        @endif
                    </div>

                    {{-- Card Actions --}}
                    <div class="flex items-center gap-2 pt-3 border-t border-outline dark:border-outline">
                        <a
                            href="{{ url('/dashboard/schedule/templates/' . $template->id . '/edit') }}"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium text-on-surface hover:bg-surface border border-outline transition-colors duration-200"
                        >
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path><path d="m15 5 4 4"></path></svg>
                            {{ __('Edit') }}
                        </a>
                        <a
                            href="{{ url('/dashboard/schedule/templates/' . $template->id . '/apply') }}"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium text-info hover:bg-info-subtle border border-outline transition-colors duration-200"
                        >
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"></rect><line x1="16" x2="16" y1="2" y2="6"></line><line x1="8" x2="8" y1="2" y2="6"></line><line x1="3" x2="21" y1="10" y2="10"></line></svg>
                            {{ __('Apply') }}
                        </a>
                        <button
                            type="button"
                            x-on:click="confirmDelete({{ $template->id }}, '{{ addslashes($template->name) }}', {{ $template->cook_schedules_count }})"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium text-danger hover:bg-danger-subtle border border-outline transition-colors duration-200"
                        >
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" x2="10" y1="11" y2="17"></line><line x1="14" x2="14" y1="11" y2="17"></line></svg>
                            {{ __('Delete') }}
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- F-104: Delete Confirmation Modal --}}
    {{-- BR-147: Confirmation dialog before deletion --}}
    {{-- BR-148: Shows applied-to count when > 0 --}}
    <div
        x-show="showDeleteModal"
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
        <div class="absolute inset-0 bg-black/50 dark:bg-black/70" x-on:click="cancelDelete()"></div>

        {{-- Modal Content --}}
        <div
            x-show="showDeleteModal"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-dropdown max-w-md w-full p-6"
            x-on:keydown.escape.window="cancelDelete()"
        >
            {{-- Warning Icon --}}
            <div class="flex justify-center mb-4">
                <div class="w-12 h-12 rounded-full bg-danger-subtle flex items-center justify-center">
                    <svg class="w-6 h-6 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" x2="10" y1="11" y2="17"></line><line x1="14" x2="14" y1="11" y2="17"></line></svg>
                </div>
            </div>

            {{-- Title --}}
            <h3 class="text-lg font-semibold text-on-surface-strong text-center mb-2">
                {{ __('Delete Template') }}
            </h3>

            {{-- Confirmation message with template name --}}
            <p class="text-sm text-on-surface/70 text-center mb-2">
                {{ __('Delete template') }} "<span class="font-medium text-on-surface-strong" x-text="deleteTemplateName"></span>"? {{ __('This cannot be undone.') }}
            </p>

            {{-- Applied-to notice (BR-148) --}}
            <template x-if="deleteTemplateAppliedCount > 0">
                <div class="mt-3 p-3 rounded-lg bg-info-subtle border border-info/20">
                    <p class="text-xs text-info flex items-start gap-2">
                        <svg class="w-4 h-4 shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                        <span>{{ __('This template was applied to') }} <strong x-text="deleteTemplateAppliedCount"></strong> {{ __('day schedule(s). Those schedules will keep their current settings.') }}</span>
                    </p>
                </div>
            </template>

            {{-- Action Buttons --}}
            <div class="flex items-center gap-3 mt-6">
                <button
                    type="button"
                    x-on:click="cancelDelete()"
                    class="flex-1 px-4 py-2.5 rounded-lg text-sm font-medium text-on-surface hover:bg-surface border border-outline transition-colors duration-200"
                >
                    {{ __('Cancel') }}
                </button>
                <button
                    type="button"
                    x-on:click="executeDelete()"
                    class="flex-1 px-4 py-2.5 rounded-lg text-sm font-medium bg-danger hover:bg-danger/90 text-on-danger transition-colors duration-200"
                >
                    {{ __('Delete') }}
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
