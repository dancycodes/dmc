{{--
    Tenant Detail View
    ------------------
    F-047: Comprehensive detail page for a single tenant in the admin panel.

    BR-070: Total revenue = sum of completed orders (stubbed)
    BR-071: Active meals = meals with status "available" (stubbed)
    BR-072: Activity history scoped to this tenant
    BR-073: Commission rate from settings, default 10%
    BR-074: Visit Site link opens tenant subdomain in new tab
--}}
@extends('layouts.admin')

@section('title', $tenant->name . ' — ' . __('Tenant Detail'))
@section('page-title', __('Tenant Detail'))

@section('content')
<div class="space-y-6">
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[
        ['label' => __('Tenants'), 'url' => '/vault-entry/tenants'],
        ['label' => $tenant->name],
    ]" />

    {{-- Header Section: Tenant name, status badge, action buttons --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div class="flex items-start gap-4">
            {{-- Tenant avatar --}}
            <div class="w-14 h-14 rounded-xl bg-primary-subtle flex items-center justify-center text-primary font-bold text-xl shrink-0">
                {{ mb_strtoupper(mb_substr($tenant->name, 0, 1)) }}
            </div>
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-xl sm:text-2xl font-bold text-on-surface-strong">{{ $tenant->name }}</h2>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $tenant->is_active ? 'bg-success-subtle text-success' : 'bg-outline/20 text-on-surface/60' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current mr-1.5"></span>
                        {{ $tenant->is_active ? __('Active') : __('Inactive') }}
                    </span>
                </div>
                <p class="text-sm text-on-surface mt-1">
                    {{ __('Created :date', ['date' => $tenant->created_at?->format('M d, Y')]) }}
                    @if($tenant->updated_at && $tenant->updated_at->ne($tenant->created_at))
                        &middot; {{ __('Updated :date', ['date' => $tenant->updated_at?->format('M d, Y')]) }}
                    @endif
                </p>
            </div>
        </div>

        {{-- Action buttons --}}
        <div class="flex flex-wrap items-center gap-2 self-start" x-data x-navigate>
            {{-- Edit Tenant (F-048) --}}
            <a
                href="{{ url('/vault-entry/tenants/' . $tenant->slug . '/edit') }}"
                class="h-9 px-4 text-sm rounded-lg font-semibold border border-outline text-on-surface hover:bg-surface-alt transition-all duration-200 inline-flex items-center gap-2
                       focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path><path d="m15 5 4 4"></path></svg>
                {{ __('Edit Tenant') }}
            </a>

            {{-- Configure Commission (F-062) --}}
            <a
                href="{{ url('/vault-entry/tenants/' . $tenant->slug . '/commission') }}"
                class="h-9 px-4 text-sm rounded-lg font-semibold border border-outline text-on-surface hover:bg-surface-alt transition-all duration-200 inline-flex items-center gap-2
                       focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"></path><path d="M12 18V6"></path></svg>
                {{ __('Configure Commission') }}
            </a>

            {{-- BR-074: Visit Site opens tenant subdomain in new tab --}}
            <a
                href="{{ $tenant->custom_domain ? 'https://' . $tenant->custom_domain : 'https://' . $tenant->slug . '.' . $mainDomain }}"
                target="_blank"
                rel="noopener noreferrer"
                x-navigate-skip
                class="h-9 px-4 text-sm rounded-lg font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200 inline-flex items-center gap-2
                       focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" x2="21" y1="14" y2="3"></line></svg>
                {{ __('Visit Site') }}
            </a>
        </div>
    </div>

    {{-- Tenant Information Section --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-6">
        <h3 class="text-base font-semibold text-on-surface-strong mb-4">{{ __('Tenant Information') }}</h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
            {{-- Name (English) --}}
            <div>
                <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide mb-1">{{ __('Name (English)') }}</p>
                <p class="text-sm text-on-surface-strong">{{ $tenant->name_en }}</p>
            </div>

            {{-- Name (French) --}}
            <div>
                <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide mb-1">{{ __('Name (French)') }}</p>
                <p class="text-sm text-on-surface-strong">{{ $tenant->name_fr }}</p>
            </div>

            {{-- Subdomain --}}
            <div>
                <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide mb-1">{{ __('Subdomain') }}</p>
                <a
                    href="https://{{ $tenant->slug }}.{{ $mainDomain }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-sm text-primary hover:text-primary-hover font-mono transition-colors inline-flex items-center gap-1"
                >
                    {{ $tenant->slug }}.{{ $mainDomain }}
                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" x2="21" y1="14" y2="3"></line></svg>
                </a>
            </div>

            {{-- Custom Domain --}}
            <div>
                <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide mb-1">{{ __('Custom Domain') }}</p>
                @if($tenant->custom_domain)
                    <a
                        href="https://{{ $tenant->custom_domain }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-sm text-primary hover:text-primary-hover font-mono transition-colors inline-flex items-center gap-1"
                    >
                        {{ $tenant->custom_domain }}
                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" x2="21" y1="14" y2="3"></line></svg>
                    </a>
                @else
                    <span class="text-sm text-on-surface/40 italic">{{ __('None') }}</span>
                @endif
            </div>

            {{-- Status --}}
            <div>
                <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide mb-1">{{ __('Status') }}</p>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $tenant->is_active ? 'bg-success-subtle text-success' : 'bg-outline/20 text-on-surface/60' }}">
                    <span class="w-1.5 h-1.5 rounded-full bg-current mr-1.5"></span>
                    {{ $tenant->is_active ? __('Active') : __('Inactive') }}
                </span>
            </div>

            {{-- Created --}}
            <div>
                <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide mb-1">{{ __('Created') }}</p>
                <p class="text-sm text-on-surface-strong" title="{{ $tenant->created_at?->format('Y-m-d H:i:s') }}">{{ $tenant->created_at?->format('M d, Y \a\t h:i A') }}</p>
            </div>
        </div>

        {{-- Description with read more toggle --}}
        @if($tenant->description)
            <div class="mt-5 pt-5 border-t border-outline dark:border-outline">
                <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide mb-2">{{ __('Description') }}</p>
                <div
                    x-data="{ expanded: false, needsToggle: false }"
                    x-init="$nextTick(() => { needsToggle = $refs.descText.scrollHeight > 96 })"
                >
                    <div
                        x-ref="descText"
                        class="text-sm text-on-surface leading-relaxed overflow-hidden transition-all duration-300"
                        :class="expanded ? '' : 'max-h-24'"
                    >
                        {{ $tenant->description }}
                    </div>
                    <button
                        x-show="needsToggle"
                        @click="expanded = !expanded"
                        class="mt-2 text-sm text-primary hover:text-primary-hover font-medium transition-colors"
                        x-text="expanded ? '{{ __('Show less') }}' : '{{ __('Read more') }}'"
                        x-cloak
                    ></button>
                </div>
            </div>
        @endif
    </div>

    {{-- Metrics Section: 4 summary cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Orders --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-info-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"></path><path d="M3 6h18"></path><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Total Orders') }}</p>
                    <p class="text-2xl font-bold text-on-surface-strong">{{ number_format($totalOrders) }}</p>
                </div>
            </div>
        </div>

        {{-- Total Revenue --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-success-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="1" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Total Revenue') }}</p>
                    <p class="text-2xl font-bold text-on-surface-strong">{{ __('XAF :amount', ['amount' => number_format($totalRevenue)]) }}</p>
                </div>
            </div>
        </div>

        {{-- Commission Rate --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-warning-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"></path><path d="M12 18V6"></path></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Commission Rate') }}</p>
                    <p class="text-2xl font-bold text-on-surface-strong">{{ $commissionRate }}%</p>
                </div>
            </div>
        </div>

        {{-- Active Meals --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"></path><path d="M7 2v20"></path><path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Active Meals') }}</p>
                    <p class="text-2xl font-bold text-on-surface-strong">{{ number_format($activeMeals) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Cook Section --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-6" x-data x-navigate>
        <h3 class="text-base font-semibold text-on-surface-strong mb-4">{{ __('Assigned Cook') }}</h3>

        @if($cook)
            <div class="flex items-center gap-4">
                {{-- Cook avatar --}}
                <div class="w-12 h-12 rounded-full {{ $cook->is_active ? 'bg-primary-subtle text-primary' : 'bg-outline/20 text-on-surface/60' }} flex items-center justify-center font-semibold text-base shrink-0">
                    {{ mb_strtoupper(mb_substr($cook->name, 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-semibold text-on-surface-strong">{{ $cook->name }}</p>
                        @if(!$cook->is_active)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-danger-subtle text-danger">
                                {{ __('Deactivated') }}
                            </span>
                        @endif
                    </div>
                    <p class="text-sm text-on-surface mt-0.5">{{ $cook->email }}</p>
                    @if($cook->phone)
                        <p class="text-sm text-on-surface mt-0.5">+237 {{ $cook->phone }}</p>
                    @endif
                </div>
                {{-- View Profile link (F-051) --}}
                <a
                    href="{{ url('/vault-entry/users/' . $cook->id) }}"
                    class="h-9 px-4 text-sm rounded-lg font-semibold border border-outline text-on-surface hover:bg-surface transition-all duration-200 inline-flex items-center gap-2 shrink-0
                           focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                    {{ __('View Profile') }}
                </a>
            </div>
            @if(!$cook->is_active)
                <div class="mt-3 p-3 bg-warning-subtle rounded-lg border border-warning/20">
                    <p class="text-sm text-warning font-medium">{{ __('Warning: The assigned cook account has been deactivated. The tenant site may not function properly.') }}</p>
                </div>
            @endif
        @else
            {{-- No cook assigned --}}
            <div class="text-center py-6">
                <div class="w-12 h-12 mx-auto rounded-full bg-outline/10 flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" x2="19" y1="8" y2="14"></line><line x1="22" x2="16" y1="11" y2="11"></line></svg>
                </div>
                <p class="text-sm text-on-surface font-medium">{{ __('No cook assigned to this tenant.') }}</p>
                <p class="text-xs text-on-surface/60 mt-1">{{ __('Assign a cook to enable the tenant website.') }}</p>
                <a
                    href="{{ url('/vault-entry/tenants/' . $tenant->slug . '/assign-cook') }}"
                    class="mt-3 inline-flex items-center gap-2 h-9 px-4 text-sm rounded-lg font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200
                           focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" x2="19" y1="8" y2="14"></line><line x1="22" x2="16" y1="11" y2="11"></line></svg>
                    {{ __('Assign Cook') }}
                </a>
            </div>
        @endif
    </div>

    {{-- Activity History Section --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-6">
        <h3 class="text-base font-semibold text-on-surface-strong mb-4">{{ __('Activity History') }}</h3>

        @fragment('activity-history-content')
        <div id="activity-history-content">
            @if($activities->count() > 0)
                <div class="space-y-4">
                    @foreach($activities as $activity)
                        <div class="flex items-start gap-3">
                            {{-- Causer avatar --}}
                            <div class="w-8 h-8 rounded-full bg-primary-subtle flex items-center justify-center text-primary font-semibold text-xs shrink-0 mt-0.5">
                                @if($activity->causer)
                                    {{ mb_strtoupper(mb_substr($activity->causer->name, 0, 1)) }}
                                @else
                                    <svg class="w-4 h-4 text-on-surface/50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-on-surface-strong">
                                    @if($activity->causer)
                                        <span class="font-medium">{{ $activity->causer->name }}</span>
                                    @else
                                        <span class="font-medium italic text-on-surface/60">{{ __('System') }}</span>
                                    @endif
                                    —
                                    {{ __('Tenant :action', ['action' => $activity->description]) }}
                                </p>
                                @if($activity->properties && $activity->properties->count() > 0)
                                    @php
                                        $props = $activity->properties->toArray();
                                        // Show old/new for updates
                                        $old = $props['old'] ?? null;
                                        $attributes = $props['attributes'] ?? null;
                                    @endphp
                                    @if($old && $attributes)
                                        <div class="mt-1 flex flex-wrap gap-2">
                                            @foreach($attributes as $key => $newValue)
                                                @if(isset($old[$key]) && $old[$key] !== $newValue && !is_array($old[$key]) && !is_array($newValue))
                                                    <span class="inline-flex items-center text-xs px-2 py-0.5 rounded bg-surface dark:bg-surface border border-outline dark:border-outline text-on-surface">
                                                        <span class="font-medium">{{ $key }}</span>:
                                                        <span class="text-danger line-through mx-1">{{ is_bool($old[$key]) ? ($old[$key] ? 'true' : 'false') : Str::limit((string)$old[$key], 20) }}</span>
                                                        <svg class="w-3 h-3 mx-0.5 text-on-surface/50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                                                        <span class="text-success">{{ is_bool($newValue) ? ($newValue ? 'true' : 'false') : Str::limit((string)$newValue, 20) }}</span>
                                                    </span>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                @endif
                                <p class="text-xs text-on-surface/50 mt-1" title="{{ $activity->created_at?->format('Y-m-d H:i:s') }}">
                                    {{ $activity->created_at?->diffForHumans() }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Activity pagination --}}
                @if($activities->hasPages())
                    <div class="mt-6 pt-4 border-t border-outline dark:border-outline" x-data x-navigate>
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <p class="text-xs text-on-surface/60">
                                {{ __('Showing :from-:to of :total activities', [
                                    'from' => $activities->firstItem(),
                                    'to' => $activities->lastItem(),
                                    'total' => $activities->total(),
                                ]) }}
                            </p>
                            <div class="flex gap-2">
                                @if($activities->previousPageUrl())
                                    <a
                                        href="{{ $activities->previousPageUrl() }}"
                                        class="h-8 px-3 text-xs rounded-lg font-medium border border-outline text-on-surface hover:bg-surface transition-colors inline-flex items-center gap-1"
                                    >
                                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                                        {{ __('Previous') }}
                                    </a>
                                @endif
                                @if($activities->nextPageUrl())
                                    <a
                                        href="{{ $activities->nextPageUrl() }}"
                                        class="h-8 px-3 text-xs rounded-lg font-medium border border-outline text-on-surface hover:bg-surface transition-colors inline-flex items-center gap-1"
                                    >
                                        {{ __('Next') }}
                                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            @else
                {{-- Empty state --}}
                <div class="text-center py-8">
                    <div class="w-12 h-12 mx-auto rounded-full bg-outline/10 flex items-center justify-center mb-3">
                        <svg class="w-6 h-6 text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"></path><path d="M14 2v6h6"></path><path d="M16 13H8"></path><path d="M16 17H8"></path><path d="M10 9H8"></path></svg>
                    </div>
                    <p class="text-sm text-on-surface font-medium">{{ __('No activity recorded yet.') }}</p>
                    <p class="text-xs text-on-surface/60 mt-1">{{ __('Activity will appear here when changes are made to this tenant.') }}</p>
                </div>
            @endif
        </div>
        @endfragment
    </div>

    {{-- Back navigation --}}
    <div x-data x-navigate>
        <a
            href="{{ url('/vault-entry/tenants') }}"
            class="inline-flex items-center gap-2 text-sm font-medium text-on-surface hover:text-primary transition-colors"
        >
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
            {{ __('Back to Tenant List') }}
        </a>
    </div>
</div>
@endsection
