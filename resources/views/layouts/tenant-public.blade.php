{{--
    Tenant Domain Public Layout
    ---------------------------
    F-126: Tenant Landing Page Layout
    Used on tenant domains (cook.dmc.test) for public-facing pages.
    Features: Cook branding (name/logo), responsive nav, auth links,
    theme/language switchers, scroll-anchor navigation, footer.
    BR-126: Renders ONLY on tenant domains
    BR-128: Navigation includes Home, Meals, About, Contact as scroll-anchor links
    BR-129: Auth state reflected (guest vs authenticated)
    BR-130: Fully responsive, mobile-first with hamburger nav
    BR-132: Supports light and dark mode
    BR-135: All interactions use Gale; no full page reloads
--}}
@extends('layouts.app')

@section('body')
@php
    $currentTenant = tenant();
    $tenantName = $currentTenant?->name ?? config('app.name', 'DancyMeals');
@endphp

<div x-data="{
    mobileMenuOpen: false,
    scrolled: false,
    activeSection: 'hero',
    init() {
        /* BR-135: Track scroll position for sticky nav styling */
        const onScroll = () => {
            this.scrolled = window.scrollY > 20;
            this.updateActiveSection();
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        this.$cleanup = () => window.removeEventListener('scroll', onScroll);
    },
    updateActiveSection() {
        const sections = ['hero', 'meals', 'about', 'contact'];
        for (let i = sections.length - 1; i >= 0; i--) {
            const el = document.getElementById(sections[i]);
            if (el && el.getBoundingClientRect().top <= 120) {
                this.activeSection = sections[i];
                return;
            }
        }
        this.activeSection = 'hero';
    },
    scrollTo(id) {
        const el = document.getElementById(id);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            /* Update URL hash without triggering scroll */
            history.replaceState(null, '', '#' + id);
            this.activeSection = id;
        }
        this.mobileMenuOpen = false;
    }
}" class="min-h-screen flex flex-col">

    {{-- Header / Navbar --}}
    {{-- BR-130: Fixed at top with semi-transparent background on scroll --}}
    <header
        class="sticky top-0 z-50 transition-all duration-300"
        :class="scrolled
            ? 'bg-surface/95 dark:bg-surface/95 backdrop-blur-md shadow-card border-b border-outline dark:border-outline'
            : 'bg-surface dark:bg-surface border-b border-outline dark:border-outline'"
    >
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="h-16 flex items-center justify-between">
                {{-- Cook Brand / Logo --}}
                <button
                    @click="scrollTo('hero')"
                    class="flex items-center gap-2 shrink-0 min-w-0 cursor-pointer"
                >
                    <span class="w-8 h-8 rounded-full bg-primary text-on-primary flex items-center justify-center font-bold text-sm shrink-0">
                        {{ mb_strtoupper(mb_substr($tenantName, 0, 1)) }}
                    </span>
                    <span class="font-display text-lg font-bold text-on-surface-strong truncate max-w-[200px]" title="{{ $tenantName }}">
                        {{ $tenantName }}
                    </span>
                </button>

                {{-- Desktop Navigation (scroll-anchor links) --}}
                {{-- BR-128: Home, Meals, About, Contact --}}
                <nav class="hidden lg:flex items-center gap-1" aria-label="{{ __('Main navigation') }}">
                    @php
                        $navItems = [
                            ['id' => 'hero', 'label' => __('Home'), 'icon' => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline>'],
                            ['id' => 'meals', 'label' => __('Meals'), 'icon' => '<rect width="7" height="7" x="3" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="14" rx="1"></rect><rect width="7" height="7" x="3" y="14" rx="1"></rect>'],
                            ['id' => 'about', 'label' => __('About'), 'icon' => '<circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path>'],
                            ['id' => 'contact', 'label' => __('Contact'), 'icon' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>'],
                        ];
                    @endphp
                    @foreach($navItems as $item)
                        <button
                            @click="scrollTo('{{ $item['id'] }}')"
                            class="px-4 py-2 text-sm font-medium rounded-lg transition-colors duration-200"
                            :class="activeSection === '{{ $item['id'] }}'
                                ? 'text-primary bg-primary-subtle'
                                : 'text-on-surface hover:text-on-surface-strong hover:bg-surface-alt'"
                        >
                            {{ $item['label'] }}
                        </button>
                    @endforeach
                </nav>

                {{-- Desktop Right Section --}}
                <div class="hidden lg:flex items-center gap-3">
                    {{-- F-139: Cart icon with badge (tenant domain nav) --}}
                    @php
                        $cartSummary = app(\App\Services\CartService::class)->getCartSummary(tenant()?->id ?? 0);
                    @endphp
                    <a href="{{ url('/cart') }}" class="relative w-10 h-10 flex items-center justify-center rounded-lg text-on-surface hover:bg-surface-alt transition-colors duration-200" x-navigate aria-label="{{ __('Cart') }}">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path></svg>
                        @if($cartSummary['count'] > 0)
                            <span class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] rounded-full bg-primary text-on-primary text-[10px] font-bold flex items-center justify-center px-1">{{ $cartSummary['count'] }}</span>
                        @endif
                    </a>

                    <x-theme-switcher />
                    <x-language-switcher />

                    @auth
                        <div class="flex items-center gap-3 ml-2" x-data x-navigate>
                            {{-- BR-129: Authenticated user sees name with dropdown --}}
                            <div x-data="{ userMenuOpen: false }" class="relative">
                                <button
                                    @click="userMenuOpen = !userMenuOpen"
                                    class="flex items-center gap-2 text-sm font-medium text-on-surface hover:text-on-surface-strong transition-colors duration-200"
                                >
                                    <div class="w-8 h-8 rounded-full bg-primary-subtle flex items-center justify-center text-primary font-semibold text-sm">
                                        {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                                    </div>
                                    <span class="max-w-[120px] truncate">{{ auth()->user()->name }}</span>
                                    <svg class="w-4 h-4 transition-transform" :class="userMenuOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
                                </button>

                                {{-- Dropdown Menu --}}
                                <div
                                    x-show="userMenuOpen"
                                    @click.outside="userMenuOpen = false"
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-100"
                                    x-transition:leave-start="opacity-100 scale-100"
                                    x-transition:leave-end="opacity-0 scale-95"
                                    class="absolute right-0 mt-2 w-48 bg-surface dark:bg-surface border border-outline dark:border-outline rounded-lg shadow-dropdown py-1 z-50"
                                    x-cloak
                                >
                                    <a href="{{ url('/my-orders') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-on-surface hover:bg-surface-alt transition-colors duration-200">
                                        {{-- ClipboardList icon --}}
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"></rect><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><path d="M12 11h4"></path><path d="M12 16h4"></path><path d="M8 11h.01"></path><path d="M8 16h.01"></path></svg>
                                        {{ __('My Orders') }}
                                    </a>
                                    <a href="{{ url('/my-wallet') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-on-surface hover:bg-surface-alt transition-colors duration-200">
                                        {{-- Wallet icon (Lucide) --}}
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"></path><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"></path></svg>
                                        {{ __('My Wallet') }}
                                    </a>
                                    <a href="{{ url('/my-transactions') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-on-surface hover:bg-surface-alt transition-colors duration-200">
                                        {{-- Receipt icon (Lucide) --}}
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"></path><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"></path><path d="M12 17.5v-11"></path></svg>
                                        {{ __('Transactions') }}
                                    </a>
                                    <a href="{{ url('/profile') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-on-surface hover:bg-surface-alt transition-colors duration-200">
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                        {{ __('Profile') }}
                                    </a>
                                    <a href="{{ url('/profile/addresses') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-on-surface hover:bg-surface-alt transition-colors duration-200">
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                        {{ __('My Addresses') }}
                                    </a>
                                    <div class="border-t border-outline dark:border-outline my-1"></div>
                                    <form method="POST" action="{{ route('logout') }}" x-navigate-skip>
                                        @csrf
                                        <button type="submit" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-danger hover:bg-danger-subtle transition-colors duration-200 text-left">
                                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" x2="9" y1="12" y2="12"></line></svg>
                                            {{ __('Logout') }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @else
                        {{-- BR-129: Guest sees Login/Register links --}}
                        <div class="flex items-center gap-2 ml-2" x-data x-navigate>
                            <a href="{{ route('login') }}" class="h-9 px-4 text-sm rounded-lg font-medium border border-outline text-on-surface hover:bg-surface-alt transition-all duration-200 inline-flex items-center">
                                {{ __('Login') }}
                            </a>
                            <a href="{{ route('register') }}" class="h-9 px-4 text-sm rounded-lg font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200 inline-flex items-center">
                                {{ __('Register') }}
                            </a>
                        </div>
                    @endauth
                </div>

                {{-- Mobile: Cart + Theme/Language + Hamburger --}}
                <div class="flex items-center gap-2 lg:hidden">
                    {{-- F-139: Mobile cart icon --}}
                    <a href="{{ url('/cart') }}" class="relative w-10 h-10 flex items-center justify-center rounded-lg text-on-surface hover:bg-surface-alt transition-colors duration-200" x-navigate aria-label="{{ __('Cart') }}">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path></svg>
                        @if($cartSummary['count'] > 0)
                            <span class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] rounded-full bg-primary text-on-primary text-[10px] font-bold flex items-center justify-center px-1">{{ $cartSummary['count'] }}</span>
                        @endif
                    </a>
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
            <nav class="px-4 py-4 space-y-1" aria-label="{{ __('Mobile navigation') }}">
                {{-- BR-128: Scroll anchor links --}}
                <button @click="scrollTo('hero')" class="w-full flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium transition-colors duration-200" :class="activeSection === 'hero' ? 'text-primary bg-primary-subtle' : 'text-on-surface hover:bg-surface-alt'">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                    {{ __('Home') }}
                </button>
                <button @click="scrollTo('meals')" class="w-full flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium transition-colors duration-200" :class="activeSection === 'meals' ? 'text-primary bg-primary-subtle' : 'text-on-surface hover:bg-surface-alt'">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="14" rx="1"></rect><rect width="7" height="7" x="3" y="14" rx="1"></rect></svg>
                    {{ __('Meals') }}
                </button>
                <button @click="scrollTo('about')" class="w-full flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium transition-colors duration-200" :class="activeSection === 'about' ? 'text-primary bg-primary-subtle' : 'text-on-surface hover:bg-surface-alt'">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                    {{ __('About') }}
                </button>
                <button @click="scrollTo('contact')" class="w-full flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium transition-colors duration-200" :class="activeSection === 'contact' ? 'text-primary bg-primary-subtle' : 'text-on-surface hover:bg-surface-alt'">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                    {{ __('Contact') }}
                </button>

                <div class="border-t border-outline dark:border-outline my-2"></div>

                @auth
                    <div x-data x-navigate>
                        <a href="{{ url('/my-orders') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-on-surface hover:bg-surface-alt transition-colors duration-200">
                            {{-- ClipboardList icon --}}
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"></rect><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><path d="M12 11h4"></path><path d="M12 16h4"></path><path d="M8 11h.01"></path><path d="M8 16h.01"></path></svg>
                            {{ __('My Orders') }}
                        </a>
                        <a href="{{ url('/my-wallet') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-on-surface hover:bg-surface-alt transition-colors duration-200">
                            {{-- Wallet icon (Lucide) --}}
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"></path><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"></path></svg>
                            {{ __('My Wallet') }}
                        </a>
                        <a href="{{ url('/my-transactions') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-on-surface hover:bg-surface-alt transition-colors duration-200">
                            {{-- Receipt icon (Lucide) --}}
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"></path><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"></path><path d="M12 17.5v-11"></path></svg>
                            {{ __('Transactions') }}
                        </a>
                        <a href="{{ url('/profile') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-on-surface hover:bg-surface-alt transition-colors duration-200">
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                            {{ __('Profile') }}
                        </a>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" x-navigate-skip>
                        @csrf
                        <button type="submit" class="w-full flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-danger hover:bg-danger-subtle transition-colors duration-200">
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" x2="9" y1="12" y2="12"></line></svg>
                            {{ __('Logout') }}
                        </button>
                    </form>
                @else
                    <div x-data x-navigate>
                        <a href="{{ route('login') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-on-surface hover:bg-surface-alt transition-colors duration-200">
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" x2="3" y1="12" y2="12"></line></svg>
                            {{ __('Login') }}
                        </a>
                        <a href="{{ route('register') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium text-primary hover:bg-primary-subtle transition-colors duration-200">
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" x2="19" y1="8" y2="14"></line><line x1="22" x2="16" y1="11" y2="11"></line></svg>
                            {{ __('Register') }}
                        </a>
                    </div>
                @endauth
            </nav>
        </div>
    </header>

    {{-- Main Content --}}
    <main class="flex-1">
        @yield('content')
    </main>

    {{-- Footer --}}
    {{-- BR-133: Footer section at the bottom --}}
    <footer id="contact" class="bg-surface-alt dark:bg-surface-alt border-t border-outline dark:border-outline mt-auto scroll-mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            {{-- Contact & Social Links placeholder (F-134 will populate) --}}
            @yield('footer-content')

            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-6 border-t border-outline dark:border-outline">
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
