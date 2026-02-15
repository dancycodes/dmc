@extends('layouts.main-public')

@section('title', __('Home'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
    {{-- Hero Section --}}
    <div class="text-center">
        <h1 class="text-4xl sm:text-5xl font-display font-bold text-on-surface-strong">
            {{ config('app.name', 'DancyMeals') }}
        </h1>
        <p class="mt-4 text-lg text-on-surface max-w-2xl mx-auto">
            {{ __('Your favorite home-cooked meals, delivered.') }}
        </p>
        <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="{{ url('/discover') }}" class="h-12 px-8 text-base rounded-lg font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200 inline-flex items-center gap-2">
                {{-- Search icon --}}
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                {{ __('Discover Cooks') }}
            </a>
            @guest
                <a href="{{ route('register') }}" class="h-12 px-8 text-base rounded-lg font-semibold border border-outline text-on-surface hover:bg-surface-alt transition-all duration-200 inline-flex items-center">
                    {{ __('Get Started') }}
                </a>
            @endguest
        </div>
    </div>
</div>
@endsection
