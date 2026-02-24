{{--
    Tenant List & Search View
    -------------------------
    F-046: Paginated, searchable, sortable list of all tenants in the admin panel.

    BR-064: Paginated with 15 items per page
    BR-065: Search covers name_en, name_fr, subdomain, custom_domain
    BR-066: Status filter: All, Active, Inactive
    BR-067: Default sort: created_at descending (newest first)
    BR-068: All columns are sortable
    BR-069: Order count aggregate (stub: orders table not yet created)
--}}
@extends('layouts.admin')

@section('title', __('Tenants'))
@section('page-title', __('Tenants'))

@section('content')
<div class="space-y-6">
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[['label' => __('Tenants')]]" />

    {{-- Header with create button --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-on-surface-strong">{{ __('All Tenants') }}</h2>
            <p class="text-sm text-on-surface mt-1">{{ __('Manage tenant websites and their configurations.') }}</p>
        </div>
        <a
            href="{{ url('/vault-entry/tenants/create') }}"
            class="h-10 px-5 text-sm rounded-lg font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200 inline-flex items-center gap-2 self-start
                   focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 active:scale-[0.98]"
        >
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
            {{ __('Create Tenant') }}
        </a>
    </div>

    {{-- Toast message --}}
    @if(session('toast'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 5000)"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="p-4 rounded-lg border {{ session('toast.type') === 'success' ? 'bg-success-subtle border-success/30 text-success' : 'bg-danger-subtle border-danger/30 text-danger' }}"
        >
            <div class="flex items-center gap-2">
                @if(session('toast.type') === 'success')
                    <svg class="w-5 h-5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                @endif
                <p class="text-sm font-medium">{{ session('toast.message') }}</p>
            </div>
        </div>
    @endif

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        {{-- Total Tenants --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center">
                    <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="14" rx="1"></rect><rect width="7" height="7" x="3" y="14" rx="1"></rect></svg>
                </span>
                <div>
                    <p class="text-xs font-medium text-on-surface uppercase tracking-wide">{{ __('Total Tenants') }}</p>
                    <p class="text-2xl font-bold text-on-surface-strong">{{ $totalCount }}</p>
                </div>
            </div>
        </div>

        {{-- Active --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-success-subtle flex items-center justify-center">
                    <svg class="w-5 h-5 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                </span>
                <div>
                    <p class="text-xs font-medium text-on-surface uppercase tracking-wide">{{ __('Active') }}</p>
                    <p class="text-2xl font-bold text-success">{{ $activeCount }}</p>
                </div>
            </div>
        </div>

        {{-- Inactive --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-outline/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-on-surface/60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" x2="19.07" y1="4.93" y2="19.07"></line></svg>
                </span>
                <div>
                    <p class="text-xs font-medium text-on-surface uppercase tracking-wide">{{ __('Inactive') }}</p>
                    <p class="text-2xl font-bold text-on-surface/60">{{ $inactiveCount }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Main content area with search/filter/table --}}
    @fragment('tenant-list-content')
    <div id="tenant-list-content"
         x-data="{
             search: '{{ addslashes($search ?? '') }}',
             status: '{{ $status ?? '' }}',
             sortBy: '{{ $sortBy ?? 'created_at' }}',
             sortDir: '{{ $sortDir ?? 'desc' }}',
             baseUrl: '{{ url('/vault-entry/tenants') }}',
             buildUrl() {
                 let params = new URLSearchParams();
                 if (this.search) params.set('search', this.search);
                 if (this.status) params.set('status', this.status);
                 if (this.sortBy !== 'created_at' || this.sortDir !== 'desc') {
                     params.set('sort', this.sortBy);
                     params.set('direction', this.sortDir);
                 }
                 let qs = params.toString();
                 return this.baseUrl + (qs ? '?' + qs : '');
             },
             doSearch() {
                 $navigate(this.buildUrl(), { key: 'tenant-list', replace: true });
             },
             setSort(column) {
                 if (this.sortBy === column) {
                     this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                 } else {
                     this.sortBy = column;
                     this.sortDir = 'asc';
                 }
                 $navigate(this.buildUrl(), { key: 'tenant-list', replace: true });
             },
             setStatus(val) {
                 this.status = val;
                 $navigate(this.buildUrl(), { key: 'tenant-list', replace: true });
             },
             clearSearch() {
                 this.search = '';
                 $navigate(this.buildUrl(), { key: 'tenant-list', replace: true });
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
                    placeholder="{{ __('Search by name, subdomain, or domain...') }}"
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

            {{-- Status filter dropdown --}}
            <div class="relative">
                <select
                    x-model="status"
                    @change="setStatus($event.target.value)"
                    class="h-10 pl-3 pr-8 border border-outline dark:border-outline rounded-lg text-sm text-on-surface-strong bg-surface dark:bg-surface transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary appearance-none cursor-pointer"
                >
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="active">{{ __('Active') }} ({{ $activeCount }})</option>
                    <option value="inactive">{{ __('Inactive') }} ({{ $inactiveCount }})</option>
                </select>
                <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-on-surface/50 pointer-events-none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
            </div>
        </div>

        {{-- Table (Desktop) --}}
        @if($tenants->count() > 0)
            {{-- Desktop table view --}}
            <div class="hidden md:block bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-outline dark:border-outline">
                                @php
                                    $columns = [
                                        ['key' => 'name', 'label' => __('Name'), 'align' => 'left'],
                                        ['key' => 'slug', 'label' => __('Subdomain'), 'align' => 'left'],
                                        ['key' => 'custom_domain', 'label' => __('Domain'), 'align' => 'left'],
                                        ['key' => 'cook', 'label' => __('Cook'), 'align' => 'left', 'sortable' => false],
                                        ['key' => 'status', 'label' => __('Status'), 'align' => 'center'],
                                        ['key' => 'orders', 'label' => __('Orders'), 'align' => 'center', 'sortable' => false],
                                        ['key' => 'created_at', 'label' => __('Created'), 'align' => 'left'],
                                    ];
                                @endphp
                                @foreach($columns as $col)
                                    @php
                                        $isSortable = $col['sortable'] ?? true;
                                    @endphp
                                    <th class="{{ $col['align'] === 'center' ? 'text-center' : 'text-left' }} text-xs font-semibold uppercase tracking-wider text-on-surface/60 px-4 py-3 whitespace-nowrap {{ $isSortable ? 'cursor-pointer select-none hover:text-on-surface-strong transition-colors' : '' }}"
                                        @if($isSortable) @click="setSort('{{ $col['key'] }}')" @endif
                                    >
                                        <span class="inline-flex items-center gap-1">
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
                            @foreach($tenants as $tenant)
                                <tr class="hover:bg-surface dark:hover:bg-surface transition-colors cursor-pointer group">
                                    {{-- Name --}}
                                    <td class="px-4 py-3">
                                        <a href="{{ url('/vault-entry/tenants/'.$tenant->slug) }}" class="block">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-lg bg-primary-subtle flex items-center justify-center text-primary font-semibold text-xs shrink-0">
                                                    {{ mb_strtoupper(mb_substr($tenant->name, 0, 1)) }}
                                                </div>
                                                <span class="text-sm font-medium text-on-surface-strong group-hover:text-primary transition-colors truncate max-w-[200px]" title="{{ $tenant->name }}">
                                                    {{ $tenant->name }}
                                                </span>
                                            </div>
                                        </a>
                                    </td>

                                    {{-- Subdomain --}}
                                    <td class="px-4 py-3">
                                        <a href="{{ url('/vault-entry/tenants/'.$tenant->slug) }}" class="block">
                                            <span class="text-sm text-on-surface font-mono truncate max-w-[180px] block" title="{{ $tenant->slug }}.{{ $mainDomain }}">
                                                {{ $tenant->slug }}.{{ $mainDomain }}
                                            </span>
                                        </a>
                                    </td>

                                    {{-- Custom Domain --}}
                                    <td class="px-4 py-3">
                                        <a href="{{ url('/vault-entry/tenants/'.$tenant->slug) }}" class="block">
                                            @if($tenant->custom_domain)
                                                <span class="text-sm text-on-surface font-mono truncate max-w-[180px] block" title="{{ $tenant->custom_domain }}">{{ $tenant->custom_domain }}</span>
                                            @else
                                                <span class="text-sm text-on-surface/40 italic">{{ __('None') }}</span>
                                            @endif
                                        </a>
                                    </td>

                                    {{-- Cook --}}
                                    <td class="px-4 py-3">
                                        <a href="{{ url('/vault-entry/tenants/'.$tenant->slug) }}" class="block">
                                            @if($tenant->cook)
                                                <span class="text-sm text-on-surface">{{ $tenant->cook->name }}</span>
                                            @else
                                                <span class="text-sm text-on-surface/50 italic">{{ __('Unassigned') }}</span>
                                            @endif
                                        </a>
                                    </td>

                                    {{-- Status --}}
                                    <td class="px-4 py-3 text-center">
                                        <a href="{{ url('/vault-entry/tenants/'.$tenant->slug) }}" class="block">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $tenant->is_active ? 'bg-success-subtle text-success' : 'bg-outline/20 text-on-surface/60' }}">
                                                <span class="w-1.5 h-1.5 rounded-full bg-current mr-1.5"></span>
                                                {{ $tenant->is_active ? __('Active') : __('Inactive') }}
                                            </span>
                                        </a>
                                    </td>

                                    {{-- Orders --}}
                                    <td class="px-4 py-3 text-center">
                                        <a href="{{ url('/vault-entry/tenants/'.$tenant->slug) }}" class="block">
                                            <span class="text-sm text-on-surface font-mono">0</span>
                                        </a>
                                    </td>

                                    {{-- Created --}}
                                    <td class="px-4 py-3">
                                        <a href="{{ url('/vault-entry/tenants/'.$tenant->slug) }}" class="block">
                                            <span class="text-sm text-on-surface" title="{{ $tenant->created_at?->format('Y-m-d H:i') }}">{{ $tenant->created_at?->format('M d, Y') }}</span>
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
                @foreach($tenants as $tenant)
                    <a href="{{ url('/vault-entry/tenants/'.$tenant->slug) }}" class="block bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 hover:border-primary/30 transition-colors">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-10 h-10 rounded-lg bg-primary-subtle flex items-center justify-center text-primary font-semibold text-sm shrink-0">
                                    {{ mb_strtoupper(mb_substr($tenant->name, 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-sm font-semibold text-on-surface-strong truncate">{{ $tenant->name }}</h3>
                                    <p class="text-xs text-on-surface/60 font-mono mt-0.5 truncate">{{ $tenant->slug }}.{{ $mainDomain }}</p>
                                </div>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold shrink-0 {{ $tenant->is_active ? 'bg-success-subtle text-success' : 'bg-outline/20 text-on-surface/60' }}">
                                <span class="w-1.5 h-1.5 rounded-full bg-current mr-1"></span>
                                {{ $tenant->is_active ? __('Active') : __('Inactive') }}
                            </span>
                        </div>
                        <div class="mt-3 flex items-center gap-4 text-xs text-on-surface/60">
                            @if($tenant->custom_domain)
                                <span class="font-mono truncate" title="{{ $tenant->custom_domain }}">{{ $tenant->custom_domain }}</span>
                            @endif
                            <span>{{ $tenant->created_at?->format('M d, Y') }}</span>
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if($tenants->hasPages())
                <div class="mt-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <p class="text-sm text-on-surface/60">
                        {{ __('Showing :from-:to of :total tenants', [
                            'from' => $tenants->firstItem(),
                            'to' => $tenants->lastItem(),
                            'total' => $tenants->total(),
                        ]) }}
                    </p>
                    <div x-data x-navigate>
                        {{ $tenants->links() }}
                    </div>
                </div>
            @else
                <p class="mt-4 text-sm text-on-surface/60">
                    {{ __('Showing :from-:to of :total tenants', [
                        'from' => $tenants->firstItem() ?? 0,
                        'to' => $tenants->lastItem() ?? 0,
                        'total' => $tenants->total(),
                    ]) }}
                </p>
            @endif

        @elseif(!empty($search) || !empty($status))
            {{-- No results from search/filter --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-8 sm:p-12 text-center">
                <svg class="w-12 h-12 mx-auto text-on-surface/30 mb-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                <p class="text-on-surface font-medium">{{ __('No tenants match your search.') }}</p>
                <p class="text-sm text-on-surface/60 mt-1">{{ __('Try adjusting your search or filter criteria.') }}</p>
                <button
                    @click="search = ''; status = ''; $navigate(baseUrl, { key: 'tenant-list', replace: true })"
                    class="mt-4 inline-flex items-center gap-2 h-10 px-5 text-sm rounded-lg font-semibold border border-outline text-on-surface hover:bg-surface-alt transition-all duration-200"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                    {{ __('Clear Filters') }}
                </button>
            </div>
        @else
            {{-- Empty state: no tenants exist at all --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-8 sm:p-12 text-center">
                <svg class="w-12 h-12 mx-auto text-on-surface/30 mb-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="14" rx="1"></rect><rect width="7" height="7" x="3" y="14" rx="1"></rect></svg>
                <p class="text-on-surface font-medium">{{ __('No tenants yet. Create your first tenant.') }}</p>
                <p class="text-sm text-on-surface/60 mt-1">{{ __('Create your first tenant to get started.') }}</p>
                <a
                    href="{{ url('/vault-entry/tenants/create') }}"
                    class="mt-4 inline-flex items-center gap-2 h-10 px-5 text-sm rounded-lg font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                    {{ __('Create Tenant') }}
                </a>
            </div>
        @endif
    </div>
    @endfragment
</div>
@endsection
