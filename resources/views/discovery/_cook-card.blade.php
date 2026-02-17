{{--
    Cook Card Component (F-067)
    ---------------------------
    Reusable card displaying a cook's summary on the discovery grid.
    BR-074: Shows name, description, cover carousel, meal count, rating, town.
    BR-075: Cover images auto-carousel every 4s, pauses on hover (desktop).
    BR-076: Clicking card navigates to tenant domain (subdomain or custom).
    BR-077: Cards with no cover images show a branded placeholder.
    BR-078: Cooks with zero reviews show "New" instead of star rating.
    BR-079: Description truncated to 2 lines with ellipsis.
    BR-080: Grid responsiveness is handled by parent (1/2/3 columns).
    BR-081: All text localized; name/description via HasTranslatable.
--}}
@php
    $cookName = $tenant->name;
    $description = $tenant->description;
    $initial = mb_substr($tenant->name, 0, 1);
    $tenantUrl = $tenant->getUrl();

    // Cover images: forward-compatible for F-081 (Cook Cover Images Management).
    // When F-081 is built, this will be populated with actual image URLs.
    $coverImages = $tenant->cover_images ?? [];

    // Active meal count: forward-compatible for F-108 (Meal Creation).
    // When meals table exists, DiscoveryService will load this.
    $mealCount = $tenant->active_meal_count ?? 0;

    // Average rating: forward-compatible for F-176 (Order Rating).
    // When reviews exist, DiscoveryService will compute this.
    $averageRating = $tenant->average_rating ?? null;
    $hasRating = $averageRating !== null && $averageRating > 0;

    // Primary delivery town: forward-compatible for F-082 (Add Town).
    // When towns table exists, DiscoveryService will load this.
    $primaryTown = $tenant->primary_town ?? null;
@endphp

<article
    class="group bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-card hover:shadow-dropdown hover:-translate-y-1 transition-all duration-300 overflow-hidden cursor-pointer"
    onclick="window.location.href='{{ $tenantUrl }}'"
    role="link"
    aria-label="{{ __('Visit :cook', ['cook' => $cookName]) }}"
    tabindex="0"
    onkeydown="if(event.key==='Enter')window.location.href='{{ $tenantUrl }}'"
>
    {{-- Cover Image Area (~60% of card height) --}}
    @if(count($coverImages) > 1)
        {{-- Carousel for multiple images (BR-075) --}}
        <div
            x-data="{
                images: @js($coverImages),
                current: 0,
                interval: null,
                paused: false,
                startCarousel() {
                    this.interval = setInterval(() => {
                        if (!this.paused) {
                            this.current = (this.current + 1) % this.images.length;
                        }
                    }, 4000);
                },
                stopCarousel() {
                    if (this.interval) {
                        clearInterval(this.interval);
                        this.interval = null;
                    }
                }
            }"
            x-init="startCarousel()"
            x-on:mouseenter="paused = true"
            x-on:mouseleave="paused = false"
            class="relative h-44 sm:h-48 bg-primary-subtle dark:bg-primary-subtle overflow-hidden"
        >
            {{-- Image slides with fade transition --}}
            <template x-for="(img, index) in images" :key="index">
                <img
                    :src="img"
                    :alt="'{{ $cookName }}'"
                    class="absolute inset-0 w-full h-full object-cover transition-opacity duration-700"
                    :class="current === index ? 'opacity-100' : 'opacity-0'"
                    loading="lazy"
                    onerror="this.style.display='none'"
                >
            </template>

            {{-- Carousel dots indicator --}}
            <div class="absolute bottom-2 left-1/2 -translate-x-1/2 flex items-center gap-1.5 z-10">
                <template x-for="(img, index) in images" :key="'dot-' + index">
                    <button
                        @click.stop="current = index"
                        class="w-2 h-2 rounded-full transition-all duration-300"
                        :class="current === index ? 'bg-white w-4' : 'bg-white/60 hover:bg-white/80'"
                        :aria-label="'{{ __('Image') }} ' + (index + 1)"
                        :aria-current="current === index ? 'true' : 'false'"
                    ></button>
                </template>
            </div>

            {{-- Gradient overlay for depth --}}
            <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent pointer-events-none"></div>
        </div>
    @elseif(count($coverImages) === 1)
        {{-- Single image, no carousel --}}
        <div class="relative h-44 sm:h-48 bg-primary-subtle dark:bg-primary-subtle overflow-hidden">
            <img
                src="{{ $coverImages[0] }}"
                alt="{{ $cookName }}"
                class="w-full h-full object-cover"
                loading="lazy"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'"
            >
            {{-- Fallback if image fails to load --}}
            <div class="absolute inset-0 items-center justify-center hidden">
                <div class="w-16 h-16 rounded-full bg-primary/20 dark:bg-primary/30 flex items-center justify-center">
                    <svg class="w-8 h-8 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m16 2-2.3 2.3a3 3 0 0 0 0 4.2l1.8 1.8a3 3 0 0 0 4.2 0L22 8"></path><path d="M15 15 3.3 3.3a4.2 4.2 0 0 0 0 6l7.3 7.3c1.7 1.7 4.3 1.7 6 0"></path><path d="m2 22 5.5-1.5L21.17 6.83a2.82 2.82 0 0 0-4-4L3.5 16.5z"></path><path d="m18 16 4 4"></path><path d="m17 21 1-3"></path></svg>
                </div>
            </div>
            <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent pointer-events-none"></div>
        </div>
    @else
        {{-- Placeholder when no cover images (BR-077) --}}
        <div class="relative h-44 sm:h-48 bg-gradient-to-br from-primary-subtle to-secondary-subtle dark:from-primary-subtle dark:to-secondary-subtle flex items-center justify-center overflow-hidden">
            <div class="w-16 h-16 rounded-full bg-primary/20 dark:bg-primary/30 flex items-center justify-center">
                {{-- UtensilsCrossed icon (Lucide) --}}
                <svg class="w-8 h-8 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m16 2-2.3 2.3a3 3 0 0 0 0 4.2l1.8 1.8a3 3 0 0 0 4.2 0L22 8"></path><path d="M15 15 3.3 3.3a4.2 4.2 0 0 0 0 6l7.3 7.3c1.7 1.7 4.3 1.7 6 0"></path><path d="m2 22 5.5-1.5L21.17 6.83a2.82 2.82 0 0 0-4-4L3.5 16.5z"></path><path d="m18 16 4 4"></path><path d="m17 21 1-3"></path></svg>
            </div>
            {{-- Decorative pattern --}}
            <div class="absolute inset-0 opacity-5 dark:opacity-10" style="background-image: radial-gradient(circle, currentColor 1px, transparent 1px); background-size: 20px 20px;"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-black/10 to-transparent pointer-events-none"></div>
        </div>
    @endif

    {{-- Card Body (~40% of card height) --}}
    <div class="p-4 sm:p-5">
        {{-- Cook Avatar & Name --}}
        <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-on-primary font-bold text-sm shrink-0">
                {{ strtoupper($initial) }}
            </div>
            <div class="min-w-0 flex-1">
                <h3 class="font-semibold text-on-surface-strong text-base truncate group-hover:text-primary transition-colors duration-200" title="{{ $cookName }}">
                    {{ $cookName }}
                </h3>
            </div>
        </div>

        {{-- Description (BR-079: truncated to 2 lines) --}}
        @if($description)
            <p class="text-sm text-on-surface line-clamp-2 mb-3 leading-relaxed">
                {{ $description }}
            </p>
        @else
            <p class="text-sm text-on-surface/60 italic mb-3 leading-relaxed">
                {{ __('Delicious home-cooked meals') }}
            </p>
        @endif

        {{-- Stats Row: Meals, Rating, Town --}}
        <div class="flex items-center flex-wrap gap-x-3 gap-y-1.5 pt-3 border-t border-outline dark:border-outline text-sm">
            {{-- Active Meal Count --}}
            <div class="flex items-center gap-1 text-on-surface" title="{{ __('Active meals') }}">
                {{-- UtensilsCrossed icon (Lucide, sm=16) --}}
                <svg class="w-4 h-4 text-on-surface/60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 2-2.3 2.3a3 3 0 0 0 0 4.2l1.8 1.8a3 3 0 0 0 4.2 0L22 8"></path><path d="M15 15 3.3 3.3a4.2 4.2 0 0 0 0 6l7.3 7.3c1.7 1.7 4.3 1.7 6 0"></path><path d="m2 22 5.5-1.5L21.17 6.83a2.82 2.82 0 0 0-4-4L3.5 16.5z"></path><path d="m18 16 4 4"></path><path d="m17 21 1-3"></path></svg>
                <span class="text-on-surface-strong font-medium">{{ $mealCount }}</span>
                <span class="text-on-surface/60 hidden sm:inline">{{ trans_choice(':count meal|:count meals', $mealCount, ['count' => $mealCount]) }}</span>
            </div>

            {{-- Separator --}}
            <span class="text-outline dark:text-outline" aria-hidden="true">&middot;</span>

            {{-- Average Rating (BR-078) --}}
            @if($hasRating)
                <div class="flex items-center gap-1" title="{{ __('Average rating') }}">
                    {{-- Star icon (Lucide, filled) --}}
                    <svg class="w-4 h-4 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                    <span class="text-on-surface-strong font-medium">{{ number_format($averageRating, 1) }}</span>
                </div>
            @else
                <div class="flex items-center gap-1" title="{{ __('No ratings yet') }}">
                    {{-- Star icon (Lucide, outlined) --}}
                    <svg class="w-4 h-4 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                    <span class="text-on-surface-strong font-medium">{{ __('New') }}</span>
                </div>
            @endif

            {{-- Primary Town --}}
            @if($primaryTown)
                <span class="text-outline dark:text-outline" aria-hidden="true">&middot;</span>
                <div class="flex items-center gap-1" title="{{ __('Primary delivery area') }}">
                    {{-- MapPin icon (Lucide, sm=16) --}}
                    <svg class="w-4 h-4 text-on-surface/60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    <span class="text-on-surface truncate max-w-[8rem]">{{ $primaryTown }}</span>
                </div>
            @endif
        </div>
    </div>
</article>
