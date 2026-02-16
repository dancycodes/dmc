{{--
    Payment Method List (F-038 + F-040)
    ----------------------------
    Displays all of the user's saved payment methods in a list.
    Each method shows its label, provider icon, and masked phone number.
    The default method is visually marked. Users can set any method
    as default and access edit/delete actions.

    F-040: Delete with confirmation modal, default reassignment,
    Gale-powered removal without page reload.

    BR-156: All methods displayed, default first.
    BR-157: Phone numbers masked (only last 2 digits visible).
    BR-158: Provider displayed with icon/logo.
    BR-159: Only one payment method can be default at a time.
    BR-160: Setting new default removes previous default.
    BR-161: "Add" button only if < 3 methods.
    BR-162: Each method has edit and delete links.
    BR-170: Confirmation dialog before deletion.
    BR-171: Payment methods can always be deleted (no order dependency).
    BR-172: Default reassignment after deletion.
    BR-173: Users can only delete their own payment methods.
    BR-174: Hard delete (permanent).
--}}
@extends(tenant() ? 'layouts.tenant-public' : 'layouts.main-public')

@section('title', __('Payment Methods'))

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12"
    x-data="{
        deleteModal: false,
        deleteMethodId: null,
        deleteMethodLabel: '',
        deleteMethodProvider: '',
        deleting: false,
        confirmDelete(id, label, provider) {
            this.deleteMethodId = id;
            this.deleteMethodLabel = label;
            this.deleteMethodProvider = provider;
            this.deleteModal = true;
        },
        cancelDelete() {
            this.deleteModal = false;
            this.deleteMethodId = null;
            this.deleteMethodLabel = '';
            this.deleteMethodProvider = '';
        },
        async executeDelete() {
            if (this.deleting) return;
            this.deleting = true;
            try {
                await $action('/profile/payment-methods/' + this.deleteMethodId, {
                    method: 'DELETE'
                });
            } finally {
                this.deleting = false;
                this.deleteModal = false;
            }
        }
    }"
>
    {{-- Back Link --}}
    <div class="mb-6" x-data x-navigate>
        <a href="{{ url('/profile') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-on-surface hover:text-primary transition-colors">
            {{-- Arrow left icon (Lucide, sm=16px) --}}
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m12 19-7-7 7-7"></path>
                <path d="M19 12H5"></path>
            </svg>
            {{ __('Back to Profile') }}
        </a>
    </div>

    {{-- Card --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-xl shadow-card border border-outline overflow-hidden">
        {{-- Card Header --}}
        <div class="px-4 sm:px-6 py-5 border-b border-outline flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-lg sm:text-xl font-bold text-on-surface-strong font-display">
                    {{ __('Payment Methods') }}
                </h1>
                <p class="text-sm text-on-surface mt-1">
                    {{ __('Manage your saved mobile money numbers.') }}
                    <span class="text-on-surface/60">({{ __(':count of :max methods', ['count' => $paymentMethods->count(), 'max' => $maxMethods]) }})</span>
                </p>
            </div>

            {{-- BR-161: Add button only if < 3 methods --}}
            @if($canAddMore && $paymentMethods->count() > 0)
                <div x-data x-navigate class="shrink-0">
                    <a href="{{ url('/profile/payment-methods/create') }}" class="inline-flex items-center gap-2 h-10 px-5 rounded-lg text-sm font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 active:scale-[0.98]">
                        {{-- Plus icon (Lucide, sm=16px) --}}
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14"></path>
                            <path d="M12 5v14"></path>
                        </svg>
                        {{ __('Add Payment Method') }}
                    </a>
                </div>
            @endif
        </div>

        @if($paymentMethods->count() === 0)
            {{-- Empty State --}}
            <div class="px-4 sm:px-6 py-12 text-center">
                <div class="w-16 h-16 rounded-full bg-info-subtle mx-auto flex items-center justify-center mb-4">
                    {{-- CreditCard icon (Lucide, xl=32px) --}}
                    <svg class="w-8 h-8 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="20" height="14" x="2" y="5" rx="2"></rect>
                        <line x1="2" x2="22" y1="10" y2="10"></line>
                    </svg>
                </div>
                <h2 class="text-base font-semibold text-on-surface-strong mb-2">
                    {{ __('You have no saved payment methods.') }}
                </h2>
                <p class="text-sm text-on-surface max-w-sm mx-auto mb-6">
                    {{ __('Save a mobile money number for faster checkout when ordering meals.') }}
                </p>
                <div x-data x-navigate>
                    <a href="{{ url('/profile/payment-methods/create') }}" class="inline-flex items-center gap-2 h-10 px-6 rounded-lg text-sm font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 active:scale-[0.98]">
                        {{-- Plus icon (Lucide, sm=16px) --}}
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14"></path>
                            <path d="M12 5v14"></path>
                        </svg>
                        {{ __('Add Your First Payment Method') }}
                    </a>
                </div>
            </div>
        @else
            {{-- Payment Method List --}}
            <div class="divide-y divide-outline">
                @foreach($paymentMethods as $method)
                    <div class="px-4 sm:px-6 py-4 {{ $method->is_default ? 'bg-primary-subtle/30 dark:bg-primary-subtle/10' : '' }} hover:bg-surface/50 dark:hover:bg-surface/30 transition-colors"
                        x-data
                    >
                        <div class="flex items-start gap-3 sm:gap-4">
                            {{-- Provider Icon --}}
                            <div class="shrink-0 mt-0.5">
                                @if($method->provider === \App\Models\PaymentMethod::PROVIDER_MTN_MOMO)
                                    <span class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 {{ $method->is_default ? 'bg-[#ffcc00]' : 'bg-[#ffcc00]/20' }}">
                                        {{-- Phone icon for MTN --}}
                                        <svg class="w-5 h-5 {{ $method->is_default ? 'text-black' : 'text-[#ffcc00]' }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                        </svg>
                                    </span>
                                @else
                                    <span class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 {{ $method->is_default ? 'bg-[#ff6600]' : 'bg-[#ff6600]/20' }}">
                                        {{-- Phone icon for Orange --}}
                                        <svg class="w-5 h-5 {{ $method->is_default ? 'text-white' : 'text-[#ff6600]' }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                        </svg>
                                    </span>
                                @endif
                            </div>

                            {{-- Method Details --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3 class="text-sm font-semibold text-on-surface-strong">
                                        {{ $method->label }}
                                    </h3>
                                    {{-- BR-159: Default badge --}}
                                    @if($method->is_default)
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-primary bg-primary-subtle px-2 py-0.5 rounded-full">
                                            {{-- Star icon (Lucide, xs=14px) --}}
                                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                            </svg>
                                            {{ __('Default') }}
                                        </span>
                                    @endif
                                </div>

                                {{-- BR-158: Provider name --}}
                                <p class="text-xs text-on-surface mt-0.5">
                                    {{ $method->providerLabel() }}
                                </p>

                                {{-- BR-157: Masked phone number --}}
                                <p class="text-sm font-mono text-on-surface-strong mt-1">
                                    {{ $method->maskedPhone() }}
                                </p>
                            </div>

                            {{-- Action Buttons --}}
                            <div class="shrink-0 flex items-center gap-1 sm:gap-2">
                                {{-- Set as Default (only for non-default, and if more than one method) --}}
                                @if(!$method->is_default && $paymentMethods->count() > 1)
                                    <form @submit.prevent="$action('{{ route('payment-methods.set-default', $method) }}')" x-data>
                                        <button
                                            type="submit"
                                            class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg text-xs font-medium border border-outline text-on-surface hover:bg-primary-subtle hover:text-primary hover:border-primary/30 transition-all duration-200"
                                            title="{{ __('Set as Default') }}"
                                        >
                                            <span x-show="!$fetching()">
                                                {{-- Star icon (Lucide, xs=14px) --}}
                                                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                                </svg>
                                            </span>
                                            <span x-show="$fetching()" x-cloak>
                                                <svg class="w-3.5 h-3.5 animate-spin-slow" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                </svg>
                                            </span>
                                            <span class="hidden sm:inline">{{ __('Set as Default') }}</span>
                                        </button>
                                    </form>
                                @endif

                                {{-- BR-162: Edit link (F-039) --}}
                                <a href="{{ url('/profile/payment-methods/' . $method->id . '/edit') }}"
                                   x-navigate
                                   class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-on-surface/60 hover:bg-info-subtle hover:text-info transition-all duration-200"
                                   title="{{ __('Edit') }}"
                                >
                                    {{-- Pencil icon (Lucide, sm=16px) --}}
                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path>
                                        <path d="m15 5 4 4"></path>
                                    </svg>
                                </a>

                                {{-- BR-162 / BR-170: Delete button — opens confirmation modal (F-040) --}}
                                <button
                                    type="button"
                                    @click="confirmDelete({{ $method->id }}, '{{ addslashes($method->label) }}', '{{ $method->providerLabel() }}')"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-on-surface/60 hover:bg-danger-subtle hover:text-danger transition-all duration-200"
                                    title="{{ __('Delete') }}"
                                >
                                    {{-- Trash icon (Lucide, sm=16px) --}}
                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 6h18"></path>
                                        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                                        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                                        <line x1="10" x2="10" y1="11" y2="17"></line>
                                        <line x1="14" x2="14" y1="11" y2="17"></line>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Delete Confirmation Modal (F-040 / BR-170) --}}
    <template x-teleport="body">
        <div
            x-show="deleteModal"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            @keydown.escape.window="cancelDelete()"
            x-cloak
        >
            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/50 dark:bg-black/70" @click="cancelDelete()"></div>

            {{-- Modal Content --}}
            <div
                x-show="deleteModal"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="relative bg-surface-alt dark:bg-surface-alt rounded-xl shadow-lg border border-outline w-full max-w-sm p-6"
                @click.stop
            >
                {{-- Warning Icon --}}
                <div class="w-12 h-12 rounded-full bg-danger-subtle mx-auto flex items-center justify-center mb-4">
                    {{-- AlertTriangle icon (Lucide, lg=24px) --}}
                    <svg class="w-6 h-6 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path>
                        <path d="M12 9v4"></path>
                        <path d="M12 17h.01"></path>
                    </svg>
                </div>

                {{-- Title --}}
                <h3 class="text-base font-semibold text-on-surface-strong text-center mb-2">
                    {{ __('Delete this payment method?') }}
                </h3>

                {{-- Description --}}
                <p class="text-sm text-on-surface text-center mb-1">
                    {{ __('This cannot be undone.') }}
                </p>
                <p class="text-sm font-medium text-on-surface-strong text-center mb-1" x-text="deleteMethodLabel"></p>
                <p class="text-xs text-on-surface text-center mb-6" x-text="deleteMethodProvider"></p>

                {{-- Action Buttons --}}
                <div class="flex gap-3">
                    <button
                        type="button"
                        @click="cancelDelete()"
                        class="flex-1 h-10 px-4 rounded-lg text-sm font-medium border border-outline text-on-surface hover:bg-surface dark:hover:bg-surface transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-outline focus:ring-offset-2"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button
                        type="button"
                        @click="executeDelete()"
                        :disabled="deleting"
                        class="flex-1 h-10 px-4 rounded-lg text-sm font-semibold bg-danger hover:bg-danger/90 text-on-danger transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-danger focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span x-show="!deleting">{{ __('Delete') }}</span>
                        <span x-show="deleting" x-cloak class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin-slow" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            {{ __('Deleting...') }}
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection
