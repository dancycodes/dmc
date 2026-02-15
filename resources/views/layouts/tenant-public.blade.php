{{--
    Tenant Domain Public Layout
    ---------------------------
    Used on tenant domains (cook.dmc.test) for public-facing pages.
    Features: Cook branding (name/logo), responsive nav, auth links,
    theme/language switchers, notification bell, footer.
    BR-122: tenant-public variant
    BR-131: Shows cook's branding in the header
--}}
@extends('layouts.app')

@section('body')
@php
    $currentTenant = tenant();
    $tenantName = $currentTenant?->name ?? config('app.name', 'DancyMeals');
@endphp

<div x-data="{ mobileMenuOpen: false }" class="min-h-screen flex flex-col">
    {{-- Header / Navbar with Cook Branding --}}
    <header class="bg-surface dark:bg-surface border-b border-outline dark:border-outline sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="h-16 flex items-center justify-between" x-data x-navigate>
                {{-- Cook Brand / Logo --}}
                <a href="{{ url('/') }}" class="flex items-center gap-2 shrink-0 min-w-0">
                    <span class="w-8 h-8 rounded-full bg-primary text-on-primary flex items-center justify-center font-bold text-sm shrink-0">
                        {{ strtoupper(substr($tenantName, 0, 1)) }}
                    </span>
                    <span class="font-display text-lg font-bold text-on-surface-strong truncate max-w-[200px]" title="{{ $tenantName }}">
                        {{ $tenantName }}
                    </span>
                </a>

                {{-- Desktop Navigation --}}
                <nav class="hidden lg:flex items-center gap-6" aria-label="{{ __('Main navigation') }}">
                    <a href="{{ url('/') }}" class="text-sm font-medium text-on-surface hover:text-on-surface-strong transition-colors duration-200">
                        {{ __('Menu') }}
                    </a>
                    <a href="{{ url('/schedule') }}" class="text-sm font-medium text-on-surface hover:text-on-surface-strong transition-colors duration-200">
                        {{ __('Schedule') }}
                    </a>
                    <a href="{{ url('/delivery') }}" class="text-sm font-medium text-on-surface hover:text-on-surface-strong transition-colors duration-200">
                        {{ __('Delivery') }}
                    </a>
                </nav>

                {{-- Desktop Right Section --}}
                <div class="hidden lg:flex items-center gap-3">
                    <x-nav.notification-bell />
                    <x-theme-switcher />
                    <x-language-switcher />

                    @auth
                        <div class="flex items-center gap-3 ml-2">
                            <a href="{{ url('/profile') }}" class="flex items-center gap-2 text-sm font-medium text-on-surface hover:text-on-surface-strong transition-colors duration-200">
                                <div class="w-8 h-8 rounded-full bg-primary-subtle flex items-center justify-center text-primary font-semibold text-sm">
                                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                                </div>
                                <span class="max-w-[120px] truncate">{{ auth()->user()->name }}</span>
                            </a>
                            <form method="POST" action="{{ route('logout') }}" x-navigate-skip>
                                @csrf
                                <button type="submit" class="text-sm font-medium text-on-surface hover:text-danger transition-colors duration-200" title="{{ __('Logout') }}">
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" x2="9" y1="12" y2="12"></line></svg>
                                </button>
                            </form>
                        </div>
                    @else
                        <div class="flex items-center gap-2 ml-2">
                            <a href="{{ route('login') }}" class="h-9 px-4 text-sm rounded-lg font-medium border border-outline text-on-surface hover:bg-surface-alt transition-all duration-200 inline-flex items-center">
                                {{ __('Login') }}
                            </a>
                            <a href="{{ route('register') }}" class="h-9 px-4 text-sm rounded-lg font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200 inline-flex items-center">
                                {{ __('Register') }}
                            </a>
                        </div>
                    @endauth
                </div>

                {{-- Mobile: Theme/Language + Hamburger --}}
                <div class="flex items-center gap-2 lg:hidden">
                    <x-theme-switcher />
                    <x-language-switcher />
                    <button
                        type="button"
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        class="w-10 h-10 rounded-lg flex items-center justify-center text-on-surface hover:bg-surface-alt transition-colors duration-200"
                        :aria-expanded="mobileMenuOpen"
                        aria-label="{{ __('Toggle menu') }}"
                    >
                        <svg x-show="!mobileMenuOpen" class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"></line><line x1="4" x2="20" y1="6" y2="6"></line><line x1="4" x2="20" y1="18" y2="18"></line></svg>
                        <svg x-show="mobileMenuOpen" x-cloak class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- Mobile Menu --}}
        <div
            x-show="mobileMenuOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2"
            class="lg:hidden border-t border-outline dark:border-outline bg-surface dark:bg-surface"
            @click.away="mobileMenuOpen = false"
            x-cloak
        >
            <nav class="px-4 py-4 space-y-1" x-data x-navigate aria-label="{{ __('Mobile navigation') }}">
                <a href="{{ url('/') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-on-surface hover:bg-surface-alt transition-colors duration-200">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="14" rx="1"></rect><rect width="7" height="7" x="3" y="14" rx="1"></rect></svg>
                    {{ __('Menu') }}
                </a>
                <a href="{{ url('/schedule') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-on-surface hover:bg-surface-alt transition-colors duration-200">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path></svg>
                    {{ __('Schedule') }}
                </a>
                <a href="{{ url('/delivery') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-on-surface hover:bg-surface-alt transition-colors duration-200">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    {{ __('Delivery') }}
                </a>

                <div class="border-t border-outline dark:border-outline my-2"></div>

                @auth
                    <a href="{{ url('/profile') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-on-surface hover:bg-surface-alt transition-colors duration-200">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        {{ __('Profile') }}
                    </a>
                    <form method="POST" action="{{ route('logout') }}" x-navigate-skip>
                        @csrf
                        <button type="submit" class="w-full flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-danger hover:bg-danger-subtle transition-colors duration-200">
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" x2="9" y1="12" y2="12"></line></svg>
                            {{ __('Logout') }}
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-on-surface hover:bg-surface-alt transition-colors duration-200">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" x2="3" y1="12" y2="12"></line></svg>
                        {{ __('Login') }}
                    </a>
                    <a href="{{ route('register') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-primary hover:bg-primary-subtle transition-colors duration-200">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" x2="19" y1="8" y2="14"></line><line x1="22" x2="16" y1="11" y2="11"></line></svg>
                        {{ __('Register') }}
                    </a>
                @endauth
            </nav>
        </div>
    </header>

    {{-- Main Content --}}
    <main class="flex-1">
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="bg-surface-alt dark:bg-surface-alt border-t border-outline dark:border-outline mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="text-center sm:text-left">
                    <p class="text-sm text-on-surface">
                        {{ __('Powered by') }}
                        <a href="{{ config('app.url') }}" class="text-primary hover:text-primary-hover font-medium" x-navigate-skip>
                            {{ config('app.name', 'DancyMeals') }}
                        </a>
                    </p>
                    <p class="text-xs text-on-surface opacity-60 mt-1">&copy; {{ date('Y') }} {{ __('All rights reserved.') }}</p>
                </div>
                <div class="flex items-center gap-4">
                    <x-theme-switcher />
                    <x-language-switcher />
                </div>
            </div>
        </div>
    </footer>
</div>
@endsection
