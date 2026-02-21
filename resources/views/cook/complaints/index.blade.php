{{--
    F-184: Cook/Manager Complaint List
    -----------------------------------
    Shows complaints filed against this tenant's orders.
    UI/UX: Order ID, client name, category badge, status badge, date filed.
--}}
@extends('layouts.cook-dashboard')

@section('title', __('Complaints'))

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-6" x-data="{
    search: '{{ addslashes($search) }}',
    currentStatus: '{{ $currentStatus }}'
}">
    {{-- Page Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-display font-bold text-on-surface-strong">{{ __('Complaints') }}</h1>
        <p class="text-sm text-on-surface/60 mt-1">{{ __('View and respond to customer complaints') }}</p>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mb-6">
        {{-- Total --}}
        <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline p-4">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                </span>
                <div>
                    <p class="text-xs text-on-surface/50 font-medium">{{ __('Total') }}</p>
                    <p class="text-xl font-bold text-on-surface-strong">{{ $summary['total'] }}</p>
                </div>
            </div>
        </div>

        {{-- Open --}}
        <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline p-4">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-warning-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 8v4"></path><path d="M12 16h.01"></path></svg>
                </span>
                <div>
                    <p class="text-xs text-on-surface/50 font-medium">{{ __('Open') }}</p>
                    <p class="text-xl font-bold text-warning">{{ $summary['open'] }}</p>
                </div>
            </div>
        </div>

        {{-- In Review --}}
        <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline p-4">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-info-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"></path><path d="M12 6v6l4 2"></path></svg>
                </span>
                <div>
                    <p class="text-xs text-on-surface/50 font-medium">{{ __('In Review') }}</p>
                    <p class="text-xl font-bold text-info">{{ $summary['in_review'] }}</p>
                </div>
            </div>
        </div>

        {{-- Escalated --}}
        <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline p-4">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-danger-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                </span>
                <div>
                    <p class="text-xs text-on-surface/50 font-medium">{{ __('Escalated') }}</p>
                    <p class="text-xl font-bold text-danger">{{ $summary['escalated'] }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Search & Filter Bar --}}
    <div class="flex flex-col sm:flex-row gap-3 mb-6">
        <div class="relative flex-1">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-on-surface/40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
            <input
                type="text"
                x-model="search"
                x-on:input.debounce.400ms="$navigate('/dashboard/complaints?search=' + encodeURIComponent(search) + '&status=' + currentStatus, { key: 'complaints', merge: true, replace: true, except: ['page'] })"
                placeholder="{{ __('Search by client, order, description...') }}"
                class="w-full pl-10 pr-4 py-2.5 bg-surface dark:bg-surface border border-outline dark:border-outline rounded-lg text-sm text-on-surface placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors"
            >
        </div>

        {{-- Status Filter --}}
        <div class="flex gap-2 overflow-x-auto pb-1 sm:pb-0">
            <button
                x-on:click="currentStatus = ''; $navigate('/dashboard/complaints?search=' + encodeURIComponent(search) + '&status=', { key: 'complaints', merge: true, replace: true, except: ['page'] })"
                :class="currentStatus === '' ? 'bg-primary text-on-primary' : 'bg-surface-alt dark:bg-surface-alt text-on-surface hover:bg-primary-subtle'"
                class="px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition-colors border border-outline dark:border-outline"
            >{{ __('All') }}</button>
            <button
                x-on:click="currentStatus = 'open'; $navigate('/dashboard/complaints?search=' + encodeURIComponent(search) + '&status=open', { key: 'complaints', merge: true, replace: true, except: ['page'] })"
                :class="currentStatus === 'open' ? 'bg-warning text-on-warning' : 'bg-surface-alt dark:bg-surface-alt text-on-surface hover:bg-warning-subtle'"
                class="px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition-colors border border-outline dark:border-outline"
            >{{ __('Open') }}</button>
            <button
                x-on:click="currentStatus = 'in_review'; $navigate('/dashboard/complaints?search=' + encodeURIComponent(search) + '&status=in_review', { key: 'complaints', merge: true, replace: true, except: ['page'] })"
                :class="currentStatus === 'in_review' ? 'bg-info text-on-info' : 'bg-surface-alt dark:bg-surface-alt text-on-surface hover:bg-info-subtle'"
                class="px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition-colors border border-outline dark:border-outline"
            >{{ __('In Review') }}</button>
            <button
                x-on:click="currentStatus = 'escalated'; $navigate('/dashboard/complaints?search=' + encodeURIComponent(search) + '&status=escalated', { key: 'complaints', merge: true, replace: true, except: ['page'] })"
                :class="currentStatus === 'escalated' ? 'bg-danger text-on-danger' : 'bg-surface-alt dark:bg-surface-alt text-on-surface hover:bg-danger-subtle'"
                class="px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition-colors border border-outline dark:border-outline"
            >{{ __('Escalated') }}</button>
        </div>
    </div>

    {{-- Complaint List --}}
    @fragment('complaints-list')
    <div id="complaints-list">
        @if($complaints->isEmpty())
            {{-- Empty State --}}
            <div class="text-center py-12 bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline">
                <svg class="w-16 h-16 mx-auto text-on-surface/20 mb-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                <h3 class="text-lg font-semibold text-on-surface-strong mb-1">{{ __('No Complaints') }}</h3>
                <p class="text-sm text-on-surface/60">{{ __('There are no complaints to show at this time.') }}</p>
            </div>
        @else
            {{-- Desktop Table --}}
            <div class="hidden md:block bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                            <th class="text-left text-xs font-semibold text-on-surface/60 uppercase tracking-wider px-4 py-3">{{ __('Complaint') }}</th>
                            <th class="text-left text-xs font-semibold text-on-surface/60 uppercase tracking-wider px-4 py-3">{{ __('Client') }}</th>
                            <th class="text-left text-xs font-semibold text-on-surface/60 uppercase tracking-wider px-4 py-3">{{ __('Category') }}</th>
                            <th class="text-left text-xs font-semibold text-on-surface/60 uppercase tracking-wider px-4 py-3">{{ __('Status') }}</th>
                            <th class="text-left text-xs font-semibold text-on-surface/60 uppercase tracking-wider px-4 py-3">{{ __('Date Filed') }}</th>
                            <th class="text-right text-xs font-semibold text-on-surface/60 uppercase tracking-wider px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline dark:divide-outline">
                        @foreach($complaints as $complaint)
                            <tr class="hover:bg-surface-alt/50 dark:hover:bg-surface-alt/50 transition-colors">
                                <td class="px-4 py-3">
                                    <div class="text-sm font-semibold text-on-surface-strong">#{{ $complaint->id }}</div>
                                    <div class="text-xs text-on-surface/50 font-mono">{{ $complaint->order?->order_number ?? '—' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm text-on-surface-strong">{{ $complaint->client?->name ?? __('Unknown') }}</div>
                                    <div class="text-xs text-on-surface/50">{{ $complaint->client?->email ?? '' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    @include('cook.complaints._category-badge', ['category' => $complaint->category])
                                </td>
                                <td class="px-4 py-3">
                                    @include('cook.complaints._status-badge', ['status' => $complaint->status])
                                </td>
                                <td class="px-4 py-3 text-sm text-on-surface/60">
                                    {{ ($complaint->submitted_at ?? $complaint->created_at)->format('M d, Y') }}
                                    <div class="text-xs text-on-surface/40">{{ ($complaint->submitted_at ?? $complaint->created_at)->format('H:i') }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ url('/dashboard/complaints/' . $complaint->id) }}" class="inline-flex items-center gap-1 text-sm text-primary hover:text-primary-hover font-medium transition-colors" x-navigate>
                                        {{ __('View') }}
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile Cards --}}
            <div class="md:hidden space-y-3">
                @foreach($complaints as $complaint)
                    <a href="{{ url('/dashboard/complaints/' . $complaint->id) }}" class="block bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline p-4 hover:border-primary/30 transition-colors" x-navigate>
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <span class="text-sm font-semibold text-on-surface-strong">#{{ $complaint->id }}</span>
                                <span class="text-xs text-on-surface/50 font-mono ml-1">{{ $complaint->order?->order_number ?? '' }}</span>
                            </div>
                            @include('cook.complaints._status-badge', ['status' => $complaint->status])
                        </div>
                        <div class="text-sm text-on-surface mb-2">{{ $complaint->client?->name ?? __('Unknown') }}</div>
                        <div class="flex items-center justify-between">
                            @include('cook.complaints._category-badge', ['category' => $complaint->category])
                            <span class="text-xs text-on-surface/40">{{ ($complaint->submitted_at ?? $complaint->created_at)->diffForHumans() }}</span>
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if($complaints->hasPages())
                <div class="mt-6">
                    {{ $complaints->links() }}
                </div>
            @endif
        @endif
    </div>
    @endfragment
</div>
@endsection
