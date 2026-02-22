{{--
    F-130: Ratings Summary Display
    BR-167: Overall rating = arithmetic average of all review scores, 1 decimal place
    BR-168: Star distribution bar chart: counts per star level (5 → 1)
    BR-169: 5 most recent reviews sorted by creation date descending
    BR-170: Each review: reviewer first name + last initial, date, stars, text
    BR-171: Review text truncated to 3 lines with "Read more" toggle
    BR-172: "See all reviews" link only when total > 5
    BR-173: Empty state for zero reviews
    BR-174: All text localized via __()
    BR-175: Reviewer identity partially anonymized (first name + last initial)
--}}
@php
    $hasReviews = $ratingsDisplay['hasReviews'];
    $average = $ratingsDisplay['average'];
    $totalCount = $ratingsDisplay['totalCount'];
    $distribution = $ratingsDisplay['distribution'];
    $recentReviews = $ratingsDisplay['recentReviews'];
    $showSeeAll = $ratingsDisplay['showSeeAll'];
@endphp

@if($hasReviews)
    {{-- ============================================ --}}
    {{-- Average rating + star distribution + reviews  --}}
    {{-- ============================================ --}}

    {{-- Rating overview: large number + stars + bar chart --}}
    <div class="flex flex-col sm:flex-row sm:items-start gap-6 sm:gap-10 mb-8">

        {{-- Left: Large average + star row --}}
        <div class="flex flex-row sm:flex-col items-center sm:items-start gap-4 sm:gap-2 shrink-0">
            <span class="text-5xl sm:text-6xl font-bold font-display text-on-surface-strong leading-none">
                {{ number_format($average, 1) }}
            </span>
            <div>
                {{-- Star visualization --}}
                <div class="flex items-center gap-0.5">
                    @for($i = 1; $i <= 5; $i++)
                        @php
                            $filled  = $average >= $i;
                            $partial = !$filled && $average >= ($i - 0.5);
                        @endphp
                        @if($filled)
                            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                        @elseif($partial)
                            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
                                <defs><clipPath id="half-avg-{{ $i }}"><rect x="0" y="0" width="12" height="24"/></clipPath></defs>
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" fill="currentColor" clip-path="url(#half-avg-{{ $i }})"/>
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" fill="none" stroke="currentColor" stroke-width="1.5"/>
                            </svg>
                        @else
                            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-on-surface/20 dark:text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                        @endif
                    @endfor
                </div>
                <p class="text-sm text-on-surface mt-1">
                    {{ trans_choice(':count review|:count reviews', $totalCount, ['count' => number_format($totalCount)]) }}
                </p>
            </div>
        </div>

        {{-- Right: Star distribution bar chart --}}
        {{-- BR-168: Bars use tenant theme accent color (bg-primary) --}}
        <div class="flex-1 min-w-0 space-y-1.5">
            @foreach($distribution as $starLevel => $count)
                @php
                    $percentage = $totalCount > 0 ? round(($count / $totalCount) * 100) : 0;
                @endphp
                <div class="flex items-center gap-2 text-sm">
                    <span class="w-3 font-medium text-on-surface text-right shrink-0">{{ $starLevel }}</span>
                    <svg class="w-3.5 h-3.5 text-warning shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                    <div class="flex-1 h-2.5 bg-surface-alt dark:bg-surface-alt rounded-full overflow-hidden border border-outline/30 dark:border-outline/30">
                        <div
                            class="h-full bg-primary rounded-full transition-all duration-500"
                            style="width: {{ $percentage }}%"
                            role="progressbar"
                            aria-valuenow="{{ $count }}"
                            aria-valuemin="0"
                            aria-valuemax="{{ $totalCount }}"
                        ></div>
                    </div>
                    <span class="w-8 text-on-surface/60 dark:text-on-surface/50 text-right text-xs shrink-0">{{ $count }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ============================================ --}}
    {{-- BR-169: 5 most recent reviews               --}}
    {{-- ============================================ --}}
    <div class="space-y-4">
        @foreach($recentReviews as $review)
            <div class="bg-surface dark:bg-surface rounded-xl border border-outline dark:border-outline p-4 sm:p-5 shadow-card">
                <div class="flex items-start justify-between gap-3">
                    {{-- Reviewer avatar + name + stars --}}
                    <div class="flex items-center gap-3 min-w-0">
                        {{-- Avatar initial circle --}}
                        <div class="w-9 h-9 rounded-full bg-primary-subtle dark:bg-primary-subtle flex items-center justify-center shrink-0">
                            <span class="text-sm font-semibold text-primary dark:text-primary">
                                {{ mb_strtoupper(mb_substr($review['clientName'], 0, 1)) }}
                            </span>
                        </div>
                        <div class="min-w-0">
                            {{-- BR-170/BR-175: First name + last initial --}}
                            <p class="text-sm font-semibold text-on-surface-strong dark:text-on-surface-strong truncate">
                                {{ $review['clientName'] }}
                            </p>
                            {{-- BR-170: Stars per review --}}
                            <div class="flex items-center gap-0.5 mt-0.5">
                                @for($i = 1; $i <= 5; $i++)
                                    @if($i <= $review['stars'])
                                        <svg class="w-3.5 h-3.5 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                    @else
                                        <svg class="w-3.5 h-3.5 text-on-surface/20 dark:text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                    @endif
                                @endfor
                            </div>
                        </div>
                    </div>
                    {{-- BR-170: Date --}}
                    <span class="text-xs text-on-surface/50 dark:text-on-surface/40 shrink-0 whitespace-nowrap" title="{{ $review['date'] }}">
                        {{ $review['relativeDate'] }}
                    </span>
                </div>

                {{-- BR-171: Review text, truncated to 3 lines with "Read more" toggle --}}
                @if($review['review'])
                    @php
                        $isLong = mb_strlen($review['review']) > 200;
                    @endphp
                    <div class="mt-3"
                         x-data="{ expanded: false }"
                    >
                        <p class="text-sm text-on-surface dark:text-on-surface leading-relaxed whitespace-pre-line"
                           :class="{ 'line-clamp-3': !expanded }"
                        >{{ $review['review'] }}</p>
                        @if($isLong)
                            <button
                                x-on:click="expanded = !expanded"
                                class="mt-1 text-xs font-medium text-primary dark:text-primary hover:text-primary-hover dark:hover:text-primary-hover transition-colors duration-200 cursor-pointer"
                            >
                                <span x-show="!expanded">{{ __('Read more') }}</span>
                                <span x-show="expanded" x-cloak>{{ __('Read less') }}</span>
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- BR-172: "See all reviews" — only when total > 5 --}}
    @if($showSeeAll)
        <div class="mt-6 text-center">
            <a
                href="{{ route('tenant.reviews') }}"
                class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-primary dark:text-primary bg-primary-subtle dark:bg-primary-subtle hover:bg-primary/10 dark:hover:bg-primary/10 rounded-lg border border-primary/20 dark:border-primary/20 transition-colors duration-200"
            >
                {{ __('See all :count reviews', ['count' => number_format($totalCount)]) }}
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
            </a>
        </div>
    @endif

@else
    {{-- BR-173: Empty state when zero reviews --}}
    <div class="text-center py-12 sm:py-16">
        <div class="w-16 h-16 rounded-full bg-warning-subtle dark:bg-warning-subtle flex items-center justify-center mx-auto mb-4 border border-warning/20 dark:border-warning/20">
            <svg class="w-8 h-8 text-warning dark:text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
        </div>
        <h3 class="text-lg font-semibold text-on-surface-strong dark:text-on-surface-strong">
            {{ __('No reviews yet') }}
        </h3>
        <p class="mt-2 text-sm text-on-surface dark:text-on-surface max-w-xs mx-auto">
            {{ __('Be the first to order and leave a review!') }}
        </p>
    </div>
@endif
