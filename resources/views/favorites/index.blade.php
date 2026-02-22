{{--
    Favorites List View (F-198)
    ---------------------------
    Displays the authenticated user's favorite cooks and meals.
    Two tabs: "Favorite Cooks" (default) and "Favorite Meals".

    BR-344: Authentication required (enforced in route group).
    BR-345: Default tab is Favorite Cooks.
    BR-346: Cook cards link to the cook's tenant landing page.
    BR-347: Meal cards link to meal detail on the cook's tenant domain.
    BR-348: Each card has a remove-from-favorites action.
    BR-349: Removal via Gale without page reload; card animates out.
    BR-350: Items displayed reverse chronological (most recently favorited first).
    BR-351: Unavailable items shown with "Unavailable" badge and dimmed styling.
    BR-352: Empty state with CTA link to discovery page.
    BR-353: Tab switching via Gale navigate fragment.
    BR-354: 12 per page pagination.
    BR-355: All user-facing text via __() localization.
--}}
@extends(tenant() ? 'layouts.tenant-public' : 'layouts.main-public')

@section('title', __('Favorites'))

@section('content')
<div
    x-data="{
        activeTab: @js($tab),
        removedCookId: null,
        removedMealId: null,
        switchTab(newTab) {
            this.activeTab = newTab;
            $navigate('/my-favorites?tab=' + newTab, { key: 'favorites', replace: true });
        }
    }"
    x-sync="['removedCookId', 'removedMealId']"
    class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12"
>
    {{-- Page Header --}}
    <div class="mb-8">
        <h1 class="text-2xl sm:text-3xl font-bold text-on-surface-strong font-display">
            {{ __('My Favorites') }}
        </h1>
        <p class="mt-1 text-sm text-on-surface">
            {{ __('Your saved cooks and meals in one place.') }}
        </p>
    </div>

    {{-- Tab Navigation --}}
    <div class="flex items-center gap-1 p-1 bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline mb-8 w-fit" role="tablist" aria-label="{{ __('Favorites tabs') }}">
        {{-- Cooks Tab --}}
        <button
            type="button"
            role="tab"
            :aria-selected="activeTab === 'cooks'"
            :class="activeTab === 'cooks'
                ? 'bg-primary text-on-primary shadow-sm'
                : 'text-on-surface hover:bg-surface dark:hover:bg-surface'"
            class="flex items-center gap-2 px-5 py-2 rounded-lg text-sm font-semibold transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-1"
            @click="switchTab('cooks')"
        >
            {{-- ChefHat icon (Lucide md=20) --}}
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M6 13.87A4 4 0 0 1 7.41 6a5.11 5.11 0 0 1 1.05-1.54 5 5 0 0 1 7.08 0A5.11 5.11 0 0 1 16.59 6 4 4 0 0 1 18 13.87V21H6Z"></path>
                <line x1="6" x2="18" y1="17" y2="17"></line>
            </svg>
            {{ __('Favorite Cooks') }}
        </button>

        {{-- Meals Tab --}}
        <button
            type="button"
            role="tab"
            :aria-selected="activeTab === 'meals'"
            :class="activeTab === 'meals'
                ? 'bg-primary text-on-primary shadow-sm'
                : 'text-on-surface hover:bg-surface dark:hover:bg-surface'"
            class="flex items-center gap-2 px-5 py-2 rounded-lg text-sm font-semibold transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-1"
            @click="switchTab('meals')"
        >
            {{-- UtensilsCrossed icon (Lucide md=20) --}}
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m16 2-2.3 2.3a3 3 0 0 0 0 4.2l1.8 1.8a3 3 0 0 0 4.2 0L22 8"></path>
                <path d="M15 15 3.3 3.3a4.2 4.2 0 0 0 0 6l7.3 7.3c1.7 1.7 4.3 1.7 6 0"></path>
                <path d="m2 22 5.5-1.5L21.17 6.83a2.82 2.82 0 0 0-4-4L3.5 16.5z"></path>
                <path d="m18 16 4 4"></path>
                <path d="m17 21 1-3"></path>
            </svg>
            {{ __('Favorite Meals') }}
        </button>
    </div>

    {{-- Fragment: updated by tab switching and pagination (BR-353) --}}
    @fragment('favorites-content')
    <div id="favorites-content">
        @if($tab === 'cooks')
            {{-- ===== FAVORITE COOKS TAB ===== --}}
            @if($favorites->isEmpty())
                {{-- BR-352: Empty state with discovery CTA --}}
                <div class="flex flex-col items-center justify-center py-20 text-center">
                    {{-- Heart icon decoration --}}
                    <div class="w-20 h-20 rounded-full bg-danger-subtle flex items-center justify-center mb-5">
                        <svg class="w-10 h-10 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"></path>
                        </svg>
                    </div>
                    <h2 class="text-xl font-semibold text-on-surface-strong mb-2">{{ __('No favorite cooks yet') }}</h2>
                    <p class="text-sm text-on-surface max-w-sm mb-6">
                        {{ __('No favorite cooks yet. Discover cooks and add them to your favorites!') }}
                    </p>
                    <a
                        href="{{ url('/discover') }}"
                        class="inline-flex items-center gap-2 h-10 px-6 rounded-lg bg-primary hover:bg-primary-hover text-on-primary text-sm font-semibold transition-all duration-200"
                        x-navigate
                    >
                        {{-- Search icon (Lucide sm=16) --}}
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.3-4.3"></path>
                        </svg>
                        {{ __('Discover Cooks') }}
                    </a>
                </div>
            @else
                {{-- Cook cards grid: 1 col mobile, 2 tablet, 3 desktop --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    @foreach($favorites as $item)
                        {{-- BR-349: Each card has its own x-data scope for animation on removal --}}
                        <div
                            x-data="{
                                cookUserId: {{ $item['cook_user_id'] }},
                                removing: false,
                                get shouldHide() {
                                    return $root.removedCookId === this.cookUserId;
                                }
                            }"
                            x-show="!shouldHide"
                            x-transition:leave="transition ease-in duration-300"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="relative group"
                            :class="{ 'pointer-events-none': removing }"
                        >
                            {{-- BR-351: Unavailable overlay --}}
                            @if(!$item['is_available'])
                                <div class="absolute top-3 left-3 z-10">
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold bg-danger text-on-danger rounded-full px-2.5 py-1 shadow-sm">
                                        {{-- AlertCircle icon (Lucide xs=14) --}}
                                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <line x1="12" x2="12" y1="8" y2="12"></line>
                                            <line x1="12" x2="12.01" y1="16" y2="16"></line>
                                        </svg>
                                        {{ __('Unavailable') }}
                                    </span>
                                </div>
                            @endif

                            {{-- Card --}}
                            <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline shadow-card overflow-hidden {{ !$item['is_available'] ? 'opacity-60' : '' }} transition-all duration-300">
                                {{-- Cover image / placeholder --}}
                                @if(!empty($item['cover_images']))
                                    <div class="relative h-40 overflow-hidden">
                                        <img
                                            src="{{ $item['cover_images'][0] }}"
                                            alt="{{ $item['tenant_name_en'] ?? $item['cook_name'] }}"
                                            class="w-full h-full object-cover {{ $item['is_available'] ? 'group-hover:scale-105 transition-transform duration-500' : '' }}"
                                            loading="lazy"
                                        >
                                        <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent pointer-events-none"></div>
                                    </div>
                                @else
                                    <div class="h-40 bg-gradient-to-br from-primary-subtle to-secondary-subtle dark:from-primary-subtle dark:to-secondary-subtle flex items-center justify-center">
                                        <div class="w-14 h-14 rounded-full bg-primary/20 flex items-center justify-center">
                                            <svg class="w-7 h-7 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M6 13.87A4 4 0 0 1 7.41 6a5.11 5.11 0 0 1 1.05-1.54 5 5 0 0 1 7.08 0A5.11 5.11 0 0 1 16.59 6 4 4 0 0 1 18 13.87V21H6Z"></path>
                                                <line x1="6" x2="18" y1="17" y2="17"></line>
                                            </svg>
                                        </div>
                                    </div>
                                @endif

                                {{-- Card body --}}
                                <div class="p-4">
                                    {{-- Cook initial + name --}}
                                    <div class="flex items-center gap-3 mb-2">
                                        <div class="w-9 h-9 rounded-full bg-primary flex items-center justify-center text-on-primary font-bold text-sm shrink-0">
                                            {{ strtoupper(mb_substr($item['tenant_name_en'] ?? $item['cook_name'], 0, 1)) }}
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            @if($item['is_available'] && $item['tenant_url'])
                                                <a
                                                    href="{{ $item['tenant_url'] }}"
                                                    class="block font-semibold text-sm text-on-surface-strong truncate hover:text-primary transition-colors duration-200"
                                                    title="{{ app()->getLocale() === 'fr' ? ($item['tenant_name_fr'] ?? $item['tenant_name_en']) : $item['tenant_name_en'] }}"
                                                    x-navigate-skip
                                                >
                                                    {{ app()->getLocale() === 'fr' ? ($item['tenant_name_fr'] ?? $item['tenant_name_en']) : $item['tenant_name_en'] }}
                                                </a>
                                            @else
                                                <span class="block font-semibold text-sm text-on-surface-strong truncate">
                                                    {{ app()->getLocale() === 'fr' ? ($item['tenant_name_fr'] ?? $item['tenant_name_en']) : $item['tenant_name_en'] }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Stats row --}}
                                    <div class="flex items-center gap-3 text-xs text-on-surface mb-4 mt-1">
                                        @if($item['average_rating'] !== null && $item['average_rating'] > 0 && $item['total_ratings'] > 0)
                                            <div class="flex items-center gap-1">
                                                {{-- Star icon (Lucide xs=14 filled) --}}
                                                <svg class="w-3.5 h-3.5 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                                </svg>
                                                <span class="font-medium text-on-surface-strong">{{ number_format($item['average_rating'], 1) }}</span>
                                                <span class="text-on-surface">({{ $item['total_ratings'] }})</span>
                                            </div>
                                        @else
                                            <div class="flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                                </svg>
                                                <span class="text-on-surface">{{ __('New') }}</span>
                                            </div>
                                        @endif
                                        @if($item['primary_town'])
                                            <span class="text-outline" aria-hidden="true">&middot;</span>
                                            <div class="flex items-center gap-1 min-w-0">
                                                <svg class="w-3.5 h-3.5 text-on-surface/60 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path>
                                                    <circle cx="12" cy="10" r="3"></circle>
                                                </svg>
                                                <span class="truncate max-w-[7rem]">{{ $item['primary_town'] }}</span>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Actions row: visit + remove --}}
                                    <div class="flex items-center gap-2 pt-3 border-t border-outline">
                                        @if($item['is_available'] && $item['tenant_url'])
                                            <a
                                                href="{{ $item['tenant_url'] }}"
                                                class="flex-1 inline-flex items-center justify-center gap-1.5 h-8 px-3 rounded-lg text-xs font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200"
                                                x-navigate-skip
                                            >
                                                {{-- ExternalLink icon (Lucide xs=14) --}}
                                                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M15 3h6v6"></path>
                                                    <path d="M10 14 21 3"></path>
                                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                                </svg>
                                                {{ __('Visit') }}
                                            </a>
                                        @else
                                            <span class="flex-1 inline-flex items-center justify-center h-8 px-3 rounded-lg text-xs font-semibold bg-surface dark:bg-surface text-on-surface/40 border border-outline cursor-not-allowed">
                                                {{ __('Unavailable') }}
                                            </span>
                                        @endif

                                        {{-- BR-348: Remove from favorites --}}
                                        <button
                                            type="button"
                                            @click="removing = true; $action('{{ route('favorites.cooks.remove', $item['cook_user_id']) }}')"
                                            class="h-8 w-8 rounded-lg flex items-center justify-center text-on-surface hover:bg-danger-subtle hover:text-danger border border-outline transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-danger focus:ring-offset-1 shrink-0"
                                            :class="{ 'opacity-50 cursor-wait': removing }"
                                            :disabled="removing"
                                            title="{{ __('Remove from favorites') }}"
                                            aria-label="{{ __('Remove from favorites') }}"
                                        >
                                            <svg x-show="!$fetching()" class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"></path>
                                            </svg>
                                            <svg x-show="$fetching()" x-cloak class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Pagination --}}
                @if($favorites->hasPages())
                    <div class="mt-8 flex justify-center" x-data x-navigate>
                        {{ $favorites->appends(['tab' => 'cooks'])->links() }}
                    </div>
                @endif
            @endif

        @else
            {{-- ===== FAVORITE MEALS TAB ===== --}}
            @if($favorites->isEmpty())
                {{-- BR-352: Empty state with discovery CTA --}}
                <div class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="w-20 h-20 rounded-full bg-secondary-subtle flex items-center justify-center mb-5">
                        <svg class="w-10 h-10 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m16 2-2.3 2.3a3 3 0 0 0 0 4.2l1.8 1.8a3 3 0 0 0 4.2 0L22 8"></path>
                            <path d="M15 15 3.3 3.3a4.2 4.2 0 0 0 0 6l7.3 7.3c1.7 1.7 4.3 1.7 6 0"></path>
                            <path d="m2 22 5.5-1.5L21.17 6.83a2.82 2.82 0 0 0-4-4L3.5 16.5z"></path>
                            <path d="m18 16 4 4"></path>
                            <path d="m17 21 1-3"></path>
                        </svg>
                    </div>
                    <h2 class="text-xl font-semibold text-on-surface-strong mb-2">{{ __('No favorite meals yet') }}</h2>
                    <p class="text-sm text-on-surface max-w-sm mb-6">
                        {{ __('No favorite meals yet. Browse cooks and save the meals you love!') }}
                    </p>
                    <a
                        href="{{ url('/discover') }}"
                        class="inline-flex items-center gap-2 h-10 px-6 rounded-lg bg-primary hover:bg-primary-hover text-on-primary text-sm font-semibold transition-all duration-200"
                        x-navigate
                    >
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.3-4.3"></path>
                        </svg>
                        {{ __('Discover Cooks') }}
                    </a>
                </div>
            @else
                {{-- Meal cards grid --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    @foreach($favorites as $item)
                        <div
                            x-data="{
                                mealId: {{ $item['meal_id'] }},
                                removing: false,
                                get shouldHide() {
                                    return $root.removedMealId === this.mealId;
                                }
                            }"
                            x-show="!shouldHide"
                            x-transition:leave="transition ease-in duration-300"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="relative group"
                            :class="{ 'pointer-events-none': removing }"
                        >
                            {{-- BR-351: Unavailable badge --}}
                            @if(!$item['is_available'])
                                <div class="absolute top-3 left-3 z-10">
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold bg-danger text-on-danger rounded-full px-2.5 py-1 shadow-sm">
                                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <line x1="12" x2="12" y1="8" y2="12"></line>
                                            <line x1="12" x2="12.01" y1="16" y2="16"></line>
                                        </svg>
                                        {{ __('Unavailable') }}
                                    </span>
                                </div>
                            @endif

                            <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline shadow-card overflow-hidden {{ !$item['is_available'] ? 'opacity-60' : '' }} transition-all duration-300">
                                {{-- Meal image area --}}
                                <div class="relative aspect-[4/3] bg-outline/10 dark:bg-outline/10 overflow-hidden">
                                    @if($item['image'])
                                        <img
                                            src="{{ $item['image'] }}"
                                            alt="{{ app()->getLocale() === 'fr' ? ($item['name_fr'] ?? $item['name_en']) : $item['name_en'] }}"
                                            class="w-full h-full object-cover {{ $item['is_available'] ? 'group-hover:scale-105 transition-transform duration-500' : '' }}"
                                            loading="lazy"
                                        >
                                    @else
                                        <div class="w-full h-full flex items-center justify-center bg-surface-alt dark:bg-surface-alt">
                                            <svg class="w-12 h-12 text-on-surface/20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect>
                                                <circle cx="9" cy="9" r="2"></circle>
                                                <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path>
                                            </svg>
                                        </div>
                                    @endif
                                </div>

                                {{-- Meal info --}}
                                <div class="p-4">
                                    {{-- Meal name --}}
                                    <h3 class="font-semibold text-sm text-on-surface-strong leading-snug line-clamp-2 mb-1">
                                        {{ app()->getLocale() === 'fr' ? ($item['name_fr'] ?? $item['name_en']) : $item['name_en'] }}
                                    </h3>

                                    {{-- Cook name (subtitle) --}}
                                    <p class="text-xs text-on-surface mb-2 flex items-center gap-1">
                                        {{-- ChefHat icon (Lucide xs=14) --}}
                                        <svg class="w-3.5 h-3.5 text-on-surface/60 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M6 13.87A4 4 0 0 1 7.41 6a5.11 5.11 0 0 1 1.05-1.54 5 5 0 0 1 7.08 0A5.11 5.11 0 0 1 16.59 6 4 4 0 0 1 18 13.87V21H6Z"></path>
                                            <line x1="6" x2="18" y1="17" y2="17"></line>
                                        </svg>
                                        <span class="truncate">
                                            {{ app()->getLocale() === 'fr' ? ($item['cook_name_fr'] ?? $item['cook_name_en']) : $item['cook_name_en'] }}
                                        </span>
                                    </p>

                                    {{-- Price --}}
                                    @if($item['price'] !== null)
                                        <p class="text-sm font-bold text-primary mb-3">
                                            {{ number_format($item['price']) }} {{ __('XAF') }}
                                        </p>
                                    @else
                                        <p class="text-sm font-medium text-on-surface/60 italic mb-3">{{ __('Price TBD') }}</p>
                                    @endif

                                    {{-- Actions: visit + remove --}}
                                    <div class="flex items-center gap-2 pt-3 border-t border-outline">
                                        @if($item['is_available'] && $item['meal_url'])
                                            <a
                                                href="{{ $item['meal_url'] }}"
                                                class="flex-1 inline-flex items-center justify-center gap-1.5 h-8 px-3 rounded-lg text-xs font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200"
                                                x-navigate-skip
                                            >
                                                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M15 3h6v6"></path>
                                                    <path d="M10 14 21 3"></path>
                                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                                </svg>
                                                {{ __('View Meal') }}
                                            </a>
                                        @else
                                            <span class="flex-1 inline-flex items-center justify-center h-8 px-3 rounded-lg text-xs font-semibold bg-surface dark:bg-surface text-on-surface/40 border border-outline cursor-not-allowed">
                                                {{ __('Unavailable') }}
                                            </span>
                                        @endif

                                        {{-- BR-348: Remove from favorites --}}
                                        <button
                                            type="button"
                                            @click="removing = true; $action('{{ route('favorites.meals.remove', $item['meal_id']) }}')"
                                            class="h-8 w-8 rounded-lg flex items-center justify-center text-on-surface hover:bg-danger-subtle hover:text-danger border border-outline transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-danger focus:ring-offset-1 shrink-0"
                                            :class="{ 'opacity-50 cursor-wait': removing }"
                                            :disabled="removing"
                                            title="{{ __('Remove from favorites') }}"
                                            aria-label="{{ __('Remove from favorites') }}"
                                        >
                                            <svg x-show="!$fetching()" class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"></path>
                                            </svg>
                                            <svg x-show="$fetching()" x-cloak class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Pagination --}}
                @if($favorites->hasPages())
                    <div class="mt-8 flex justify-center" x-data x-navigate>
                        {{ $favorites->appends(['tab' => 'meals'])->links() }}
                    </div>
                @endif
            @endif
        @endif
    </div>
    @endfragment
</div>
@endsection
