{{--
    Email Verification Notice Page (F-023)

    Standalone page shown when user navigates to /email/verify.
    Includes resend button with 60-second cooldown timer (BR-041).
    Shows the user's email address for clarity.
--}}
@extends('layouts.auth')

@section('title', __('Verify Email'))

@section('content')
<div
    x-data="{
        cooldownActive: false,
        cooldownSeconds: 0,
        resent: false,
        sending: false,
        cooldownTimer: null,
        startCooldown() {
            this.cooldownActive = true;
            this.cooldownSeconds = 60;
            this.cooldownTimer = setInterval(() => {
                this.cooldownSeconds--;
                if (this.cooldownSeconds <= 0) {
                    this.cooldownActive = false;
                    clearInterval(this.cooldownTimer);
                }
            }, 1000);
        },
        async resendEmail() {
            if (this.cooldownActive || this.sending) return;
            this.sending = true;
            await $action('{{ route('verification.send') }}');
            this.sending = false;
            this.resent = true;
            this.startCooldown();
            setTimeout(() => { this.resent = false; }, 4000);
        }
    }"
    class="text-center"
>
    {{-- Mail icon --}}
    <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-primary-subtle dark:bg-primary-subtle">
        <svg class="h-8 w-8 text-primary dark:text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <rect width="20" height="16" x="2" y="4" rx="2"></rect>
            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
        </svg>
    </div>

    <h2 class="text-xl font-semibold text-on-surface-strong dark:text-on-surface-strong mb-4">
        {{ __('Verify your email address') }}
    </h2>

    <p class="text-sm text-on-surface dark:text-on-surface mb-2">
        {{ __('A verification link has been sent to your email address.') }}
    </p>

    @if(isset($email))
        <p class="text-sm font-medium text-on-surface-strong dark:text-on-surface-strong mb-6">
            {{ $email }}
        </p>
    @endif

    <p class="text-xs text-on-surface/60 dark:text-on-surface/60 mb-8">
        {{ __('Please check your inbox and click the verification link to continue.') }}
        {{ __('The link will expire in 60 minutes.') }}
    </p>

    {{-- Resend button --}}
    <button
        @click="resendEmail()"
        :disabled="cooldownActive || sending"
        class="w-full h-11 rounded-lg font-semibold text-sm transition-all duration-200 flex items-center justify-center gap-2"
        :class="cooldownActive || sending
            ? 'bg-surface-alt dark:bg-surface-alt text-on-surface/50 dark:text-on-surface/50 border border-outline cursor-not-allowed'
            : 'bg-primary hover:bg-primary-hover text-on-primary cursor-pointer'"
    >
        {{-- Loader --}}
        <template x-if="sending && !cooldownActive">
            <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </template>

        <span x-show="!cooldownActive && !sending">{{ __('Resend verification email') }}</span>
        <span x-show="cooldownActive" x-text="'{{ __('Resend in') }} ' + cooldownSeconds + 's'"></span>
        <span x-show="sending && !cooldownActive">{{ __('Sending...') }}</span>
    </button>

    {{-- Success confirmation --}}
    <div
        x-show="resent"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-1"
        class="mt-4 p-3 rounded-lg bg-success-subtle dark:bg-success-subtle text-success dark:text-success text-sm font-medium flex items-center justify-center gap-2"
    >
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 6 9 17l-5-5"></path>
        </svg>
        {{ __('Verification email sent.') }}
    </div>

    {{-- Help text --}}
    <p class="mt-6 text-xs text-on-surface/50 dark:text-on-surface/50">
        {{ __("Didn't receive the email? Check your spam folder or try resending.") }}
    </p>
</div>
@endsection

@section('footer')
    <a href="{{ url('/') }}" class="text-primary hover:text-primary-hover font-medium">
        {{ __('Go Home') }}
    </a>
@endsection
