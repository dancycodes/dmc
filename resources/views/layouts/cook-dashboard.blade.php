{{--
    Cook Dashboard Layout
    ---------------------
    Used for authenticated cooks/managers on tenant domains.
    Features: Collapsible sidebar with cook-specific nav, top bar,
    responsive, brand info.
    BR-122: cook-dashboard variant
    BR-125: Desktop uses collapsible sidebar
    BR-130: Rendered for authenticated cooks/managers on tenant domains
--}}
@extends('layouts.app')

@section('body')
@php
    $currentTenant = tenant();
    $tenantName = $currentTenant?->name ?? config('app.name', 'DancyMeals');
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
        {{-- Sidebar Header with Cook Brand --}}
        <div class="h-16 flex items-center px-4 border-b border-outline dark:border-outline shrink-0" :class="sidebarCollapsed ? 'justify-center' : 'justify-between'">
            <a href="{{ url('/dashboard') }}" class="flex items-center gap-2 min-w-0" x-show="!sidebarCollapsed">
                <span class="w-8 h-8 rounded-full bg-primary text-on-primary flex items-center justify-center font-bold text-sm shrink-0">
                    {{ strtoupper(substr($tenantName, 0, 1)) }}
                </span>
                <span class="font-display text-lg font-bold text-on-surface-strong truncate" title="{{ $tenantName }}">{{ $tenantName }}</span>
            </a>
            <a href="{{ url('/dashboard') }}" x-show="sidebarCollapsed" x-cloak class="w-8 h-8 rounded-full bg-primary text-on-primary flex items-center justify-center font-bold text-sm" title="{{ $tenantName }}">
                {{ strtoupper(substr($tenantName, 0, 1)) }}
            </a>

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
        <nav class="flex-1 overflow-y-auto p-3 space-y-1" x-data x-navigate aria-label="{{ __('Cook dashboard navigation') }}">
            @php
                $cookNavItems = [
                    ['url' => '/dashboard', 'icon' => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline>', 'label' => __('Home')],
                    ['url' => '/dashboard/meals', 'icon' => '<path d="M12 2a10 10 0 1 0 10 10H12V2Z"></path><path d="M12 2a10 10 0 0 1 10 10"></path><path d="M12 12 2.1 9.3"></path>', 'label' => __('Meals')],
                    ['url' => '/dashboard/orders', 'icon' => '<path d="M16 3h5v5"></path><path d="M8 3H3v5"></path><path d="M12 22v-8.3a4 4 0 0 0-1.172-2.872L3 3"></path><path d="m15 9 6-6"></path>', 'label' => __('Orders')],
                    ['url' => '/dashboard/brand', 'icon' => '<circle cx="12" cy="12" r="10"></circle><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"></path><path d="M2 12h20"></path>', 'label' => __('Brand')],
                    ['url' => '/dashboard/locations', 'icon' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle>', 'label' => __('Locations')],
                    ['url' => '/dashboard/schedule', 'icon' => '<path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path>', 'label' => __('Schedule')],
                    ['url' => '/dashboard/wallet', 'icon' => '<path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2.5"></path><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"></path>', 'label' => __('Wallet')],
                    ['url' => '/dashboard/managers', 'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>', 'label' => __('Managers')],
                    ['url' => '/dashboard/settings', 'icon' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle>', 'label' => __('Settings')],
                ];
            @endphp

            @foreach($cookNavItems as $item)
                <a
                    href="{{ url($item['url']) }}"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors duration-200
                           {{ request()->is(ltrim($item['url'], '/') . '*') || request()->is(ltrim($item['url'], '/'))
                               ? 'bg-primary-subtle text-primary dark:bg-primary-subtle dark:text-primary'
                               : 'text-on-surface hover:bg-surface dark:hover:bg-surface' }}"
                    :title="sidebarCollapsed ? '{{ addslashes($item['label']) }}' : ''"
                >
                    <svg class="w-5 h-5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $item['icon'] !!}</svg>
                    <span x-show="!sidebarCollapsed" class="truncate">{{ $item['label'] }}</span>
                </a>
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
                <button
                    @click="mobileMenuOpen = !mobileMenuOpen"
                    class="lg:hidden w-10 h-10 rounded-lg flex items-center justify-center text-on-surface hover:bg-surface-alt transition-colors duration-200"
                    aria-label="{{ __('Toggle menu') }}"
                >
                    <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"></line><line x1="4" x2="20" y1="6" y2="6"></line><line x1="4" x2="20" y1="18" y2="18"></line></svg>
                </button>

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
