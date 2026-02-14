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

    {{-- Success message --}}
    <div x-show="submitted" x-cloak class="mb-4 rounded-lg bg-success-subtle border border-success/20 p-4">
        <p x-message="_success" class="text-sm text-success font-medium"></p>
    </div>

    <form @submit.prevent="submitting = true; $action('{{ route('password.email') }}')" class="space-y-4">
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

        {{-- Submit --}}
        <button
            type="submit"
            :disabled="submitting"
            class="w-full rounded-lg bg-primary hover:bg-primary-hover text-on-primary font-semibold py-2.5 px-4 transition disabled:opacity-50 disabled:cursor-not-allowed"
        >
            <span x-show="!$fetching()">{{ __('Send Reset Link') }}</span>
            <span x-show="$fetching()" x-cloak class="inline-flex items-center gap-2">
                <svg class="w-4 h-4 animate-spin-slow" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                {{ __('Sending...') }}
            </span>
        </button>
    </form>
</div>
@endsection

@section('footer')
    <a href="{{ route('login') }}" class="text-primary hover:text-primary-hover font-medium">
        {{ __('Back to sign in') }}
    </a>
@endsection
