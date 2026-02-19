{{--
    F-126: Tenant Landing Page Layout
    F-127: Cook Brand Header Section
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
    {{-- HERO SECTION — F-127: Cook Brand Header      --}}
    {{-- BR-136: Brand name from locale               --}}
    {{-- BR-137: Tagline = bio excerpt (150 chars)     --}}
    {{-- BR-138: Carousel up to 5 images, 5s cycle     --}}
    {{-- BR-139: Pause on hover/touch                  --}}
    {{-- BR-140: CTA scrolls to #meals                 --}}
    {{-- BR-141/142: Rating stars + review count       --}}
    {{-- BR-143: "New Cook" badge for zero reviews     --}}
    {{-- BR-144: Fully responsive                      --}}
    {{-- BR-145: All text localized via __()           --}}
    {{-- ============================================ --}}
    <section id="hero" class="scroll-mt-16">
        @if($cookProfile['hasImages'])
            {{-- Hero with cover image carousel --}}
            @php
                $imageCount = count($cookProfile['coverImages']);
                $isSingleImage = $imageCount === 1;
            @endphp
            <div class="relative h-[40vh] sm:h-[50vh] md:h-[60vh] lg:h-[70vh] overflow-hidden bg-surface-alt dark:bg-surface-alt"
                 x-data="{
                    currentSlide: 0,
                    totalSlides: {{ $imageCount }},
                    paused: false,
                    timer: null,
                    startCarousel() {
                        if (this.totalSlides <= 1) return;
                        this.timer = setInterval(() => {
                            if (!this.paused) {
                                this.currentSlide = (this.currentSlide + 1) % this.totalSlides;
                            }
                        }, 5000);
                    },
                    goToSlide(index) {
                        this.currentSlide = index;
                    }
                 }"
                 x-init="startCarousel()"
                 x-on:mouseenter="paused = true"
                 x-on:mouseleave="paused = false"
                 x-on:touchstart.passive="paused = true"
                 x-on:touchend.passive="paused = false"
            >
                {{-- Carousel images --}}
                @foreach($cookProfile['coverImages'] as $index => $image)
                    <div
                        x-show="currentSlide === {{ $index }}"
                        x-transition:enter="transition-opacity ease-out duration-700"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition-opacity ease-in duration-500"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="absolute inset-0"
                    >
                        <img
                            src="{{ $image['url'] }}"
                            alt="{{ $tenant->name }} - {{ __('Cover image') }} {{ $index + 1 }}"
                            class="w-full h-full object-cover"
                            loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                            x-on:error="$el.style.display='none'"
                        >
                    </div>
                @endforeach

                {{-- Gradient overlay for text readability --}}
                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/30 to-black/10"></div>

                {{-- Hero content overlay --}}
                <div class="absolute inset-0 flex flex-col justify-end">
                    <div class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 pb-8 sm:pb-10 md:pb-14">
                        <div class="text-center sm:text-left">
                            {{-- BR-136: Brand name --}}
                            <h1 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-display font-bold text-white drop-shadow-lg leading-tight">
                                {{ $cookProfile['name'] }}
                            </h1>

                            {{-- BR-137: Tagline (bio excerpt, 150 chars) --}}
                            @if($cookProfile['bioExcerpt'])
                                <p class="mt-2 sm:mt-3 text-base sm:text-lg md:text-xl text-white/90 max-w-2xl drop-shadow leading-relaxed">
                                    {{ $cookProfile['bioExcerpt'] }}
                                </p>
                            @endif

                            {{-- CTA button + Rating badge --}}
                            <div class="mt-4 sm:mt-6 flex flex-col sm:flex-row items-center sm:items-end gap-4">
                                {{-- BR-140: CTA button scrolls to #meals --}}
                                <button
                                    @click="document.getElementById('meals')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                                    class="inline-flex items-center gap-2 h-12 px-8 bg-primary hover:bg-primary-hover text-on-primary font-semibold rounded-lg shadow-card transition-all duration-200 cursor-pointer"
                                >
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="14" rx="1"></rect><rect width="7" height="7" x="3" y="14" rx="1"></rect></svg>
                                    {{ __('View Meals') }}
                                </button>

                                {{-- BR-141/142/143: Rating badge --}}
                                @if($cookProfile['rating']['hasReviews'])
                                    <div class="flex items-center gap-2 bg-black/40 backdrop-blur-sm rounded-full px-4 py-2">
                                        {{-- Star icons --}}
                                        <div class="flex items-center gap-0.5">
                                            @for($i = 1; $i <= 5; $i++)
                                                @if($i <= floor($cookProfile['rating']['average']))
                                                    {{-- Filled star --}}
                                                    <svg class="w-4 h-4 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                                @elseif($i - $cookProfile['rating']['average'] < 1)
                                                    {{-- Half star (approximate) --}}
                                                    <svg class="w-4 h-4 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" opacity="0.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                                @else
                                                    {{-- Empty star --}}
                                                    <svg class="w-4 h-4 text-white/40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                                @endif
                                            @endfor
                                        </div>
                                        <span class="text-sm font-semibold text-white">{{ $cookProfile['rating']['average'] }}</span>
                                        <span class="text-sm text-white/70">({{ trans_choice(':count review|:count reviews', $cookProfile['rating']['count'], ['count' => $cookProfile['rating']['count']]) }})</span>
                                    </div>
                                @else
                                    {{-- BR-143: New Cook badge --}}
                                    <div class="flex items-center gap-2 bg-black/40 backdrop-blur-sm rounded-full px-4 py-2">
                                        <svg class="w-4 h-4 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                        <span class="text-sm font-semibold text-white">{{ __('New Cook') }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Carousel navigation dots --}}
                @if(!$isSingleImage)
                    <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-2 z-10">
                        @foreach($cookProfile['coverImages'] as $index => $image)
                            <button
                                @click="goToSlide({{ $index }})"
                                class="w-2 h-2 rounded-full transition-all duration-300 cursor-pointer"
                                :class="currentSlide === {{ $index }} ? 'bg-white w-6' : 'bg-white/50 hover:bg-white/70'"
                                aria-label="{{ __('Go to slide') }} {{ $index + 1 }}"
                            ></button>
                        @endforeach
                    </div>
                @endif
            </div>
        @else
            {{-- No cover images: gradient hero with theme colors --}}
            <div class="bg-gradient-to-br from-primary-subtle via-surface to-secondary-subtle dark:from-primary-subtle dark:via-surface dark:to-secondary-subtle">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20 md:py-28 text-center">
                    {{-- Avatar initial --}}
                    <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-primary text-on-primary flex items-center justify-center font-bold text-3xl sm:text-4xl mx-auto mb-6 shadow-card">
                        {{ mb_strtoupper(mb_substr($cookProfile['name'], 0, 1)) }}
                    </div>

                    {{-- BR-136: Brand name --}}
                    <h1 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-display font-bold text-on-surface-strong leading-tight">
                        {{ $cookProfile['name'] }}
                    </h1>

                    {{-- BR-137: Tagline --}}
                    @if($cookProfile['bioExcerpt'])
                        <p class="mt-4 text-lg sm:text-xl text-on-surface max-w-2xl mx-auto leading-relaxed">
                            {{ $cookProfile['bioExcerpt'] }}
                        </p>
                    @else
                        <p class="mt-4 text-lg sm:text-xl text-on-surface max-w-2xl mx-auto leading-relaxed">
                            {{ __('Delicious home-cooked meals made with love.') }}
                        </p>
                    @endif

                    {{-- CTA button + Rating badge --}}
                    <div class="mt-8 flex flex-col items-center gap-4">
                        {{-- BR-140: CTA button --}}
                        <button
                            @click="document.getElementById('meals')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                            class="inline-flex items-center gap-2 h-12 px-8 bg-primary hover:bg-primary-hover text-on-primary font-semibold rounded-lg shadow-card transition-all duration-200 cursor-pointer"
                        >
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="14" rx="1"></rect><rect width="7" height="7" x="3" y="14" rx="1"></rect></svg>
                            {{ __('View Meals') }}
                        </button>

                        {{-- BR-141/142/143: Rating badge --}}
                        @if($cookProfile['rating']['hasReviews'])
                            <div class="flex items-center gap-2 bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-full px-4 py-2 shadow-sm">
                                <div class="flex items-center gap-0.5">
                                    @for($i = 1; $i <= 5; $i++)
                                        @if($i <= floor($cookProfile['rating']['average']))
                                            <svg class="w-4 h-4 text-yellow-500 dark:text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                        @elseif($i - $cookProfile['rating']['average'] < 1)
                                            <svg class="w-4 h-4 text-yellow-500 dark:text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" opacity="0.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                        @else
                                            <svg class="w-4 h-4 text-on-surface/20 dark:text-on-surface/20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                        @endif
                                    @endfor
                                </div>
                                <span class="text-sm font-semibold text-on-surface-strong">{{ $cookProfile['rating']['average'] }}</span>
                                <span class="text-sm text-on-surface">({{ trans_choice(':count review|:count reviews', $cookProfile['rating']['count'], ['count' => $cookProfile['rating']['count']]) }})</span>
                            </div>
                        @else
                            {{-- BR-143: New Cook badge --}}
                            <div class="flex items-center gap-2 bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-full px-4 py-2 shadow-sm">
                                <svg class="w-4 h-4 text-yellow-500 dark:text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                <span class="text-sm font-semibold text-on-surface-strong">{{ __('New Cook') }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </section>

    {{-- ============================================ --}}
    {{-- MEALS GRID SECTION — F-128: Available Meals  --}}
    {{-- BR-146: Only live + available meals           --}}
    {{-- BR-147: Filtered by cook schedule             --}}
    {{-- BR-152: Responsive 1/2/3 column grid          --}}
    {{-- BR-153: Cards link to meal detail via Gale    --}}
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

            @include('tenant._meals-grid', ['meals' => $meals, 'sections' => $sections])
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
    {{-- SCHEDULE SECTION — F-132: Schedule &         --}}
    {{-- Availability Display                         --}}
    {{-- BR-186: All 7 days with availability status  --}}
    {{-- BR-188: Current day highlighted              --}}
    {{-- BR-189: "Available Now" badge                --}}
    {{-- BR-190: "Next available" badge               --}}
    {{-- BR-193: Times in Africa/Douala timezone      --}}
    {{-- ============================================ --}}
    <section id="schedule" class="scroll-mt-16 py-12 sm:py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-8 sm:mb-12">
                <h2 class="text-2xl sm:text-3xl font-display font-bold text-on-surface-strong">
                    {{ __('Schedule & Availability') }}
                </h2>
                <p class="mt-2 text-on-surface max-w-2xl mx-auto">
                    {{ __('Our operating hours and availability.') }}
                </p>
            </div>

            @include('tenant._schedule-section', ['scheduleDisplay' => $scheduleDisplay])
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
