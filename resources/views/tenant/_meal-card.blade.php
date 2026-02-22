{{--
    F-128: Available Meals Grid Display — Meal Card Component
    BR-148: Displays primary image, name, starting price, prep time, tags, availability
    BR-149: Starting price = min component price, displayed as "from X XAF"
    BR-150: Prep time badge with clock icon
    BR-151: Tags as chips, max 3 visible, "+N more" overflow
    BR-153: Clicking navigates to meal detail view via Gale
    BR-154: Name shown in user's current locale
--}}
@php
    /** @var array $card — built by TenantLandingService::buildMealCardData() */
@endphp

<a
    href="{{ url('/meals/' . $card['id']) }}"
    class="group block bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline overflow-hidden shadow-card hover:shadow-lg transition-all duration-300 hover:-translate-y-1"
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
            <div class="absolute top-2 right-2 flex items-center gap-1 bg-surface/90 dark:bg-surface/90 backdrop-blur-sm text-on-surface-strong text-xs font-medium rounded-full px-2.5 py-1 shadow-sm">
                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                {{ __('Est. prep: :time', ['time' => $card['prepTime']]) }}
            </div>
        @endif

        {{-- Availability / Sold Out indicator --}}
        @if($card['allComponentsUnavailable'])
            <div class="absolute top-2 left-2 flex items-center gap-1 bg-danger/90 text-on-danger text-xs font-semibold rounded-full px-2.5 py-1 shadow-sm">
                {{ __('Sold Out') }}
            </div>
        @else
            <div class="absolute top-2 left-2 flex items-center gap-1 bg-surface/90 dark:bg-surface/90 backdrop-blur-sm text-xs font-medium rounded-full px-2.5 py-1 shadow-sm">
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
