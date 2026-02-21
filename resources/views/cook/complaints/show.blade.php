{{--
    F-184: Cook/Manager Complaint Detail & Response
    ------------------------------------------------
    Shows complaint info + client info + order details + response form.
    UI/UX: Split layout — info (left/top) and response form (right/bottom).
--}}
@extends('layouts.cook-dashboard')

@section('title', __('Complaint') . ' #' . $complaint->id)

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-6" x-data="{
    message: '',
    resolution_type: 'apology_only',
    refund_amount: '',
    charCount: 0,
    showPhotoModal: false,

    updateCharCount() {
        this.charCount = this.message.length;
    },

    get showRefundAmount() {
        return this.resolution_type === 'partial_refund_offer';
    }
}">
    {{-- Back Navigation --}}
    <nav class="flex items-center gap-2 text-sm text-on-surface/60 mb-6" aria-label="{{ __('Breadcrumb') }}">
        <a href="{{ url('/dashboard/complaints') }}" class="hover:text-primary transition-colors duration-200 flex items-center gap-1" x-navigate>
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
            {{ __('Back to Complaints') }}
        </a>
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
        <span class="text-on-surface-strong font-medium">{{ __('Complaint') }} #{{ $complaint->id }}</span>
    </nav>

    {{-- Page Header --}}
    <div class="flex items-start justify-between mb-6">
        <div>
            <h1 class="text-2xl font-display font-bold text-on-surface-strong">{{ __('Complaint') }} #{{ $complaint->id }}</h1>
            <p class="text-sm text-on-surface/60 mt-1">
                {{ __('Order') }} <span class="font-mono font-semibold">{{ $complaint->order?->order_number ?? '—' }}</span>
            </p>
        </div>
        @include('cook.complaints._status-badge', ['status' => $complaint->status])
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- LEFT/TOP: Complaint Information --}}
        <div class="space-y-6">
            {{-- Client Info --}}
            <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline overflow-hidden">
                <div class="px-5 py-3.5 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                    <h2 class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                        <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        {{ __('Client Information') }}
                    </h2>
                </div>
                <div class="p-5 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-on-surface/50 uppercase tracking-wide">{{ __('Name') }}</span>
                        <span class="text-sm text-on-surface-strong font-medium">{{ $complaint->client?->name ?? __('Unknown') }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-on-surface/50 uppercase tracking-wide">{{ __('Email') }}</span>
                        <span class="text-sm text-on-surface">{{ $complaint->client?->email ?? '—' }}</span>
                    </div>
                    @if($complaint->client?->phone)
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-on-surface/50 uppercase tracking-wide">{{ __('Phone') }}</span>
                        <span class="text-sm text-on-surface font-mono">{{ $complaint->client->phone }}</span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Order Details --}}
            <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline overflow-hidden">
                <div class="px-5 py-3.5 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                    <h2 class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                        <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"></path><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path><path d="m3.3 7 8.7 5 8.7-5"></path><path d="M12 22V12"></path></svg>
                        {{ __('Order Details') }}
                    </h2>
                </div>
                <div class="p-5 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-on-surface/50 uppercase tracking-wide">{{ __('Order Number') }}</span>
                        <span class="text-sm text-on-surface-strong font-mono font-semibold">{{ $complaint->order?->order_number ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-on-surface/50 uppercase tracking-wide">{{ __('Total') }}</span>
                        <span class="text-sm text-on-surface-strong font-semibold">{{ number_format($orderTotal, 0, '.', ',') }} XAF</span>
                    </div>
                    @if(!empty($orderItems))
                    <div class="border-t border-outline dark:border-outline pt-3">
                        <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-2">{{ __('Items') }}</p>
                        <div class="space-y-1.5">
                            @foreach($orderItems as $item)
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-on-surface">{{ $item['name'] }} x{{ $item['quantity'] }}</span>
                                    <span class="text-on-surface/60 font-mono">{{ number_format($item['price'] * $item['quantity'], 0, '.', ',') }} XAF</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Complaint Details --}}
            <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline overflow-hidden">
                <div class="px-5 py-3.5 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                    <h2 class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                        <svg class="w-4 h-4 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                        {{ __('Complaint Details') }}
                    </h2>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Category') }}</p>
                        @include('cook.complaints._category-badge', ['category' => $complaint->category])
                    </div>
                    <div>
                        <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Description') }}</p>
                        <p class="text-sm text-on-surface leading-relaxed">{{ $complaint->description }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-1">{{ __('Submitted') }}</p>
                        <p class="text-sm text-on-surface/70">{{ ($complaint->submitted_at ?? $complaint->created_at)->format('M d, Y H:i') }}</p>
                    </div>

                    {{-- Photo Evidence --}}
                    @if($complaint->photo_path)
                        <div>
                            <p class="text-xs font-medium text-on-surface/50 uppercase tracking-wide mb-2">{{ __('Photo Evidence') }}</p>
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
                                    <p class="text-xs text-on-surface/40">{{ __('Image unavailable') }}</p>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- RIGHT/BOTTOM: Response Form & Previous Responses --}}
        <div class="space-y-6">
            {{-- Previous Responses --}}
            @if($complaint->responses->isNotEmpty())
                <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                        <h2 class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                            <svg class="w-4 h-4 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"></path></svg>
                            {{ __('Previous Responses') }} ({{ $complaint->responses->count() }})
                        </h2>
                    </div>
                    <div class="p-5 space-y-4">
                        @foreach($complaint->responses as $response)
                            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg p-4 @if(!$loop->last) border-b border-outline dark:border-outline pb-4 @endif">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-on-surface-strong">{{ $response->user?->name ?? __('Unknown') }}</span>
                                    <span class="text-xs text-on-surface/40">{{ $response->created_at->format('M d, Y H:i') }}</span>
                                </div>
                                <p class="text-sm text-on-surface leading-relaxed mb-2">{{ $response->message }}</p>
                                @if($response->resolution_type)
                                    <div class="flex items-center gap-2 text-xs">
                                        <span class="font-medium text-on-surface/50">{{ __('Resolution') }}:</span>
                                        <span class="font-medium text-primary">{{ $response->resolutionTypeLabel() }}</span>
                                        @if($response->refund_amount)
                                            <span class="font-mono text-on-surface/60">({{ number_format($response->refund_amount, 0, '.', ',') }} XAF)</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Response Form --}}
            @if(!$complaint->isResolved())
                <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                        <h2 class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                            <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.376 3.622a1 1 0 0 1 3.002 3.002L7.368 18.635a2 2 0 0 1-.855.506l-2.872.838a.5.5 0 0 1-.62-.62l.838-2.872a2 2 0 0 1 .506-.854z"></path></svg>
                            {{ $complaint->responses->isNotEmpty() ? __('Add Another Response') : __('Write a Response') }}
                        </h2>
                    </div>
                    <div class="p-5" x-sync="['message', 'resolution_type', 'refund_amount']">
                        {{-- Response Message --}}
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-on-surface-strong mb-1.5">
                                {{ __('Response Message') }} <span class="text-danger">*</span>
                            </label>
                            <textarea
                                x-model="message"
                                x-name="message"
                                x-on:input="updateCharCount()"
                                rows="5"
                                maxlength="{{ \App\Models\ComplaintResponse::MAX_MESSAGE_LENGTH }}"
                                placeholder="{{ __('Write your response to the customer...') }}"
                                class="w-full px-3.5 py-2.5 bg-surface dark:bg-surface border border-outline dark:border-outline rounded-lg text-sm text-on-surface placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors resize-none"
                            ></textarea>
                            <div class="flex items-center justify-between mt-1">
                                <p x-message="message" class="text-xs text-danger"></p>
                                <span class="text-xs text-on-surface/40" :class="charCount > {{ \App\Models\ComplaintResponse::MAX_MESSAGE_LENGTH - 100 }} ? 'text-warning' : ''">
                                    <span x-text="charCount">0</span>/{{ \App\Models\ComplaintResponse::MAX_MESSAGE_LENGTH }}
                                </span>
                            </div>
                        </div>

                        {{-- Resolution Type --}}
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-on-surface-strong mb-2">
                                {{ __('Resolution Offer') }} <span class="text-danger">*</span>
                            </label>
                            <div class="space-y-2">
                                {{-- Apology Only --}}
                                <label class="flex items-start gap-3 p-3 rounded-lg border border-outline dark:border-outline cursor-pointer hover:bg-surface-alt/50 transition-colors" :class="resolution_type === 'apology_only' ? 'border-primary bg-primary-subtle/30' : ''">
                                    <input type="radio" x-model="resolution_type" x-name="resolution_type" value="apology_only" class="mt-0.5 text-primary focus:ring-primary">
                                    <div>
                                        <span class="text-sm font-medium text-on-surface-strong">{{ __('Apology Only') }}</span>
                                        <p class="text-xs text-on-surface/50 mt-0.5">{{ __('Apologize and explain without offering a refund') }}</p>
                                    </div>
                                </label>

                                {{-- Partial Refund --}}
                                <label class="flex items-start gap-3 p-3 rounded-lg border border-outline dark:border-outline cursor-pointer hover:bg-surface-alt/50 transition-colors" :class="resolution_type === 'partial_refund_offer' ? 'border-primary bg-primary-subtle/30' : ''">
                                    <input type="radio" x-model="resolution_type" x-name="resolution_type" value="partial_refund_offer" class="mt-0.5 text-primary focus:ring-primary">
                                    <div>
                                        <span class="text-sm font-medium text-on-surface-strong">{{ __('Partial Refund Offer') }}</span>
                                        <p class="text-xs text-on-surface/50 mt-0.5">{{ __('Offer a partial refund to the customer') }}</p>
                                    </div>
                                </label>

                                {{-- Full Refund --}}
                                <label class="flex items-start gap-3 p-3 rounded-lg border border-outline dark:border-outline cursor-pointer hover:bg-surface-alt/50 transition-colors" :class="resolution_type === 'full_refund_offer' ? 'border-primary bg-primary-subtle/30' : ''">
                                    <input type="radio" x-model="resolution_type" x-name="resolution_type" value="full_refund_offer" class="mt-0.5 text-primary focus:ring-primary">
                                    <div>
                                        <span class="text-sm font-medium text-on-surface-strong">{{ __('Full Refund Offer') }}</span>
                                        <p class="text-xs text-on-surface/50 mt-0.5">{{ __('Offer a full refund of :amount XAF', ['amount' => number_format($orderTotal, 0, '.', ',')]) }}</p>
                                    </div>
                                </label>
                            </div>
                            <p x-message="resolution_type" class="text-xs text-danger mt-1"></p>
                        </div>

                        {{-- Partial Refund Amount (conditional) --}}
                        <div x-show="showRefundAmount" x-cloak class="mb-4">
                            <label class="block text-sm font-medium text-on-surface-strong mb-1.5">
                                {{ __('Refund Amount (XAF)') }} <span class="text-danger">*</span>
                            </label>
                            <div class="relative">
                                <input
                                    type="number"
                                    x-model="refund_amount"
                                    x-name="refund_amount"
                                    min="1"
                                    max="{{ $orderTotal }}"
                                    placeholder="{{ __('Enter amount...') }}"
                                    class="w-full px-3.5 py-2.5 bg-surface dark:bg-surface border border-outline dark:border-outline rounded-lg text-sm text-on-surface placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors"
                                >
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-on-surface/40 font-medium">XAF</span>
                            </div>
                            <p x-message="refund_amount" class="text-xs text-danger mt-1"></p>
                            <p class="text-xs text-on-surface/40 mt-1">{{ __('Maximum: :amount XAF (order total)', ['amount' => number_format($orderTotal, 0, '.', ',')]) }}</p>
                        </div>

                        {{-- Submit Button --}}
                        <button
                            x-on:click="$action('{{ url('/dashboard/complaints/' . $complaint->id . '/respond') }}')"
                            class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-primary hover:bg-primary-hover text-on-primary rounded-lg text-sm font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-primary/30"
                        >
                            <span x-show="!$fetching()">
                                <svg class="w-4 h-4 inline-block mr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"></path><path d="M22 2 11 13"></path></svg>
                                {{ __('Submit Response') }}
                            </span>
                            <span x-show="$fetching()" x-cloak class="flex items-center gap-2">
                                <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                {{ __('Submitting...') }}
                            </span>
                        </button>

                        {{-- Info Note --}}
                        @if($complaint->status === 'open')
                            <div class="mt-4 bg-info-subtle dark:bg-info-subtle rounded-lg p-3 flex items-start gap-2.5">
                                <svg class="w-4 h-4 text-info shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                                <p class="text-xs text-on-surface/70 leading-relaxed">
                                    {{ __('Responding will change the complaint status to "In Review" and cancel the 24-hour auto-escalation. Resolution offers are reviewed by the admin before processing.') }}
                                </p>
                            </div>
                        @endif

                        @if($complaint->status === 'escalated')
                            <div class="mt-4 bg-warning-subtle dark:bg-warning-subtle rounded-lg p-3 flex items-start gap-2.5">
                                <svg class="w-4 h-4 text-warning shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                                <p class="text-xs text-on-surface/70 leading-relaxed">
                                    {{ __('This complaint has been escalated to the admin team. You can still respond to provide additional context.') }}
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                {{-- Resolved Notice --}}
                <div class="bg-success-subtle dark:bg-success-subtle rounded-xl p-5 text-center">
                    <svg class="w-10 h-10 mx-auto text-success mb-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><path d="m9 11 3 3L22 4"></path></svg>
                    <h3 class="text-sm font-semibold text-success">{{ __('This complaint has been resolved') }}</h3>
                    @if($complaint->resolved_at)
                        <p class="text-xs text-on-surface/50 mt-1">{{ __('Resolved on') }} {{ $complaint->resolved_at->format('M d, Y H:i') }}</p>
                    @endif
                </div>
            @endif
        </div>
    </div>

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
