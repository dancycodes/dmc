{{--
    Delivery Fee Configuration
    --------------------------
    F-091: Delivery Fee Configuration

    Centralized view for configuring delivery fees across all quarters and groups.
    Allows inline editing of individual quarter fees and group-level fees.

    BR-273: Delivery fee must be >= 0 XAF for all quarters and groups
    BR-274: Fee of 0 XAF means free delivery to that quarter
    BR-275: Group fee overrides individual quarter fees for all quarters in the group
    BR-276: Fee changes apply to new orders only; existing orders retain their original fee
    BR-277: Fees are stored as integers in XAF
    BR-278: Fee configuration is accessible from the Locations section of the dashboard
    BR-279: Changes saved via Gale without page reload
    BR-280: Only users with location management permission can modify fees
--}}
@extends('layouts.cook-dashboard')

@section('title', __('Delivery Fees'))
@section('page-title', __('Delivery Fees'))

@section('content')
<div class="max-w-4xl mx-auto">
    {{-- Breadcrumb (BR-278) --}}
    <nav class="flex items-center gap-2 text-sm text-on-surface/60 mb-6" aria-label="{{ __('Breadcrumb') }}">
        <a href="{{ url('/dashboard') }}" class="hover:text-primary transition-colors duration-200">
            {{ __('Dashboard') }}
        </a>
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
        <a href="{{ url('/dashboard/locations') }}" class="hover:text-primary transition-colors duration-200">
            {{ __('Locations') }}
        </a>
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
        <span class="text-on-surface-strong font-medium">{{ __('Delivery Fees') }}</span>
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
            {{-- Lucide: check-circle --}}
            <svg class="w-5 h-5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><path d="m9 11 3 3L22 4"></path></svg>
            <p class="text-sm font-medium">{{ session('success') }}</p>
        </div>
    @endif

    {{-- Error Toast --}}
    @if(session('error'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 6000)"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-2"
            class="mb-6 p-4 rounded-lg border bg-danger-subtle border-danger/30 text-danger flex items-center gap-3"
            role="alert"
        >
            {{-- Lucide: alert-circle --}}
            <svg class="w-5 h-5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" x2="12" y1="8" y2="12"></line><line x1="12" x2="12.01" y1="16" y2="16"></line></svg>
            <p class="text-sm font-medium">{{ session('error') }}</p>
        </div>
    @endif

    {{-- Page Content --}}
    <div
        x-data="{
            editingQuarterFeeId: null,
            fee_value: '',
            editingGroupFeeId: null,
            group_fee_value: '',
            startEditQuarterFee(quarterId, currentFee) {
                this.editingQuarterFeeId = quarterId;
                this.fee_value = String(currentFee);
                this.editingGroupFeeId = null;
            },
            cancelEditQuarterFee() {
                this.editingQuarterFeeId = null;
                this.fee_value = '';
            },
            startEditGroupFee(groupId, currentFee) {
                this.editingGroupFeeId = groupId;
                this.group_fee_value = String(currentFee);
                this.editingQuarterFeeId = null;
            },
            cancelEditGroupFee() {
                this.editingGroupFeeId = null;
                this.group_fee_value = '';
            }
        }"
        x-sync="['fee_value', 'group_fee_value']"
    >
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h2 class="text-xl font-semibold text-on-surface-strong">{{ __('Delivery Fee Configuration') }}</h2>
                <p class="text-sm text-on-surface mt-1">{{ __('Review and manage delivery fees for all your service areas.') }}</p>
            </div>
            <a
                href="{{ url('/dashboard/locations') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-outline text-sm font-medium text-on-surface hover:bg-surface-alt transition-colors duration-200 self-start sm:self-auto"
            >
                {{-- Lucide: arrow-left --}}
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
                {{ __('Back to Locations') }}
            </a>
        </div>

        @if($summary['total_quarters'] > 0)
            {{-- Summary Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
                {{-- Total Quarters --}}
                <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-4 shadow-card">
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center">
                            {{-- Lucide: map-pin (md=20) --}}
                            <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        </span>
                        <div>
                            <p class="text-2xl font-bold text-on-surface-strong">{{ $summary['total_quarters'] }}</p>
                            <p class="text-xs text-on-surface/60">{{ __('Total Quarters') }}</p>
                        </div>
                    </div>
                </div>

                {{-- Free Delivery --}}
                <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-4 shadow-card">
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-full bg-success-subtle flex items-center justify-center">
                            {{-- Lucide: badge-check (md=20) --}}
                            <svg class="w-5 h-5 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76Z"></path><path d="m9 12 2 2 4-4"></path></svg>
                        </span>
                        <div>
                            <p class="text-2xl font-bold text-on-surface-strong">{{ $summary['free_delivery_count'] }}</p>
                            <p class="text-xs text-on-surface/60">{{ __('Free Delivery') }}</p>
                        </div>
                    </div>
                </div>

                {{-- In Groups --}}
                <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-4 shadow-card">
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-full bg-info-subtle flex items-center justify-center">
                            {{-- Lucide: layers (md=20) --}}
                            <svg class="w-5 h-5 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"></path><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"></path><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"></path></svg>
                        </span>
                        <div>
                            <p class="text-2xl font-bold text-on-surface-strong">{{ $summary['grouped_count'] }}</p>
                            <p class="text-xs text-on-surface/60">{{ __('In Groups') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Group Fees Section (BR-275: Group fee overrides individual fees) --}}
            @if(count($groups) > 0)
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-on-surface-strong mb-4 flex items-center gap-2">
                        {{-- Lucide: layers --}}
                        <svg class="w-5 h-5 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"></path><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"></path><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"></path></svg>
                        {{ __('Group Fees') }}
                    </h3>
                    <p class="text-sm text-on-surface/60 mb-4">
                        {{ __('Group fees override individual quarter fees for all quarters in the group.') }}
                    </p>
                    <div class="space-y-3">
                        @foreach($groups as $group)
                            <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-card overflow-hidden">
                                {{-- Group Display Row --}}
                                <div x-show="editingGroupFeeId !== {{ $group['id'] }}" class="flex items-center justify-between p-4">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <div class="w-9 h-9 rounded-full bg-info-subtle flex items-center justify-center shrink-0">
                                            {{-- Lucide: layers --}}
                                            <svg class="w-4 h-4 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"></path><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"></path><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"></path></svg>
                                        </div>
                                        <div class="min-w-0">
                                            <h4 class="text-sm font-semibold text-on-surface-strong truncate">{{ $group['name'] }}</h4>
                                            <p class="text-xs text-on-surface/60">
                                                {{ $group['quarter_count'] }} {{ $group['quarter_count'] === 1 ? __('quarter') : __('quarters') }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 ml-3 shrink-0">
                                        {{-- Fee Display --}}
                                        @if($group['delivery_fee'] === 0)
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-success-subtle text-success">
                                                {{-- Lucide: check --}}
                                                <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                                                {{ __('Free delivery') }}
                                            </span>
                                        @else
                                            <span class="text-sm font-semibold text-on-surface-strong">
                                                {{ number_format($group['delivery_fee']) }} {{ __('XAF') }}
                                            </span>
                                        @endif
                                        {{-- Edit button --}}
                                        <button
                                            type="button"
                                            x-on:click="startEditGroupFee({{ $group['id'] }}, {{ $group['delivery_fee'] }})"
                                            class="p-2 rounded-lg text-on-surface/60 hover:text-primary hover:bg-primary-subtle transition-colors duration-200"
                                            title="{{ __('Edit group fee') }}"
                                            aria-label="{{ __('Edit fee for') }} {{ $group['name'] }}"
                                        >
                                            {{-- Lucide: pencil (sm=16) --}}
                                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path><path d="m15 5 4 4"></path></svg>
                                        </button>
                                    </div>
                                </div>

                                {{-- Inline Edit Form for Group Fee --}}
                                <div x-show="editingGroupFeeId === {{ $group['id'] }}" x-cloak class="p-4 border-t border-primary/20 bg-primary-subtle/10">
                                    <form x-on:submit.prevent="$action('{{ url('/dashboard/locations/delivery-fees/group/' . $group['id']) }}', { method: 'PUT' })" class="flex flex-col sm:flex-row items-start sm:items-end gap-3">
                                        <div class="flex-1 w-full sm:w-auto">
                                            <label for="group-fee-{{ $group['id'] }}" class="block text-xs font-medium text-on-surface mb-1">
                                                {{ __('Delivery Fee for :group', ['group' => $group['name']]) }}
                                            </label>
                                            <div class="relative">
                                                <input
                                                    id="group-fee-{{ $group['id'] }}"
                                                    type="number"
                                                    min="0"
                                                    step="1"
                                                    x-model="group_fee_value"
                                                    x-name="group_fee_value"
                                                    class="w-full px-3.5 py-2.5 pr-14 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all duration-200 text-sm"
                                                    placeholder="{{ __('e.g. 500') }}"
                                                    autocomplete="off"
                                                >
                                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-on-surface/50">
                                                    {{ __('XAF') }}
                                                </span>
                                            </div>
                                            <p x-message="group_fee_value" class="mt-1 text-xs text-danger"></p>
                                            <p class="mt-1 text-xs text-on-surface/50">
                                                {{ __('This fee applies to all :count quarters in this group.', ['count' => $group['quarter_count']]) }}
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <button
                                                type="button"
                                                x-on:click="cancelEditGroupFee()"
                                                class="px-3 py-2 rounded-lg text-sm font-medium text-on-surface hover:bg-surface dark:hover:bg-surface transition-colors duration-200"
                                            >
                                                {{ __('Cancel') }}
                                            </button>
                                            <button
                                                type="submit"
                                                class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg bg-primary text-on-primary text-sm font-medium hover:bg-primary-hover transition-colors duration-200 shadow-sm"
                                            >
                                                <span x-show="!$fetching()">
                                                    {{-- Lucide: check --}}
                                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                                                </span>
                                                <span x-show="$fetching()">
                                                    <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                                </span>
                                                <span x-text="$fetching() ? '{{ __('Saving...') }}' : '{{ __('Save') }}'"></span>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Individual Quarter Fees by Town --}}
            <div>
                <h3 class="text-lg font-semibold text-on-surface-strong mb-4 flex items-center gap-2">
                    {{-- Lucide: map-pin --}}
                    <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    {{ __('Quarter Fees by Town') }}
                </h3>
                <p class="text-sm text-on-surface/60 mb-4">
                    {{ __('Click the edit button to change a quarter\'s delivery fee. Grouped quarters use their group fee.') }}
                </p>

                <div class="space-y-4">
                    @foreach($areas as $area)
                        <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-card overflow-hidden">
                            {{-- Town Header --}}
                            <div class="px-4 py-3 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-full bg-primary-subtle flex items-center justify-center shrink-0">
                                        {{-- Lucide: map-pin (sm=16) --}}
                                        <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-semibold text-on-surface-strong">{{ $area['town_name'] }}</h4>
                                        <p class="text-xs text-on-surface/60">
                                            {{ count($area['quarters']) }} {{ count($area['quarters']) === 1 ? __('quarter') : __('quarters') }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {{-- Quarter Fee Table (Desktop) --}}
                            <div class="hidden md:block">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-outline dark:border-outline">
                                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-on-surface/60 uppercase tracking-wider">{{ __('Quarter') }}</th>
                                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-on-surface/60 uppercase tracking-wider">{{ __('Fee (XAF)') }}</th>
                                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-on-surface/60 uppercase tracking-wider">{{ __('Group') }}</th>
                                            <th class="px-4 py-2.5 text-right text-xs font-semibold text-on-surface/60 uppercase tracking-wider">{{ __('Actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-outline dark:divide-outline">
                                        @foreach($area['quarters'] as $quarter)
                                            <tr x-show="editingQuarterFeeId !== {{ $quarter['id'] }}" class="hover:bg-surface dark:hover:bg-surface transition-colors duration-150">
                                                <td class="px-4 py-3">
                                                    <span class="font-medium text-on-surface-strong">{{ $quarter['quarter_name'] }}</span>
                                                </td>
                                                <td class="px-4 py-3">
                                                    @if($quarter['effective_fee'] === 0)
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-success-subtle text-success">
                                                            {{-- Lucide: check --}}
                                                            <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                                                            {{ __('Free delivery') }}
                                                        </span>
                                                    @else
                                                        <span class="font-medium text-on-surface-strong">{{ number_format($quarter['effective_fee']) }} {{ __('XAF') }}</span>
                                                    @endif
                                                    @if($quarter['is_grouped'])
                                                        <span class="text-xs text-on-surface/50 block mt-0.5">
                                                            {{ __('(group fee)') }}
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3">
                                                    @if($quarter['is_grouped'])
                                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-medium bg-info-subtle text-info">
                                                            {{-- Lucide: layers --}}
                                                            <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"></path><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"></path><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"></path></svg>
                                                            {{ $quarter['group_name'] }}
                                                        </span>
                                                    @else
                                                        <span class="text-xs text-on-surface/40">{{ __('None') }}</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-right">
                                                    @if($quarter['is_grouped'])
                                                        <span class="text-xs text-on-surface/40 italic">{{ __('Edit group') }}</span>
                                                    @else
                                                        <button
                                                            type="button"
                                                            x-on:click="startEditQuarterFee({{ $quarter['id'] }}, {{ $quarter['delivery_fee'] }})"
                                                            class="p-1.5 rounded-lg text-on-surface/60 hover:text-primary hover:bg-primary-subtle transition-colors duration-200"
                                                            title="{{ __('Edit fee') }}"
                                                            aria-label="{{ __('Edit fee for') }} {{ $quarter['quarter_name'] }}"
                                                        >
                                                            {{-- Lucide: pencil (sm=16) --}}
                                                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path><path d="m15 5 4 4"></path></svg>
                                                        </button>
                                                    @endif
                                                </td>
                                            </tr>
                                            {{-- Inline Edit Row for Quarter Fee --}}
                                            @if(! $quarter['is_grouped'])
                                                <tr x-show="editingQuarterFeeId === {{ $quarter['id'] }}" x-cloak class="bg-primary-subtle/10">
                                                    <td colspan="4" class="px-4 py-3">
                                                        <form x-on:submit.prevent="$action('{{ url('/dashboard/locations/delivery-fees/quarter/' . $quarter['id']) }}', { method: 'PUT' })" class="flex flex-col sm:flex-row items-start sm:items-end gap-3">
                                                            <div class="flex-1 w-full sm:w-auto">
                                                                <label for="quarter-fee-{{ $quarter['id'] }}" class="block text-xs font-medium text-on-surface mb-1">
                                                                    {{ __('Delivery Fee for :quarter', ['quarter' => $quarter['quarter_name']]) }}
                                                                </label>
                                                                <div class="relative">
                                                                    <input
                                                                        id="quarter-fee-{{ $quarter['id'] }}"
                                                                        type="number"
                                                                        min="0"
                                                                        step="1"
                                                                        x-model="fee_value"
                                                                        x-name="fee_value"
                                                                        class="w-full sm:w-48 px-3.5 py-2.5 pr-14 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all duration-200 text-sm"
                                                                        placeholder="{{ __('e.g. 500') }}"
                                                                        autocomplete="off"
                                                                    >
                                                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-on-surface/50">
                                                                        {{ __('XAF') }}
                                                                    </span>
                                                                </div>
                                                                <p x-message="fee_value" class="mt-1 text-xs text-danger"></p>
                                                            </div>
                                                            <div class="flex items-center gap-2 shrink-0">
                                                                <button
                                                                    type="button"
                                                                    x-on:click="cancelEditQuarterFee()"
                                                                    class="px-3 py-2 rounded-lg text-sm font-medium text-on-surface hover:bg-surface dark:hover:bg-surface transition-colors duration-200"
                                                                >
                                                                    {{ __('Cancel') }}
                                                                </button>
                                                                <button
                                                                    type="submit"
                                                                    class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg bg-primary text-on-primary text-sm font-medium hover:bg-primary-hover transition-colors duration-200 shadow-sm"
                                                                >
                                                                    <span x-show="!$fetching()">
                                                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                                                                    </span>
                                                                    <span x-show="$fetching()">
                                                                        <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                                                    </span>
                                                                    <span x-text="$fetching() ? '{{ __('Saving...') }}' : '{{ __('Save') }}'"></span>
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            {{-- Quarter Fee Cards (Mobile) --}}
                            <div class="md:hidden divide-y divide-outline dark:divide-outline">
                                @foreach($area['quarters'] as $quarter)
                                    {{-- Quarter Card Display --}}
                                    <div x-show="editingQuarterFeeId !== {{ $quarter['id'] }}" class="p-4">
                                        <div class="flex items-center justify-between">
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-medium text-on-surface-strong truncate">{{ $quarter['quarter_name'] }}</p>
                                                @if($quarter['is_grouped'])
                                                    <span class="inline-flex items-center gap-1 mt-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-info-subtle text-info">
                                                        {{-- Lucide: layers --}}
                                                        <svg class="w-2.5 h-2.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"></path><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"></path><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"></path></svg>
                                                        {{ $quarter['group_name'] }}
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-2 ml-3 shrink-0">
                                                @if($quarter['effective_fee'] === 0)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-success-subtle text-success">
                                                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                                                        {{ __('Free delivery') }}
                                                    </span>
                                                @else
                                                    <span class="text-sm font-semibold text-on-surface-strong">
                                                        {{ number_format($quarter['effective_fee']) }} {{ __('XAF') }}
                                                        @if($quarter['is_grouped'])
                                                            <span class="text-[10px] text-on-surface/50 block text-right">{{ __('(group)') }}</span>
                                                        @endif
                                                    </span>
                                                @endif
                                                @if(! $quarter['is_grouped'])
                                                    <button
                                                        type="button"
                                                        x-on:click="startEditQuarterFee({{ $quarter['id'] }}, {{ $quarter['delivery_fee'] }})"
                                                        class="p-1.5 rounded-lg text-on-surface/60 hover:text-primary hover:bg-primary-subtle transition-colors duration-200"
                                                        title="{{ __('Edit fee') }}"
                                                        aria-label="{{ __('Edit fee for') }} {{ $quarter['quarter_name'] }}"
                                                    >
                                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path><path d="m15 5 4 4"></path></svg>
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Mobile Inline Edit --}}
                                    @if(! $quarter['is_grouped'])
                                        <div x-show="editingQuarterFeeId === {{ $quarter['id'] }}" x-cloak class="p-4 bg-primary-subtle/10">
                                            <form x-on:submit.prevent="$action('{{ url('/dashboard/locations/delivery-fees/quarter/' . $quarter['id']) }}', { method: 'PUT' })" class="space-y-3">
                                                <div>
                                                    <label for="quarter-fee-mobile-{{ $quarter['id'] }}" class="block text-xs font-medium text-on-surface mb-1">
                                                        {{ __('Delivery Fee for :quarter', ['quarter' => $quarter['quarter_name']]) }}
                                                    </label>
                                                    <div class="relative">
                                                        <input
                                                            id="quarter-fee-mobile-{{ $quarter['id'] }}"
                                                            type="number"
                                                            min="0"
                                                            step="1"
                                                            x-model="fee_value"
                                                            x-name="fee_value"
                                                            class="w-full px-3.5 py-2.5 pr-14 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all duration-200 text-sm"
                                                            placeholder="{{ __('e.g. 500') }}"
                                                            autocomplete="off"
                                                        >
                                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-on-surface/50">
                                                            {{ __('XAF') }}
                                                        </span>
                                                    </div>
                                                    <p x-message="fee_value" class="mt-1 text-xs text-danger"></p>
                                                </div>
                                                <div class="flex items-center justify-end gap-2">
                                                    <button
                                                        type="button"
                                                        x-on:click="cancelEditQuarterFee()"
                                                        class="px-3 py-2 rounded-lg text-sm font-medium text-on-surface hover:bg-surface dark:hover:bg-surface transition-colors duration-200"
                                                    >
                                                        {{ __('Cancel') }}
                                                    </button>
                                                    <button
                                                        type="submit"
                                                        class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg bg-primary text-on-primary text-sm font-medium hover:bg-primary-hover transition-colors duration-200 shadow-sm"
                                                    >
                                                        <span x-show="!$fetching()">
                                                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                                                        </span>
                                                        <span x-show="$fetching()">
                                                            <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                                        </span>
                                                        <span x-text="$fetching() ? '{{ __('Saving...') }}' : '{{ __('Save') }}'"></span>
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Info Notice about existing orders (BR-276) --}}
            <div class="mt-6 p-4 rounded-lg border bg-info-subtle border-info/20 text-info">
                <div class="flex items-start gap-3">
                    {{-- Lucide: info --}}
                    <svg class="w-5 h-5 shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                    <p class="text-sm">
                        {{ __('Fee changes only apply to new orders. Existing orders retain the delivery fee that was active when the order was placed.') }}
                    </p>
                </div>
            </div>

        @else
            {{-- Empty State --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-8 text-center shadow-card">
                <div class="w-16 h-16 rounded-full bg-primary-subtle/50 flex items-center justify-center mx-auto mb-4">
                    {{-- Lucide: map-pin (xl=32) --}}
                    <svg class="w-8 h-8 text-primary/60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                </div>
                <h3 class="text-lg font-semibold text-on-surface-strong mb-2">{{ __('No quarters configured') }}</h3>
                <p class="text-sm text-on-surface/60 mb-4 max-w-md mx-auto">
                    {{ __('You haven\'t added any quarters yet. Add towns and quarters first, then configure their delivery fees here.') }}
                </p>
                <a
                    href="{{ url('/dashboard/locations') }}"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-primary text-on-primary text-sm font-medium hover:bg-primary-hover transition-colors duration-200 shadow-sm"
                >
                    {{-- Lucide: plus --}}
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                    {{ __('Add Quarters') }}
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
