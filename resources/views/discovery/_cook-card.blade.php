{{--
    Cook Card Component (F-066 placeholder, expanded in F-067)
    -----------------------------------------------------------
    Displays a single cook's card in the discovery grid.
    Shows: cook name, description, cover placeholder, link to tenant domain.
--}}
@php
    $cookName = $tenant->name;
    $description = $tenant->description;
    $initial = mb_substr($tenant->name, 0, 1);
    $tenantUrl = 'https://' . $tenant->slug . '.' . parse_url(config('app.url'), PHP_URL_HOST);
@endphp

<article class="group bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-card hover:shadow-dropdown transition-all duration-200 overflow-hidden">
    {{-- Cover Image Placeholder --}}
    <div class="relative h-36 sm:h-40 bg-primary-subtle dark:bg-primary-subtle flex items-center justify-center overflow-hidden">
        <div class="w-16 h-16 rounded-full bg-primary/20 dark:bg-primary/30 flex items-center justify-center">
            {{-- UtensilsCrossed icon (Lucide) --}}
            <svg class="w-8 h-8 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m16 2-2.3 2.3a3 3 0 0 0 0 4.2l1.8 1.8a3 3 0 0 0 4.2 0L22 8"></path><path d="M15 15 3.3 3.3a4.2 4.2 0 0 0 0 6l7.3 7.3c1.7 1.7 4.3 1.7 6 0"></path><path d="m2 22 5.5-1.5L21.17 6.83a2.82 2.82 0 0 0-4-4L3.5 16.5z"></path><path d="m18 16 4 4"></path><path d="m17 21 1-3"></path></svg>
        </div>
        {{-- Gradient overlay for depth --}}
        <div class="absolute inset-0 bg-gradient-to-t from-black/10 to-transparent"></div>
    </div>

    {{-- Card Body --}}
    <div class="p-4 sm:p-5">
        {{-- Cook Avatar & Name --}}
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-on-primary font-bold text-sm shrink-0">
                {{ strtoupper($initial) }}
            </div>
            <div class="min-w-0 flex-1">
                <h3 class="font-semibold text-on-surface-strong text-base truncate group-hover:text-primary transition-colors duration-200" title="{{ $cookName }}">
                    {{ $cookName }}
                </h3>
            </div>
        </div>

        {{-- Description --}}
        @if($description)
            <p class="text-sm text-on-surface line-clamp-2 mb-3">
                {{ $description }}
            </p>
        @else
            <p class="text-sm text-on-surface/60 italic mb-3">
                {{ __('Delicious home-cooked meals') }}
            </p>
        @endif

        {{-- Card Footer: Rating placeholder + Visit button --}}
        <div class="flex items-center justify-between pt-3 border-t border-outline dark:border-outline">
            {{-- Rating placeholder (F-067 will expand) --}}
            <div class="flex items-center gap-1 text-sm text-on-surface">
                {{-- Star icon (Lucide) --}}
                <svg class="w-4 h-4 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                <span class="text-on-surface-strong font-medium">{{ __('New') }}</span>
            </div>

            {{-- Visit Link --}}
            <a
                href="{{ $tenantUrl }}"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:text-primary-hover transition-colors duration-200"
            >
                {{ __('Visit') }}
                {{-- ArrowRight icon (Lucide) --}}
                <svg class="w-4 h-4 group-hover:translate-x-0.5 transition-transform duration-200" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
            </a>
        </div>
    </div>
</article>
