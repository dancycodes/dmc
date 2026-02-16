{{--
    Delivery Address List (F-034)
    ----------------------------
    Displays all of the user's saved delivery addresses in a list.
    Each address shows its label, town, quarter, and neighbourhood.
    The default address is visually marked. Users can set any address
    as default and access edit/delete actions.

    BR-128: All addresses displayed, default first.
    BR-129: Default address visually distinguished (badge).
    BR-130: Only one address can be default at a time.
    BR-131: Setting new default removes previous default.
    BR-132: "Add Address" button only if < 5 addresses.
    BR-133: Each address has edit and delete links.
    BR-134: Localized town and quarter names.
--}}
@extends(tenant() ? 'layouts.tenant-public' : 'layouts.main-public')

@section('title', __('Delivery Addresses'))

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
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
                    {{ __('Delivery Addresses') }}
                </h1>
                <p class="text-sm text-on-surface mt-1">
                    {{ __('Manage your saved delivery addresses.') }}
                    <span class="text-on-surface/60">({{ __(':count of :max addresses', ['count' => $addresses->count(), 'max' => $maxAddresses]) }})</span>
                </p>
            </div>

            {{-- BR-132: Add Address button only if < 5 addresses --}}
            @if($canAddMore && $addresses->count() > 0)
                <div x-data x-navigate class="shrink-0">
                    <a href="{{ url('/profile/addresses/create') }}" class="inline-flex items-center gap-2 h-10 px-5 rounded-lg text-sm font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 active:scale-[0.98]">
                        {{-- Plus icon (Lucide, sm=16px) --}}
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14"></path>
                            <path d="M12 5v14"></path>
                        </svg>
                        {{ __('Add Address') }}
                    </a>
                </div>
            @endif
        </div>

        @if($addresses->count() === 0)
            {{-- Empty State --}}
            <div class="px-4 sm:px-6 py-12 text-center">
                <div class="w-16 h-16 rounded-full bg-info-subtle mx-auto flex items-center justify-center mb-4">
                    {{-- MapPin icon (Lucide, xl=32px) --}}
                    <svg class="w-8 h-8 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                </div>
                <h2 class="text-base font-semibold text-on-surface-strong mb-2">
                    {{ __('You have no saved addresses.') }}
                </h2>
                <p class="text-sm text-on-surface max-w-sm mx-auto mb-6">
                    {{ __('Save addresses for faster checkout when ordering meals.') }}
                </p>
                <div x-data x-navigate>
                    <a href="{{ url('/profile/addresses/create') }}" class="inline-flex items-center gap-2 h-10 px-6 rounded-lg text-sm font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 active:scale-[0.98]">
                        {{-- Plus icon (Lucide, sm=16px) --}}
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14"></path>
                            <path d="M12 5v14"></path>
                        </svg>
                        {{ __('Add Your First Address') }}
                    </a>
                </div>
            </div>
        @else
            {{-- Address List --}}
            <div class="divide-y divide-outline">
                @foreach($addresses as $address)
                    <div class="px-4 sm:px-6 py-4 {{ $address->is_default ? 'bg-primary-subtle/30 dark:bg-primary-subtle/10' : '' }} hover:bg-surface/50 dark:hover:bg-surface/30 transition-colors"
                        x-data
                    >
                        <div class="flex items-start gap-3 sm:gap-4">
                            {{-- Address Icon --}}
                            <div class="shrink-0 mt-0.5">
                                <span class="w-10 h-10 rounded-full {{ $address->is_default ? 'bg-primary-subtle' : 'bg-surface dark:bg-surface' }} flex items-center justify-center border {{ $address->is_default ? 'border-primary/30' : 'border-outline' }}">
                                    {{-- MapPin icon (Lucide, md=20px) --}}
                                    <svg class="w-5 h-5 {{ $address->is_default ? 'text-primary' : 'text-on-surface/60' }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                                        <circle cx="12" cy="10" r="3"></circle>
                                    </svg>
                                </span>
                            </div>

                            {{-- Address Details --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3 class="text-sm font-semibold text-on-surface-strong">
                                        {{ $address->label }}
                                    </h3>
                                    {{-- BR-129: Default badge --}}
                                    @if($address->is_default)
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-primary bg-primary-subtle px-2 py-0.5 rounded-full">
                                            {{-- Star icon (Lucide, xs=14px) --}}
                                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                            </svg>
                                            {{ __('Default') }}
                                        </span>
                                    @endif
                                </div>

                                {{-- BR-134: Localized town, quarter, neighbourhood --}}
                                <p class="text-sm text-on-surface mt-1">
                                    {{ $address->town->{localized('name')} }}, {{ $address->quarter->{localized('name')} }}
                                    @if($address->neighbourhood)
                                        <span class="text-on-surface/60">&mdash; {{ $address->neighbourhood }}</span>
                                    @endif
                                </p>
                            </div>

                            {{-- Action Buttons --}}
                            <div class="shrink-0 flex items-center gap-1 sm:gap-2">
                                {{-- Set as Default (only for non-default, and if more than one address) --}}
                                @if(!$address->is_default && $addresses->count() > 1)
                                    <form @submit.prevent="$action('{{ route('addresses.set-default', $address) }}')" x-data>
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

                                {{-- BR-133: Edit link (F-035) --}}
                                <a href="{{ url('/profile/addresses/' . $address->id . '/edit') }}"
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

                                {{-- BR-133: Delete link (F-036) --}}
                                <a href="{{ url('/profile/addresses/' . $address->id . '/delete') }}"
                                   x-navigate
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
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
