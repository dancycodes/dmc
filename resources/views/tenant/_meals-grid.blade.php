{{--
    F-128: Available Meals Grid Display — Meals Grid Section
    BR-146: Only live + available meals displayed
    BR-148: Each card shows image, name, price, prep time, tags, availability
    BR-152: Responsive grid: 1 col (<640px), 2 cols (640-1024px), 3 cols (>1024px)
    BR-153: Cards link to meal detail view via Gale navigation
    BR-155: Ordered by position then name
    Edge case: 50+ meals use "Load More" pagination (12 per page)
--}}
@php
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $meals */
    $landingService = app(\App\Services\TenantLandingService::class);
@endphp

@if($meals->total() > 0)
    {{-- BR-152: Responsive grid --}}
    <div id="meals-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($meals as $meal)
            @php
                $card = $landingService->buildMealCardData($meal);
            @endphp
            @include('tenant._meal-card', ['card' => $card])
        @endforeach
    </div>

    {{-- Load More pagination --}}
    @if($meals->hasMorePages())
        <div class="mt-8 text-center" x-data>
            <a
                href="{{ $meals->nextPageUrl() }}"
                class="inline-flex items-center gap-2 h-11 px-6 bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline text-on-surface-strong font-medium rounded-lg hover:bg-primary-subtle hover:border-primary hover:text-primary transition-all duration-200"
                x-navigate
            >
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                {{ __('Load More Meals') }}
            </a>
        </div>
    @endif

    {{-- Summary text --}}
    <div class="mt-4 text-center">
        <p class="text-sm text-on-surface/60">
            {{ trans_choice(':count meal|:count meals', $meals->total(), ['count' => $meals->total()]) }}
            {{ __('available') }}
        </p>
    </div>
@else
    {{-- Empty state --}}
    <div class="text-center py-12">
        <div class="w-16 h-16 rounded-full bg-surface-alt dark:bg-surface-alt flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-on-surface opacity-40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="14" rx="1"></rect><rect width="7" height="7" x="3" y="14" rx="1"></rect></svg>
        </div>
        <p class="text-on-surface/60 text-lg font-medium">{{ __('No meals available right now.') }}</p>
        <p class="text-on-surface/40 text-sm mt-1">{{ __('Check back soon!') }}</p>
        @if(isset($sections) && ($sections['schedule']['hasData'] ?? false))
            <a
                href="#schedule"
                class="inline-flex items-center gap-1.5 mt-4 text-sm text-primary hover:text-primary-hover font-medium transition-colors duration-200"
                onclick="document.getElementById('schedule')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); return false;"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path></svg>
                {{ __("View our schedule") }}
            </a>
        @endif
    </div>
@endif
