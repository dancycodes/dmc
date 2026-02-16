{{--
    User Management List & Search
    -----------------------------
    F-050: Paginated, searchable, filterable list of all platform users in admin panel.

    BR-089: User list shows ALL platform users regardless of tenant
    BR-090: Pagination defaults to 20 items per page
    BR-091: Search covers: name, email, phone number
    BR-092: Role filter shows all system roles
    BR-093: Status filter options: All, Active, Inactive
    BR-094: Default sort: registration date descending (newest first)
    BR-095: Last login shows relative time
    BR-096: Users with multiple roles show all roles as separate badges
--}}
@extends('layouts.admin')

@section('title', __('Users'))
@section('page-title', __('Users'))

@section('content')
<div class="space-y-6">
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[['label' => __('Users')]]" />

    {{-- Header --}}
    <div>
        <h2 class="text-lg font-semibold text-on-surface-strong">{{ __('All Users') }}</h2>
        <p class="text-sm text-on-surface mt-1">{{ __('View and manage all platform user accounts.') }}</p>
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
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        {{-- Total Users --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center">
                    <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                </span>
                <div>
                    <p class="text-xs font-medium text-on-surface uppercase tracking-wide">{{ __('Total Users') }}</p>
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

        {{-- New This Month --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-info-subtle flex items-center justify-center">
                    <svg class="w-5 h-5 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" x2="19" y1="8" y2="14"></line><line x1="22" x2="16" y1="11" y2="11"></line></svg>
                </span>
                <div>
                    <p class="text-xs font-medium text-on-surface uppercase tracking-wide">{{ __('New This Month') }}</p>
                    <p class="text-2xl font-bold text-info">{{ $newThisMonth }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Main content area with search/filter/table --}}
    @fragment('user-list-content')
    <div id="user-list-content"
         x-data="{
             search: '{{ addslashes($search ?? '') }}',
             role: '{{ $role ?? '' }}',
             status: '{{ $status ?? '' }}',
             sortBy: '{{ $sortBy ?? 'created_at' }}',
             sortDir: '{{ $sortDir ?? 'desc' }}',
             baseUrl: '{{ url('/vault-entry/users') }}',
             buildUrl() {
                 let params = new URLSearchParams();
                 if (this.search) params.set('search', this.search);
                 if (this.role) params.set('role', this.role);
                 if (this.status) params.set('status', this.status);
                 if (this.sortBy !== 'created_at' || this.sortDir !== 'desc') {
                     params.set('sort', this.sortBy);
                     params.set('direction', this.sortDir);
                 }
                 let qs = params.toString();
                 return this.baseUrl + (qs ? '?' + qs : '');
             },
             doSearch() {
                 $navigate(this.buildUrl(), { key: 'user-list', replace: true });
             },
             setSort(column) {
                 if (this.sortBy === column) {
                     this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                 } else {
                     this.sortBy = column;
                     this.sortDir = 'asc';
                 }
                 $navigate(this.buildUrl(), { key: 'user-list', replace: true });
             },
             setRole(val) {
                 this.role = val;
                 $navigate(this.buildUrl(), { key: 'user-list', replace: true });
             },
             setStatus(val) {
                 this.status = val;
                 $navigate(this.buildUrl(), { key: 'user-list', replace: true });
             },
             clearFilters() {
                 this.search = '';
                 this.role = '';
                 this.status = '';
                 this.sortBy = 'created_at';
                 this.sortDir = 'desc';
                 $navigate(this.baseUrl, { key: 'user-list', replace: true });
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
                    placeholder="{{ __('Search by name, email, or phone...') }}"
                    class="w-full h-10 pl-10 pr-9 border border-outline dark:border-outline rounded-lg text-sm text-on-surface-strong placeholder:text-on-surface/50 bg-surface dark:bg-surface transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                />
                <button
                    x-show="search.length > 0"
                    @click="search = ''; doSearch()"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface/50 hover:text-on-surface transition-colors"
                    x-cloak
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                </button>
            </div>

            {{-- Role filter dropdown --}}
            <div class="relative">
                <select
                    x-model="role"
                    @change="setRole($event.target.value)"
                    class="h-10 pl-3 pr-8 border border-outline dark:border-outline rounded-lg text-sm text-on-surface-strong bg-surface dark:bg-surface transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary appearance-none cursor-pointer"
                >
                    <option value="">{{ __('All Roles') }}</option>
                    @foreach($allRoles as $roleName)
                        <option value="{{ $roleName }}">{{ ucfirst(str_replace('-', ' ', $roleName)) }}</option>
                    @endforeach
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
                    <option value="active">{{ __('Active') }} ({{ $activeCount }})</option>
                    <option value="inactive">{{ __('Inactive') }} ({{ $inactiveCount }})</option>
                </select>
                <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-on-surface/50 pointer-events-none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
            </div>
        </div>

        {{-- Table (Desktop) --}}
        @if($users->count() > 0)
            {{-- Desktop table view --}}
            <div class="hidden md:block bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-outline dark:border-outline">
                                @php
                                    $columns = [
                                        ['key' => 'name', 'label' => __('Name'), 'align' => 'left'],
                                        ['key' => 'email', 'label' => __('Email'), 'align' => 'left'],
                                        ['key' => 'phone', 'label' => __('Phone'), 'align' => 'left'],
                                        ['key' => 'roles', 'label' => __('Roles'), 'align' => 'left', 'sortable' => false],
                                        ['key' => 'status', 'label' => __('Status'), 'align' => 'center'],
                                        ['key' => 'created_at', 'label' => __('Registered'), 'align' => 'left'],
                                        ['key' => 'last_login_at', 'label' => __('Last Login'), 'align' => 'left'],
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
                            @foreach($users as $user)
                                <tr class="hover:bg-surface dark:hover:bg-surface transition-colors cursor-pointer group">
                                    {{-- Name --}}
                                    <td class="px-4 py-3">
                                        <a href="{{ url('/vault-entry/users/'.$user->id) }}" class="block">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-full bg-primary-subtle flex items-center justify-center text-primary font-semibold text-xs shrink-0">
                                                    {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                                                </div>
                                                <span class="text-sm font-medium text-on-surface-strong group-hover:text-primary transition-colors truncate max-w-[180px]" title="{{ $user->name }}">
                                                    {{ $user->name }}
                                                </span>
                                            </div>
                                        </a>
                                    </td>

                                    {{-- Email --}}
                                    <td class="px-4 py-3">
                                        <a href="{{ url('/vault-entry/users/'.$user->id) }}" class="block">
                                            <span class="text-sm text-on-surface truncate max-w-[200px] block" title="{{ $user->email }}">
                                                {{ $user->email }}
                                            </span>
                                        </a>
                                    </td>

                                    {{-- Phone --}}
                                    <td class="px-4 py-3">
                                        <a href="{{ url('/vault-entry/users/'.$user->id) }}" class="block">
                                            <span class="text-sm text-on-surface font-mono">+237 {{ $user->phone }}</span>
                                        </a>
                                    </td>

                                    {{-- Roles (BR-096) --}}
                                    <td class="px-4 py-3">
                                        <a href="{{ url('/vault-entry/users/'.$user->id) }}" class="block">
                                            <div class="flex flex-wrap gap-1">
                                                @forelse($user->roles as $userRole)
                                                    @php
                                                        $roleBadgeClass = match($userRole->name) {
                                                            'super-admin' => 'bg-danger-subtle text-danger',
                                                            'admin' => 'bg-warning-subtle text-warning',
                                                            'cook' => 'bg-info-subtle text-info',
                                                            'manager' => 'bg-secondary-subtle text-secondary',
                                                            'client' => 'bg-success-subtle text-success',
                                                            default => 'bg-outline/20 text-on-surface/60',
                                                        };
                                                    @endphp
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $roleBadgeClass }}">
                                                        {{ ucfirst(str_replace('-', ' ', $userRole->name)) }}
                                                    </span>
                                                @empty
                                                    <span class="text-xs text-on-surface/40 italic">{{ __('No role') }}</span>
                                                @endforelse
                                            </div>
                                        </a>
                                    </td>

                                    {{-- Status --}}
                                    <td class="px-4 py-3 text-center">
                                        <a href="{{ url('/vault-entry/users/'.$user->id) }}" class="block">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $user->is_active ? 'bg-success-subtle text-success' : 'bg-outline/20 text-on-surface/60' }}">
                                                <span class="w-1.5 h-1.5 rounded-full bg-current mr-1.5"></span>
                                                {{ $user->is_active ? __('Active') : __('Inactive') }}
                                            </span>
                                        </a>
                                    </td>

                                    {{-- Registered --}}
                                    <td class="px-4 py-3">
                                        <a href="{{ url('/vault-entry/users/'.$user->id) }}" class="block">
                                            <span class="text-sm text-on-surface" title="{{ $user->created_at?->format('Y-m-d H:i') }}">{{ $user->created_at?->format('M d, Y') }}</span>
                                        </a>
                                    </td>

                                    {{-- Last Login (BR-095) --}}
                                    <td class="px-4 py-3">
                                        <a href="{{ url('/vault-entry/users/'.$user->id) }}" class="block">
                                            @if($user->last_login_at)
                                                <span class="text-sm text-on-surface" title="{{ $user->last_login_at->format('Y-m-d H:i') }}">{{ $user->last_login_at->diffForHumans() }}</span>
                                            @else
                                                <span class="text-sm text-on-surface/40 italic">{{ __('Never') }}</span>
                                            @endif
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
                @foreach($users as $user)
                    <a href="{{ url('/vault-entry/users/'.$user->id) }}" class="block bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 hover:border-primary/30 transition-colors">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center text-primary font-semibold text-sm shrink-0">
                                    {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-sm font-semibold text-on-surface-strong truncate">{{ $user->name }}</h3>
                                    <p class="text-xs text-on-surface/60 mt-0.5 truncate">{{ $user->email }}</p>
                                </div>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold shrink-0 {{ $user->is_active ? 'bg-success-subtle text-success' : 'bg-outline/20 text-on-surface/60' }}">
                                <span class="w-1.5 h-1.5 rounded-full bg-current mr-1"></span>
                                {{ $user->is_active ? __('Active') : __('Inactive') }}
                            </span>
                        </div>
                        <div class="mt-3 space-y-2">
                            {{-- Phone --}}
                            <div class="flex items-center gap-2 text-xs text-on-surface/60">
                                <svg class="w-3.5 h-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                <span class="font-mono">+237 {{ $user->phone }}</span>
                            </div>
                            {{-- Roles --}}
                            <div class="flex flex-wrap gap-1">
                                @forelse($user->roles as $userRole)
                                    @php
                                        $roleBadgeClass = match($userRole->name) {
                                            'super-admin' => 'bg-danger-subtle text-danger',
                                            'admin' => 'bg-warning-subtle text-warning',
                                            'cook' => 'bg-info-subtle text-info',
                                            'manager' => 'bg-secondary-subtle text-secondary',
                                            'client' => 'bg-success-subtle text-success',
                                            default => 'bg-outline/20 text-on-surface/60',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $roleBadgeClass }}">
                                        {{ ucfirst(str_replace('-', ' ', $userRole->name)) }}
                                    </span>
                                @empty
                                    <span class="text-xs text-on-surface/40 italic">{{ __('No role') }}</span>
                                @endforelse
                            </div>
                            {{-- Meta: registered + last login --}}
                            <div class="flex items-center gap-4 text-xs text-on-surface/60">
                                <span>{{ __('Joined') }} {{ $user->created_at?->format('M d, Y') }}</span>
                                @if($user->last_login_at)
                                    <span title="{{ $user->last_login_at->format('Y-m-d H:i') }}">{{ __('Last login') }} {{ $user->last_login_at->diffForHumans() }}</span>
                                @else
                                    <span>{{ __('Last login') }}: {{ __('Never') }}</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if($users->hasPages())
                <div class="mt-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <p class="text-sm text-on-surface/60">
                        {{ __('Showing :from-:to of :total users', [
                            'from' => $users->firstItem(),
                            'to' => $users->lastItem(),
                            'total' => $users->total(),
                        ]) }}
                    </p>
                    <div x-data x-navigate>
                        {{ $users->links() }}
                    </div>
                </div>
            @else
                <p class="mt-4 text-sm text-on-surface/60">
                    {{ __('Showing :from-:to of :total users', [
                        'from' => $users->firstItem() ?? 0,
                        'to' => $users->lastItem() ?? 0,
                        'total' => $users->total(),
                    ]) }}
                </p>
            @endif

        @elseif(!empty($search) || !empty($role) || !empty($status))
            {{-- No results from search/filter --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-8 sm:p-12 text-center">
                <svg class="w-12 h-12 mx-auto text-on-surface/30 mb-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                <p class="text-on-surface font-medium">{{ __('No users match your criteria.') }}</p>
                <p class="text-sm text-on-surface/60 mt-1">{{ __('Try adjusting your search or filter criteria.') }}</p>
                <button
                    @click="clearFilters()"
                    class="mt-4 inline-flex items-center gap-2 h-10 px-5 text-sm rounded-lg font-semibold border border-outline text-on-surface hover:bg-surface-alt transition-all duration-200"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                    {{ __('Clear Filters') }}
                </button>
            </div>
        @else
            {{-- Empty state: no users at all --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-8 sm:p-12 text-center">
                <svg class="w-12 h-12 mx-auto text-on-surface/30 mb-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                <p class="text-on-surface font-medium">{{ __('No users yet.') }}</p>
                <p class="text-sm text-on-surface/60 mt-1">{{ __('Users will appear here once they register on the platform.') }}</p>
            </div>
        @endif
    </div>
    @endfragment
</div>
@endsection
