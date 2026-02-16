{{--
    Add Payment Method (F-037)
    ---------------------------
    Allows users to add a saved mobile money number for checkout.
    Fields: label, provider (MTN MoMo / Orange Money), phone number.

    BR-147: Maximum 3 saved payment methods per user.
    BR-148: Label required, unique per user, max 50 chars.
    BR-149: Provider required, mtn_momo or orange_money.
    BR-150: Phone must be valid Cameroon mobile number (+237 format).
    BR-151: Phone prefix must match selected provider.
    BR-152: First payment method auto-set as default.
    BR-153: Payment methods are user-scoped, not tenant-scoped.
    BR-154: Phone stored in normalized +237XXXXXXXXX format.
    BR-155: All text localized via __().
--}}
@extends(tenant() ? 'layouts.tenant-public' : 'layouts.main-public')

@section('title', __('Add Payment Method'))

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    {{-- Back Link --}}
    <div class="mb-6" x-data x-navigate>
        <a href="{{ url('/profile') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-on-surface hover:text-primary transition-colors">
            {{-- Arrow left icon (Lucide) --}}
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
        <div class="px-4 sm:px-6 py-5 border-b border-outline">
            <h1 class="text-lg sm:text-xl font-bold text-on-surface-strong font-display">
                {{ __('Add Payment Method') }}
            </h1>
            <p class="text-sm text-on-surface mt-1">
                {{ __('Save a mobile money number for faster checkout.') }}
                <span class="text-on-surface/60">({{ $methodCount }}/{{ $maxMethods }})</span>
            </p>
        </div>

        @if(!$canAddMore)
            {{-- Maximum Payment Methods Limit Message --}}
            <div class="px-4 sm:px-6 py-8 text-center">
                <div class="w-16 h-16 rounded-full bg-warning-subtle mx-auto flex items-center justify-center mb-4">
                    {{-- AlertTriangle icon (Lucide) --}}
                    <svg class="w-8 h-8 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path>
                        <path d="M12 9v4"></path>
                        <path d="M12 17h.01"></path>
                    </svg>
                </div>
                <h2 class="text-base font-semibold text-on-surface-strong mb-2">
                    {{ __('Payment Method Limit Reached') }}
                </h2>
                <p class="text-sm text-on-surface max-w-sm mx-auto">
                    {{ __('You can save up to :max payment methods. Please remove one to add a new one.', ['max' => $maxMethods]) }}
                </p>
                <div class="mt-6" x-data x-navigate>
                    <a href="{{ url('/profile') }}" class="inline-flex items-center justify-center h-10 px-5 rounded-lg text-sm font-semibold border border-outline text-on-surface hover:bg-surface dark:hover:bg-surface transition-all duration-200">
                        {{ __('Back to Profile') }}
                    </a>
                </div>
            </div>
        @else
            {{-- Payment Method Form --}}
            <div class="px-4 sm:px-6 py-6"
                x-data="{
                    label: '',
                    provider: '',
                    phone: '',

                    get formattedPhone() {
                        return this.phone.replace(/[^\d]/g, '');
                    },

                    get phonePreview() {
                        const digits = this.phone.replace(/[^\d]/g, '');
                        if (digits.length === 0) return '';
                        return '+237 ' + digits.replace(/(\d{1})(\d{2})(\d{2})(\d{2})(\d{2})/, '$1 $2 $3 $4 $5').trim();
                    }
                }"
                x-sync="['label', 'provider', 'phone']"
            >
                <form @submit.prevent="$action('{{ route('payment-methods.store') }}')" class="space-y-5">

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

                    {{-- Provider Selection (Radio Buttons) --}}
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-on-surface-strong">
                            {{ __('Provider') }} <span class="text-danger">*</span>
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            {{-- MTN MoMo --}}
                            <label
                                class="relative flex items-center gap-3 p-4 rounded-lg border-2 cursor-pointer transition-all duration-200"
                                :class="provider === 'mtn_momo'
                                    ? 'border-[#ffcc00] bg-[#ffcc00]/10 dark:bg-[#ffcc00]/5'
                                    : 'border-outline hover:border-outline-strong'"
                            >
                                <input
                                    type="radio"
                                    x-name="provider"
                                    x-model="provider"
                                    value="mtn_momo"
                                    class="sr-only"
                                >
                                {{-- MTN icon circle --}}
                                <span class="w-10 h-10 rounded-full flex items-center justify-center shrink-0"
                                    :class="provider === 'mtn_momo' ? 'bg-[#ffcc00]' : 'bg-[#ffcc00]/20'"
                                >
                                    {{-- Phone icon --}}
                                    <svg class="w-5 h-5" :class="provider === 'mtn_momo' ? 'text-black' : 'text-[#ffcc00]'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                    </svg>
                                </span>
                                <div>
                                    <p class="text-sm font-semibold text-on-surface-strong">{{ __('MTN MoMo') }}</p>
                                    <p class="text-xs text-on-surface mt-0.5">{{ __('MTN Mobile Money') }}</p>
                                </div>
                                {{-- Check indicator --}}
                                <span
                                    x-show="provider === 'mtn_momo'"
                                    x-cloak
                                    class="absolute top-2 right-2"
                                >
                                    <svg class="w-5 h-5 text-[#ffcc00]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <path d="m9 11 3 3L22 4"></path>
                                    </svg>
                                </span>
                            </label>

                            {{-- Orange Money --}}
                            <label
                                class="relative flex items-center gap-3 p-4 rounded-lg border-2 cursor-pointer transition-all duration-200"
                                :class="provider === 'orange_money'
                                    ? 'border-[#ff6600] bg-[#ff6600]/10 dark:bg-[#ff6600]/5'
                                    : 'border-outline hover:border-outline-strong'"
                            >
                                <input
                                    type="radio"
                                    x-name="provider"
                                    x-model="provider"
                                    value="orange_money"
                                    class="sr-only"
                                >
                                {{-- Orange icon circle --}}
                                <span class="w-10 h-10 rounded-full flex items-center justify-center shrink-0"
                                    :class="provider === 'orange_money' ? 'bg-[#ff6600]' : 'bg-[#ff6600]/20'"
                                >
                                    {{-- Phone icon --}}
                                    <svg class="w-5 h-5" :class="provider === 'orange_money' ? 'text-white' : 'text-[#ff6600]'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                    </svg>
                                </span>
                                <div>
                                    <p class="text-sm font-semibold text-on-surface-strong">{{ __('Orange Money') }}</p>
                                    <p class="text-xs text-on-surface mt-0.5">{{ __('Orange Mobile Money') }}</p>
                                </div>
                                {{-- Check indicator --}}
                                <span
                                    x-show="provider === 'orange_money'"
                                    x-cloak
                                    class="absolute top-2 right-2"
                                >
                                    <svg class="w-5 h-5 text-[#ff6600]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <path d="m9 11 3 3L22 4"></path>
                                    </svg>
                                </span>
                            </label>
                        </div>
                        <p x-message="provider" class="text-xs text-danger"></p>
                    </div>

                    {{-- Phone Number --}}
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
                            <span x-show="provider === 'mtn_momo'" x-cloak>
                                {{ __('MTN numbers start with 67, 68, or 650-654.') }}
                            </span>
                            <span x-show="provider === 'orange_money'" x-cloak>
                                {{ __('Orange numbers start with 69 or 655-659.') }}
                            </span>
                            <span x-show="!provider">
                                {{ __('Select a provider to see valid number prefixes.') }}
                            </span>
                        </p>
                    </div>

                    {{-- Default Notice --}}
                    @if($methodCount === 0)
                        <div class="flex items-start gap-3 p-3 rounded-lg bg-info-subtle border border-info/20">
                            <svg class="w-5 h-5 text-info shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="M12 16v-4"></path>
                                <path d="M12 8h.01"></path>
                            </svg>
                            <p class="text-sm text-info">
                                {{ __('This will be set as your default payment method.') }}
                            </p>
                        </div>
                    @endif

                    {{-- Action Buttons --}}
                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-2">
                        <a href="{{ url('/profile') }}" x-data x-navigate class="inline-flex items-center justify-center h-10 px-5 rounded-lg text-sm font-semibold border border-outline text-on-surface hover:bg-surface dark:hover:bg-surface transition-all duration-200">
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
                                    {{ __('Save Payment Method') }}
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
        @endif
    </div>
</div>
@endsection
