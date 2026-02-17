{{--
    Complaint Escalation Queue
    --------------------------
    F-060: Displays a queue of complaints escalated to admin level.

    BR-158: Auto-escalated if cook does not respond within 24h
    BR-159: Clients and cooks can manually escalate at any time
    BR-160: Default sort: oldest unresolved complaints first (priority queue)
    BR-161: Categories: Food Quality, Late Delivery, Missing Items, Wrong Order, Rude Behavior, Other
    BR-162: Statuses: Pending Resolution, Under Review, Resolved, Dismissed
    BR-163: Only shows complaints that have reached admin level
    BR-164: Resolved/dismissed complaints sorted below unresolved ones

    UI/UX:
    - Priority queue styling with left border accent on unresolved
    - Category badges color-coded
    - Time since escalation with red color for >48h
    - Summary bar: Total Escalated, Pending Resolution, Resolved This Week
    - Mobile: card layout
--}}
@extends('layouts.admin')

@section('title', __('Complaints'))
@section('page-title', __('Complaints'))

@section('content')
<div class="space-y-6">
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[['label' => __('Complaints')]]" />

    {{-- Header --}}
    <div>
        <h2 class="text-lg font-semibold text-on-surface-strong">{{ __('Complaint Escalation Queue') }}</h2>
        <p class="text-sm text-on-surface mt-1">{{ __('Review and manage escalated complaints from customers.') }}</p>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
        {{-- Total Escalated --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-on-surface uppercase tracking-wide truncate">{{ __('Total Escalated') }}</p>
                    <p class="text-2xl font-bold text-on-surface-strong">{{ number_format($totalEscalated) }}</p>
                </div>
            </div>
        </div>

        {{-- Pending Resolution --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-warning-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-on-surface uppercase tracking-wide truncate">{{ __('Pending') }}</p>
                    <p class="text-2xl font-bold text-warning">{{ number_format($pendingCount) }}</p>
                </div>
            </div>
        </div>

        {{-- Resolved This Week --}}
        <div class="col-span-2 sm:col-span-1 bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-success-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-on-surface uppercase tracking-wide truncate">{{ __('Resolved This Week') }}</p>
                    <p class="text-2xl font-bold text-success">{{ number_format($resolvedThisWeek) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Main content area with search/filter/table --}}
    @fragment('complaint-list-content')
    <div id="complaint-list-content"
         x-data="{
             search: '{{ addslashes($search ?? '') }}',
             category: '{{ $category ?? '' }}',
             status: '{{ $status ?? '' }}',
             sortBy: '{{ $sortBy ?? '' }}',
             sortDir: '{{ $sortDir ?? 'asc' }}',
             baseUrl: '{{ url('/vault-entry/complaints') }}',
             buildUrl() {
                 let params = new URLSearchParams();
                 if (this.search) params.set('search', this.search);
                 if (this.category) params.set('category', this.category);
                 if (this.status) params.set('status', this.status);
                 if (this.sortBy) {
                     params.set('sort', this.sortBy);
                     params.set('direction', this.sortDir);
                 }
                 let qs = params.toString();
                 return this.baseUrl + (qs ? '?' + qs : '');
             },
             doSearch() {
                 $navigate(this.buildUrl(), { key: 'complaint-list', replace: true });
             },
             setSort(column) {
                 if (this.sortBy === column) {
                     this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                 } else {
                     this.sortBy = column;
                     this.sortDir = column === 'escalated_at' || column === 'submitted_at' ? 'asc' : 'asc';
                 }
                 $navigate(this.buildUrl(), { key: 'complaint-list', replace: true });
             },
             setCategory(val) {
                 this.category = val;
                 $navigate(this.buildUrl(), { key: 'complaint-list', replace: true });
             },
             setStatus(val) {
                 this.status = val;
                 $navigate(this.buildUrl(), { key: 'complaint-list', replace: true });
             },
             clearSearch() {
                 this.search = '';
                 $navigate(this.buildUrl(), { key: 'complaint-list', replace: true });
             },
             clearAll() {
                 this.search = '';
                 this.category = '';
                 this.status = '';
                 this.sortBy = '';
                 this.sortDir = 'asc';
                 $navigate(this.baseUrl, { key: 'complaint-list', replace: true });
             }
         }"
    >
        {{-- Search and Filters bar --}}
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
            {{-- Search input --}}
            <div class="relative flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-on-surface/50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                <input
                    type="text"
                    x-model="search"
                    @input.debounce.300ms="doSearch()"
                    placeholder="{{ __('Search by ID, client, cook, or description...') }}"
                    class="w-full h-10 pl-10 pr-9 border border-outline dark:border-outline rounded-lg text-sm text-on-surface-strong placeholder:text-on-surface/50 bg-surface dark:bg-surface transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                />
                <button
                    x-show="search.length > 0"
                    @click="clearSearch()"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface/50 hover:text-on-surface transition-colors"
                    x-cloak
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                </button>
            </div>

            {{-- Category filter dropdown --}}
            <div class="relative">
                <select
                    x-model="category"
                    @change="setCategory($event.target.value)"
                    class="h-10 pl-3 pr-8 border border-outline dark:border-outline rounded-lg text-sm text-on-surface-strong bg-surface dark:bg-surface transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary appearance-none cursor-pointer"
                >
                    <option value="">{{ __('All Categories') }}</option>
                    <option value="food_quality">{{ __('Food Quality') }}</option>
                    <option value="late_delivery">{{ __('Late Delivery') }}</option>
                    <option value="missing_items">{{ __('Missing Items') }}</option>
                    <option value="wrong_order">{{ __('Wrong Order') }}</option>
                    <option value="rude_behavior">{{ __('Rude Behavior') }}</option>
                    <option value="other">{{ __('Other') }}</option>
                </select>
                <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-on-surface/50 pointer-events-none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
            </div>

            {{-- Status filter dropdown --}}
            <div class="relative">
                <select
                    x-model="status"
                    @change="setStatus($event.target.value)"
                    class="h-10 pl-3 pr-8 border border-outline dark:border-outline rounded-lg text-sm text-on-surface-strong bg-surface dark:bg-surface transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary appearance-none cursor-pointer"
                >
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="pending_resolution">{{ __('Pending Resolution') }}</option>
                    <option value="under_review">{{ __('Under Review') }}</option>
                    <option value="resolved">{{ __('Resolved') }}</option>
                    <option value="dismissed">{{ __('Dismissed') }}</option>
                </select>
                <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-on-surface/50 pointer-events-none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
            </div>
        </div>

        {{-- Table (Desktop) --}}
        @if($complaints->count() > 0)
            <div class="hidden md:block bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-outline dark:border-outline">
                                @php
                                    $columns = [
                                        ['key' => 'id', 'label' => __('ID'), 'align' => 'left'],
                                        ['key' => '', 'label' => __('Client'), 'align' => 'left', 'sortable' => false],
                                        ['key' => '', 'label' => __('Cook'), 'align' => 'left', 'sortable' => false],
                                        ['key' => 'category', 'label' => __('Category'), 'align' => 'center'],
                                        ['key' => '', 'label' => __('Escalation Reason'), 'align' => 'left', 'sortable' => false],
                                        ['key' => 'escalated_at', 'label' => __('Escalated'), 'align' => 'left'],
                                        ['key' => 'status', 'label' => __('Status'), 'align' => 'center'],
                                    ];
                                @endphp
                                @foreach($columns as $col)
                                    @php $isSortable = ($col['sortable'] ?? true) && $col['key'] !== ''; @endphp
                                    <th class="{{ $col['align'] === 'center' ? 'text-center' : ($col['align'] === 'right' ? 'text-right' : 'text-left') }} text-xs font-semibold uppercase tracking-wider text-on-surface/60 px-4 py-3 whitespace-nowrap {{ $isSortable ? 'cursor-pointer select-none hover:text-on-surface-strong transition-colors' : '' }}"
                                        @if($isSortable) @click="setSort('{{ $col['key'] }}')" @endif
                                    >
                                        <span class="inline-flex items-center gap-1 {{ $col['align'] === 'right' ? 'justify-end' : '' }}">
                                            {{ $col['label'] }}
                                            @if($isSortable)
                                                <span class="inline-flex flex-col" :class="sortBy === '{{ $col['key'] }}' ? 'text-primary' : 'text-on-surface/30'">
                                                    <svg x-show="!(sortBy === '{{ $col['key'] }}' && sortDir === 'desc')" class="w-3 h-3 -mb-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"></path></svg>
                                                    <svg x-show="!(sortBy === '{{ $col['key'] }}' && sortDir === 'asc')" class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
                                                </span>
                                            @endif
                                        </span>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline dark:divide-outline" x-data x-navigate>
                            @foreach($complaints as $complaint)
                                <tr class="hover:bg-surface dark:hover:bg-surface transition-colors cursor-pointer group {{ $complaint->isUnresolved() ? 'border-l-4 border-l-warning' : '' }} {{ $complaint->isOverdue() ? 'bg-danger-subtle/20 dark:bg-danger-subtle/10' : '' }}">
                                    {{-- Complaint ID --}}
                                    <td class="px-4 py-3">
                                        <a href="{{ url('/vault-entry/complaints/'.$complaint->id) }}" class="block">
                                            <span class="text-sm font-mono font-medium text-on-surface-strong group-hover:text-primary transition-colors">
                                                #{{ $complaint->id }}
                                            </span>
                                            @if($complaint->order_id)
                                                <span class="text-xs text-on-surface/60 block">{{ __('Order') }} #{{ $complaint->order_id }}</span>
                                            @endif
                                        </a>
                                    </td>

                                    {{-- Client --}}
                                    <td class="px-4 py-3">
                                        <a href="{{ url('/vault-entry/complaints/'.$complaint->id) }}" class="block">
                                            <p class="text-sm text-on-surface-strong truncate max-w-[150px]">
                                                {{ $complaint->client?->name ?? '—' }}
                                                @if($complaint->client && ! $complaint->client->is_active)
                                                    <span class="text-xs text-danger ml-1">({{ __('Deactivated') }})</span>
                                                @endif
                                            </p>
                                            <p class="text-xs text-on-surface/60 truncate max-w-[150px]">{{ $complaint->client?->email ?? '' }}</p>
                                        </a>
                                    </td>

                                    {{-- Cook --}}
                                    <td class="px-4 py-3">
                                        <a href="{{ url('/vault-entry/complaints/'.$complaint->id) }}" class="block">
                                            <span class="text-sm text-on-surface truncate max-w-[120px] block">
                                                {{ $complaint->cook?->name ?? '—' }}
                                                @if($complaint->cook && ! $complaint->cook->is_active)
                                                    <span class="text-xs text-danger ml-1">({{ __('Deactivated') }})</span>
                                                @endif
                                            </span>
                                        </a>
                                    </td>

                                    {{-- Category --}}
                                    <td class="px-4 py-3 text-center">
                                        <a href="{{ url('/vault-entry/complaints/'.$complaint->id) }}" class="block">
                                            @include('admin.complaints._category-badge', ['category' => $complaint->category])
                                        </a>
                                    </td>

                                    {{-- Escalation Reason --}}
                                    <td class="px-4 py-3">
                                        <a href="{{ url('/vault-entry/complaints/'.$complaint->id) }}" class="block">
                                            <span class="text-sm text-on-surface">{{ $complaint->escalationReasonLabel() }}</span>
                                        </a>
                                    </td>

                                    {{-- Escalated Date --}}
                                    <td class="px-4 py-3">
                                        <a href="{{ url('/vault-entry/complaints/'.$complaint->id) }}" class="block">
                                            <span class="text-sm {{ $complaint->isOverdue() ? 'text-danger font-semibold' : 'text-on-surface' }}" title="{{ $complaint->escalated_at?->format('Y-m-d H:i:s') }}">
                                                {{ $complaint->timeSinceEscalation() }}
                                            </span>
                                            <span class="text-xs text-on-surface/60 block">{{ $complaint->escalated_at?->format('M d, Y') }}</span>
                                        </a>
                                    </td>

                                    {{-- Status --}}
                                    <td class="px-4 py-3 text-center">
                                        <a href="{{ url('/vault-entry/complaints/'.$complaint->id) }}" class="block">
                                            @include('admin.complaints._status-badge', ['status' => $complaint->status])
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Mobile card view --}}
            <div class="md:hidden space-y-3" x-data x-navigate>
                @foreach($complaints as $complaint)
                    <a href="{{ url('/vault-entry/complaints/'.$complaint->id) }}" class="block bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 hover:border-primary/30 transition-colors {{ $complaint->isUnresolved() ? 'border-l-4 border-l-warning' : '' }} {{ $complaint->isOverdue() ? 'border-l-danger bg-danger-subtle/20 dark:bg-danger-subtle/10' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-mono font-semibold text-on-surface-strong">#{{ $complaint->id }}</span>
                                    @include('admin.complaints._category-badge', ['category' => $complaint->category])
                                </div>
                                <p class="text-xs text-on-surface/60 mt-1">
                                    {{ $complaint->client?->name ?? '—' }}
                                    @if($complaint->client && ! $complaint->client->is_active)
                                        <span class="text-danger">({{ __('Deactivated') }})</span>
                                    @endif
                                    <span class="mx-1">vs</span>
                                    {{ $complaint->cook?->name ?? '—' }}
                                    @if($complaint->cook && ! $complaint->cook->is_active)
                                        <span class="text-danger">({{ __('Deactivated') }})</span>
                                    @endif
                                </p>
                            </div>
                            @include('admin.complaints._status-badge', ['status' => $complaint->status])
                        </div>
                        <p class="text-sm text-on-surface mt-2 line-clamp-2">{{ $complaint->description }}</p>
                        <div class="mt-3 flex items-center justify-between text-xs text-on-surface/60">
                            <span>{{ $complaint->escalationReasonLabel() }}</span>
                            <span class="{{ $complaint->isOverdue() ? 'text-danger font-semibold' : '' }}">{{ $complaint->timeSinceEscalation() }}</span>
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if($complaints->hasPages())
                <div class="mt-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <p class="text-sm text-on-surface/60">
                        {{ __('Showing :from-:to of :total complaints', [
                            'from' => $complaints->firstItem(),
                            'to' => $complaints->lastItem(),
                            'total' => $complaints->total(),
                        ]) }}
                    </p>
                    <div x-data x-navigate>
                        {{ $complaints->links() }}
                    </div>
                </div>
            @else
                <p class="mt-4 text-sm text-on-surface/60">
                    {{ __('Showing :from-:to of :total complaints', [
                        'from' => $complaints->firstItem() ?? 0,
                        'to' => $complaints->lastItem() ?? 0,
                        'total' => $complaints->total(),
                    ]) }}
                </p>
            @endif

        @elseif(!empty($search) || !empty($category) || !empty($status))
            {{-- No results from search/filter --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-8 sm:p-12 text-center">
                <svg class="w-12 h-12 mx-auto text-on-surface/30 mb-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                <p class="text-on-surface font-medium">{{ __('No complaints match your filters.') }}</p>
                <p class="text-sm text-on-surface/60 mt-1">{{ __('Try adjusting your search or filter criteria.') }}</p>
                <button
                    @click="clearAll()"
                    class="mt-4 inline-flex items-center gap-2 h-10 px-5 text-sm rounded-lg font-semibold border border-outline text-on-surface hover:bg-surface-alt transition-all duration-200"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                    {{ __('Clear Filters') }}
                </button>
            </div>
        @else
            {{-- Empty state: no escalated complaints --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-8 sm:p-12 text-center">
                <svg class="w-12 h-12 mx-auto text-success/50 mb-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <p class="text-on-surface font-medium">{{ __('No escalated complaints. All issues have been resolved.') }}</p>
                <p class="text-sm text-on-surface/60 mt-1">{{ __('Escalated complaints will appear here when they need admin attention.') }}</p>
            </div>
        @endif
    </div>
    @endfragment
</div>
@endsection
