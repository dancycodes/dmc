@extends('layouts.auth')

@section('title', __('Reset Password'))

@section('content')
@if($tokenError === 'expired')
    {{-- Error state: expired token (BR-079) --}}
    <div class="text-center py-4">
        <div class="w-14 h-14 rounded-full bg-warning-subtle flex items-center justify-center mx-auto mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-warning" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <h2 class="text-xl font-semibold text-on-surface-strong mb-2">
            {{ __('Link Expired') }}
        </h2>
        <p class="text-sm text-on-surface mb-6">
            {{ __('This password reset link has expired.') }}
        </p>
        <a
            x-data x-navigate
            href="{{ route('password.request') }}"
            class="inline-flex items-center justify-center w-full h-12 rounded-lg font-semibold bg-primary hover:bg-primary-hover text-on-primary text-base transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 active:scale-[0.98]"
        >
            {{ __('Request new reset link') }}
        </a>
    </div>
@elseif($tokenError === 'invalid')
    {{-- Error state: invalid token (BR-079) --}}
    <div class="text-center py-4">
        <div class="w-14 h-14 rounded-full bg-danger-subtle flex items-center justify-center mx-auto mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-danger" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
        </div>
        <h2 class="text-xl font-semibold text-on-surface-strong mb-2">
            {{ __('Invalid Link') }}
        </h2>
        <p class="text-sm text-on-surface mb-6">
            {{ __('This password reset link is invalid.') }}
        </p>
        <a
            x-data x-navigate
            href="{{ route('password.request') }}"
            class="inline-flex items-center justify-center w-full h-12 rounded-lg font-semibold bg-primary hover:bg-primary-hover text-on-primary text-base transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 active:scale-[0.98]"
        >
            {{ __('Request new reset link') }}
        </a>
    </div>
@else
    {{-- Valid token: show password reset form --}}
    <div x-data="{
        token: '{{ $token }}',
        email: '{{ $email }}',
        password: '',
        password_confirmation: '',
        showPassword: false,
        submitting: false,
    }" x-sync>
        <h2 class="text-xl font-semibold text-on-surface-strong mb-2">
            {{ __('Set new password') }}
        </h2>
        <p class="text-sm text-on-surface mb-6">
            {{ __('Enter your new password below.') }}
        </p>

        <form @submit.prevent="submitting = true; $action('{{ route('password.update') }}')" class="space-y-4">

            {{-- Email (read-only for context) --}}
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
                        :value="email"
                        disabled
                        class="w-full h-11 pl-10 pr-3 border border-outline rounded-lg text-sm text-on-surface/60 placeholder:text-on-surface/50 bg-surface-alt dark:bg-surface-alt cursor-not-allowed transition-colors"
                    >
                </div>
            </div>

            {{-- New Password --}}
            <div class="space-y-1.5">
                <label for="reset-password" class="block text-sm font-medium text-on-surface-strong">
                    {{ __('New Password') }}
                </label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface/50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </span>
                    <input
                        id="reset-password"
                        :type="showPassword ? 'text' : 'password'"
                        x-name="password"
                        x-model="password"
                        required
                        autocomplete="new-password"
                        class="w-full h-11 pl-10 pr-11 border border-outline rounded-lg text-sm text-on-surface-strong placeholder:text-on-surface/50 bg-surface dark:bg-surface-alt transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                        placeholder="{{ __('Min. 8 characters') }}"
                    >
                    <button
                        type="button"
                        @click="showPassword = !showPassword"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface/50 hover:text-on-surface-strong transition-colors p-0.5"
                        :aria-label="showPassword ? '{{ __('Hide password') }}' : '{{ __('Show password') }}'"
                    >
                        <svg x-show="!showPassword" xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg x-show="showPassword" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
                    </button>
                </div>
                <p x-message="password" class="text-xs text-danger"></p>
            </div>

            {{-- Confirm Password --}}
            <div class="space-y-1.5">
                <label for="reset-password-confirm" class="block text-sm font-medium text-on-surface-strong">
                    {{ __('Confirm Password') }}
                </label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface/50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/></svg>
                    </span>
                    <input
                        id="reset-password-confirm"
                        :type="showPassword ? 'text' : 'password'"
                        x-name="password_confirmation"
                        x-model="password_confirmation"
                        required
                        autocomplete="new-password"
                        class="w-full h-11 pl-10 pr-3 border border-outline rounded-lg text-sm text-on-surface-strong placeholder:text-on-surface/50 bg-surface dark:bg-surface-alt transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                        placeholder="{{ __('Repeat your password') }}"
                    >
                </div>
                <p x-message="password_confirmation" class="text-xs text-danger"></p>
            </div>

            {{-- Submit Button --}}
            <button
                type="submit"
                :disabled="submitting"
                class="w-full h-12 rounded-lg font-semibold bg-primary hover:bg-primary-hover text-on-primary text-base transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100"
            >
                <span x-show="!$fetching()">{{ __('Reset Password') }}</span>
                <span x-show="$fetching()" x-cloak class="inline-flex items-center gap-2">
                    <svg class="w-4 h-4 animate-spin-slow" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    {{ __('Resetting...') }}
                </span>
            </button>
        </form>
    </div>
@endif
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
