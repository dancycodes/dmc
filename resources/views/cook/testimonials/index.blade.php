{{--
    F-181: Cook Testimonial Moderation
    ------------------------------------
    Allows the cook (or manager with manage-testimonials) to review, approve,
    reject, and un-approve client testimonials.

    UI/UX:
    - Three tabs: Pending, Approved, Rejected with count badges
    - Card per testimonial: client name, date, text (expandable), status badge
    - Approve button (green, checkmark) on pending/rejected
    - Reject button (red, X) on pending/approved
    - Un-approve button (outline gray) on approved
    - No edit capability — testimonial text is read-only (BR-440)
    - All interactions via Gale (no page reloads)
    - Mobile-first card layout
    - Empty state per tab
--}}
@extends('layouts.cook-dashboard')

@section('title', __('Testimonials'))

@section('content')
<div
    class="px-4 sm:px-6 lg:px-8 py-6"
    x-data="{
        activeTab: '{{ $activeTab }}',
        expandedCards: {}
    }"
>
    {{-- Page Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-display font-bold text-on-surface-strong">{{ __('Testimonials') }}</h1>
        <p class="text-sm text-on-surface/60 mt-1">{{ __('Review and moderate client testimonials for your page') }}</p>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-3 gap-3 sm:gap-4 mb-6">
        {{-- Pending --}}
        <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline p-4">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-warning-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 8v4"></path><path d="M12 16h.01"></path></svg>
                </span>
                <div>
                    <p class="text-xs text-on-surface/50 font-medium">{{ __('Pending') }}</p>
                    <p class="text-xl font-bold text-warning">{{ $counts['pending'] }}</p>
                </div>
            </div>
        </div>

        {{-- Approved --}}
        <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline p-4">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-success-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                </span>
                <div>
                    <p class="text-xs text-on-surface/50 font-medium">{{ __('Approved') }}</p>
                    <p class="text-xl font-bold text-success">{{ $counts['approved'] }}</p>
                </div>
            </div>
        </div>

        {{-- Rejected --}}
        <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline p-4">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-danger-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                </span>
                <div>
                    <p class="text-xs text-on-surface/50 font-medium">{{ __('Rejected') }}</p>
                    <p class="text-xl font-bold text-danger">{{ $counts['rejected'] }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Tab Navigation --}}
    <div class="border-b border-outline dark:border-outline mb-6">
        <nav class="flex gap-0 -mb-px overflow-x-auto" aria-label="{{ __('Testimonial status tabs') }}">
            {{-- Pending Tab --}}
            <a
                href="{{ url('/dashboard/testimonials?tab=pending') }}"
                @click.prevent="activeTab = 'pending'; $navigate('/dashboard/testimonials?tab=pending', { key: 'testimonials', merge: true, replace: true })"
                :class="activeTab === 'pending'
                    ? 'border-b-2 border-primary text-primary font-semibold'
                    : 'border-b-2 border-transparent text-on-surface/60 hover:text-on-surface hover:border-outline-strong'"
                class="flex items-center gap-2 px-4 py-3 text-sm whitespace-nowrap transition-colors duration-150"
            >
                {{ __('Pending') }}
                @if($counts['pending'] > 0)
                    <span class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-xs font-bold bg-warning text-on-primary">
                        {{ $counts['pending'] }}
                    </span>
                @endif
            </a>

            {{-- Approved Tab --}}
            <a
                href="{{ url('/dashboard/testimonials?tab=approved') }}"
                @click.prevent="activeTab = 'approved'; $navigate('/dashboard/testimonials?tab=approved', { key: 'testimonials', merge: true, replace: true })"
                :class="activeTab === 'approved'
                    ? 'border-b-2 border-primary text-primary font-semibold'
                    : 'border-b-2 border-transparent text-on-surface/60 hover:text-on-surface hover:border-outline-strong'"
                class="flex items-center gap-2 px-4 py-3 text-sm whitespace-nowrap transition-colors duration-150"
            >
                {{ __('Approved') }}
                @if($counts['approved'] > 0)
                    <span class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-xs font-bold bg-success text-on-primary">
                        {{ $counts['approved'] }}
                    </span>
                @endif
            </a>

            {{-- Rejected Tab --}}
            <a
                href="{{ url('/dashboard/testimonials?tab=rejected') }}"
                @click.prevent="activeTab = 'rejected'; $navigate('/dashboard/testimonials?tab=rejected', { key: 'testimonials', merge: true, replace: true })"
                :class="activeTab === 'rejected'
                    ? 'border-b-2 border-primary text-primary font-semibold'
                    : 'border-b-2 border-transparent text-on-surface/60 hover:text-on-surface hover:border-outline-strong'"
                class="flex items-center gap-2 px-4 py-3 text-sm whitespace-nowrap transition-colors duration-150"
            >
                {{ __('Rejected') }}
                @if($counts['rejected'] > 0)
                    <span class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-xs font-bold bg-surface-alt text-on-surface">
                        {{ $counts['rejected'] }}
                    </span>
                @endif
            </a>
        </nav>
    </div>

    {{-- Testimonials Content (fragment target) --}}
    @fragment('testimonials-content')
    <div id="testimonials-content">
        @if($testimonials->isEmpty())
            {{-- Empty State --}}
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <span class="w-16 h-16 rounded-full bg-surface-alt flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"></path><path d="M8 12h.01"></path><path d="M12 12h.01"></path><path d="M16 12h.01"></path></svg>
                </span>
                <p class="text-base font-medium text-on-surface-strong mb-1">
                    @if($activeTab === 'pending')
                        {{ __('No pending testimonials') }}
                    @elseif($activeTab === 'approved')
                        {{ __('No approved testimonials yet') }}
                    @else
                        {{ __('No rejected testimonials') }}
                    @endif
                </p>
                <p class="text-sm text-on-surface/50">
                    @if($activeTab === 'pending')
                        {{ __('New testimonials from clients will appear here for review.') }}
                    @elseif($activeTab === 'approved')
                        {{ __('Approved testimonials will appear on your landing page.') }}
                    @else
                        {{ __('Rejected testimonials are not shown publicly.') }}
                    @endif
                </p>
            </div>
        @else
            {{-- Testimonial Cards --}}
            <div class="space-y-4">
                @foreach($testimonials as $testimonial)
                    <div
                        class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline p-5 animate-fade-in"
                        id="testimonial-{{ $testimonial->id }}"
                    >
                        {{-- Card Header --}}
                        <div class="flex items-start justify-between gap-4 mb-3">
                            <div class="flex items-center gap-3 min-w-0">
                                {{-- Avatar --}}
                                <span class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center shrink-0 text-sm font-bold text-primary">
                                    {{ mb_strtoupper(mb_substr($testimonial->user?->name ?? 'F', 0, 1)) }}
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-on-surface-strong truncate">
                                        {{ $testimonial->user?->name ?? __('Former User') }}
                                    </p>
                                    <p class="text-xs text-on-surface/50">
                                        {{ $testimonial->created_at->format('M j, Y') }}
                                    </p>
                                </div>
                            </div>

                            {{-- Status Badge --}}
                            @if($testimonial->status === \App\Models\Testimonial::STATUS_PENDING)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-warning-subtle text-warning shrink-0">
                                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 8v4"></path><path d="M12 16h.01"></path></svg>
                                    {{ __('Pending Review') }}
                                </span>
                            @elseif($testimonial->status === \App\Models\Testimonial::STATUS_APPROVED)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-success-subtle text-success shrink-0">
                                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    {{ __('Approved') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-danger-subtle text-danger shrink-0">
                                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                    {{ __('Rejected') }}
                                </span>
                            @endif
                        </div>

                        {{-- Testimonial Text --}}
                        <div
                            class="mb-4"
                            x-data="{ expanded: false }"
                        >
                            @if(mb_strlen($testimonial->text) > 200)
                                <p
                                    class="text-sm text-on-surface leading-relaxed"
                                    x-show="!expanded"
                                >
                                    {{ mb_substr($testimonial->text, 0, 200) }}<span class="text-on-surface/40">...</span>
                                </p>
                                <p
                                    class="text-sm text-on-surface leading-relaxed"
                                    x-show="expanded"
                                    x-cloak
                                >
                                    {{ $testimonial->text }}
                                </p>
                                <button
                                    @click="expanded = !expanded"
                                    class="text-xs text-primary hover:text-primary-hover font-medium mt-1 transition-colors duration-150"
                                    x-text="expanded ? '{{ __('Show less') }}' : '{{ __('Read more') }}'"
                                ></button>
                            @else
                                <p class="text-sm text-on-surface leading-relaxed">{{ $testimonial->text }}</p>
                            @endif
                        </div>

                        {{-- Approved/Rejected Timestamps --}}
                        @if($testimonial->approved_at)
                            <p class="text-xs text-on-surface/40 mb-3">
                                {{ __('Approved on') }} {{ $testimonial->approved_at->format('M j, Y') }}
                            </p>
                        @elseif($testimonial->rejected_at)
                            <p class="text-xs text-on-surface/40 mb-3">
                                {{ __('Rejected on') }} {{ $testimonial->rejected_at->format('M j, Y') }}
                            </p>
                        @endif

                        {{-- Action Buttons --}}
                        <div class="flex flex-wrap items-center gap-2 pt-3 border-t border-outline dark:border-outline">
                            @if($testimonial->status === \App\Models\Testimonial::STATUS_PENDING)
                                {{-- Pending: Show Approve + Reject --}}
                                <button
                                    @click="$action('/dashboard/testimonials/{{ $testimonial->id }}/approve', { include: ['activeTab'] })"
                                    :disabled="$fetching()"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold bg-success text-on-primary hover:bg-success/90 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-150"
                                >
                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    <span x-show="!$fetching()">{{ __('Approve') }}</span>
                                    <span x-show="$fetching()" x-cloak>{{ __('Saving...') }}</span>
                                </button>

                                <button
                                    @click="$action('/dashboard/testimonials/{{ $testimonial->id }}/reject', { include: ['activeTab'] })"
                                    :disabled="$fetching()"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold bg-danger text-on-primary hover:bg-danger/90 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-150"
                                >
                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                    <span x-show="!$fetching()">{{ __('Reject') }}</span>
                                    <span x-show="$fetching()" x-cloak>{{ __('Saving...') }}</span>
                                </button>

                            @elseif($testimonial->status === \App\Models\Testimonial::STATUS_APPROVED)
                                {{-- Approved: Show Un-approve (remove from display) + Reject --}}
                                <button
                                    @click="$action('/dashboard/testimonials/{{ $testimonial->id }}/unapprove', { include: ['activeTab'] })"
                                    :disabled="$fetching()"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold border border-outline dark:border-outline text-on-surface bg-surface-alt hover:bg-surface hover:border-outline-strong disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-150"
                                >
                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                                    <span x-show="!$fetching()">{{ __('Remove from display') }}</span>
                                    <span x-show="$fetching()" x-cloak>{{ __('Saving...') }}</span>
                                </button>

                                <button
                                    @click="$action('/dashboard/testimonials/{{ $testimonial->id }}/reject', { include: ['activeTab'] })"
                                    :disabled="$fetching()"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold bg-danger text-on-primary hover:bg-danger/90 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-150"
                                >
                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                    <span x-show="!$fetching()">{{ __('Reject') }}</span>
                                    <span x-show="$fetching()" x-cloak>{{ __('Saving...') }}</span>
                                </button>

                            @elseif($testimonial->status === \App\Models\Testimonial::STATUS_REJECTED)
                                {{-- Rejected: Show Approve to reconsider --}}
                                <button
                                    @click="$action('/dashboard/testimonials/{{ $testimonial->id }}/approve', { include: ['activeTab'] })"
                                    :disabled="$fetching()"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold bg-success text-on-primary hover:bg-success/90 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-150"
                                >
                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    <span x-show="!$fetching()">{{ __('Approve') }}</span>
                                    <span x-show="$fetching()" x-cloak>{{ __('Saving...') }}</span>
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if($testimonials->hasPages())
                <div class="mt-6">
                    {{ $testimonials->appends(['tab' => $activeTab])->links() }}
                </div>
            @endif
        @endif
    </div>
    @endfragment
</div>
@endsection
