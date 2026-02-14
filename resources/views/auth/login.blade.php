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
        {{-- Email --}}
        <div>
            <label for="email" class="block text-sm font-medium text-on-surface-strong mb-1">
                {{ __('Email Address') }}
            </label>
            <input
                id="email"
                type="email"
                x-name="email"
                x-model="email"
                required
                autocomplete="email"
                class="w-full rounded-lg border border-outline bg-surface dark:bg-surface-alt px-3 py-2.5 text-on-surface-strong placeholder-on-surface/50 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition"
                placeholder="{{ __('you@example.com') }}"
            >
            <p x-message="email" class="mt-1 text-sm text-danger"></p>
        </div>

        {{-- Password --}}
        <div>
            <div class="flex items-center justify-between mb-1">
                <label for="password" class="block text-sm font-medium text-on-surface-strong">
                    {{ __('Password') }}
                </label>
                <a href="{{ route('password.request') }}" class="text-sm text-primary hover:text-primary-hover">
                    {{ __('Forgot password?') }}
                </a>
            </div>
            <div class="relative">
                <input
                    id="password"
                    :type="showPassword ? 'text' : 'password'"
                    x-name="password"
                    x-model="password"
                    required
                    autocomplete="current-password"
                    class="w-full rounded-lg border border-outline bg-surface dark:bg-surface-alt px-3 py-2.5 pr-10 text-on-surface-strong placeholder-on-surface/50 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition"
                    placeholder="{{ __('Enter your password') }}"
                >
                <button
                    type="button"
                    @click="showPassword = !showPassword"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface hover:text-on-surface-strong transition"
                >
                    <svg x-show="!showPassword" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg x-show="showPassword" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
                </button>
            </div>
            <p x-message="password" class="mt-1 text-sm text-danger"></p>
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

        {{-- Submit --}}
        <button
            type="submit"
            :disabled="submitting"
            class="w-full rounded-lg bg-primary hover:bg-primary-hover text-on-primary font-semibold py-2.5 px-4 transition disabled:opacity-50 disabled:cursor-not-allowed"
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

@section('footer')
    {{ __("Don't have an account?") }}
    <a href="{{ route('register') }}" class="text-primary hover:text-primary-hover font-medium">
        {{ __('Create one') }}
    </a>
@endsection
