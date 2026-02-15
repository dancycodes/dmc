@extends('layouts.tenant-public')

@section('title', tenant()?->name ?? __('Home'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
    <div class="text-center">
        <h1 class="text-4xl sm:text-5xl font-display font-bold text-on-surface-strong">
            {{ tenant()?->name ?? __('Welcome') }}
        </h1>
        <p class="mt-4 text-lg text-on-surface max-w-2xl mx-auto">
            {{ __('Delicious home-cooked meals made with love.') }}
        </p>
    </div>
</div>
@endsection
