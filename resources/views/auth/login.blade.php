@extends('layouts.auth')

@section('title', __('Sign In'))

@section('content')
<div x-data="{
    email: '',
    password: '',
    remember: false,
    showPassword: false,
    submitting: false,
}" x-sync>
    <h2 class="text-xl font-semibold text-on-surface-strong mb-6">
        {{ __('Sign in to your account') }}
    </h2>

    <form @submit.prevent="submitting = true; $action('{{ route('login') }}')" class="space-y-4">
        <x-honeypot />

        {{-- Email --}}
        <div class="space-y-1.5">
            <label for="login-email" class="block text-sm font-medium text-on-surface-strong">
                {{ __('Email Address') }}
            </label>
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface/50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                </span>
                <input
                    id="login-email"
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

        {{-- Password --}}
        <div class="space-y-1.5">
            <div class="flex items-center justify-between">
                <label for="login-password" class="block text-sm font-medium text-on-surface-strong">
                    {{ __('Password') }}
                </label>
                <a x-data x-navigate href="{{ route('password.request') }}" class="text-xs text-primary hover:text-primary-hover font-medium transition-colors">
                    {{ __('Forgot password?') }}
                </a>
            </div>
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface/50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </span>
                <input
                    id="login-password"
                    :type="showPassword ? 'text' : 'password'"
                    x-name="password"
                    x-model="password"
                    required
                    autocomplete="current-password"
                    class="w-full h-11 pl-10 pr-11 border border-outline rounded-lg text-sm text-on-surface-strong placeholder:text-on-surface/50 bg-surface dark:bg-surface-alt transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                    placeholder="{{ __('Enter your password') }}"
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

        {{-- Remember Me --}}
        <div class="flex items-center gap-2">
            <input
                id="remember"
                type="checkbox"
                x-model="remember"
                class="rounded border-outline text-primary focus:ring-primary/20 w-4 h-4"
            >
            <label for="remember" class="text-sm text-on-surface">
                {{ __('Remember me') }}
            </label>
        </div>

        {{-- Submit Button --}}
        <button
            type="submit"
            :disabled="submitting"
            class="w-full h-12 rounded-lg font-semibold bg-primary hover:bg-primary-hover text-on-primary text-base transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100"
        >
            <span x-show="!$fetching()">{{ __('Sign In') }}</span>
            <span x-show="$fetching()" x-cloak class="inline-flex items-center gap-2">
                <svg class="w-4 h-4 animate-spin-slow" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                {{ __('Signing in...') }}
            </span>
        </button>
    </form>
</div>
@endsection

@section('tenant-notice')
    <p class="text-sm text-on-surface mt-2">
        {{ __('Log in with your DancyMeals account.') }}
    </p>
    <p class="text-xs text-on-surface/60 mt-1">
        {{ __('Powered by') }}
        <a href="{{ config('app.url') }}" class="text-primary hover:text-primary-hover font-medium">
            {{ config('app.name', 'DancyMeals') }}
        </a>
    </p>
@endsection

@section('footer')
    {{ __("Don't have an account?") }}
    <a x-data x-navigate href="{{ route('register') }}" class="text-primary hover:text-primary-hover font-medium transition-colors">
        {{ __('Create one') }}
    </a>
@endsection
