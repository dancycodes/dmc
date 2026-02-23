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

                            {{-- CTA button + Rating badge + Favorite --}}
                            <div class="mt-4 sm:mt-6 flex flex-col sm:flex-row items-center sm:items-end gap-4">
                                {{-- BR-140: CTA button scrolls to #meals --}}
                                <button
                                    @click="document.getElementById('meals')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                                    class="inline-flex items-center gap-2 h-12 px-8 bg-primary hover:bg-primary-hover text-on-primary font-semibold rounded-lg shadow-card transition-all duration-200 cursor-pointer"
                                >
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="14" rx="1"></rect><rect width="7" height="7" x="3" y="14" rx="1"></rect></svg>
                                    {{ __('View Meals') }}
                                </button>

                                {{-- F-196: Favorite Cook Button (tenant hero — with cover images) --}}
                                {{-- BR-323: Guests redirected to login. BR-325: Idempotent toggle. --}}
                                {{-- BR-327: Heart reflects current state. BR-328: Gale toggle. --}}
                                <div
                                    x-data="{
                                        isFavorited: {{ ($isFavorited ?? false) ? 'true' : 'false' }},
                                        favoriteError: null
                                    }"
                                    x-sync="['isFavorited']"
                                >
                                    @if($isAuthenticated ?? false)
                                        <button
                                            @click.stop="$action('{{ route('favorite-cooks.toggle', ['tenant' => $tenant->slug]) }}')"
                                            class="inline-flex items-center gap-2 h-12 px-4 sm:px-5 rounded-lg bg-black/40 backdrop-blur-sm hover:bg-black/60 text-white transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-white/50 cursor-pointer border border-white/20"
                                            :aria-label="isFavorited ? '{{ __('Remove from favorites') }}' : '{{ __('Add to favorites') }}'"
                                            :title="isFavorited ? '{{ __('Remove from favorites') }}' : '{{ __('Add to favorites') }}'"
                                            aria-label="{{ ($isFavorited ?? false) ? __('Remove from favorites') : __('Add to favorites') }}"
                                        >
                                            {{-- Loading spinner --}}
                                            <svg x-show="$fetching()" x-cloak class="w-5 h-5 text-white animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                            {{-- Filled heart --}}
                                            <svg x-show="isFavorited && !$fetching()" class="w-5 h-5 text-red-400 transition-colors duration-200" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"></path></svg>
                                            {{-- Outline heart --}}
                                            <svg x-show="!isFavorited && !$fetching()" class="w-5 h-5 text-white/90 transition-colors duration-200" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"></path></svg>
                                            <span x-text="isFavorited ? '{{ __('Favorited') }}' : '{{ __('Add to favorites') }}'" class="text-sm font-medium hidden sm:inline"></span>
                                        </button>
                                    @else
                                        <a
                                            href="{{ route('login') }}"
                                            class="inline-flex items-center gap-2 h-12 px-4 sm:px-5 rounded-lg bg-black/40 backdrop-blur-sm hover:bg-black/60 text-white transition-all duration-200 border border-white/20 focus:outline-none focus:ring-2 focus:ring-white/50"
                                            aria-label="{{ __('Log in to add to favorites') }}"
                                            title="{{ __('Log in to add to favorites') }}"
                                            x-navigate-skip
                                        >
                                            <svg class="w-5 h-5 text-white/90" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"></path></svg>
                                            <span class="text-sm font-medium hidden sm:inline">{{ __('Add to favorites') }}</span>
                                        </a>
                                    @endif
                                </div>

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

                    {{-- CTA button + Rating badge + Favorite --}}
                    <div class="mt-8 flex flex-col items-center gap-4">
                        {{-- Inline row: CTA + Favorite side by side on desktop --}}
                        <div class="flex flex-wrap items-center justify-center gap-3">
                        {{-- BR-140: CTA button --}}
                        <button
                            @click="document.getElementById('meals')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                            class="inline-flex items-center gap-2 h-12 px-8 bg-primary hover:bg-primary-hover text-on-primary font-semibold rounded-lg shadow-card transition-all duration-200 cursor-pointer"
                        >
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="14" rx="1"></rect><rect width="7" height="7" x="3" y="14" rx="1"></rect></svg>
                            {{ __('View Meals') }}
                        </button>

                        {{-- F-196: Favorite Cook Button (tenant hero — no cover image variant) --}}
                        {{-- BR-323: Guests redirected to login. BR-325: Idempotent toggle. --}}
                        <div
                            x-data="{
                                isFavorited: {{ ($isFavorited ?? false) ? 'true' : 'false' }},
                                favoriteError: null
                            }"
                            x-sync="['isFavorited']"
                        >
                            @if($isAuthenticated ?? false)
                                <button
                                    @click.stop="$action('{{ route('favorite-cooks.toggle', ['tenant' => $tenant->slug]) }}')"
                                    class="inline-flex items-center gap-2 h-12 px-4 sm:px-5 rounded-lg border transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary cursor-pointer"
                                    :class="isFavorited
                                        ? 'bg-danger-subtle dark:bg-danger-subtle border-danger/30 dark:border-danger/30 text-danger dark:text-danger'
                                        : 'bg-surface dark:bg-surface border-outline dark:border-outline text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt'"
                                    :aria-label="isFavorited ? '{{ __('Remove from favorites') }}' : '{{ __('Add to favorites') }}'"
                                    :title="isFavorited ? '{{ __('Remove from favorites') }}' : '{{ __('Add to favorites') }}'"
                                    aria-label="{{ ($isFavorited ?? false) ? __('Remove from favorites') : __('Add to favorites') }}"
                                >
                                    {{-- Loading spinner --}}
                                    <svg x-show="$fetching()" x-cloak class="w-5 h-5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                    {{-- Filled heart --}}
                                    <svg x-show="isFavorited && !$fetching()" class="w-5 h-5 text-red-500 transition-all duration-200 scale-110" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"></path></svg>
                                    {{-- Outline heart --}}
                                    <svg x-show="!isFavorited && !$fetching()" class="w-5 h-5 transition-all duration-200" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"></path></svg>
                                    <span x-text="isFavorited ? '{{ __('Favorited') }}' : '{{ __('Save cook') }}'" class="text-sm font-medium"></span>
                                </button>
                            @else
                                <a
                                    href="{{ route('login') }}"
                                    class="inline-flex items-center gap-2 h-12 px-4 sm:px-5 rounded-lg bg-surface dark:bg-surface border border-outline dark:border-outline text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary"
                                    aria-label="{{ __('Log in to add to favorites') }}"
                                    title="{{ __('Log in to add to favorites') }}"
                                    x-navigate-skip
                                >
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"></path></svg>
                                    <span class="text-sm font-medium">{{ __('Save cook') }}</span>
                                </a>
                            @endif
                        </div>
                        </div>

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

            {{-- F-135: Meal Search Bar --}}
            @include('tenant._meal-search', ['searchQuery' => '', 'filterData' => $filterData, 'currentSort' => 'popular'])

            {{-- F-136: Desktop sidebar layout with filters + grid --}}
            @if(($filterData['hasTags'] ?? false) || ($filterData['hasPriceRange'] ?? false))
                <div class="lg:flex lg:gap-8">
                    {{-- F-136: Filter sidebar (desktop) + floating button + bottom sheet (mobile) --}}
                    <div class="lg:w-64 lg:shrink-0">
                        @include('tenant._meal-filters', ['filterData' => $filterData, 'currentSort' => 'popular'])
                    </div>

                    {{-- Meals grid with F-137: Sort dropdown --}}
                    <div class="flex-1 min-w-0">
                        @include('tenant._meal-sort', ['currentSort' => 'popular', 'meals' => $meals])
                        @include('tenant._meals-grid', ['meals' => $meals, 'sections' => $sections, 'searchQuery' => '', 'activeFilterCount' => 0, 'currentSort' => 'popular', 'userFavoriteMealIds' => $userFavoriteMealIds ?? [], 'isMealCardAuthenticated' => $isMealCardAuthenticated ?? false])
                    </div>
                </div>
            @else
                {{-- No filters available — full-width grid with sort --}}
                @include('tenant._meal-sort', ['currentSort' => 'popular', 'meals' => $meals])
                @include('tenant._meals-grid', ['meals' => $meals, 'sections' => $sections, 'searchQuery' => '', 'activeFilterCount' => 0, 'currentSort' => 'popular', 'userFavoriteMealIds' => $userFavoriteMealIds ?? [], 'isMealCardAuthenticated' => $isMealCardAuthenticated ?? false])
            @endif
        </div>
    </section>

    {{-- ============================================ --}}
    {{-- ABOUT / BIO SECTION                          --}}
    {{-- ============================================ --}}
    {{-- Only render when the cook has set a bio --}}
    @if($sections['about']['hasData'])
    <section id="about" class="scroll-mt-16 py-12 sm:py-16 bg-surface-alt dark:bg-surface-alt">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-8 sm:mb-12">
                <h2 class="text-2xl sm:text-3xl font-display font-bold text-on-surface-strong">
                    {{ __('About Us') }}
                </h2>
            </div>

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
        </div>
    </section>
    @endif

    {{-- ============================================ --}}
    {{-- RATINGS SECTION — F-130: Ratings Summary     --}}
    {{-- BR-167: Average rating with star display      --}}
    {{-- BR-168: Star distribution bar chart           --}}
    {{-- BR-169: 5 most recent reviews                 --}}
    {{-- BR-172: "See all reviews" when > 5 reviews    --}}
    {{-- BR-173: Empty state when no reviews           --}}
    {{-- ============================================ --}}
    <section id="ratings" class="scroll-mt-16 py-12 sm:py-16 bg-surface-alt dark:bg-surface-alt">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-8 sm:mb-12">
                <h2 class="text-2xl sm:text-3xl font-display font-bold text-on-surface-strong dark:text-on-surface-strong">
                    {{ __('Ratings & Reviews') }}
                </h2>
                @if($ratingsDisplay['hasReviews'])
                    <p class="mt-2 text-on-surface dark:text-on-surface max-w-2xl mx-auto">
                        {{ __('What our customers are saying.') }}
                    </p>
                @endif
            </div>

            <div class="max-w-2xl mx-auto">
                @include('tenant._ratings-section', ['ratingsDisplay' => $ratingsDisplay])
            </div>
        </div>
    </section>

    {{-- ============================================ --}}
    {{-- TESTIMONIALS SECTION                         --}}
    {{-- F-182: Approved testimonials display         --}}
    {{-- F-180: Submission CTA embedded at bottom     --}}
    {{-- BR-446: Only approved testimonials shown     --}}
    {{-- BR-447: Maximum 10 displayed                 --}}
    {{-- BR-450: Carousel on mobile, grid on desktop  --}}
    {{-- BR-454: All text localized via __()          --}}
    {{-- ============================================ --}}
    <section id="testimonials" class="scroll-mt-16 py-12 sm:py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-8 sm:mb-12">
                <h2 class="text-2xl sm:text-3xl font-display font-bold text-on-surface-strong dark:text-on-surface-strong">
                    {{ __('What Our Customers Say') }}
                </h2>
                @if($testimonialsDisplay['hasTestimonials'])
                    <p class="mt-2 text-on-surface dark:text-on-surface max-w-2xl mx-auto">
                        {{ __('Real experiences from people who love our food.') }}
                    </p>
                @endif
            </div>

            {{-- F-182: Testimonials display with F-180 CTA embedded --}}
            @include('tenant._testimonials-display', [
                'testimonialsDisplay' => $testimonialsDisplay,
                'testimonialContext' => $testimonialContext,
                'tenant' => $tenant,
            ])
        </div>
    </section>

    {{-- ============================================ --}}
    {{-- SCHEDULE SECTION — F-132: Schedule &         --}}
    {{-- Availability Display                         --}}
    {{-- BR-186: All 7 days with availability status  --}}
    {{-- BR-188: Current day highlighted              --}}
    {{-- BR-189: "Available Now" badge                --}}
    {{-- BR-190: "Next available" badge               --}}
    {{-- BR-193: Times in Africa/Douala timezone      --}}
    {{-- Only rendered when the cook has a schedule   --}}
    {{-- ============================================ --}}
    @if($scheduleDisplay['hasSchedule'])
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
    @endif

    {{-- ============================================ --}}
    {{-- DELIVERY AREAS SECTION — F-133: Delivery    --}}
    {{-- Areas & Fees Display                         --}}
    {{-- BR-195: Hierarchical town > quarter > fee    --}}
    {{-- BR-196: Free delivery for 0 fee              --}}
    {{-- BR-197: Grouped quarters shown together      --}}
    {{-- BR-198: Pickup locations with addresses       --}}
    {{-- BR-199: Pickup always "Free"                  --}}
    {{-- BR-200: Fallback with WhatsApp contact        --}}
    {{-- BR-201: Expandable/collapsible on mobile      --}}
    {{-- BR-202/BR-203: Localized text and names       --}}
    {{-- Only rendered when delivery areas exist      --}}
    {{-- ============================================ --}}
    @if($deliveryDisplay['hasDeliveryAreas'])
    <section id="delivery" class="scroll-mt-16 py-12 sm:py-16 bg-surface-alt dark:bg-surface-alt">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-8 sm:mb-12">
                <h2 class="text-2xl sm:text-3xl font-display font-bold text-on-surface-strong">
                    {{ __('Delivery Areas & Pickup') }}
                </h2>
                <p class="mt-2 text-on-surface max-w-2xl mx-auto">
                    {{ __('Find out if we deliver to your area.') }}
                </p>
            </div>

            @include('tenant._delivery-section', ['deliveryDisplay' => $deliveryDisplay])
        </div>
    </section>
    @endif

    {{-- ============================================ --}}
    {{-- F-212: Cancellation Policy                   --}}
    {{-- F-213: Minimum Order Amount Display          --}}
    {{-- BR-501: Cancellation policy displayed on     --}}
    {{-- tenant landing page.                         --}}
    {{-- BR-512: Minimum order displayed when > 0.    --}}
    {{-- BR-514: Hidden when minimum is 0.            --}}
    {{-- BR-505/BR-518: All text uses __().           --}}
    {{-- ============================================ --}}
    <section id="ordering-info" class="scroll-mt-16 py-8 sm:py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row items-stretch justify-center gap-3">
                {{-- Cancellation Policy badge --}}
                <div class="flex flex-col sm:flex-row items-center justify-center gap-3 bg-info-subtle dark:bg-info-subtle border border-info/30 dark:border-info/30 rounded-xl px-6 py-4 text-center sm:text-left flex-1">
                    <div class="flex items-center gap-2 shrink-0">
                        <svg class="w-5 h-5 text-info shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                        <span class="font-semibold text-info text-sm">{{ __('Cancellation Policy') }}</span>
                    </div>
                    <p class="text-sm text-on-surface">
                        {{ __('Free cancellation within :minutes minutes of placing your order.', ['minutes' => $cancellationWindowMinutes]) }}
                    </p>
                </div>

                {{-- BR-512: Minimum order badge — only shown when minimum > 0 (BR-514) --}}
                @if($minimumOrderAmount > 0)
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-3 bg-secondary-subtle dark:bg-secondary-subtle border border-secondary/30 dark:border-secondary/30 rounded-xl px-6 py-4 text-center sm:text-left flex-1">
                        <div class="flex items-center gap-2 shrink-0">
                            <svg class="w-5 h-5 text-secondary shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                            <span class="font-semibold text-secondary text-sm">{{ __('Minimum Order') }}</span>
                        </div>
                        <p class="text-sm text-on-surface">
                            {{ __('Minimum order: :amount XAF', ['amount' => number_format($minimumOrderAmount)]) }}
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </section>
</div>

{{-- ============================================ --}}
{{-- F-134: WhatsApp Floating Action Button       --}}
{{-- BR-204: Always visible, bottom-right corner  --}}
{{-- BR-205: wa.me link with pre-filled message   --}}
{{-- BR-212: Does not obstruct other elements     --}}
{{-- ============================================ --}}
@include('tenant._whatsapp-fab', ['cookProfile' => $cookProfile])
@endsection

@section('footer-content')
    {{-- F-134: Enhanced Contact & Social Links Footer --}}
    @include('tenant._contact-section', ['cookProfile' => $cookProfile])
@endsection
