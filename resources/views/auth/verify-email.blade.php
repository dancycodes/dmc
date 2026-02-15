@extends('layouts.auth')

@section('title', __('Verify Email'))

@section('content')
<div class="text-center">
    <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-primary-subtle dark:bg-primary-subtle">
        {{-- Mail icon (Lucide) --}}
        <svg class="h-8 w-8 text-primary dark:text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
        </svg>
    </div>
    <h2 class="text-xl font-semibold text-on-surface-strong dark:text-on-surface-strong mb-4">
        {{ __('Verify your email address') }}
    </h2>
    <p class="text-sm text-on-surface dark:text-on-surface mb-6">
        {{ __('A verification link has been sent to your email address.') }}
    </p>
    <p class="text-xs text-on-surface/60 dark:text-on-surface/60">
        {{ __('Please check your inbox and click the verification link to continue.') }}
    </p>
</div>
@endsection
