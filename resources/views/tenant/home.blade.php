{{--
    F-126: Tenant Landing Page Layout
    BR-126: Renders ONLY on tenant domains
    BR-127: Cook's selected theme applied (via InjectTenantTheme middleware + x-tenant-theme-styles)
    BR-133: Sections in order: hero, meals grid, about/bio, ratings, testimonials, schedule, delivery, footer
    BR-134: Publicly accessible without authentication
--}}
@extends('layouts.tenant-public')

@section('title', $tenant?->name ?? __('Home'))

@section('content')
<div class="scroll-smooth">
    {{-- ============================================ --}}
    {{-- HERO SECTION (F-127 will populate fully)     --}}
    {{-- ============================================ --}}
    <section id="hero" class="scroll-mt-16">
        @if($cookProfile['hasImages'])
            {{-- Cover image carousel placeholder --}}
            <div class="relative h-64 sm:h-80 md:h-96 lg:h-[28rem] overflow-hidden bg-surface-alt dark:bg-surface-alt">
                @if(count($cookProfile['coverImages']) > 0)
                    <div x-data="{ currentSlide: 0, totalSlides: {{ count($cookProfile['coverImages']) }} }"
                         x-init="setInterval(() => { currentSlide = (currentSlide + 1) % totalSlides }, 5000)"
                         class="relative w-full h-full">
                        @foreach($cookProfile['coverImages'] as $index => $image)
                            <div
                                x-show="currentSlide === {{ $index }}"
                                x-transition:enter="transition-opacity ease-out duration-500"
                                x-transition:enter-start="opacity-0"
                                x-transition:enter-end="opacity-100"
                                x-transition:leave="transition-opacity ease-in duration-300"
                                x-transition:leave-start="opacity-100"
                                x-transition:leave-end="opacity-0"
                                class="absolute inset-0"
                            >
                                <img
                                    src="{{ $image['url'] }}"
                                    alt="{{ $tenant->name }} - {{ __('Cover image') }} {{ $index + 1 }}"
                                    class="w-full h-full object-cover"
                                    loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                                >
                            </div>
                        @endforeach

                        {{-- Gradient overlay for readability --}}
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent"></div>

                        {{-- Slide indicators --}}
                        @if(count($cookProfile['coverImages']) > 1)
                            <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2">
                                @foreach($cookProfile['coverImages'] as $index => $image)
                                    <button
                                        @click="currentSlide = {{ $index }}"
                                        class="w-2 h-2 rounded-full transition-all duration-300"
                                        :class="currentSlide === {{ $index }} ? 'bg-white w-6' : 'bg-white/50'"
                                        aria-label="{{ __('Go to slide') }} {{ $index + 1 }}"
                                    ></button>
                                @endforeach
                            </div>
                        @endif

                        {{-- Hero text overlay --}}
                        <div class="absolute bottom-0 left-0 right-0 p-6 sm:p-8 md:p-12">
                            <div class="max-w-7xl mx-auto">
                                <h1 class="text-3xl sm:text-4xl md:text-5xl font-display font-bold text-white drop-shadow-lg">
                                    {{ $cookProfile['name'] }}
                                </h1>
                                @if($cookProfile['bio'])
                                    <p class="mt-2 text-base sm:text-lg text-white/90 max-w-2xl line-clamp-2 drop-shadow">
                                        {{ $cookProfile['bio'] }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @else
            {{-- No cover images: text-based hero --}}
            <div class="bg-gradient-to-br from-primary-subtle via-surface to-secondary-subtle dark:from-primary-subtle dark:via-surface dark:to-secondary-subtle">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20 md:py-24 text-center">
                    <div class="w-20 h-20 rounded-full bg-primary text-on-primary flex items-center justify-center font-bold text-3xl mx-auto mb-6">
                        {{ mb_strtoupper(mb_substr($cookProfile['name'], 0, 1)) }}
                    </div>
                    <h1 class="text-3xl sm:text-4xl md:text-5xl font-display font-bold text-on-surface-strong">
                        {{ $cookProfile['name'] }}
                    </h1>
                    @if($cookProfile['bio'])
                        <p class="mt-4 text-lg text-on-surface max-w-2xl mx-auto">
                            {{ $cookProfile['bio'] }}
                        </p>
                    @else
                        <p class="mt-4 text-lg text-on-surface max-w-2xl mx-auto">
                            {{ __('Delicious home-cooked meals made with love.') }}
                        </p>
                    @endif

                    {{-- CTA button --}}
                    <div class="mt-8">
                        <button
                            @click="$root.scrollTo('meals')"
                            class="inline-flex items-center gap-2 h-12 px-8 bg-primary hover:bg-primary-hover text-on-primary font-semibold rounded-lg shadow-card transition-all duration-200"
                        >
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="14" rx="1"></rect><rect width="7" height="7" x="3" y="14" rx="1"></rect></svg>
                            {{ __('View Meals') }}
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </section>

    {{-- ============================================ --}}
    {{-- MEALS GRID SECTION (F-128 will populate)     --}}
    {{-- ============================================ --}}
    <section id="meals" class="scroll-mt-16 py-12 sm:py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-8 sm:mb-12">
                <h2 class="text-2xl sm:text-3xl font-display font-bold text-on-surface-strong">
                    {{ __('Our Meals') }}
                </h2>
                <p class="mt-2 text-on-surface max-w-2xl mx-auto">
                    {{ __('Discover our selection of freshly prepared dishes.') }}
                </p>
            </div>

            @if($sections['meals']['hasData'])
                {{-- Meals grid placeholder — F-128 will replace this with actual meal cards --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @for($i = 0; $i < 3; $i++)
                        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline overflow-hidden animate-pulse">
                            <div class="h-48 bg-outline/20 dark:bg-outline/20"></div>
                            <div class="p-4 space-y-3">
                                <div class="h-5 bg-outline/20 dark:bg-outline/20 rounded w-3/4"></div>
                                <div class="h-4 bg-outline/20 dark:bg-outline/20 rounded w-1/2"></div>
                                <div class="h-4 bg-outline/20 dark:bg-outline/20 rounded w-1/4"></div>
                            </div>
                        </div>
                    @endfor
                </div>
            @else
                {{-- Empty state --}}
                <div class="text-center py-12">
                    <div class="w-16 h-16 rounded-full bg-surface-alt dark:bg-surface-alt flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-on-surface opacity-40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="14" rx="1"></rect><rect width="7" height="7" x="3" y="14" rx="1"></rect></svg>
                    </div>
                    <p class="text-on-surface opacity-60">{{ __('No meals available yet. Check back soon!') }}</p>
                </div>
            @endif
        </div>
    </section>

    {{-- ============================================ --}}
    {{-- ABOUT / BIO SECTION                          --}}
    {{-- ============================================ --}}
    <section id="about" class="scroll-mt-16 py-12 sm:py-16 bg-surface-alt dark:bg-surface-alt">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-8 sm:mb-12">
                <h2 class="text-2xl sm:text-3xl font-display font-bold text-on-surface-strong">
                    {{ __('About Us') }}
                </h2>
            </div>

            @if($sections['about']['hasData'])
                <div class="max-w-3xl mx-auto">
                    <div class="bg-surface dark:bg-surface rounded-lg border border-outline dark:border-outline p-6 sm:p-8 shadow-card">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-full bg-primary text-on-primary flex items-center justify-center font-bold text-lg shrink-0">
                                {{ mb_strtoupper(mb_substr($cookProfile['name'], 0, 1)) }}
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-on-surface-strong">{{ $cookProfile['name'] }}</h3>
                                <p class="mt-2 text-on-surface leading-relaxed whitespace-pre-line">{{ $cookProfile['bio'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="text-center py-8">
                    <div class="w-16 h-16 rounded-full bg-surface dark:bg-surface flex items-center justify-center mx-auto mb-4 border border-outline dark:border-outline">
                        <svg class="w-8 h-8 text-on-surface opacity-40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                    </div>
                    <p class="text-on-surface opacity-60">{{ __('More details coming soon.') }}</p>
                </div>
            @endif
        </div>
    </section>

    {{-- ============================================ --}}
    {{-- RATINGS SECTION (F-130 will populate)        --}}
    {{-- ============================================ --}}
    {{-- Hidden until F-130 populates with data --}}

    {{-- ============================================ --}}
    {{-- TESTIMONIALS SECTION (F-131 will populate)   --}}
    {{-- ============================================ --}}
    {{-- Hidden until F-131 populates with data --}}

    {{-- ============================================ --}}
    {{-- SCHEDULE SECTION (F-132 will populate)       --}}
    {{-- ============================================ --}}
    <section id="schedule" class="scroll-mt-16 py-12 sm:py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-8 sm:mb-12">
                <h2 class="text-2xl sm:text-3xl font-display font-bold text-on-surface-strong">
                    {{ __('Schedule') }}
                </h2>
                <p class="mt-2 text-on-surface max-w-2xl mx-auto">
                    {{ __('Our operating hours and availability.') }}
                </p>
            </div>

            @if($sections['schedule']['hasData'])
                {{-- Schedule placeholder — F-132 will replace with actual schedule display --}}
                <div class="max-w-2xl mx-auto">
                    <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-6 animate-pulse">
                        <div class="space-y-4">
                            @for($i = 0; $i < 5; $i++)
                                <div class="flex justify-between items-center">
                                    <div class="h-4 bg-outline/20 dark:bg-outline/20 rounded w-24"></div>
                                    <div class="h-4 bg-outline/20 dark:bg-outline/20 rounded w-32"></div>
                                </div>
                            @endfor
                        </div>
                    </div>
                </div>
            @else
                <div class="text-center py-8">
                    <div class="w-16 h-16 rounded-full bg-surface-alt dark:bg-surface-alt flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-on-surface opacity-40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path></svg>
                    </div>
                    <p class="text-on-surface opacity-60">{{ __('Schedule information coming soon.') }}</p>
                </div>
            @endif
        </div>
    </section>

    {{-- ============================================ --}}
    {{-- DELIVERY AREAS SECTION (F-133 will populate) --}}
    {{-- ============================================ --}}
    <section id="delivery-areas" class="scroll-mt-16 py-12 sm:py-16 bg-surface-alt dark:bg-surface-alt">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-8 sm:mb-12">
                <h2 class="text-2xl sm:text-3xl font-display font-bold text-on-surface-strong">
                    {{ __('Delivery Areas') }}
                </h2>
                <p class="mt-2 text-on-surface max-w-2xl mx-auto">
                    {{ __('Find out if we deliver to your area.') }}
                </p>
            </div>

            @if($sections['delivery']['hasData'])
                {{-- Delivery areas placeholder — F-133 will replace --}}
                <div class="max-w-2xl mx-auto">
                    <div class="bg-surface dark:bg-surface rounded-lg border border-outline dark:border-outline p-6 animate-pulse">
                        <div class="space-y-4">
                            @for($i = 0; $i < 3; $i++)
                                <div class="flex justify-between items-center">
                                    <div class="h-4 bg-outline/20 dark:bg-outline/20 rounded w-32"></div>
                                    <div class="h-4 bg-outline/20 dark:bg-outline/20 rounded w-20"></div>
                                </div>
                            @endfor
                        </div>
                    </div>
                </div>
            @else
                <div class="text-center py-8">
                    <div class="w-16 h-16 rounded-full bg-surface dark:bg-surface flex items-center justify-center mx-auto mb-4 border border-outline dark:border-outline">
                        <svg class="w-8 h-8 text-on-surface opacity-40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    </div>
                    <p class="text-on-surface opacity-60">{{ __('Delivery information coming soon.') }}</p>
                </div>
            @endif
        </div>
    </section>
</div>
@endsection

@section('footer-content')
    {{-- Quick contact info in footer --}}
    @if($cookProfile['whatsapp'] || $cookProfile['phone'] || $cookProfile['socialLinks']['facebook'] || $cookProfile['socialLinks']['instagram'] || $cookProfile['socialLinks']['tiktok'])
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6">
            {{-- Contact Info --}}
            <div>
                <h3 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wider mb-3">
                    {{ __('Contact') }}
                </h3>
                <div class="space-y-2">
                    @if($cookProfile['whatsapp'])
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $cookProfile['whatsapp']) }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 text-sm text-on-surface hover:text-primary transition-colors duration-200">
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                            {{ __('WhatsApp') }}
                        </a>
                    @endif
                    @if($cookProfile['phone'])
                        <a href="tel:{{ $cookProfile['phone'] }}" class="flex items-center gap-2 text-sm text-on-surface hover:text-primary transition-colors duration-200">
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                            {{ $cookProfile['phone'] }}
                        </a>
                    @endif
                </div>
            </div>

            {{-- Social Links --}}
            @if($cookProfile['socialLinks']['facebook'] || $cookProfile['socialLinks']['instagram'] || $cookProfile['socialLinks']['tiktok'])
                <div>
                    <h3 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wider mb-3">
                        {{ __('Follow Us') }}
                    </h3>
                    <div class="flex gap-3">
                        @if($cookProfile['socialLinks']['facebook'])
                            <a href="{{ $cookProfile['socialLinks']['facebook'] }}" target="_blank" rel="noopener noreferrer" class="w-9 h-9 rounded-lg bg-surface dark:bg-surface border border-outline dark:border-outline flex items-center justify-center text-on-surface hover:text-primary hover:border-primary transition-all duration-200" title="Facebook">
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>
                            </a>
                        @endif
                        @if($cookProfile['socialLinks']['instagram'])
                            <a href="{{ $cookProfile['socialLinks']['instagram'] }}" target="_blank" rel="noopener noreferrer" class="w-9 h-9 rounded-lg bg-surface dark:bg-surface border border-outline dark:border-outline flex items-center justify-center text-on-surface hover:text-primary hover:border-primary transition-all duration-200" title="Instagram">
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="20" x="2" y="2" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"></line></svg>
                            </a>
                        @endif
                        @if($cookProfile['socialLinks']['tiktok'])
                            <a href="{{ $cookProfile['socialLinks']['tiktok'] }}" target="_blank" rel="noopener noreferrer" class="w-9 h-9 rounded-lg bg-surface dark:bg-surface border border-outline dark:border-outline flex items-center justify-center text-on-surface hover:text-primary hover:border-primary transition-all duration-200" title="TikTok">
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.89 2.89 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1v-3.5a6.37 6.37 0 0 0-.79-.05A6.34 6.34 0 0 0 3.14 15.67 6.34 6.34 0 0 0 9.48 22a6.34 6.34 0 0 0 6.34-6.34V9.39a8.16 8.16 0 0 0 3.77.92V6.69z"></path></svg>
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endif
@endsection
