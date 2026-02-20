{{--
    Main Domain Public Layout
    -------------------------
    Used on the main domain (dmc.test) for public-facing pages.
    Features: DancyMeals branding, responsive nav, auth links,
    theme/language switchers, notification bell, footer.
    BR-122: main-public variant
    BR-123: 100% responsive, mobile-first
    BR-132: Gale SPA navigation
--}}
@extends('layouts.app')

@section('body')
<div x-data="{ mobileMenuOpen: false }" class="min-h-screen flex flex-col">
    {{-- Header / Navbar --}}
    <header class="bg-surface dark:bg-surface border-b border-outline dark:border-outline sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="h-16 flex items-center justify-between" x-data x-navigate>
                {{-- Logo / Brand --}}
                <a href="{{ url('/') }}" class="flex items-center gap-2 shrink-0">
                    <span class="w-8 h-8 rounded-lg bg-primary text-on-primary flex items-center justify-center font-bold text-sm">
                        DM
                    </span>
                    <span class="font-display text-xl font-bold text-on-surface-strong hidden sm:block">
                        {{ config('app.name', 'DancyMeals') }}
                    </span>
                </a>

                {{-- Desktop Navigation --}}
                <nav class="hidden lg:flex items-center gap-6" aria-label="{{ __('Main navigation') }}">
                    <a href="{{ url('/') }}" class="text-sm font-medium text-on-surface hover:text-on-surface-strong transition-colors duration-200">
                        {{ __('Home') }}
                    </a>
                    <a href="{{ url('/discover') }}" class="text-sm font-medium text-on-surface hover:text-on-surface-strong transition-colors duration-200">
                        {{ __('Discover Cooks') }}
                    </a>
                    @auth
                        <a href="{{ url('/my-orders') }}" class="text-sm font-medium text-on-surface hover:text-on-surface-strong transition-colors duration-200 inline-flex items-center gap-1.5">
                            {{ __('My Orders') }}
                            @php
                                $clientActiveOrderCount = \App\Models\Order::query()->where('client_id', auth()->id())->whereIn('status', \App\Services\ClientOrderService::ACTIVE_STATUSES)->count();
                            @endphp
                            @if($clientActiveOrderCount > 0)
                                <span class="min-w-[18px] h-[18px] rounded-full bg-primary text-on-primary text-[10px] font-bold flex items-center justify-center px-1">{{ $clientActiveOrderCount }}</span>
                            @endif
                        </a>
                    @endauth
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
                                    {{-- LogOut icon (Lucide) --}}
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                        <polyline points="16 17 21 12 16 7"></polyline>
                                        <line x1="21" x2="9" y1="12" y2="12"></line>
                                    </svg>
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
                        {{-- Hamburger / X icon --}}
                        <svg x-show="!mobileMenuOpen" class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="4" x2="20" y1="12" y2="12"></line>
                            <line x1="4" x2="20" y1="6" y2="6"></line>
                            <line x1="4" x2="20" y1="18" y2="18"></line>
                        </svg>
                        <svg x-show="mobileMenuOpen" x-cloak class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"></path>
                            <path d="m6 6 12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- Mobile Menu Slide-out --}}
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
                    {{-- Home icon --}}
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                    {{ __('Home') }}
                </a>
                <a href="{{ url('/discover') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-on-surface hover:bg-surface-alt transition-colors duration-200">
                    {{-- Search icon --}}
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                    {{ __('Discover Cooks') }}
                </a>

                <div class="border-t border-outline dark:border-outline my-2"></div>

                @auth
                    <a href="{{ url('/my-orders') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-on-surface hover:bg-surface-alt transition-colors duration-200">
                        {{-- ClipboardList icon --}}
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"></rect><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><path d="M12 11h4"></path><path d="M12 16h4"></path><path d="M8 11h.01"></path><path d="M8 16h.01"></path></svg>
                        {{ __('My Orders') }}
                        @if(isset($clientActiveOrderCount) && $clientActiveOrderCount > 0)
                            <span class="min-w-[18px] h-[18px] rounded-full bg-primary text-on-primary text-[10px] font-bold flex items-center justify-center px-1 ml-auto">{{ $clientActiveOrderCount }}</span>
                        @endif
                    </a>
                    <a href="{{ url('/profile') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-on-surface hover:bg-surface-alt transition-colors duration-200">
                        {{-- User icon --}}
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        {{ __('Profile') }}
                    </a>
                    <x-nav.notification-bell />
                    <form method="POST" action="{{ route('logout') }}" x-navigate-skip>
                        @csrf
                        <button type="submit" class="w-full flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-danger hover:bg-danger-subtle transition-colors duration-200">
                            {{-- LogOut icon --}}
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" x2="9" y1="12" y2="12"></line></svg>
                            {{ __('Logout') }}
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-on-surface hover:bg-surface-alt transition-colors duration-200">
                        {{-- LogIn icon --}}
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" x2="3" y1="12" y2="12"></line></svg>
                        {{ __('Login') }}
                    </a>
                    <a href="{{ route('register') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-primary hover:bg-primary-subtle transition-colors duration-200">
                        {{-- UserPlus icon --}}
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
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded bg-primary text-on-primary flex items-center justify-center font-bold text-xs">DM</span>
                    <span class="text-sm text-on-surface">&copy; {{ date('Y') }} {{ config('app.name', 'DancyMeals') }}. {{ __('All rights reserved.') }}</span>
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
