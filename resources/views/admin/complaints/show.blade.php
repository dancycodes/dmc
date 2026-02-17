{{--
    Complaint Resolution View (Stub)
    ---------------------------------
    F-061: Admin Complaint Resolution — full implementation in F-061.
    This is a placeholder view that shows basic complaint info.
--}}
@extends('layouts.admin')

@section('title', __('Complaint') . ' #' . $complaint->id)
@section('page-title', __('Complaint Details'))

@section('content')
<div class="space-y-6">
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[
        ['label' => __('Complaints'), 'url' => url('/vault-entry/complaints')],
        ['label' => '#' . $complaint->id],
    ]" />

    {{-- Header --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-6">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-lg font-semibold text-on-surface-strong">{{ __('Complaint') }} #{{ $complaint->id }}</h2>
                <p class="text-sm text-on-surface mt-1">{{ __('Submitted') }} {{ $complaint->submitted_at?->format('M d, Y H:i') }}</p>
            </div>
            @include('admin.complaints._status-badge', ['status' => $complaint->status])
        </div>

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Client') }}</p>
                <p class="text-sm text-on-surface-strong mt-1">{{ $complaint->client?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Cook') }}</p>
                <p class="text-sm text-on-surface-strong mt-1">{{ $complaint->cook?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Category') }}</p>
                <div class="mt-1">@include('admin.complaints._category-badge', ['category' => $complaint->category])</div>
            </div>
            <div>
                <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Escalation Reason') }}</p>
                <p class="text-sm text-on-surface-strong mt-1">{{ $complaint->escalationReasonLabel() }}</p>
            </div>
        </div>

        <div class="mt-4">
            <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Description') }}</p>
            <p class="text-sm text-on-surface mt-1">{{ $complaint->description }}</p>
        </div>

        <div class="mt-6 p-4 bg-info-subtle/30 rounded-lg border border-info/20">
            <p class="text-sm text-info font-medium">{{ __('Resolution tools will be available in a future update.') }}</p>
        </div>
    </div>
</div>
@endsection
