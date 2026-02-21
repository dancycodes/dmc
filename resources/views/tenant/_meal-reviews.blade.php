{{--
    F-178: Rating & Review Display on Meal
    BR-408: Average rating from all ratings for orders containing this meal
    BR-409: Average displayed as X.X/5 with star visualization
    BR-410: Review count shows total number of ratings
    BR-411: Individual reviews show client name, stars, text (if any), date
    BR-412: Reviews sorted by date descending (newest first)
    BR-413: Paginated with 10 per page
    BR-414: Ratings without review text still appear in the list
    BR-415: Client name privacy — first name + last initial
    BR-416: All text localized via __()
--}}
@fragment('meal-reviews-section')
<div id="meal-reviews-section">
    @php
        $stats = $reviewData['stats'];
        $reviews = $reviewData['reviews'];
        $pagination = $reviewData['pagination'];
        $hasReviews = $stats['total'] > 0;
    @endphp

    {{-- Rating summary header --}}
    <div class="mb-6">
        @if($hasReviews)
            <div class="flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-8">
                {{-- Average rating display --}}
                <div class="flex items-center gap-3">
                    <span class="text-4xl font-bold text-on-surface-strong font-display">
                        {{ number_format($stats['average'], 1) }}
                    </span>
                    <div>
                        {{-- Star visualization --}}
                        <div class="flex items-center gap-0.5">
                            @for($i = 1; $i <= 5; $i++)
                                @php
                                    $filled = $stats['average'] >= $i;
                                    $halfFilled = !$filled && $stats['average'] >= ($i - 0.5);
                                @endphp
                                @if($filled)
                                    <svg class="w-5 h-5 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                @elseif($halfFilled)
                                    <svg class="w-5 h-5 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                        <defs><clipPath id="half-{{ $i }}"><rect x="0" y="0" width="12" height="24"/></clipPath></defs>
                                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" fill="currentColor" clip-path="url(#half-{{ $i }})"/>
                                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" fill="none" stroke="currentColor" stroke-width="1.5"/>
                                    </svg>
                                @else
                                    <svg class="w-5 h-5 text-on-surface/20 dark:text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                @endif
                            @endfor
                        </div>
                        <p class="text-sm text-on-surface mt-0.5">
                            {{ trans_choice(':count review|:count reviews', $stats['total'], ['count' => $stats['total']]) }}
                        </p>
                    </div>
                </div>

                {{-- Star distribution bars --}}
                <div class="flex-1 max-w-xs">
                    @foreach($stats['distribution'] as $starLevel => $count)
                        @php
                            $percentage = $stats['total'] > 0 ? round(($count / $stats['total']) * 100) : 0;
                        @endphp
                        <div class="flex items-center gap-2 text-sm">
                            <span class="w-3 text-on-surface font-medium text-right shrink-0">{{ $starLevel }}</span>
                            <svg class="w-3.5 h-3.5 text-warning shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                            <div class="flex-1 h-2 bg-surface-alt dark:bg-surface-alt rounded-full overflow-hidden border border-outline/30">
                                <div class="h-full bg-warning rounded-full transition-all duration-300" style="width: {{ $percentage }}%"></div>
                            </div>
                            <span class="w-8 text-on-surface/60 text-right shrink-0">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            {{-- Empty state --}}
            <div class="text-center py-8 bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline">
                <svg class="w-10 h-10 mx-auto text-on-surface/30 mb-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                <p class="text-sm font-medium text-on-surface/60">{{ __('No reviews yet') }}</p>
                <p class="text-xs text-on-surface/40 mt-1">{{ __('Be the first to try this meal!') }}</p>
            </div>
        @endif
    </div>

    {{-- Individual reviews list --}}
    @if($hasReviews)
        <div class="space-y-4" id="reviews-list">
            @foreach($reviews as $review)
                <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            {{-- Avatar placeholder --}}
                            <div class="w-9 h-9 rounded-full bg-primary-subtle flex items-center justify-center shrink-0">
                                <span class="text-sm font-semibold text-primary">
                                    {{ mb_strtoupper(mb_substr($review['clientName'], 0, 1)) }}
                                </span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-on-surface-strong truncate">
                                    {{ $review['clientName'] }}
                                </p>
                                {{-- Star rating --}}
                                <div class="flex items-center gap-0.5 mt-0.5">
                                    @for($i = 1; $i <= 5; $i++)
                                        @if($i <= $review['stars'])
                                            <svg class="w-3.5 h-3.5 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                        @else
                                            <svg class="w-3.5 h-3.5 text-on-surface/20 dark:text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                        @endif
                                    @endfor
                                </div>
                            </div>
                        </div>
                        {{-- Date --}}
                        <span class="text-xs text-on-surface/50 shrink-0 whitespace-nowrap" title="{{ $review['date'] }}">
                            {{ $review['relativeDate'] }}
                        </span>
                    </div>

                    {{-- Review text --}}
                    @if($review['review'])
                        @php
                            $isLongReview = mb_strlen($review['review']) > 300;
                        @endphp
                        <div class="mt-3 text-sm text-on-surface leading-relaxed"
                             x-data="{ expanded: false }"
                             :class="{ 'line-clamp-4': !expanded && {{ $isLongReview ? 'true' : 'false' }} }">
                            <p class="whitespace-pre-line">{{ $review['review'] }}</p>
                            @if($isLongReview)
                                <button
                                    @click="expanded = !expanded"
                                    class="mt-1 text-xs font-medium text-primary hover:text-primary-hover transition-colors duration-200 cursor-pointer"
                                >
                                    <span x-show="!expanded">{{ __('Read more') }}</span>
                                    <span x-show="expanded" x-cloak>{{ __('Show less') }}</span>
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Load more button --}}
        @if($pagination['hasMore'])
            <div class="mt-6 text-center" x-data>
                <button
                    @click="$navigate('{{ route('tenant.meal.reviews', ['meal' => $meal->id, 'review_page' => $pagination['currentPage'] + 1]) }}', { key: 'reviews', replace: true })"
                    class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-primary bg-primary-subtle hover:bg-primary/10 rounded-lg transition-colors duration-200 cursor-pointer"
                >
                    <span x-show="!$fetching()">{{ __('Load more reviews') }}</span>
                    <span x-show="$fetching()" x-cloak class="inline-flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        {{ __('Loading...') }}
                    </span>
                </button>
            </div>
        @endif
    @endif
</div>
@endfragment
