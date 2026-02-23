@extends('layouts.admin')

@section('title', __('Admin Dashboard'))
@section('page-title', __('Dashboard'))

@section('content')
<div class="space-y-6">
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[['label' => __('Dashboard')]]" />

    {{-- Welcome message --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-6">
        <h2 class="text-xl font-semibold text-on-surface-strong mb-2">
            {{ __('Welcome back, :name', ['name' => auth()->user()->name]) }}
        </h2>
        <p class="text-on-surface">
            {{ __('Manage your platform from here. Use the sidebar to navigate between sections.') }}
        </p>
    </div>

    {{-- Quick stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @php
            $statCards = [
                [
                    'label' => __('Total Tenants'),
                    'value' => $totalTenants,
                    'link' => route('admin.tenants.index'),
                    'icon' => '<rect width="7" height="7" x="3" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="14" rx="1"></rect><rect width="7" height="7" x="3" y="14" rx="1"></rect>',
                    'color' => 'primary',
                ],
                [
                    'label' => __('Total Users'),
                    'value' => $totalUsers,
                    'link' => route('admin.users.index'),
                    'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" x2="19" y1="8" y2="14"></line><line x1="22" x2="16" y1="11" y2="11"></line>',
                    'color' => 'secondary',
                ],
                [
                    'label' => __('Active Orders'),
                    'value' => $activeOrders,
                    'link' => null,
                    'icon' => '<path d="M16 3h5v5"></path><path d="M8 3H3v5"></path><path d="M12 22v-8.3a4 4 0 0 0-1.172-2.872L3 3"></path><path d="m15 9 6-6"></path>',
                    'color' => 'success',
                ],
                [
                    'label' => __('Open Complaints'),
                    'value' => $openComplaints,
                    'link' => route('admin.complaints.index'),
                    'icon' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>',
                    'color' => 'warning',
                ],
            ];
        @endphp

        @foreach($statCards as $stat)
            @if($stat['link'])
                <a href="{{ $stat['link'] }}" class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-5 hover:border-primary transition-colors">
            @else
                <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-5">
            @endif
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-on-surface">{{ $stat['label'] }}</p>
                        <p class="text-2xl font-bold text-on-surface-strong mt-1">{{ number_format($stat['value']) }}</p>
                    </div>
                    <span class="w-10 h-10 rounded-full bg-{{ $stat['color'] }}-subtle flex items-center justify-center">
                        <svg class="w-5 h-5 text-{{ $stat['color'] }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $stat['icon'] !!}</svg>
                    </span>
                </div>
            @if($stat['link'])
                </a>
            @else
                </div>
            @endif
        @endforeach
    </div>
</div>
@endsection
