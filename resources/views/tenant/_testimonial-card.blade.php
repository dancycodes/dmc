{{--
    F-182: Testimonial Card
    -----------------------
    Reusable testimonial card displayed in the landing page carousel and grid.

    BR-449: Shows client name (first name + last initial), date, text.
    Edge case: Very long text (near 1000 chars) — truncated with "Read more" toggle.

    Variables expected:
        $testimonial: Testimonial (with user relationship loaded)
        $isCarousel: bool — true = full-width mobile card, false = grid card
--}}
<div
    class="bg-surface dark:bg-surface rounded-2xl border border-outline dark:border-outline shadow-card p-5 sm:p-6 flex flex-col gap-3 animate-fade-in {{ $isCarousel ? 'mx-4' : '' }}"
    x-data="{ expanded: false }"
>
    {{-- Quotation mark icon --}}
    <svg class="w-7 h-7 text-primary/25 dark:text-primary/25 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path d="M11.192 15.757c0-.88-.23-1.618-.69-2.217-.326-.412-.768-.683-1.327-.812-.55-.128-1.07-.137-1.54-.028-.16-.95.1-1.95.78-3-.86.248-1.49.64-1.9 1.18-.41.54-.63 1.14-.63 1.8 0 .84.29 1.54.87 2.1.57.56 1.27.84 2.1.84.8 0 1.48-.28 2.04-.84.57-.57.85-1.28.85-2.16.01-.05.01-.1.01-.15zm7.43 0c0-.88-.23-1.618-.69-2.217-.326-.42-.77-.692-1.327-.812-.55-.128-1.07-.137-1.54-.028-.16-.95.1-1.95.78-3-.86.248-1.49.64-1.9 1.18-.41.54-.63 1.14-.63 1.8 0 .84.29 1.54.87 2.1.57.56 1.27.84 2.1.84.8 0 1.48-.28 2.04-.84.57-.57.85-1.28.85-2.16.01-.05.01-.1.01-.15z"/>
    </svg>

    {{-- Testimonial text with "Read more" for long texts --}}
    @php
        $textLength = mb_strlen($testimonial->text);
        $isLong = $textLength > 300;
        $truncated = $isLong ? mb_substr($testimonial->text, 0, 300) : $testimonial->text;
    @endphp

    <div class="flex-1">
        @if($isLong)
            <p class="text-sm sm:text-base text-on-surface dark:text-on-surface leading-relaxed italic" x-show="!expanded">
                {{ $truncated }}<span class="text-on-surface/40">...</span>
            </p>
            <p class="text-sm sm:text-base text-on-surface dark:text-on-surface leading-relaxed italic" x-show="expanded" x-cloak>
                {{ $testimonial->text }}
            </p>
            <button
                @click="expanded = !expanded"
                class="text-xs text-primary hover:text-primary-hover font-medium mt-1.5 transition-colors duration-150"
                x-text="expanded ? '{{ __('Show less') }}' : '{{ __('Read more') }}'"
            ></button>
        @else
            <p class="text-sm sm:text-base text-on-surface dark:text-on-surface leading-relaxed italic">
                {{ $testimonial->text }}
            </p>
        @endif
    </div>

    {{-- Client info footer --}}
    <div class="flex items-center gap-3 pt-3 border-t border-outline/50 dark:border-outline/50 mt-auto">
        {{-- Avatar initial --}}
        <span class="w-9 h-9 rounded-full bg-primary-subtle flex items-center justify-center shrink-0 text-xs font-bold text-primary">
            {{ mb_strtoupper(mb_substr($testimonial->user?->name ?? 'F', 0, 1)) }}
        </span>

        <div class="min-w-0">
            {{-- BR-449: First name + last initial --}}
            <p class="text-sm font-semibold text-on-surface-strong dark:text-on-surface-strong leading-tight">
                {{ $testimonial->getClientDisplayName() }}
            </p>
            {{-- BR-449: Date --}}
            <p class="text-xs text-on-surface/50 dark:text-on-surface/50">
                {{ $testimonial->approved_at?->format('M j, Y') ?? $testimonial->created_at->format('M j, Y') }}
            </p>
        </div>
    </div>
</div>
