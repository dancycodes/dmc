@extends('layouts.auth')

@section('title', __('Verify Email'))

@section('content')
<div class="text-center">
    <h2 class="text-xl font-semibold text-on-surface-strong mb-4">
        {{ __('Verify your email address') }}
    </h2>
    <p class="text-sm text-on-surface mb-6">
        {{ __('A verification link has been sent to your email address.') }}
    </p>
</div>
@endsection
