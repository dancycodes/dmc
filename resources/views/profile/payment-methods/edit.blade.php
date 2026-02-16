{{--
    Edit Payment Method (F-039)
    ----------------------------
    Allows users to edit the label and phone number of a saved payment method.
    The provider is displayed but not editable (BR-163, BR-164).
    Phone number shown unmasked for editing (BR-167).

    BR-163: Only label and phone number are editable. Provider is read-only.
    BR-164: To change provider, user must delete and re-add.
    BR-165: Phone validation must match existing provider.
    BR-166: Label uniqueness excludes current method.
    BR-167: Phone shown unmasked for editing.
    BR-168: Users can only edit their own payment methods.
    BR-169: Save via Gale without page reload.
--}}
@extends(tenant() ? 'layouts.tenant-public' : 'layouts.main-public')

@section('title', __('Edit Payment Method'))

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    {{-- Back Link --}}
    <div class="mb-6" x-data x-navigate>
        <a href="{{ url('/profile/payment-methods') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-on-surface hover:text-primary transition-colors">
            {{-- Arrow left icon (Lucide, sm=16px) --}}
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m12 19-7-7 7-7"></path>
                <path d="M19 12H5"></path>
            </svg>
            {{ __('Back to Payment Methods') }}
        </a>
    </div>

    {{-- Card --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-xl shadow-card border border-outline overflow-hidden">
        {{-- Card Header --}}
        <div class="px-4 sm:px-6 py-5 border-b border-outline">
            <h1 class="text-lg sm:text-xl font-bold text-on-surface-strong font-display">
                {{ __('Edit Payment Method') }}
            </h1>
            <p class="text-sm text-on-surface mt-1">
                {{ __('Update your payment method details.') }}
            </p>
        </div>

        {{-- Payment Method Edit Form --}}
        <div class="px-4 sm:px-6 py-6"
            x-data="{
                label: {{ Js::from($paymentMethod->label) }},
                phone: {{ Js::from(ltrim(substr($paymentMethod->phone, 4), '0')) }},

                get phonePreview() {
                    const digits = this.phone.replace(/[^\d]/g, '');
                    if (digits.length === 0) return '';
                    return '+237 ' + digits.replace(/(\d{1})(\d{2})(\d{2})(\d{2})(\d{2})/, '$1 $2 $3 $4 $5').trim();
                }
            }"
            x-sync="['label', 'phone']"
        >
            <form @submit.prevent="$action('{{ route('payment-methods.update', $paymentMethod) }}')" class="space-y-5">

                {{-- Label --}}
                <div class="space-y-1.5">
                    <label for="pm-label" class="block text-sm font-medium text-on-surface-strong">
                        {{ __('Label') }} <span class="text-danger">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface/50">
                            {{-- Tag icon (Lucide) --}}
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"></path>
                                <circle cx="7.5" cy="7.5" r=".5" fill="currentColor"></circle>
                            </svg>
                        </span>
                        <input
                            id="pm-label"
                            type="text"
                            x-name="label"
                            x-model="label"
                            required
                            maxlength="50"
                            class="w-full h-11 pl-10 pr-3 border border-outline rounded-lg text-sm text-on-surface-strong placeholder:text-on-surface/50 bg-surface dark:bg-surface-alt transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                            placeholder="{{ __('e.g., MTN Main, Orange Personal') }}"
                        >
                    </div>
                    <p x-message="label" class="text-xs text-danger"></p>
                </div>

                {{-- Provider (Read-Only) BR-163 --}}
                <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-on-surface-strong">
                        {{ __('Provider') }}
                    </label>
                    <div class="flex items-center gap-3 p-4 rounded-lg border-2 border-outline bg-surface/50 dark:bg-surface/20">
                        {{-- Provider Icon --}}
                        @if($paymentMethod->provider === \App\Models\PaymentMethod::PROVIDER_MTN_MOMO)
                            <span class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 bg-[#ffcc00]">
                                <svg class="w-5 h-5 text-black" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                </svg>
                            </span>
                        @else
                            <span class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 bg-[#ff6600]">
                                <svg class="w-5 h-5 text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                </svg>
                            </span>
                        @endif
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-on-surface-strong">
                                {{ $providerLabels[$paymentMethod->provider] ?? $paymentMethod->provider }}
                            </p>
                            <p class="text-xs text-on-surface/60 mt-0.5">
                                @if($paymentMethod->provider === \App\Models\PaymentMethod::PROVIDER_MTN_MOMO)
                                    {{ __('MTN Mobile Money') }}
                                @else
                                    {{ __('Orange Mobile Money') }}
                                @endif
                            </p>
                        </div>
                        {{-- Lock icon --}}
                        <span class="text-on-surface/40">
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </span>
                    </div>
                    {{-- BR-164: Explanation note --}}
                    <p class="text-xs text-on-surface/60">
                        {{ __('To change provider, please delete this method and add a new one.') }}
                    </p>
                </div>

                {{-- Phone Number BR-167: shown unmasked --}}
                <div class="space-y-1.5">
                    <label for="pm-phone" class="block text-sm font-medium text-on-surface-strong">
                        {{ __('Phone Number') }} <span class="text-danger">*</span>
                    </label>
                    <div class="relative">
                        {{-- Country code prefix --}}
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-strong font-medium text-sm pointer-events-none">
                            +237
                        </span>
                        <input
                            id="pm-phone"
                            type="tel"
                            x-name="phone"
                            x-model="phone"
                            required
                            maxlength="15"
                            class="w-full h-11 pl-14 pr-3 border border-outline rounded-lg text-sm text-on-surface-strong placeholder:text-on-surface/50 bg-surface dark:bg-surface-alt transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                            placeholder="{{ __('6XX XXX XXX') }}"
                        >
                    </div>
                    <p x-message="phone" class="text-xs text-danger"></p>
                    <p class="text-xs text-on-surface/60">
                        @if($paymentMethod->provider === \App\Models\PaymentMethod::PROVIDER_MTN_MOMO)
                            {{ __('MTN numbers start with 67, 68, or 650-654.') }}
                        @else
                            {{ __('Orange numbers start with 69 or 655-659.') }}
                        @endif
                    </p>
                </div>

                {{-- Action Buttons --}}
                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-2">
                    <a href="{{ url('/profile/payment-methods') }}" x-data x-navigate class="inline-flex items-center justify-center h-10 px-5 rounded-lg text-sm font-semibold border border-outline text-on-surface hover:bg-surface dark:hover:bg-surface transition-all duration-200">
                        {{ __('Cancel') }}
                    </a>
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center h-10 px-6 rounded-lg text-sm font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100"
                    >
                        <span x-show="!$fetching()">
                            <span class="inline-flex items-center gap-2">
                                {{-- Save icon (Lucide) --}}
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                                    <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                                    <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                                </svg>
                                {{ __('Save Changes') }}
                            </span>
                        </span>
                        <span x-show="$fetching()" x-cloak class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin-slow" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            {{ __('Saving...') }}
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
