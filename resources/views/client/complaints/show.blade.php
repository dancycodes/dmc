{{--
    F-187: Complaint Status Tracking — Client View
    ------------------------------------------------
    Shows complaint progression through Open > In Review > Escalated > Resolved.
    Visual timeline, message thread, resolution details.

    BR-232: Status timeline with current state highlighted.
    BR-233: All messages visible.
    BR-234: Resolution details shown.
    BR-235: No reopen option after resolution.
--}}
@extends(tenant() ? 'layouts.tenant-public' : 'layouts.main-public')

@section('title', __('Complaint') . ' #' . $complaint->id)

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8" x-data="{ showPhotoModal: false }">

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
            {{ __('Order') }} <span class="font-mono font-semibold">#{{ $order->order_number ?? $order->id }}</span>
            {{ __('from') }} {{ $cookName }}
        </p>
    </div>

    {{-- Status Timeline Card --}}
    <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline overflow-hidden mb-6">
        <div class="px-5 py-3.5 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
            <h2 class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                {{ __('Progress') }}
            </h2>
        </div>
        <div class="p-5">
            @include('client.complaints._status-timeline', ['timeline' => $timeline])
        </div>
    </div>

    {{-- Complaint Details Card --}}
    <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline overflow-hidden mb-6">
        <div class="px-5 py-3.5 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt flex items-center justify-between">
            <h2 class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                <svg class="w-4 h-4 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                {{ __('Complaint Details') }}
            </h2>
            @include('cook.complaints._status-badge', ['status' => $complaint->status])
        </div>
        <div class="p-5 space-y-4">
            {{-- Category --}}
            <div>
                <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Category') }}</p>
                <p class="text-sm text-on-surface-strong">{{ $complaint->categoryLabel() }}</p>
            </div>

            {{-- Photo Evidence --}}
            @if($complaint->photo_path)
                <div>
                    <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Photo Evidence') }}</p>
                    @if(Storage::disk('public')->exists($complaint->photo_path))
                        <button x-on:click="showPhotoModal = true" class="block cursor-pointer">
                            <img
                                src="{{ Storage::url($complaint->photo_path) }}"
                                alt="{{ __('Complaint evidence') }}"
                                class="rounded-lg max-h-32 object-cover border border-outline dark:border-outline hover:opacity-80 transition-opacity"
                            >
                        </button>
                        <p class="text-xs text-on-surface/40 mt-1">{{ __('Click to enlarge') }}</p>
                    @else
                        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg p-4 text-center">
                            <svg class="w-8 h-8 mx-auto text-on-surface/20 mb-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect><circle cx="9" cy="9" r="2"></circle><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path></svg>
                            <p class="text-xs text-on-surface/40">{{ __('Image no longer available') }}</p>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Message Thread --}}
    <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline overflow-hidden mb-6">
        <div class="px-5 py-3.5 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
            <h2 class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                <svg class="w-4 h-4 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"></path></svg>
                {{ __('Messages') }} ({{ count($messages) }})
            </h2>
        </div>
        <div class="p-5 space-y-4 max-h-96 overflow-y-auto">
            @foreach($messages as $msg)
                <div class="@if($msg['role'] === 'client') ml-0 mr-6 @else ml-6 mr-0 @endif">
                    <div class="@if($msg['role'] === 'client') bg-primary-subtle/30 dark:bg-primary-subtle/20 @else bg-surface-alt dark:bg-surface-alt @endif rounded-lg p-4">
                        {{-- Sender info --}}
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-on-surface-strong">{{ $msg['sender'] }}</span>
                                @php
                                    $roleBadgeClasses = match($msg['role']) {
                                        'client' => 'bg-primary-subtle text-primary',
                                        'cook' => 'bg-secondary-subtle text-secondary',
                                        'manager' => 'bg-info-subtle text-info',
                                        'admin' => 'bg-danger-subtle text-danger',
                                        default => 'bg-surface-alt text-on-surface/60',
                                    };
                                    $roleLabel = match($msg['role']) {
                                        'client' => __('Client'),
                                        'cook' => __('Cook'),
                                        'manager' => __('Manager'),
                                        'admin' => __('Admin'),
                                        default => __('Unknown'),
                                    };
                                @endphp
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wider {{ $roleBadgeClasses }}">
                                    {{ $roleLabel }}
                                </span>
                            </div>
                            <span class="text-xs text-on-surface/40">{{ $msg['timestamp'] }}</span>
                        </div>

                        {{-- Message text --}}
                        <p class="text-sm text-on-surface leading-relaxed">{{ $msg['message'] }}</p>

                        {{-- Resolution offer (if present in response) --}}
                        @if(isset($msg['resolution_type']))
                            <div class="mt-2 pt-2 border-t border-outline/30 dark:border-outline/30 flex items-center gap-2 text-xs">
                                <span class="font-medium text-on-surface/50">{{ __('Resolution Offer') }}:</span>
                                <span class="font-medium text-primary">{{ $msg['resolution_label'] ?? '' }}</span>
                                @if(isset($msg['refund_amount']) && $msg['refund_amount'])
                                    <span class="font-mono text-on-surface/60">({{ number_format($msg['refund_amount'], 0, '.', ',') }} XAF)</span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Resolution Card (shown when resolved) --}}
    @if($resolution)
        <div class="rounded-xl overflow-hidden mb-6
            @if(in_array($resolution['type'], ['partial_refund', 'full_refund']))
                bg-success-subtle dark:bg-success-subtle border-2 border-success/30
            @elseif($resolution['type'] === 'dismiss')
                bg-surface-alt dark:bg-surface-alt border-2 border-outline
            @else
                bg-info-subtle dark:bg-info-subtle border-2 border-info/30
            @endif
        ">
            <div class="p-5">
                <div class="flex items-center gap-3 mb-3">
                    @if(in_array($resolution['type'], ['partial_refund', 'full_refund']))
                        <div class="w-10 h-10 rounded-full bg-success/20 flex items-center justify-center">
                            <svg class="w-5 h-5 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><path d="m9 11 3 3L22 4"></path></svg>
                        </div>
                    @else
                        <div class="w-10 h-10 rounded-full bg-info/20 flex items-center justify-center">
                            <svg class="w-5 h-5 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                        </div>
                    @endif
                    <div>
                        <h3 class="text-base font-semibold text-on-surface-strong">{{ __('Complaint Resolved') }}</h3>
                        <p class="text-sm text-on-surface/70">{{ $resolution['label'] }}</p>
                    </div>
                </div>

                {{-- Refund amount --}}
                @if($resolution['amount'])
                    <div class="bg-surface/50 dark:bg-surface/50 rounded-lg p-3 mb-3">
                        <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Refund Amount') }}</p>
                        <p class="text-lg font-semibold text-success font-mono">{{ number_format($resolution['amount'], 0, '.', ',') }} XAF</p>
                        <p class="text-xs text-on-surface/50 mt-0.5">{{ __('Credited to your wallet') }}</p>
                    </div>
                @endif

                {{-- Admin notes (not shown for warn_cook to client) --}}
                @if($resolution['notes'])
                    <div class="mb-3">
                        <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Resolution Notes') }}</p>
                        <p class="text-sm text-on-surface leading-relaxed">{{ $resolution['notes'] }}</p>
                    </div>
                @endif

                {{-- Resolution metadata --}}
                <div class="flex items-center gap-4 text-xs text-on-surface/40">
                    @if($resolution['resolved_at'])
                        <span>{{ __('Resolved on') }} {{ $resolution['resolved_at'] }}</span>
                    @endif
                    @if($resolution['resolved_by'])
                        <span>{{ __('by') }} {{ $resolution['resolved_by'] }}</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- BR-235: No reopen info --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg p-4 text-center mb-6">
            <svg class="w-6 h-6 mx-auto text-on-surface/30 mb-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            <p class="text-xs text-on-surface/40">{{ __('This complaint has been resolved and cannot be reopened.') }}</p>
        </div>
    @else
        {{-- Info card for open/in_review/escalated states --}}
        @if($complaint->status === 'open')
            <div class="bg-info-subtle dark:bg-info-subtle rounded-xl p-4 flex items-start gap-3 mb-6">
                <svg class="w-5 h-5 text-info shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                <p class="text-sm text-on-surface/70 leading-relaxed">
                    {{ __('Your complaint is being reviewed by the cook. If not resolved within 24 hours, it will be automatically escalated to the DancyMeals support team.') }}
                </p>
            </div>
        @elseif($complaint->status === 'in_review' || $complaint->status === 'responded')
            <div class="bg-info-subtle dark:bg-info-subtle rounded-xl p-4 flex items-start gap-3 mb-6">
                <svg class="w-5 h-5 text-info shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                <p class="text-sm text-on-surface/70 leading-relaxed">
                    {{ __('The cook has responded to your complaint. The resolution is being reviewed.') }}
                </p>
            </div>
        @elseif(in_array($complaint->status, ['escalated', 'pending_resolution', 'under_review']))
            <div class="bg-warning-subtle dark:bg-warning-subtle rounded-xl p-4 flex items-start gap-3 mb-6">
                <svg class="w-5 h-5 text-warning shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                <p class="text-sm text-on-surface/70 leading-relaxed">
                    {{ __('Your complaint has been escalated to our support team. They will review it and take appropriate action.') }}
                </p>
            </div>
        @endif
    @endif

    {{-- Photo Enlargement Modal --}}
    @if($complaint->photo_path && Storage::disk('public')->exists($complaint->photo_path))
        <div
            x-show="showPhotoModal"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
            x-on:click.self="showPhotoModal = false"
            x-on:keydown.escape.window="showPhotoModal = false"
            role="dialog"
            aria-modal="true"
        >
            <div class="relative max-w-3xl max-h-[90vh]">
                <button
                    x-on:click="showPhotoModal = false"
                    class="absolute -top-3 -right-3 w-8 h-8 bg-surface dark:bg-surface rounded-full flex items-center justify-center shadow-lg border border-outline dark:border-outline hover:bg-surface-alt transition-colors"
                    aria-label="{{ __('Close') }}"
                >
                    <svg class="w-4 h-4 text-on-surface" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                </button>
                <img
                    src="{{ Storage::url($complaint->photo_path) }}"
                    alt="{{ __('Complaint evidence') }}"
                    class="rounded-lg max-h-[85vh] object-contain"
                >
            </div>
        </div>
    @endif
</div>
@endsection
