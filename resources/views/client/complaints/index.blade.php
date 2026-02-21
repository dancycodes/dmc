{{--
    F-187: Client Complaints List — "My Complaints"
    -------------------------------------------------
    Lists all complaints filed by the authenticated client.
    UI/UX: Card-based list with status badges and links to detail.
--}}
@extends(tenant() ? 'layouts.tenant-public' : 'layouts.main-public')

@section('title', __('My Complaints'))

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8" x-data>

    {{-- Back Navigation --}}
    <nav class="flex items-center gap-2 text-sm text-on-surface/60 mb-6" aria-label="{{ __('Breadcrumb') }}">
        <a href="{{ url('/my-orders') }}" class="hover:text-primary transition-colors duration-200 flex items-center gap-1" x-navigate>
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
            {{ __('My Orders') }}
        </a>
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
        <span class="text-on-surface-strong font-medium">{{ __('My Complaints') }}</span>
    </nav>

    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-display font-bold text-on-surface-strong">{{ __('My Complaints') }}</h1>
        <p class="text-sm text-on-surface/60 mt-1">{{ __('Track the status of your filed complaints') }}</p>
    </div>

    @if($complaints->isEmpty())
        {{-- Empty State --}}
        <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline p-8 text-center">
            <svg class="w-12 h-12 mx-auto text-on-surface/20 mb-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><path d="m9 11 3 3L22 4"></path></svg>
            <h3 class="text-sm font-semibold text-on-surface-strong mb-1">{{ __('No Complaints') }}</h3>
            <p class="text-xs text-on-surface/50">{{ __('You have not filed any complaints yet.') }}</p>
        </div>
    @else
        {{-- Complaints List --}}
        <div class="space-y-3">
            @foreach($complaints as $complaint)
                <a
                    href="{{ url('/my-orders/' . $complaint->order_id . '/complaint/' . $complaint->id) }}"
                    class="block bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline overflow-hidden hover:border-primary/50 transition-colors"
                    x-navigate
                >
                    <div class="p-4 sm:p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                {{-- Order info --}}
                                <div class="flex items-center gap-2 mb-1.5">
                                    <span class="text-sm font-semibold text-on-surface-strong">{{ __('Complaint') }} #{{ $complaint->id }}</span>
                                    <span class="text-xs text-on-surface/40 font-mono">{{ $complaint->order?->order_number ?? '—' }}</span>
                                </div>

                                {{-- Category --}}
                                <p class="text-sm text-on-surface/70 mb-1">{{ $complaint->categoryLabel() }}</p>

                                {{-- Tenant name --}}
                                <p class="text-xs text-on-surface/50">{{ $complaint->tenant?->name ?? __('Unknown Cook') }}</p>

                                {{-- Date --}}
                                <p class="text-xs text-on-surface/40 mt-1">
                                    {{ ($complaint->submitted_at ?? $complaint->created_at)->format('M d, Y H:i') }}
                                </p>
                            </div>

                            {{-- Status badge --}}
                            <div class="shrink-0">
                                @include('cook.complaints._status-badge', ['status' => $complaint->status])
                            </div>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($complaints->hasPages())
            <div class="mt-6">
                {{ $complaints->links() }}
            </div>
        @endif
    @endif
</div>
@endsection
