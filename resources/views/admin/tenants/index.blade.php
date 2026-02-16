{{--
    Tenant List (Stub)
    ------------------
    F-045: Basic tenant list page for navigation and redirect after creation.
    F-046 will implement the full list & search view.
--}}
@extends('layouts.admin')

@section('title', __('Tenants'))
@section('page-title', __('Tenants'))

@section('content')
<div class="space-y-6">
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[['label' => __('Tenants')]]" />

    {{-- Header with create button --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-on-surface-strong">{{ __('All Tenants') }}</h2>
            <p class="text-sm text-on-surface mt-1">{{ __('Manage tenant websites and their configurations.') }}</p>
        </div>
        <a
            href="{{ url('/vault-entry/tenants/create') }}"
            class="h-10 px-5 text-sm rounded-lg font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200 inline-flex items-center gap-2
                   focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 active:scale-[0.98]"
        >
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
            {{ __('Create Tenant') }}
        </a>
    </div>

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

    {{-- Tenant list (stub for F-046) --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline">
        @if(isset($tenants) && $tenants->count() > 0)
            <div class="divide-y divide-outline dark:divide-outline">
                @foreach($tenants as $tenant)
                    <div class="p-4 sm:p-5 flex items-center justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-semibold text-on-surface-strong truncate">
                                    {{ $tenant->name }}
                                </h3>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $tenant->is_active ? 'bg-success-subtle text-success' : 'bg-outline/20 text-on-surface/60' }}">
                                    {{ $tenant->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </div>
                            <p class="text-xs text-on-surface/60 mt-0.5 font-mono">{{ $tenant->slug }}.{{ $mainDomain ?? \App\Services\TenantService::mainDomain() }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="p-8 sm:p-12 text-center">
                <svg class="w-12 h-12 mx-auto text-on-surface/30 mb-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="14" rx="1"></rect><rect width="7" height="7" x="3" y="14" rx="1"></rect></svg>
                <p class="text-on-surface font-medium">{{ __('No tenants yet') }}</p>
                <p class="text-sm text-on-surface/60 mt-1">{{ __('Create your first tenant to get started.') }}</p>
                <a
                    href="{{ url('/vault-entry/tenants/create') }}"
                    class="mt-4 inline-flex items-center gap-2 h-10 px-5 text-sm rounded-lg font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                    {{ __('Create Tenant') }}
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
