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
                    @php
                        $navActiveClass = 'text-sm font-medium text-primary transition-colors duration-200';
                        $navDefaultClass = 'text-sm font-medium text-on-surface hover:text-on-surface-strong transition-colors duration-200';
                    @endphp
                    <a href="{{ url('/') }}" class="{{ request()->is('/') || request()->is('discover') ? $navActiveClass : $navDefaultClass }}" @if(request()->is('/') || request()->is('discover')) aria-current="page" @endif>
                        {{ __('Home') }}
                    </a>
                    <a href="{{ url('/discover') }}" class="{{ request()->is('discover') ? $navActiveClass : $navDefaultClass }}">
                        {{ __('Discover Cooks') }}
                    </a>
                    @auth
                        @php
                            $clientActiveOrderCount = \App\Models\Order::query()->where('client_id', auth()->id())->whereIn('status', \App\Services\ClientOrderService::ACTIVE_STATUSES)->count();
                        @endphp
                        <a href="{{ url('/my-orders') }}" class="{{ request()->is('my-orders*') ? $navActiveClass : $navDefaultClass }} inline-flex items-center gap-1.5" @if(request()->is('my-orders*')) aria-current="page" @endif>
                            {{ __('My Orders') }}
                            @if($clientActiveOrderCount > 0)
                                <span class="min-w-[18px] h-[18px] rounded-full bg-primary text-on-primary text-[10px] font-bold flex items-center justify-center px-1">{{ $clientActiveOrderCount }}</span>
                            @endif
                        </a>
                        <a href="{{ url('/my-wallet') }}" class="{{ request()->is('my-wallet*') ? $navActiveClass : $navDefaultClass }}" @if(request()->is('my-wallet*')) aria-current="page" @endif>
                            {{ __('My Wallet') }}
                        </a>
                        <a href="{{ url('/my-transactions') }}" class="{{ request()->is('my-transactions*') ? $navActiveClass : $navDefaultClass }}" @if(request()->is('my-transactions*')) aria-current="page" @endif>
                            {{ __('Transactions') }}
                        </a>
                        <a href="{{ url('/my-complaints') }}" class="{{ request()->is('my-complaints*') ? $navActiveClass : $navDefaultClass }}" @if(request()->is('my-complaints*')) aria-current="page" @endif>
                            {{ __('Complaints') }}
                        </a>
                        <a href="{{ url('/my-favorites') }}" class="{{ request()->is('my-favorites*') ? $navActiveClass : $navDefaultClass }} inline-flex items-center gap-1" @if(request()->is('my-favorites*')) aria-current="page" @endif>
                            {{-- Heart icon (Lucide xs=14) --}}
                            <svg class="w-3.5 h-3.5 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"></path>
                            </svg>
                            {{ __('Favorites') }}
                        </a>
                        <a href="{{ url('/my-stats') }}" class="{{ request()->is('my-stats*') ? $navActiveClass : $navDefaultClass }}" @if(request()->is('my-stats*')) aria-current="page" @endif>
                            {{ __('My Stats') }}
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
                                @if(auth()->user()->profile_photo_path)
                                    <img
                                        src="{{ asset('storage/' . auth()->user()->profile_photo_path) }}"
                                        alt="{{ auth()->user()->name }}"
                                        class="w-8 h-8 rounded-full object-cover border border-outline shrink-0"
                                    >
                                @else
                                    <div class="w-8 h-8 rounded-full bg-primary-subtle flex items-center justify-center text-primary font-semibold text-sm shrink-0">
                                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                                    </div>
                                @endif
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
                @php
                    $mobileActiveClass = 'flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium bg-primary-subtle text-primary transition-colors duration-200';
                    $mobileDefaultClass = 'flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-on-surface hover:bg-surface-alt transition-colors duration-200';
                @endphp
                <a href="{{ url('/') }}" @click="mobileMenuOpen = false" class="{{ request()->is('/') || request()->is('discover') ? $mobileActiveClass : $mobileDefaultClass }}" @if(request()->is('/') || request()->is('discover')) aria-current="page" @endif>
                    {{-- Home icon --}}
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                    {{ __('Home') }}
                </a>
                <a href="{{ url('/discover') }}" @click="mobileMenuOpen = false" class="{{ request()->is('discover') ? $mobileActiveClass : $mobileDefaultClass }}" @if(request()->is('discover')) aria-current="page" @endif>
                    {{-- Search icon --}}
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                    {{ __('Discover Cooks') }}
                </a>

                <div class="border-t border-outline dark:border-outline my-2"></div>

                @auth
                    <a href="{{ url('/my-orders') }}" @click="mobileMenuOpen = false" class="{{ request()->is('my-orders*') ? $mobileActiveClass : $mobileDefaultClass }}" @if(request()->is('my-orders*')) aria-current="page" @endif>
                        {{-- ClipboardList icon --}}
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"></rect><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><path d="M12 11h4"></path><path d="M12 16h4"></path><path d="M8 11h.01"></path><path d="M8 16h.01"></path></svg>
                        {{ __('My Orders') }}
                        @if(isset($clientActiveOrderCount) && $clientActiveOrderCount > 0)
                            <span class="min-w-[18px] h-[18px] rounded-full bg-primary text-on-primary text-[10px] font-bold flex items-center justify-center px-1 ml-auto">{{ $clientActiveOrderCount }}</span>
                        @endif
                    </a>
                    <a href="{{ url('/my-wallet') }}" @click="mobileMenuOpen = false" class="{{ request()->is('my-wallet*') ? $mobileActiveClass : $mobileDefaultClass }}" @if(request()->is('my-wallet*')) aria-current="page" @endif>
                        {{-- Wallet icon (Lucide) --}}
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"></path><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"></path></svg>
                        {{ __('My Wallet') }}
                    </a>
                    <a href="{{ url('/my-transactions') }}" @click="mobileMenuOpen = false" class="{{ request()->is('my-transactions*') ? $mobileActiveClass : $mobileDefaultClass }}" @if(request()->is('my-transactions*')) aria-current="page" @endif>
                        {{-- Receipt icon (Lucide) --}}
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"></path><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"></path><path d="M12 17.5v-11"></path></svg>
                        {{ __('Transactions') }}
                    </a>
                    <a href="{{ url('/my-complaints') }}" @click="mobileMenuOpen = false" class="{{ request()->is('my-complaints*') ? $mobileActiveClass : $mobileDefaultClass }}" @if(request()->is('my-complaints*')) aria-current="page" @endif>
                        {{-- Shield alert icon (Lucide) --}}
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path><path d="M12 8v4"></path><path d="M12 16h.01"></path></svg>
                        {{ __('My Complaints') }}
                    </a>
                    <a href="{{ url('/my-favorites') }}" @click="mobileMenuOpen = false" class="{{ request()->is('my-favorites*') ? $mobileActiveClass : $mobileDefaultClass }}" @if(request()->is('my-favorites*')) aria-current="page" @endif>
                        {{-- Heart icon (Lucide md=20) --}}
                        <svg class="w-5 h-5 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"></path>
                        </svg>
                        {{ __('Favorites') }}
                    </a>
                    <a href="{{ url('/my-stats') }}" @click="mobileMenuOpen = false" class="{{ request()->is('my-stats*') ? $mobileActiveClass : $mobileDefaultClass }}" @if(request()->is('my-stats*')) aria-current="page" @endif>
                        {{-- BarChart3 icon (Lucide md=20) --}}
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"></path><path d="M18 17V9"></path><path d="M13 17V5"></path><path d="M8 17v-3"></path></svg>
                        {{ __('My Stats') }}
                    </a>
                    <a href="{{ url('/profile') }}" @click="mobileMenuOpen = false" class="{{ request()->is('profile*') ? $mobileActiveClass : $mobileDefaultClass }}" @if(request()->is('profile*')) aria-current="page" @endif>
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
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded bg-primary text-on-primary flex items-center justify-center font-bold text-xs">DM</span>
                    <span class="text-sm text-on-surface">&copy; {{ date('Y') }} {{ config('app.name', 'DancyMeals') }}. {{ __('All rights reserved.') }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <x-theme-switcher />
                    <x-language-switcher />
                </div>
            </div>
        </div>
    </footer>
</div>
@endsection
