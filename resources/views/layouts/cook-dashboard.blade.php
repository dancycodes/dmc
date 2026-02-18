{{--
    Cook Dashboard Layout
    ---------------------
    F-076: Cook Dashboard Layout & Navigation
    Used for authenticated cooks/managers on tenant domains.

    Features:
    - Collapsible sidebar with 12 navigation sections grouped logically (BR-159)
    - Permission-based navigation — hidden sections for managers (BR-158)
    - Tenant branding (brand name, avatar) in sidebar header (BR-160)
    - Light/dark mode support (BR-161)
    - Fully responsive with mobile slide-over sidebar (BR-162)
    - Setup incomplete banner when tenant not ready (BR-163)
    - All text localized with __() (BR-164)
    - Theme and language switchers in sidebar footer
    - Active nav item with accent color and left border indicator
    - Content loads via Gale navigation (no full page reloads)

    BR-156: Dashboard routes ONLY accessible on tenant domains
    BR-157: Only users with cook or manager role for the current tenant
--}}
@extends('layouts.app')

@section('body')
@php
    $currentTenant = tenant();
    $tenantName = $currentTenant?->name ?? config('app.name', 'DancyMeals');
    $user = auth()->user();

    // BR-159: All 12 sidebar sections grouped logically per UI/UX notes
    // BR-158: Each item has a permission requirement — only rendered if user can access
    $cookNavGroups = [
        [
            'title' => __('Overview'),
            'items' => [
                [
                    'url' => '/dashboard',
                    'icon' => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline>',
                    'label' => __('Home'),
                    'permission' => null,
                ],
            ],
        ],
        [
            'title' => __('Business'),
            'items' => [
                [
                    'url' => '/dashboard/meals',
                    'icon' => '<path d="M15 11h.01"></path><path d="M11 15h.01"></path><path d="M16 16h.01"></path><path d="m2 16 20 6-6-20A20 20 0 0 0 2 16"></path><path d="M5.71 17.11a17.04 17.04 0 0 1 11.4-11.4"></path>',
                    'label' => __('Meals'),
                    'permission' => 'can-manage-meals',
                ],
                [
                    'url' => '/dashboard/tags',
                    'icon' => '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"></path><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"></circle>',
                    'label' => __('Tags'),
                    'permission' => 'can-manage-meals',
                ],
                [
                    'url' => '/dashboard/orders',
                    'icon' => '<path d="M16 3h5v5"></path><path d="M8 3H3v5"></path><path d="M12 22v-8.3a4 4 0 0 0-1.172-2.872L3 3"></path><path d="m15 9 6-6"></path>',
                    'label' => __('Orders'),
                    'permission' => 'can-manage-orders',
                ],
            ],
        ],
        [
            'title' => __('Coverage'),
            'items' => [
                [
                    'url' => '/dashboard/locations',
                    'icon' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle>',
                    'label' => __('Locations'),
                    'permission' => 'can-manage-locations',
                ],
                [
                    'url' => '/dashboard/schedule',
                    'icon' => '<path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path>',
                    'label' => __('Schedule'),
                    'permission' => 'can-manage-schedules',
                ],
            ],
        ],
        [
            'title' => __('Brand'),
            'items' => [
                [
                    'url' => '/dashboard/profile',
                    'icon' => '<circle cx="12" cy="12" r="10"></circle><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"></path><path d="M2 12h20"></path>',
                    'label' => __('Profile'),
                    'permission' => 'can-manage-brand',
                ],
                [
                    'url' => '/dashboard/managers',
                    'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
                    'label' => __('Managers'),
                    'permission' => 'can-manage-managers',
                ],
            ],
        ],
        [
            'title' => __('Insights'),
            'items' => [
                [
                    'url' => '/dashboard/analytics',
                    'icon' => '<path d="M3 3v18h18"></path><path d="m19 9-5 5-4-4-3 3"></path>',
                    'label' => __('Analytics'),
                    'permission' => 'can-view-cook-analytics',
                ],
                [
                    'url' => '/dashboard/wallet',
                    'icon' => '<path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2.5"></path><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"></path>',
                    'label' => __('Wallet'),
                    'permission' => 'can-manage-cook-wallet',
                ],
            ],
        ],
        [
            'title' => __('Engagement'),
            'items' => [
                [
                    'url' => '/dashboard/complaints',
                    'icon' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>',
                    'label' => __('Complaints'),
                    'permission' => null,
                ],
                [
                    'url' => '/dashboard/testimonials',
                    'icon' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"></path><path d="M8 12h.01"></path><path d="M12 12h.01"></path><path d="M16 12h.01"></path>',
                    'label' => __('Testimonials'),
                    'permission' => 'can-manage-testimonials',
                ],
            ],
        ],
        [
            'title' => __('System'),
            'items' => [
                [
                    'url' => '/dashboard/settings',
                    'icon' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle>',
                    'label' => __('Settings'),
                    'permission' => 'can-manage-cook-settings',
                ],
            ],
        ],
    ];

    // BR-158: Filter groups to only include items the user has permission for
    $filteredGroups = collect($cookNavGroups)->map(function ($group) use ($user) {
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
        {{-- Sidebar Header with Tenant Brand (BR-160) --}}
        <div class="h-16 flex items-center px-4 border-b border-outline dark:border-outline shrink-0" :class="sidebarCollapsed ? 'justify-center' : 'justify-between'">
            <a href="{{ url('/dashboard') }}" class="flex items-center gap-2 min-w-0" x-show="!sidebarCollapsed">
                <span class="w-8 h-8 rounded-full bg-primary text-on-primary flex items-center justify-center font-bold text-sm shrink-0">
                    {{ mb_strtoupper(mb_substr($tenantName, 0, 1)) }}
                </span>
                <span class="font-display text-lg font-bold text-on-surface-strong truncate" title="{{ $tenantName }}">{{ $tenantName }}</span>
            </a>
            <a href="{{ url('/dashboard') }}" x-show="sidebarCollapsed" x-cloak class="w-8 h-8 rounded-full bg-primary text-on-primary flex items-center justify-center font-bold text-sm" title="{{ $tenantName }}">
                {{ mb_strtoupper(mb_substr($tenantName, 0, 1)) }}
            </a>

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

        {{-- Sidebar Navigation (BR-158, BR-159) --}}
        <nav class="flex-1 overflow-y-auto p-3 space-y-4" x-data x-navigate aria-label="{{ __('Cook dashboard navigation') }}">
            @foreach($filteredGroups as $groupIndex => $group)
                <div>
                    {{-- Group label (hidden when collapsed) --}}
                    <p
                        x-show="!sidebarCollapsed"
                        class="px-3 mb-1.5 text-xs font-semibold uppercase tracking-wider text-on-surface/60"
                    >
                        {{ $group['title'] }}
                    </p>
                    @if($groupIndex > 0)
                        <div x-show="sidebarCollapsed" class="border-t border-outline dark:border-outline mb-2 mx-2" x-cloak></div>
                    @endif

                    <div class="space-y-0.5">
                        @foreach($group['items'] as $item)
                            @php
                                // Active state detection with left border indicator
                                $isActive = false;
                                $itemPath = ltrim($item['url'], '/');
                                if ($item['url'] === '/dashboard') {
                                    // Dashboard Home: only active on exact match
                                    $isActive = request()->is('dashboard') && ! request()->is('dashboard/*');
                                } else {
                                    // Other items: active on exact match or any sub-path
                                    $isActive = request()->is($itemPath) || request()->is($itemPath . '/*');
                                }
                            @endphp
                            <a
                                href="{{ url($item['url']) }}"
                                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors duration-200 relative
                                       {{ $isActive
                                           ? 'bg-primary-subtle text-primary dark:bg-primary-subtle dark:text-primary'
                                           : 'text-on-surface hover:bg-surface dark:hover:bg-surface' }}"
                                :title="sidebarCollapsed ? '{{ addslashes($item['label']) }}' : ''"
                                @if($isActive) aria-current="page" @endif
                            >
                                {{-- Active indicator: left border --}}
                                @if($isActive)
                                    <span class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-5 bg-primary rounded-r-full"></span>
                                @endif
                                <svg class="w-5 h-5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $item['icon'] !!}</svg>
                                <span x-show="!sidebarCollapsed" class="truncate">{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        {{-- Sidebar Footer: Theme, Language, User info --}}
        <div class="border-t border-outline dark:border-outline p-3 shrink-0 space-y-3">
            {{-- Theme and Language switchers (hidden on mobile — shown in header instead) --}}
            <div x-show="!sidebarCollapsed" class="hidden sm:flex items-center gap-2">
                <x-theme-switcher />
                <x-language-switcher />
            </div>

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

                {{-- Breadcrumb / Page title --}}
                <div class="flex items-center gap-2">
                    <h1 class="text-lg font-semibold text-on-surface-strong">@yield('page-title', __('Dashboard'))</h1>
                </div>
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
            {{-- BR-163: Setup incomplete banner --}}
            <x-cook.setup-banner />

            @yield('content')
        </main>
    </div>
</div>
@endsection
