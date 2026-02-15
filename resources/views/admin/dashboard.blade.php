@extends('layouts.admin')

@section('title', __('Admin Dashboard'))
@section('page-title', __('Dashboard'))

@section('content')
<div class="space-y-6">
    <p class="text-on-surface">
        {{ __('Welcome to the admin panel. Features will be added in upcoming modules.') }}
    </p>
</div>
@endsection
