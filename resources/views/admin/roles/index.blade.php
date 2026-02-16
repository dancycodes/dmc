{{--
    Role List View (Stub)
    ---------------------
    F-052: Stub page for role list.
    Full implementation in F-053: Role List View.

    Shows existing roles with basic info. Will be enhanced in F-053.
--}}
@extends('layouts.admin')

@section('title', __('Roles'))
@section('page-title', __('Roles'))

@section('content')
<div class="space-y-6">
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[
        ['label' => __('Roles')]
    ]" />

    {{-- Header with Create button --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <p class="text-sm text-on-surface">
                {{ __('Manage platform roles and their permissions.') }}
            </p>
        </div>
        @can('can-manage-roles')
            <a
                href="{{ url('/vault-entry/roles/create') }}"
                class="h-10 px-5 text-sm rounded-lg font-semibold bg-primary text-on-primary hover:bg-primary-hover transition-all duration-200 inline-flex items-center gap-2 shrink-0
                       focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 active:scale-[0.98]"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                {{ __('Create Role') }}
            </a>
        @endcan
    </div>

    {{-- Roles Table --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-outline dark:border-outline">
                        <th class="text-left px-4 py-3 font-semibold text-on-surface-strong">{{ __('Role') }}</th>
                        <th class="text-left px-4 py-3 font-semibold text-on-surface-strong hidden sm:table-cell">{{ __('Description') }}</th>
                        <th class="text-left px-4 py-3 font-semibold text-on-surface-strong hidden md:table-cell">{{ __('Type') }}</th>
                        <th class="text-left px-4 py-3 font-semibold text-on-surface-strong hidden md:table-cell">{{ __('Guard') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline dark:divide-outline">
                    @foreach($roles as $role)
                        <tr class="hover:bg-surface dark:hover:bg-surface transition-colors duration-200">
                            <td class="px-4 py-3">
                                <div>
                                    <span class="font-medium text-on-surface-strong">{{ $role->name_en ?? $role->name }}</span>
                                    @if($role->name_fr)
                                        <span class="text-on-surface/60 ml-1">({{ $role->name_fr }})</span>
                                    @endif
                                </div>
                                <span class="text-xs text-on-surface/50 font-mono">{{ $role->name }}</span>
                            </td>
                            <td class="px-4 py-3 text-on-surface hidden sm:table-cell">
                                {{ $role->description ? \Illuminate\Support\Str::limit($role->description, 60) : '—' }}
                            </td>
                            <td class="px-4 py-3 hidden md:table-cell">
                                @if($role->is_system)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-warning-subtle text-warning">
                                        {{ __('System') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-info-subtle text-info">
                                        {{ __('Custom') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-on-surface/60 font-mono text-xs hidden md:table-cell">
                                {{ $role->guard_name }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($roles->isEmpty())
            <div class="px-4 py-12 text-center">
                <svg class="w-12 h-12 mx-auto text-on-surface/30 mb-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"></path></svg>
                <p class="text-on-surface/60 font-medium">{{ __('No roles found.') }}</p>
            </div>
        @endif
    </div>
</div>
@endsection
