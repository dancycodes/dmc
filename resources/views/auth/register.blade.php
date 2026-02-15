@extends('layouts.auth')

@section('title', __('Create Account'))

@section('content')
<div x-data="{
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    showPassword: false,
}" x-sync>
    <h2 class="text-xl font-semibold text-on-surface-strong mb-6">
        {{ __('Create your account') }}
    </h2>

    <form @submit.prevent="$action('{{ route('register') }}')" class="space-y-4">
        <x-honeypot />

        {{-- Name --}}
        <div class="space-y-1.5">
            <label for="reg-name" class="block text-sm font-medium text-on-surface-strong">
                {{ __('Full Name') }}
            </label>
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface/50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </span>
                <input
                    id="reg-name"
                    type="text"
                    x-name="name"
                    x-model="name"
                    required
                    maxlength="255"
                    autocomplete="name"
                    class="w-full h-11 pl-10 pr-3 border border-outline rounded-lg text-sm text-on-surface-strong placeholder:text-on-surface/50 bg-surface dark:bg-surface-alt transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                    placeholder="{{ __('Enter your full name') }}"
                >
            </div>
            <p x-message="name" class="text-xs text-danger"></p>
        </div>

        {{-- Email --}}
        <div class="space-y-1.5">
            <label for="reg-email" class="block text-sm font-medium text-on-surface-strong">
                {{ __('Email Address') }}
            </label>
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface/50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                </span>
                <input
                    id="reg-email"
                    type="email"
                    x-name="email"
                    x-model="email"
                    required
                    maxlength="255"
                    autocomplete="email"
                    class="w-full h-11 pl-10 pr-3 border border-outline rounded-lg text-sm text-on-surface-strong placeholder:text-on-surface/50 bg-surface dark:bg-surface-alt transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                    placeholder="{{ __('you@example.com') }}"
                >
            </div>
            <p x-message="email" class="text-xs text-danger"></p>
        </div>

        {{-- Phone --}}
        <div class="space-y-1.5">
            <label for="reg-phone" class="block text-sm font-medium text-on-surface-strong">
                {{ __('Phone Number') }}
            </label>
            <div class="flex">
                <span class="inline-flex items-center gap-1.5 px-3 border border-r-0 border-outline rounded-l-lg bg-surface-alt text-sm text-on-surface font-medium shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-on-surface/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    +237
                </span>
                <input
                    id="reg-phone"
                    type="tel"
                    x-name="phone"
                    x-model="phone"
                    required
                    autocomplete="tel"
                    class="w-full h-11 px-3 border border-outline rounded-r-lg text-sm text-on-surface-strong placeholder:text-on-surface/50 bg-surface dark:bg-surface-alt transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                    placeholder="{{ __('6XXXXXXXX') }}"
                >
            </div>
            <p x-message="phone" class="text-xs text-danger"></p>
        </div>

        {{-- Password --}}
        <div class="space-y-1.5">
            <label for="reg-password" class="block text-sm font-medium text-on-surface-strong">
                {{ __('Password') }}
            </label>
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface/50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </span>
                <input
                    id="reg-password"
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
            <label for="reg-password-confirm" class="block text-sm font-medium text-on-surface-strong">
                {{ __('Confirm Password') }}
            </label>
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface/50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/></svg>
                </span>
                <input
                    id="reg-password-confirm"
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
            class="w-full h-12 rounded-lg font-semibold bg-primary hover:bg-primary-hover text-on-primary text-base transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100"
        >
            <span x-show="!$fetching()">{{ __('Create Account') }}</span>
            <span x-show="$fetching()" x-cloak class="inline-flex items-center gap-2">
                <svg class="w-4 h-4 animate-spin-slow" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                {{ __('Creating...') }}
            </span>
        </button>
    </form>
</div>
@endsection

@section('tenant-notice')
    <p class="text-sm text-on-surface mt-2">
        {{ __('You are creating a DancyMeals account. Use it on any cook\'s site.') }}
    </p>
    <p class="text-xs text-on-surface/60 mt-1">
        {{ __('Powered by') }}
        <a href="{{ config('app.url') }}" class="text-primary hover:text-primary-hover font-medium">
            {{ config('app.name', 'DancyMeals') }}
        </a>
    </p>
@endsection

@section('footer')
    {{ __('Already have an account?') }}
    <a x-data x-navigate href="{{ route('login') }}" class="text-primary hover:text-primary-hover font-medium transition-colors">
        {{ __('Sign in') }}
    </a>
@endsection
