{{--
    User Detail View & Status Toggle
    ---------------------------------
    F-051: Comprehensive detail page for a single user in the admin panel.

    BR-097: Deactivating a user invalidates all their active sessions immediately
    BR-098: Deactivated users cannot log in; login attempt shows "Your account has been deactivated"
    BR-099: Admins cannot deactivate super-admin accounts (only other super-admins can)
    BR-100: Admins cannot deactivate their own account from the admin panel
    BR-101: Reactivation allows the user to log in again; no data is lost
    BR-102: Status changes are recorded in the activity log with the admin as causer
    BR-103: Wallet balance is displayed but cannot be modified from this page
--}}
@extends('layouts.admin')

@section('title', $user->name . ' — ' . __('User Detail'))
@section('page-title', __('User Detail'))

@section('content')
<div class="space-y-6">
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[
        ['label' => __('Users'), 'url' => '/vault-entry/users'],
        ['label' => $user->name],
    ]" />

    {{-- Toast message --}}
    @if(session('toast'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 5000)"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="p-4 rounded-lg border {{ session('toast.type') === 'success' ? 'bg-success-subtle border-success/30 text-success' : 'bg-danger-subtle border-danger/30 text-danger' }}"
        >
            <div class="flex items-center gap-2">
                @if(session('toast.type') === 'success')
                    <svg class="w-5 h-5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                @endif
                <p class="text-sm font-medium">{{ session('toast.message') }}</p>
            </div>
        </div>
    @endif

    {{-- Header Section: User avatar, name, status badge, action buttons --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div class="flex items-start gap-4">
            {{-- User avatar (large) --}}
            @if($user->profile_photo_path)
                <img
                    src="{{ Storage::url($user->profile_photo_path) }}"
                    alt="{{ $user->name }}"
                    class="w-16 h-16 rounded-xl object-cover shrink-0"
                />
            @else
                <div class="w-16 h-16 rounded-xl bg-primary-subtle flex items-center justify-center text-primary font-bold text-2xl shrink-0">
                    {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                </div>
            @endif
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-xl sm:text-2xl font-bold text-on-surface-strong">{{ $user->name }}</h2>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $user->is_active ? 'bg-success-subtle text-success' : 'bg-outline/20 text-on-surface/60' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current mr-1.5"></span>
                        {{ $user->is_active ? __('Active') : __('Inactive') }}
                    </span>
                </div>
                <p class="text-sm text-on-surface mt-1">{{ $user->email }}</p>
                @if($user->phone)
                    <p class="text-sm text-on-surface mt-0.5 font-mono">+237 {{ $user->phone }}</p>
                @endif
            </div>
        </div>

        {{-- Status toggle button --}}
        <div class="flex flex-wrap items-center gap-2 self-start"
             x-data="{
                 showConfirm: false,
                 toggleUrl: '{{ url('/vault-entry/users/'.$user->id.'/toggle-status') }}'
             }"
        >
            @if($canToggleStatus)
                @if($user->is_active)
                    {{-- Deactivate button --}}
                    <button
                        @click="showConfirm = true"
                        class="h-9 px-4 text-sm rounded-lg font-semibold border border-danger/30 text-danger bg-danger-subtle hover:bg-danger hover:text-on-danger transition-all duration-200 inline-flex items-center gap-2
                               focus:outline-none focus:ring-2 focus:ring-danger focus:ring-offset-2"
                    >
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" x2="19.07" y1="4.93" y2="19.07"></line></svg>
                        {{ __('Deactivate User') }}
                    </button>
                @else
                    {{-- Reactivate button --}}
                    <button
                        @click="$action(toggleUrl)"
                        class="h-9 px-4 text-sm rounded-lg font-semibold bg-success hover:bg-success/90 text-on-success transition-all duration-200 inline-flex items-center gap-2
                               focus:outline-none focus:ring-2 focus:ring-success focus:ring-offset-2"
                    >
                        <span x-show="!$fetching()" class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                            {{ __('Reactivate User') }}
                        </span>
                        <span x-show="$fetching()" x-cloak class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            {{ __('Processing...') }}
                        </span>
                    </button>
                @endif
            @else
                {{-- Disabled toggle with tooltip --}}
                <div class="relative group">
                    <button
                        disabled
                        class="h-9 px-4 text-sm rounded-lg font-semibold border border-outline text-on-surface/40 bg-surface-alt cursor-not-allowed inline-flex items-center gap-2"
                    >
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        {{ __('Status Locked') }}
                    </button>
                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-1.5 bg-on-surface-strong text-surface text-xs rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                        {{ $toggleDisabledReason }}
                        <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-on-surface-strong"></div>
                    </div>
                </div>
            @endif

            {{-- Deactivation confirmation modal --}}
            @if($canToggleStatus && $user->is_active)
                <template x-teleport="body">
                    <div
                        x-show="showConfirm"
                        x-cloak
                        class="fixed inset-0 z-50 flex items-center justify-center p-4"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                    >
                        {{-- Backdrop --}}
                        <div class="absolute inset-0 bg-on-surface/50 dark:bg-on-surface/70" @click="showConfirm = false"></div>

                        {{-- Modal --}}
                        <div
                            class="relative w-full max-w-md bg-surface dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-lg p-6 z-10"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            @keydown.escape.window="showConfirm = false"
                        >
                            {{-- Warning icon --}}
                            <div class="w-12 h-12 mx-auto rounded-full bg-danger-subtle flex items-center justify-center mb-4">
                                <svg class="w-6 h-6 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                            </div>

                            <h3 class="text-lg font-semibold text-on-surface-strong text-center mb-2">
                                {{ __('Deactivate User') }}
                            </h3>
                            <p class="text-sm text-on-surface text-center mb-6">
                                {{ __('Deactivating this user will immediately log them out and prevent future login. Continue?') }}
                            </p>

                            <div class="flex items-center gap-3">
                                <button
                                    @click="showConfirm = false"
                                    class="flex-1 h-10 px-4 text-sm rounded-lg font-semibold border border-outline text-on-surface hover:bg-surface-alt transition-all duration-200
                                           focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                                >
                                    {{ __('Cancel') }}
                                </button>
                                <button
                                    @click="showConfirm = false; $action(toggleUrl)"
                                    class="flex-1 h-10 px-4 text-sm rounded-lg font-semibold bg-danger hover:bg-danger/90 text-on-danger transition-all duration-200
                                           focus:outline-none focus:ring-2 focus:ring-danger focus:ring-offset-2"
                                >
                                    <span x-show="!$fetching()" class="inline-flex items-center justify-center gap-2">
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" x2="19.07" y1="4.93" y2="19.07"></line></svg>
                                        {{ __('Deactivate') }}
                                    </span>
                                    <span x-show="$fetching()" x-cloak class="inline-flex items-center justify-center gap-2">
                                        <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                        {{ __('Deactivating...') }}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            @endif
        </div>
    </div>

    {{-- Two-column layout: left for profile + roles, right for metrics + wallet --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Left Column --}}
        <div class="space-y-6">
            {{-- Profile Information --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-6">
                <h3 class="text-base font-semibold text-on-surface-strong mb-4">{{ __('Profile Information') }}</h3>

                <div class="space-y-4">
                    {{-- Full Name --}}
                    <div>
                        <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide mb-1">{{ __('Full Name') }}</p>
                        <p class="text-sm text-on-surface-strong">{{ $user->name }}</p>
                    </div>

                    {{-- Email --}}
                    <div>
                        <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide mb-1">{{ __('Email') }}</p>
                        <div class="flex items-center gap-2">
                            <p class="text-sm text-on-surface-strong">{{ $user->email }}</p>
                            @if($user->hasVerifiedEmail())
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-success-subtle text-success">
                                    <svg class="w-3 h-3 mr-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    {{ __('Verified') }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-warning-subtle text-warning">
                                    {{ __('Unverified') }}
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Phone --}}
                    <div>
                        <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide mb-1">{{ __('Phone') }}</p>
                        @if($user->phone)
                            <p class="text-sm text-on-surface-strong font-mono">+237 {{ $user->phone }}</p>
                        @else
                            <p class="text-sm text-on-surface/40 italic">{{ __('Not provided') }}</p>
                        @endif
                    </div>

                    {{-- Preferred Language --}}
                    <div>
                        <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide mb-1">{{ __('Preferred Language') }}</p>
                        <p class="text-sm text-on-surface-strong">
                            {{ $user->preferred_language === 'fr' ? __('French') : __('English') }}
                        </p>
                    </div>

                    {{-- Registration Date --}}
                    <div>
                        <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide mb-1">{{ __('Registration Date') }}</p>
                        <p class="text-sm text-on-surface-strong" title="{{ $user->created_at?->format('Y-m-d H:i:s') }}">
                            {{ $user->created_at?->format('M d, Y \a\t h:i A') }}
                        </p>
                    </div>

                    {{-- Last Login --}}
                    <div>
                        <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide mb-1">{{ __('Last Login') }}</p>
                        @if($user->last_login_at)
                            <p class="text-sm text-on-surface-strong" title="{{ $user->last_login_at->format('Y-m-d H:i:s') }}">
                                {{ $user->last_login_at->diffForHumans() }}
                                <span class="text-on-surface/50">({{ $user->last_login_at->format('M d, Y') }})</span>
                            </p>
                        @else
                            <p class="text-sm text-on-surface/40 italic">{{ __('Never logged in') }}</p>
                        @endif
                    </div>

                    {{-- Account Status --}}
                    <div>
                        <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide mb-1">{{ __('Account Status') }}</p>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $user->is_active ? 'bg-success-subtle text-success' : 'bg-outline/20 text-on-surface/60' }}">
                            <span class="w-1.5 h-1.5 rounded-full bg-current mr-1.5"></span>
                            {{ $user->is_active ? __('Active') : __('Inactive') }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Roles Section --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-6">
                <h3 class="text-base font-semibold text-on-surface-strong mb-4">{{ __('Roles') }}</h3>

                @if(count($userRolesWithTenants) > 0)
                    <div class="space-y-3" x-data x-navigate>
                        @foreach($userRolesWithTenants as $roleInfo)
                            <div class="flex items-center justify-between gap-3 p-3 bg-surface dark:bg-surface rounded-lg border border-outline/50 dark:border-outline/50">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $roleInfo['role_class'] }}">
                                        {{ ucfirst(str_replace('-', ' ', $roleInfo['role'])) }}
                                    </span>
                                    @if($roleInfo['tenant'])
                                        <span class="text-sm text-on-surface truncate" title="{{ $roleInfo['tenant']->name }}">
                                            {{ $roleInfo['tenant']->name }}
                                        </span>
                                    @else
                                        <span class="text-sm text-on-surface/50 italic">
                                            {{ $roleInfo['role'] === 'client' ? __('Global') : __('Platform') }}
                                        </span>
                                    @endif
                                </div>
                                @if($roleInfo['tenant'])
                                    <a
                                        href="{{ url('/vault-entry/tenants/' . $roleInfo['tenant']->slug) }}"
                                        class="text-xs text-primary hover:text-primary-hover font-medium transition-colors inline-flex items-center gap-1 shrink-0"
                                    >
                                        {{ __('View Tenant') }}
                                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-6">
                        <div class="w-10 h-10 mx-auto rounded-full bg-outline/10 flex items-center justify-center mb-2">
                            <svg class="w-5 h-5 text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                        </div>
                        <p class="text-sm text-on-surface/60">{{ __('No roles assigned') }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Right Column --}}
        <div class="space-y-6">
            {{-- Order Summary --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-6">
                <h3 class="text-base font-semibold text-on-surface-strong mb-4">{{ __('Order Summary') }}</h3>

                {{-- Client metrics --}}
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="p-3 bg-surface dark:bg-surface rounded-lg border border-outline/50 dark:border-outline/50">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="w-7 h-7 rounded-full bg-info-subtle flex items-center justify-center">
                                <svg class="w-3.5 h-3.5 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"></path><path d="M3 6h18"></path><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                            </span>
                            <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Total Orders') }}</p>
                        </div>
                        <p class="text-xl font-bold text-on-surface-strong">{{ number_format($clientOrderCount) }}</p>
                    </div>
                    <div class="p-3 bg-surface dark:bg-surface rounded-lg border border-outline/50 dark:border-outline/50">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="w-7 h-7 rounded-full bg-success-subtle flex items-center justify-center">
                                <svg class="w-3.5 h-3.5 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="1" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                            </span>
                            <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Total Spent') }}</p>
                        </div>
                        <p class="text-xl font-bold text-on-surface-strong">{{ __('XAF :amount', ['amount' => number_format($clientTotalSpent)]) }}</p>
                    </div>
                </div>

                {{-- Cook metrics (only if user is a cook) --}}
                @if($isCook)
                    <div class="pt-4 border-t border-outline/50 dark:border-outline/50">
                        <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide mb-3">{{ __('As Cook') }}</p>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-3 bg-surface dark:bg-surface rounded-lg border border-outline/50 dark:border-outline/50">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="w-7 h-7 rounded-full bg-primary-subtle flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"></path><path d="M7 2v20"></path><path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path></svg>
                                    </span>
                                    <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Cook Orders') }}</p>
                                </div>
                                <p class="text-xl font-bold text-on-surface-strong">{{ number_format($cookOrderCount) }}</p>
                            </div>
                            <div class="p-3 bg-surface dark:bg-surface rounded-lg border border-outline/50 dark:border-outline/50">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="w-7 h-7 rounded-full bg-warning-subtle flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"></path><path d="M12 18V6"></path></svg>
                                    </span>
                                    <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Cook Revenue') }}</p>
                                </div>
                                <p class="text-xl font-bold text-on-surface-strong">{{ __('XAF :amount', ['amount' => number_format($cookRevenue)]) }}</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Wallet Section (BR-103) --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-6">
                <h3 class="text-base font-semibold text-on-surface-strong mb-4">{{ __('Wallet') }}</h3>

                @if($walletBalance !== null)
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-full bg-success-subtle flex items-center justify-center">
                            <svg class="w-5 h-5 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"></path><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"></path><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"></path></svg>
                        </span>
                        <div>
                            <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Balance') }}</p>
                            <p class="text-2xl font-bold text-on-surface-strong">{{ __('XAF :amount', ['amount' => number_format($walletBalance)]) }}</p>
                        </div>
                    </div>
                @else
                    <div class="text-center py-4">
                        <div class="w-10 h-10 mx-auto rounded-full bg-outline/10 flex items-center justify-center mb-2">
                            <svg class="w-5 h-5 text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"></path><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"></path><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"></path></svg>
                        </div>
                        <p class="text-sm text-on-surface/60">{{ __('No wallet') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Activity Log Section --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-6">
        <h3 class="text-base font-semibold text-on-surface-strong mb-4">{{ __('Activity Log') }}</h3>

        @fragment('user-activity-log')
        <div id="user-activity-log">
            @if($activities->count() > 0)
                <div class="space-y-4">
                    @foreach($activities as $activity)
                        <div class="flex items-start gap-3">
                            {{-- Causer avatar --}}
                            <div class="w-8 h-8 rounded-full bg-primary-subtle flex items-center justify-center text-primary font-semibold text-xs shrink-0 mt-0.5">
                                @if($activity->causer)
                                    {{ mb_strtoupper(mb_substr($activity->causer->name, 0, 1)) }}
                                @else
                                    <svg class="w-4 h-4 text-on-surface/50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-on-surface-strong">
                                    @if($activity->causer)
                                        <span class="font-medium">{{ $activity->causer->name }}</span>
                                    @else
                                        <span class="font-medium italic text-on-surface/60">{{ __('System') }}</span>
                                    @endif
                                    —
                                    {{ ucfirst($activity->description) }}
                                </p>
                                @if($activity->properties && $activity->properties->count() > 0)
                                    @php
                                        $props = $activity->properties->toArray();
                                        $old = $props['old'] ?? null;
                                        $attributes = $props['attributes'] ?? null;
                                    @endphp
                                    @if($old && $attributes)
                                        <div class="mt-1 flex flex-wrap gap-2">
                                            @foreach($attributes as $key => $newValue)
                                                @if(isset($old[$key]) && $old[$key] !== $newValue && !is_array($old[$key]) && !is_array($newValue))
                                                    <span class="inline-flex items-center text-xs px-2 py-0.5 rounded bg-surface dark:bg-surface border border-outline dark:border-outline text-on-surface">
                                                        <span class="font-medium">{{ $key }}</span>:
                                                        <span class="text-danger line-through mx-1">{{ is_bool($old[$key]) ? ($old[$key] ? 'true' : 'false') : Str::limit((string)$old[$key], 20) }}</span>
                                                        <svg class="w-3 h-3 mx-0.5 text-on-surface/50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                                                        <span class="text-success">{{ is_bool($newValue) ? ($newValue ? 'true' : 'false') : Str::limit((string)$newValue, 20) }}</span>
                                                    </span>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                @endif
                                <p class="text-xs text-on-surface/50 mt-1" title="{{ $activity->created_at?->format('Y-m-d H:i:s') }}">
                                    {{ $activity->created_at?->diffForHumans() }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Activity pagination --}}
                @if($activities->hasPages())
                    <div class="mt-6 pt-4 border-t border-outline dark:border-outline" x-data x-navigate>
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <p class="text-xs text-on-surface/60">
                                {{ __('Showing :from-:to of :total activities', [
                                    'from' => $activities->firstItem(),
                                    'to' => $activities->lastItem(),
                                    'total' => $activities->total(),
                                ]) }}
                            </p>
                            <div class="flex gap-2">
                                @if($activities->previousPageUrl())
                                    <a
                                        href="{{ $activities->previousPageUrl() }}"
                                        x-navigate.key.activity-log
                                        class="h-8 px-3 text-xs rounded-lg font-medium border border-outline text-on-surface hover:bg-surface transition-colors inline-flex items-center gap-1"
                                    >
                                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                                        {{ __('Previous') }}
                                    </a>
                                @endif
                                @if($activities->nextPageUrl())
                                    <a
                                        href="{{ $activities->nextPageUrl() }}"
                                        x-navigate.key.activity-log
                                        class="h-8 px-3 text-xs rounded-lg font-medium border border-outline text-on-surface hover:bg-surface transition-colors inline-flex items-center gap-1"
                                    >
                                        {{ __('Next') }}
                                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            @else
                {{-- Empty state --}}
                <div class="text-center py-8">
                    <div class="w-12 h-12 mx-auto rounded-full bg-outline/10 flex items-center justify-center mb-3">
                        <svg class="w-6 h-6 text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"></path><path d="M14 2v6h6"></path><path d="M16 13H8"></path><path d="M16 17H8"></path><path d="M10 9H8"></path></svg>
                    </div>
                    <p class="text-sm text-on-surface font-medium">{{ __('No activity recorded yet.') }}</p>
                    <p class="text-xs text-on-surface/60 mt-1">{{ __('Activity will appear here when this user performs actions on the platform.') }}</p>
                </div>
            @endif
        </div>
        @endfragment
    </div>

    {{-- Back navigation --}}
    <div x-data x-navigate>
        <a
            href="{{ url('/vault-entry/users') }}"
            class="inline-flex items-center gap-2 text-sm font-medium text-on-surface hover:text-primary transition-colors"
        >
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
            {{ __('Back to User List') }}
        </a>
    </div>
</div>
@endsection
