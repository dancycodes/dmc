{{--
    F-183/F-187: Complaint Status View (Stub)
    -------------------------------------------
    Shows the status of a filed complaint.
    Scenario 2: Client has already filed a complaint and views it.
    Full implementation in F-187.
--}}
@extends(tenant() ? 'layouts.tenant-public' : 'layouts.main-public')

@section('title', __('Complaint') . ' #' . $complaint->id)

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8" x-data>

    {{-- Back Navigation --}}
    <nav class="flex items-center gap-2 text-sm text-on-surface/60 mb-6" aria-label="{{ __('Breadcrumb') }}">
        <a href="{{ url('/my-orders/' . $order->id) }}" class="hover:text-primary transition-colors duration-200 flex items-center gap-1" x-navigate>
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
            {{ __('Back to Order') }}
        </a>
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
        <span class="text-on-surface-strong font-medium">{{ __('Complaint Status') }}</span>
    </nav>

    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-display font-bold text-on-surface-strong">{{ __('Complaint Status') }}</h1>
        <p class="text-sm text-on-surface/60 mt-1">
            {{ __('Order') }} <span class="font-mono font-semibold">#{{ $order->order_number }}</span>
            {{ __('from') }} {{ $cookName }}
        </p>
    </div>

    {{-- Complaint Card --}}
    <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline overflow-hidden">
        <div class="px-5 py-3.5 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt flex items-center justify-between">
            <h2 class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                <svg class="w-4 h-4 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                {{ __('Complaint Details') }}
            </h2>
            @php
                $statusClasses = match($complaint->status) {
                    'open' => 'bg-warning-subtle text-warning',
                    'in_review' => 'bg-info-subtle text-info',
                    'responded' => 'bg-info-subtle text-info',
                    'escalated' => 'bg-danger-subtle text-danger',
                    'resolved' => 'bg-success-subtle text-success',
                    'dismissed' => 'bg-surface-alt text-on-surface/60',
                    default => 'bg-surface-alt text-on-surface',
                };
            @endphp
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClasses }}">
                {{ $complaint->statusLabel() }}
            </span>
        </div>
        <div class="p-5 space-y-4">
            {{-- Category --}}
            <div>
                <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Category') }}</p>
                <p class="text-sm text-on-surface-strong">{{ $complaint->categoryLabel() }}</p>
            </div>

            {{-- Description --}}
            <div>
                <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Description') }}</p>
                <p class="text-sm text-on-surface leading-relaxed">{{ $complaint->description }}</p>
            </div>

            {{-- Photo --}}
            @if($complaint->photo_path)
                <div>
                    <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Photo Evidence') }}</p>
                    <img
                        src="{{ Storage::url($complaint->photo_path) }}"
                        alt="{{ __('Complaint evidence') }}"
                        class="rounded-lg max-h-48 object-cover border border-outline dark:border-outline"
                    >
                </div>
            @endif

            {{-- Submitted At --}}
            <div>
                <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Submitted') }}</p>
                <p class="text-sm text-on-surface/70">{{ $complaint->submitted_at?->format('M d, Y H:i') ?? $complaint->created_at->format('M d, Y H:i') }}</p>
            </div>

            {{-- Cook Response (if any) --}}
            @if($complaint->cook_response)
                <div class="bg-surface-alt dark:bg-surface-alt rounded-lg p-3">
                    <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Cook Response') }}</p>
                    <p class="text-sm text-on-surface leading-relaxed">{{ $complaint->cook_response }}</p>
                    @if($complaint->cook_responded_at)
                        <p class="text-xs text-on-surface/40 mt-1">{{ $complaint->cook_responded_at->format('M d, Y H:i') }}</p>
                    @endif
                </div>
            @endif

            {{-- Resolution (if resolved) --}}
            @if($complaint->isResolved() && $complaint->resolution_notes)
                <div class="bg-success-subtle dark:bg-success-subtle rounded-lg p-3">
                    <p class="text-xs font-medium text-success uppercase tracking-wide mb-1">{{ __('Resolution') }}</p>
                    <p class="text-sm text-on-surface leading-relaxed">{{ $complaint->resolution_notes }}</p>
                    @if($complaint->resolved_at)
                        <p class="text-xs text-on-surface/40 mt-1">{{ __('Resolved on') }} {{ $complaint->resolved_at->format('M d, Y H:i') }}</p>
                    @endif
                </div>
            @endif

            {{-- Info --}}
            @if($complaint->status === 'open')
                <div class="bg-info-subtle dark:bg-info-subtle rounded-lg p-3 flex items-start gap-2.5">
                    <svg class="w-4 h-4 text-info shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                    <p class="text-xs text-on-surface/70 leading-relaxed">
                        {{ __('Your complaint is being reviewed by the cook. If not resolved within 24 hours, it will be automatically escalated to the DancyMeals support team.') }}
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
