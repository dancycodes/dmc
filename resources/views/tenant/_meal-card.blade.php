{{--
    F-128: Available Meals Grid Display — Meal Card Component
    F-197: Favorite Meal Toggle — Heart button on card
    BR-148: Displays primary image, name, starting price, prep time, tags, availability
    BR-149: Starting price = min component price, displayed as "from X XAF"
    BR-150: Prep time badge with clock icon
    BR-151: Tags as chips, max 3 visible, "+N more" overflow
    BR-153: Clicking navigates to meal detail view via Gale
    BR-154: Name shown in user's current locale
    F-197 BR-333: Guests clicking heart are redirected to login.
    F-197 BR-335: Heart toggle is idempotent (add/remove).
    F-197 BR-337: Heart icon reflects current favorite state on page load.
    F-197 BR-338: Toggle happens via Gale without page reload.
    F-197 BR-339: Brief scale animation on toggle.
--}}
@php
    /** @var array $card — built by TenantLandingService::buildMealCardData() */

    // F-197: Determine initial favorite state for this meal card.
    $initialIsMealFavorited = isset($userFavoriteMealIds) && in_array($card['id'], $userFavoriteMealIds);
    $isMealCardAuthenticated = $isMealCardAuthenticated ?? false;
@endphp

<div class="group relative bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline overflow-hidden shadow-card hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
    {{-- F-197: Favorite Heart Button — top-right corner of the image area --}}
    {{-- Each card has its own x-data scope so state is per-card (BR-337). --}}
    <div
        x-data="{
            isMealFavorited: {{ $initialIsMealFavorited ? 'true' : 'false' }},
            favoriteError: null
        }"
        x-sync="['isMealFavorited']"
        class="absolute top-2 right-2 z-20"
        @click.stop
    >
        @if($isMealCardAuthenticated)
            {{-- Authenticated: Gale toggle --}}
            <button
                @click.stop="$action('{{ route('favorite-meals.toggle', ['meal' => $card['id']]) }}')"
                class="w-10 h-10 rounded-full flex items-center justify-center bg-black/30 backdrop-blur-sm hover:bg-black/50 dark:bg-black/40 dark:hover:bg-black/60 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-1 cursor-pointer"
                :aria-label="isMealFavorited ? '{{ __('Remove from favorites') }}' : '{{ __('Add to favorites') }}'"
                :title="isMealFavorited ? '{{ __('Remove from favorites') }}' : '{{ __('Add to favorites') }}'"
                aria-label="{{ $initialIsMealFavorited ? __('Remove from favorites') : __('Add to favorites') }}"
            >
                {{-- Loading spinner --}}
                <svg
                    x-show="$fetching()"
                    x-cloak
                    class="w-4 h-4 text-white animate-spin"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>

                {{-- Filled heart (favorited) — BR-339: scale animation on state change --}}
                <svg
                    x-show="isMealFavorited && !$fetching()"
                    class="w-4 h-4 text-red-400 transition-all duration-200 scale-100 hover:scale-110"
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                    stroke="currentColor"
                    stroke-width="1"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"></path>
                </svg>

                {{-- Outline heart (not favorited) --}}
                <svg
                    x-show="!isMealFavorited && !$fetching()"
                    class="w-4 h-4 text-white/90 transition-all duration-200 hover:text-red-300 hover:scale-110"
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"></path>
                </svg>
            </button>
        @else
            {{-- Guest: redirect to login on click --}}
            <a
                href="{{ route('login') }}"
                class="w-10 h-10 rounded-full flex items-center justify-center bg-black/30 backdrop-blur-sm hover:bg-black/50 dark:bg-black/40 dark:hover:bg-black/60 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-1"
                aria-label="{{ __('Log in to add to favorites') }}"
                title="{{ __('Log in to add to favorites') }}"
                x-navigate-skip
                @click.stop
            >
                <svg class="w-4 h-4 text-white/90 hover:text-red-300 transition-colors duration-200" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"></path>
                </svg>
            </a>
        @endif
    </div>

    {{-- Clickable link wrapper for the whole card --}}
    <a
        href="{{ url('/meals/' . $card['id']) }}"
        class="block"
        x-navigate
    >
        {{-- Image Section — approximately 60% of card height --}}
        <div class="relative aspect-[4/3] bg-outline/10 dark:bg-outline/10 overflow-hidden">
            @if($card['image'])
                <img
                    src="{{ $card['image'] }}"
                    alt="{{ $card['name'] }}"
                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                    loading="lazy"
                    x-on:error="$el.style.display='none'; $el.nextElementSibling.style.display='flex'"
                >
                {{-- Fallback placeholder (hidden by default, shown on image error) --}}
                <div class="absolute inset-0 items-center justify-center bg-surface-alt dark:bg-surface-alt" style="display:none;">
                    <svg class="w-12 h-12 text-on-surface/20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect><circle cx="9" cy="9" r="2"></circle><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path></svg>
                </div>
            @else
                {{-- No image: placeholder --}}
                <div class="w-full h-full flex items-center justify-center bg-surface-alt dark:bg-surface-alt">
                    <svg class="w-12 h-12 text-on-surface/20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect><circle cx="9" cy="9" r="2"></circle><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path></svg>
                </div>
            @endif

            {{-- BR-150: Prep time badge (BR-274: ~N min or ~N hr format) --}}
            @if($card['prepTime'])
                <div class="absolute top-2 left-2 flex items-center gap-1 bg-surface/90 dark:bg-surface/90 backdrop-blur-sm text-on-surface-strong text-xs font-medium rounded-full px-2.5 py-1 shadow-sm">
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    {{ __('Est. prep: :time', ['time' => $card['prepTime']]) }}
                </div>
            @endif

            {{-- Availability / Sold Out indicator --}}
            @if($card['allComponentsUnavailable'])
                <div class="absolute bottom-2 left-2 flex items-center gap-1 bg-danger/90 text-on-danger text-xs font-semibold rounded-full px-2.5 py-1 shadow-sm">
                    {{ __('Sold Out') }}
                </div>
            @else
                <div class="absolute bottom-2 left-2 flex items-center gap-1 bg-surface/90 dark:bg-surface/90 backdrop-blur-sm text-xs font-medium rounded-full px-2.5 py-1 shadow-sm">
                    <span class="w-2 h-2 rounded-full bg-success"></span>
                    <span class="text-on-surface-strong">{{ __('Available') }}</span>
                </div>
            @endif
        </div>

        {{-- Content Section --}}
        <div class="p-4">
            {{-- BR-154: Meal name in current locale, truncated to 2 lines --}}
            <h3 class="text-base font-semibold text-on-surface-strong leading-snug line-clamp-2 group-hover:text-primary transition-colors duration-200">
                {{ $card['name'] }}
            </h3>

            {{-- BR-149: Starting price --}}
            <div class="mt-2">
                @if($card['startingPrice'] !== null)
                    <p class="text-sm font-bold text-primary">
                        {{ __('from') }} {{ \App\Services\TenantLandingService::formatPrice($card['startingPrice']) }}
                    </p>
                @else
                    <p class="text-sm font-medium text-on-surface/60 italic">
                        {{ __('Price TBD') }}
                    </p>
                @endif
            </div>

            {{-- BR-151: Tag chips --}}
            @if(!empty($card['tags']))
                <div class="mt-3 flex flex-wrap gap-1.5">
                    @foreach($card['tags'] as $tag)
                        <span class="inline-flex items-center text-xs font-medium bg-primary-subtle text-primary rounded-full px-2.5 py-0.5">
                            {{ $tag['name'] }}
                        </span>
                    @endforeach
                    @if($card['tagOverflow'] > 0)
                        <span class="inline-flex items-center text-xs font-medium bg-surface dark:bg-surface text-on-surface border border-outline rounded-full px-2.5 py-0.5">
                            +{{ $card['tagOverflow'] }}
                        </span>
                    @endif
                </div>
            @endif
        </div>
    </a>
</div>
