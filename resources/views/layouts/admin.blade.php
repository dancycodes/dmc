{{--
    Admin Panel Layout
    ------------------
    Used at /vault-entry/* on the main domain for platform administration.
    F-043: Admin Panel Layout & Access Control

    Features:
    - Collapsible sidebar with permission-based navigation (BR-046)
    - Grouped navigation: Overview, Management, Insights, Operations, System
    - Responsive mobile overlay (BR-048)
    - Light/dark mode support (BR-047)
    - Breadcrumb navigation on all pages
    - Platform default theme (not tenant themes)

    BR-043: Admin panel routes ONLY accessible on main domain
    BR-045: Only users with can-access-admin-panel permission
    BR-046: Sidebar sections based on user permissions — hidden if no permission
    BR-047: Must support light and dark mode
    BR-048: All admin pages fully responsive (mobile-first)
--}}
@extends('layouts.app')

@section('body')
@php
    $user = auth()->user();

    // Navigation items grouped by section with permission requirements
    // BR-046: Sections without permission are hidden
    $adminNavGroups = [
        [
            'title' => __('Overview'),
            'items' => [
                [
                    'url' => '/vault-entry',
                    'icon' => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline>',
                    'label' => __('Dashboard'),
                    'permission' => null, // Always visible if user has admin panel access
                ],
            ],
        ],
        [
            'title' => __('Management'),
            'items' => [
                [
                    'url' => '/vault-entry/tenants',
                    'icon' => '<rect width="7" height="7" x="3" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="14" rx="1"></rect><rect width="7" height="7" x="3" y="14" rx="1"></rect>',
                    'label' => __('Tenants'),
                    'permission' => 'can-view-tenants',
                ],
                [
                    'url' => '/vault-entry/users',
                    'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" x2="19" y1="8" y2="14"></line><line x1="22" x2="16" y1="11" y2="11"></line>',
                    'label' => __('Users'),
                    'permission' => 'can-manage-users',
                ],
                [
                    'url' => '/vault-entry/roles',
                    'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"></path>',
                    'label' => __('Roles'),
                    'permission' => 'can-manage-roles',
                ],
            ],
        ],
        [
            'title' => __('Insights'),
            'items' => [
                [
                    'url' => '/vault-entry/analytics',
                    'icon' => '<path d="M3 3v18h18"></path><path d="m19 9-5 5-4-4-3 3"></path>',
                    'label' => __('Analytics'),
                    'permission' => 'can-view-platform-analytics',
                ],
                [
                    'url' => '/vault-entry/analytics/revenue',
                    'icon' => '<line x1="12" x2="12" y1="1" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>',
                    'label' => __('Revenue Analytics'),
                    'permission' => 'can-view-platform-analytics',
                ],
                [
                    'url' => '/vault-entry/analytics/performance',
                    'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
                    'label' => __('Cook Performance'),
                    'permission' => 'can-view-platform-analytics',
                ],
                [
                    'url' => '/vault-entry/analytics/growth',
                    'icon' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>',
                    'label' => __('Growth Metrics'),
                    'permission' => 'can-view-platform-analytics',
                ],
                [
                    'url' => '/vault-entry/finance/reports',
                    'icon' => '<line x1="12" x2="12" y1="1" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>',
                    'label' => __('Finance'),
                    'permission' => 'can-view-platform-analytics',
                ],
                [
                    'url' => '/vault-entry/payments',
                    'icon' => '<rect width="20" height="14" x="2" y="5" rx="2"></rect><line x1="2" x2="22" y1="10" y2="10"></line>',
                    'label' => __('Payments'),
                    'permission' => 'can-manage-financials',
                ],
            ],
        ],
        [
            'title' => __('Operations'),
            'items' => [
                [
                    'url' => '/vault-entry/complaints',
                    'icon' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>',
                    'label' => __('Complaints'),
                    'permission' => 'can-manage-complaints-escalated',
                ],
                [
                    'url' => '/vault-entry/payouts',
                    'icon' => '<rect width="20" height="14" x="2" y="5" rx="2"></rect><line x1="2" x2="22" y1="10" y2="10"></line>',
                    'label' => __('Payouts'),
                    'permission' => 'can-manage-payouts',
                    'badge' => \App\Models\PayoutTask::where('status', 'pending')->count(),
                ],
            ],
        ],
        [
            'title' => __('System'),
            'items' => [
                [
                    'url' => '/vault-entry/announcements',
                    'icon' => '<path d="M3 11l19-9-9 19-2-8-8-2z"></path>',
                    'label' => __('Announcements'),
                    'permission' => 'can-access-admin-panel',
                ],
                [
                    'url' => '/vault-entry/settings',
                    'icon' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle>',
                    'label' => __('Settings'),
                    'permission' => 'can-manage-platform-settings',
                ],
                [
                    'url' => '/vault-entry/activity-log',
                    'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"></path><path d="M14 2v6h6"></path><path d="M16 13H8"></path><path d="M16 17H8"></path><path d="M10 9H8"></path>',
                    'label' => __('Activity Log'),
                    'permission' => 'can-view-activity-log',
                ],
            ],
        ],
    ];

    // Filter groups to only include items the user has permission for
    $filteredGroups = collect($adminNavGroups)->map(function ($group) use ($user) {
        $group['items'] = array_filter($group['items'], function ($item) use ($user) {
            return $item['permission'] === null || $user->can($item['permission']);
        });
        return $group;
    })->filter(function ($group) {
        return count($group['items']) > 0;
    })->values()->all();
@endphp

<div
    x-data="{
        sidebarOpen: window.innerWidth >= 1024,
        sidebarCollapsed: false,
        mobileMenuOpen: false
    }"
    @resize.window="sidebarOpen = window.innerWidth >= 1024; if (window.innerWidth >= 1024) mobileMenuOpen = false"
    class="min-h-screen flex"
>
    {{-- Mobile Overlay --}}
    <div
        x-show="mobileMenuOpen"
        x-transition:enter="transition-opacity ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-50"
        x-transition:leave="transition-opacity ease-in duration-200"
        x-transition:leave-start="opacity-50"
        x-transition:leave-end="opacity-0"
        @click="mobileMenuOpen = false"
        class="fixed inset-0 z-40 bg-black lg:hidden"
        x-cloak
    ></div>

    {{-- Sidebar --}}
    <aside
        :class="{
            'translate-x-0': sidebarOpen || mobileMenuOpen,
            '-translate-x-full': !sidebarOpen && !mobileMenuOpen,
            'w-64': !sidebarCollapsed,
            'w-16': sidebarCollapsed
        }"
        class="fixed lg:sticky top-0 left-0 z-50 lg:z-30 h-screen bg-surface-alt dark:bg-surface-alt border-r border-outline dark:border-outline
               flex flex-col transition-all duration-300 shrink-0"
    >
        {{-- Sidebar Header --}}
        <div class="h-16 flex items-center px-4 border-b border-outline dark:border-outline shrink-0" :class="sidebarCollapsed ? 'justify-center' : 'justify-between'">
            <a href="{{ url('/vault-entry') }}" class="flex items-center gap-2 min-w-0" x-show="!sidebarCollapsed">
                <span class="w-8 h-8 rounded-lg bg-primary text-on-primary flex items-center justify-center font-bold text-sm shrink-0">DM</span>
                <span class="font-display text-lg font-bold text-on-surface-strong truncate">{{ __('Admin') }}</span>
            </a>
            <a href="{{ url('/vault-entry') }}" x-show="sidebarCollapsed" x-cloak class="w-8 h-8 rounded-lg bg-primary text-on-primary flex items-center justify-center font-bold text-sm" title="{{ __('Admin Panel') }}">DM</a>

            {{-- Collapse toggle (desktop only) --}}
            <button
                @click="sidebarCollapsed = !sidebarCollapsed"
                class="hidden lg:flex w-7 h-7 rounded items-center justify-center text-on-surface hover:bg-surface transition-colors duration-200"
                :title="sidebarCollapsed ? '{{ __('Expand sidebar') }}' : '{{ __('Collapse sidebar') }}'"
                x-show="!sidebarCollapsed"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m11 17-5-5 5-5"></path><path d="m18 17-5-5 5-5"></path></svg>
            </button>
        </div>

        {{-- Sidebar Navigation --}}
        <nav class="flex-1 overflow-y-auto p-3 space-y-4" x-data x-navigate aria-label="{{ __('Admin navigation') }}">
            @foreach($filteredGroups as $groupIndex => $group)
                {{-- Group label (hidden when collapsed) --}}
                <div>
                    <p
                        x-show="!sidebarCollapsed"
                        class="px-3 mb-1.5 text-xs font-semibold uppercase tracking-wider text-on-surface/60"
                    >
                        {{ $group['title'] }}
                    </p>
                    @if(! ($groupIndex === 0))
                        <div x-show="sidebarCollapsed" class="border-t border-outline dark:border-outline mb-2 mx-2" x-cloak></div>
                    @endif

                    <div class="space-y-0.5">
                        @foreach($group['items'] as $item)
                            @php
                                $isActive = request()->is(ltrim($item['url'], '/'))
                                    || (rtrim($item['url'], '/') !== '/vault-entry' && request()->is(ltrim($item['url'], '/') . '/*'));
                                // Special case: Dashboard only active on exact match
                                if ($item['url'] === '/vault-entry') {
                                    $isActive = request()->is('vault-entry') && ! request()->is('vault-entry/*');
                                }
                            @endphp
                            <a
                                href="{{ url($item['url']) }}"
                                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors duration-200
                                       {{ $isActive
                                           ? 'bg-primary-subtle text-primary dark:bg-primary-subtle dark:text-primary'
                                           : 'text-on-surface hover:bg-surface dark:hover:bg-surface' }}"
                                :title="sidebarCollapsed ? '{{ addslashes($item['label']) }}' : ''"
                                @if($isActive) aria-current="page" @endif
                            >
                                <svg class="w-5 h-5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $item['icon'] !!}</svg>
                                <span x-show="!sidebarCollapsed" class="truncate flex-1">{{ $item['label'] }}</span>
                                @if(isset($item['badge']) && $item['badge'] > 0)
                                    <span x-show="!sidebarCollapsed" class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-xs font-bold bg-danger text-on-danger">
                                        {{ $item['badge'] }}
                                    </span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        {{-- Sidebar Footer --}}
        <div class="border-t border-outline dark:border-outline p-3 shrink-0">
            @auth
                <div class="flex items-center gap-3" :class="sidebarCollapsed ? 'justify-center' : ''">
                    <div class="w-8 h-8 rounded-full bg-primary-subtle flex items-center justify-center text-primary font-semibold text-sm shrink-0">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <div x-show="!sidebarCollapsed" class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-on-surface-strong truncate">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-on-surface truncate">{{ auth()->user()->email }}</p>
                    </div>
                </div>
            @endauth
        </div>
    </aside>

    {{-- Main Content Area --}}
    <div class="flex-1 flex flex-col min-w-0">
        {{-- Top Bar --}}
        <header class="bg-surface dark:bg-surface border-b border-outline dark:border-outline h-16 flex items-center justify-between px-4 sm:px-6 sticky top-0 z-30 shrink-0">
            <div class="flex items-center gap-4">
                {{-- Mobile hamburger --}}
                <button
                    @click="mobileMenuOpen = !mobileMenuOpen"
                    class="lg:hidden w-10 h-10 rounded-lg flex items-center justify-center text-on-surface hover:bg-surface-alt transition-colors duration-200"
                    aria-label="{{ __('Toggle menu') }}"
                >
                    <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"></line><line x1="4" x2="20" y1="6" y2="6"></line><line x1="4" x2="20" y1="18" y2="18"></line></svg>
                </button>

                {{-- Expand sidebar button (when collapsed) --}}
                <button
                    x-show="sidebarCollapsed"
                    @click="sidebarCollapsed = false"
                    class="hidden lg:flex w-8 h-8 rounded items-center justify-center text-on-surface hover:bg-surface-alt transition-colors duration-200"
                    title="{{ __('Expand sidebar') }}"
                    x-cloak
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m13 17 5-5-5-5"></path><path d="m6 17 5-5-5-5"></path></svg>
                </button>

                <h1 class="text-lg font-semibold text-on-surface-strong">@yield('page-title', __('Dashboard'))</h1>
            </div>

            <div class="flex items-center gap-1 sm:gap-3">
                <x-nav.notification-bell />
                <div class="hidden sm:block"><x-theme-switcher /></div>
                <div class="hidden sm:block"><x-language-switcher /></div>

                @auth
                    <form method="POST" action="{{ route('logout') }}" x-navigate-skip>
                        @csrf
                        <button type="submit" class="w-10 h-10 rounded-full flex items-center justify-center text-on-surface hover:bg-surface-alt hover:text-danger transition-colors duration-200" title="{{ __('Logout') }}">
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" x2="9" y1="12" y2="12"></line></svg>
                        </button>
                    </form>
                @endauth
            </div>
        </header>

        {{-- Page Content --}}
        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            @yield('content')
        </main>
    </div>
</div>
@endsection
