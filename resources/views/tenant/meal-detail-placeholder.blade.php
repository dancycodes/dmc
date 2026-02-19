{{--
    F-129 Placeholder: Meal Detail View
    This page will be fully implemented by F-129.
    For now, it shows the meal name and a back link.
--}}
@extends('layouts.tenant-public')

@section('title', $meal->name . ' — ' . ($tenant?->name ?? config('app.name')))

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
    <div x-data x-navigate>
        <a href="{{ url('/') }}" class="inline-flex items-center gap-2 text-sm text-primary hover:text-primary-hover font-medium transition-colors duration-200 mb-6">
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
            {{ __('Back to meals') }}
        </a>
    </div>

    <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-8 text-center">
        <div class="w-16 h-16 rounded-full bg-primary-subtle flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="14" rx="1"></rect><rect width="7" height="7" x="3" y="14" rx="1"></rect></svg>
        </div>
        <h1 class="text-2xl sm:text-3xl font-display font-bold text-on-surface-strong">
            {{ $meal->name }}
        </h1>
        <p class="mt-3 text-on-surface/60">
            {{ __('Full meal details coming soon.') }}
        </p>
    </div>
</div>
@endsection
