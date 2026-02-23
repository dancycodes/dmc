@extends('layouts.auth')

@section('title', __('Reset Password'))

@section('content')
<div x-data="{
    email: '',
    submitted: false,
    submitting: false,
}" x-sync>
    <h2 class="text-xl font-semibold text-on-surface-strong mb-2">
        {{ __('Reset your password') }}
    </h2>
    <p class="text-sm text-on-surface mb-6">
        {{ __('Enter your email address and we will send you a link to reset your password.') }}
    </p>

    {{-- Success message (BR-064: shown as a prominent alert, not a toast) --}}
    <div x-show="submitted" x-cloak class="mb-4 rounded-lg bg-success-subtle border border-success/20 p-4">
        <div class="flex items-start gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-success flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <p x-message="_success" class="text-sm text-success font-medium"></p>
        </div>
    </div>

    <form x-show="!submitted" @submit.prevent="submitting = true; $action('{{ route('password.email') }}')" class="space-y-4">
        <x-honeypot />

        {{-- Email --}}
        <div class="space-y-1.5">
            <label for="reset-email" class="block text-sm font-medium text-on-surface-strong">
                {{ __('Email Address') }}
            </label>
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface/50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                </span>
                <input
                    id="reset-email"
                    type="email"
                    x-name="email"
                    x-model="email"
                    required
                    autocomplete="email"
                    class="w-full h-11 pl-10 pr-3 border border-outline rounded-lg text-sm text-on-surface-strong placeholder:text-on-surface/50 bg-surface dark:bg-surface-alt transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                    placeholder="{{ __('you@example.com') }}"
                >
            </div>
            <p x-message="email" class="text-xs text-danger"></p>
        </div>

        {{-- Submit --}}
        <button
            type="submit"
            :disabled="submitting"
            class="w-full h-12 rounded-lg font-semibold bg-primary hover:bg-primary-hover text-on-primary text-base transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100"
        >
            <span x-show="!$fetching()">{{ __('Send Reset Link') }}</span>
            <span x-show="$fetching()" x-cloak class="inline-flex items-center gap-2">
                <svg class="w-4 h-4 animate-spin-slow" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                {{ __('Sending...') }}
            </span>
        </button>
    </form>

    {{-- Resend prompt after success --}}
    <div x-show="submitted" x-cloak class="mt-4">
        <p class="text-sm text-on-surface text-center">
            {{ __('Didn\'t receive the email? Check your spam folder or') }}
            <button
                type="button"
                @click="submitted = false; submitting = false"
                class="text-primary hover:text-primary-hover font-medium transition-colors"
            >
                {{ __('try again') }}
            </button>.
        </p>
    </div>
</div>
@endsection

@section('tenant-notice')
    <p class="text-sm text-on-surface mt-2">
        {{ __('Reset your DancyMeals account password.') }}
    </p>
    <p class="text-xs text-on-surface/60 mt-1">
        {{ __('Powered by') }}
        <a href="{{ config('app.url') }}" class="text-primary hover:text-primary-hover font-medium">
            {{ config('app.name', 'DancyMeals') }}
        </a>
    </p>
@endsection

@section('footer')
    <a x-data x-navigate href="{{ route('login') }}" class="text-primary hover:text-primary-hover font-medium transition-colors">
        {{ __('Back to sign in') }}
    </a>
@endsection
