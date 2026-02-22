{{--
    F-130: Ratings Summary Display — All Reviews Page
    BR-172: Destination of "See all reviews" link from the landing page
    BR-169: Reviews sorted by creation date descending
    BR-174: All text localized via __()
--}}
@extends('layouts.tenant-public')

@section('title', __('Ratings & Reviews') . ' — ' . ($tenant?->name ?? ''))

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">

    {{-- Back link --}}
    <div class="mb-6">
        <a
            href="{{ route('home') }}"
            class="inline-flex items-center gap-1.5 text-sm font-medium text-on-surface hover:text-primary dark:hover:text-primary transition-colors duration-200"
        >
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
            {{ __('Back to :name', ['name' => $tenant?->name ?? __('Home')]) }}
        </a>
    </div>

    {{-- Page heading --}}
    <div class="mb-8">
        <h1 class="text-2xl sm:text-3xl font-display font-bold text-on-surface-strong dark:text-on-surface-strong">
            {{ __('Ratings & Reviews') }}
        </h1>
        @if($reviewsData['hasReviews'])
            <p class="mt-1 text-sm text-on-surface dark:text-on-surface">
                {{ trans_choice(':count review|:count reviews', $reviewsData['totalCount'], ['count' => number_format($reviewsData['totalCount'])]) }}
            </p>
        @endif
    </div>

    @if($reviewsData['hasReviews'])
        {{-- Rating overview: average + distribution --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 sm:p-6 mb-8 shadow-card">
            <div class="flex flex-col sm:flex-row sm:items-start gap-6">
                {{-- Average --}}
                <div class="flex flex-row sm:flex-col items-center sm:items-start gap-4 sm:gap-2 shrink-0">
                    <span class="text-5xl font-bold font-display text-on-surface-strong dark:text-on-surface-strong leading-none">
                        {{ number_format($reviewsData['average'], 1) }}
                    </span>
                    <div>
                        <div class="flex items-center gap-0.5">
                            @for($i = 1; $i <= 5; $i++)
                                @php
                                    $filled  = $reviewsData['average'] >= $i;
                                    $partial = !$filled && $reviewsData['average'] >= ($i - 0.5);
                                @endphp
                                @if($filled)
                                    <svg class="w-5 h-5 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                @elseif($partial)
                                    <svg class="w-5 h-5 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
                                        <defs><clipPath id="half-all-{{ $i }}"><rect x="0" y="0" width="12" height="24"/></clipPath></defs>
                                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" fill="currentColor" clip-path="url(#half-all-{{ $i }})"/>
                                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" fill="none" stroke="currentColor" stroke-width="1.5"/>
                                    </svg>
                                @else
                                    <svg class="w-5 h-5 text-on-surface/20 dark:text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                @endif
                            @endfor
                        </div>
                        <p class="text-sm text-on-surface dark:text-on-surface mt-1">
                            {{ __('out of 5') }}
                        </p>
                    </div>
                </div>

                {{-- Star distribution bars --}}
                <div class="flex-1 min-w-0 space-y-1.5">
                    @foreach($reviewsData['distribution'] as $starLevel => $count)
                        @php
                            $percentage = $reviewsData['totalCount'] > 0
                                ? round(($count / $reviewsData['totalCount']) * 100)
                                : 0;
                        @endphp
                        <div class="flex items-center gap-2 text-sm">
                            <span class="w-3 font-medium text-on-surface dark:text-on-surface text-right shrink-0">{{ $starLevel }}</span>
                            <svg class="w-3.5 h-3.5 text-warning shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                            <div class="flex-1 h-2.5 bg-surface dark:bg-surface rounded-full overflow-hidden border border-outline/30 dark:border-outline/30">
                                <div
                                    class="h-full bg-primary rounded-full"
                                    style="width: {{ $percentage }}%"
                                    role="progressbar"
                                    aria-valuenow="{{ $count }}"
                                    aria-valuemin="0"
                                    aria-valuemax="{{ $reviewsData['totalCount'] }}"
                                ></div>
                            </div>
                            <span class="w-8 text-on-surface/60 dark:text-on-surface/50 text-right text-xs shrink-0">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Reviews list --}}
        @fragment('all-reviews-content')
        <div id="all-reviews-content" x-data>
            <div class="space-y-4">
                @foreach($reviewsData['reviews'] as $review)
                    <div class="bg-surface dark:bg-surface rounded-xl border border-outline dark:border-outline p-4 sm:p-5 shadow-card">
                        <div class="flex items-start justify-between gap-3">
                            {{-- Avatar + name + stars --}}
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-9 h-9 rounded-full bg-primary-subtle dark:bg-primary-subtle flex items-center justify-center shrink-0">
                                    <span class="text-sm font-semibold text-primary dark:text-primary">
                                        {{ mb_strtoupper(mb_substr($review['clientName'], 0, 1)) }}
                                    </span>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-on-surface-strong dark:text-on-surface-strong truncate">
                                        {{ $review['clientName'] }}
                                    </p>
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
                            <span class="text-xs text-on-surface/50 dark:text-on-surface/40 shrink-0 whitespace-nowrap" title="{{ $review['date'] }}">
                                {{ $review['relativeDate'] }}
                            </span>
                        </div>

                        {{-- Review text with Read more toggle --}}
                        @if($review['review'])
                            @php
                                $isLong = mb_strlen($review['review']) > 200;
                            @endphp
                            <div class="mt-3" x-data="{ expanded: false }">
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

            {{-- Pagination --}}
            @if($reviewsData['pagination']['lastPage'] > 1)
                <div class="mt-8 flex items-center justify-center gap-2 flex-wrap">
                    {{-- Previous page --}}
                    @if($reviewsData['pagination']['currentPage'] > 1)
                        <a
                            href="{{ route('tenant.reviews', ['page' => $reviewsData['pagination']['currentPage'] - 1]) }}"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-on-surface dark:text-on-surface bg-surface-alt dark:bg-surface-alt hover:bg-primary-subtle dark:hover:bg-primary-subtle border border-outline dark:border-outline rounded-lg transition-colors duration-200"
                        >
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
                            {{ __('Previous') }}
                        </a>
                    @endif

                    {{-- Page indicator --}}
                    <span class="px-4 py-2 text-sm text-on-surface dark:text-on-surface">
                        {{ __('Page :current of :total', ['current' => $reviewsData['pagination']['currentPage'], 'total' => $reviewsData['pagination']['lastPage']]) }}
                    </span>

                    {{-- Next page --}}
                    @if($reviewsData['pagination']['hasMore'])
                        <a
                            href="{{ route('tenant.reviews', ['page' => $reviewsData['pagination']['currentPage'] + 1]) }}"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-on-surface dark:text-on-surface bg-surface-alt dark:bg-surface-alt hover:bg-primary-subtle dark:hover:bg-primary-subtle border border-outline dark:border-outline rounded-lg transition-colors duration-200"
                        >
                            {{ __('Next') }}
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                        </a>
                    @endif
                </div>
            @endif
        </div>
        @endfragment

    @else
        {{-- Empty state --}}
        <div class="text-center py-16">
            <div class="w-16 h-16 rounded-full bg-warning-subtle dark:bg-warning-subtle flex items-center justify-center mx-auto mb-4 border border-warning/20 dark:border-warning/20">
                <svg class="w-8 h-8 text-warning dark:text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
            </div>
            <h2 class="text-xl font-semibold text-on-surface-strong dark:text-on-surface-strong">
                {{ __('No reviews yet') }}
            </h2>
            <p class="mt-2 text-sm text-on-surface dark:text-on-surface max-w-xs mx-auto">
                {{ __('Be the first to order and leave a review!') }}
            </p>
            <div class="mt-6">
                <a
                    href="{{ route('home') }}"
                    class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-on-primary bg-primary hover:bg-primary-hover rounded-lg shadow-card transition-colors duration-200"
                >
                    {{ __('View Meals') }}
                </a>
            </div>
        </div>
    @endif

</div>

{{-- F-134: WhatsApp FAB always visible --}}
@include('tenant._whatsapp-fab', ['cookProfile' => ['whatsapp' => $tenant?->whatsapp, 'name' => $tenant?->name]])
@endsection
