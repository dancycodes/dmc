{{--
    System Announcement Notifications — List
    -----------------------------------------
    F-195: Admin list view for platform announcements.

    Displays all announcements with status, target audience, and timestamps.
    Admins can create new announcements, edit drafts/scheduled, and cancel scheduled ones.

    BR-311: Only admins can access this
    BR-312: Target types: all_users, all_cooks, all_clients, specific_tenant
    BR-322: Cancel action for scheduled announcements
--}}
@extends('layouts.admin')

@section('title', __('Announcements'))
@section('page-title', __('Announcements'))

@section('content')
<div
    x-data="{
        cancellingId: null,
        confirmCancelId: null,

        confirmCancel(id) {
            this.confirmCancelId = id;
        },
        closeConfirm() {
            this.confirmCancelId = null;
        },
        executeCancel(id, url) {
            this.cancellingId = id;
            this.confirmCancelId = null;
            $action(url, { include: [] });
        }
    }"
    class="space-y-6"
>
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[['label' => __('Announcements')]]" />

    {{-- Header with Create button --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-on-surface-strong">{{ __('System Announcements') }}</h2>
            <p class="text-sm text-on-surface mt-1">{{ __('Send targeted notifications to users, cooks, or specific tenants.') }}</p>
        </div>
        <a href="/vault-entry/announcements/create"
           class="inline-flex items-center gap-2 px-4 py-2 bg-primary hover:bg-primary-hover text-on-primary font-medium text-sm rounded-lg transition-colors shrink-0">
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="5" y2="19"></line><line x1="5" x2="19" y1="12" y2="12"></line></svg>
            {{ __('New Announcement') }}
        </a>
    </div>

    {{-- Summary Cards --}}
    @php
        $totalCount = $announcements->total();
        $sentCount = \App\Models\Announcement::where('status', 'sent')->count();
        $scheduledCount = \App\Models\Announcement::where('status', 'scheduled')->count();
    @endphp
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
        <div class="bg-surface-alt rounded-lg border border-outline p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l19-9-9 19-2-8-8-2z"></path></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-on-surface uppercase tracking-wide truncate">{{ __('Total') }}</p>
                    <p class="text-2xl font-bold text-on-surface-strong">{{ number_format($totalCount) }}</p>
                </div>
            </div>
        </div>
        <div class="bg-surface-alt rounded-lg border border-outline p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-success-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-on-surface uppercase tracking-wide truncate">{{ __('Sent') }}</p>
                    <p class="text-2xl font-bold text-success">{{ number_format($sentCount) }}</p>
                </div>
            </div>
        </div>
        <div class="bg-surface-alt rounded-lg border border-outline p-4 sm:p-5 col-span-2 sm:col-span-1">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-info-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-on-surface uppercase tracking-wide truncate">{{ __('Scheduled') }}</p>
                    <p class="text-2xl font-bold text-info">{{ number_format($scheduledCount) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Announcements Table (desktop) --}}
    @if($announcements->isEmpty())
        <div class="bg-surface-alt rounded-lg border border-outline p-12 text-center">
            <div class="flex flex-col items-center gap-4">
                <span class="w-16 h-16 rounded-full bg-primary-subtle flex items-center justify-center">
                    <svg class="w-8 h-8 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l19-9-9 19-2-8-8-2z"></path></svg>
                </span>
                <div>
                    <h3 class="font-semibold text-on-surface-strong">{{ __('No announcements yet') }}</h3>
                    <p class="text-sm text-on-surface mt-1">{{ __('Create your first announcement to notify users about platform updates.') }}</p>
                </div>
                <a href="/vault-entry/announcements/create"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-primary hover:bg-primary-hover text-on-primary font-medium text-sm rounded-lg transition-colors">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="5" y2="19"></line><line x1="5" x2="19" y1="12" y2="12"></line></svg>
                    {{ __('Create Announcement') }}
                </a>
            </div>
        </div>
    @else
        {{-- Desktop table --}}
        <div class="hidden md:block bg-surface-alt rounded-lg border border-outline overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-outline bg-surface">
                        <th class="text-left px-4 py-3 text-xs font-semibold text-on-surface uppercase tracking-wide">{{ __('Content') }}</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-on-surface uppercase tracking-wide">{{ __('Target') }}</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-on-surface uppercase tracking-wide">{{ __('Status') }}</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-on-surface uppercase tracking-wide">{{ __('Date') }}</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-on-surface uppercase tracking-wide">{{ __('By') }}</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-on-surface uppercase tracking-wide">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline">
                    @foreach($announcements as $announcement)
                        <tr class="hover:bg-surface transition-colors">
                            <td class="px-4 py-3 max-w-xs">
                                <p class="text-on-surface-strong font-medium truncate" title="{{ $announcement->content }}">
                                    {{ $announcement->getContentPreview(80) }}
                                </p>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium bg-primary-subtle text-primary">
                                    {{ $announcement->getTargetLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($announcement->status === \App\Models\Announcement::STATUS_SENT)
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-success-subtle text-success">
                                        <span class="w-1.5 h-1.5 rounded-full bg-success"></span>
                                        {{ __('Sent') }}
                                    </span>
                                @elseif($announcement->status === \App\Models\Announcement::STATUS_SCHEDULED)
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-info-subtle text-info">
                                        <span class="w-1.5 h-1.5 rounded-full bg-info"></span>
                                        {{ __('Scheduled') }}
                                    </span>
                                @elseif($announcement->status === \App\Models\Announcement::STATUS_CANCELLED)
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-surface text-on-surface border border-outline">
                                        <span class="w-1.5 h-1.5 rounded-full bg-on-surface/30"></span>
                                        {{ __('Cancelled') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-warning-subtle text-warning">
                                        <span class="w-1.5 h-1.5 rounded-full bg-warning"></span>
                                        {{ __('Draft') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-on-surface text-xs">
                                @if($announcement->status === \App\Models\Announcement::STATUS_SENT)
                                    {{ $announcement->sent_at?->format('M d, Y H:i') ?? '-' }}
                                @elseif($announcement->status === \App\Models\Announcement::STATUS_SCHEDULED)
                                    {{ $announcement->scheduled_at?->format('M d, Y H:i') ?? '-' }}
                                @else
                                    {{ $announcement->created_at->format('M d, Y H:i') }}
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-on-surface text-xs">
                                {{ $announcement->creator?->name ?? '-' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if($announcement->canBeEdited())
                                        <a href="/vault-entry/announcements/{{ $announcement->id }}/edit"
                                           class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-on-surface bg-surface border border-outline rounded hover:bg-surface-alt transition-colors">
                                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                            {{ __('Edit') }}
                                        </a>
                                    @endif
                                    @if($announcement->canBeCancelled())
                                        <button
                                            type="button"
                                            @click="confirmCancel({{ $announcement->id }})"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-danger bg-danger-subtle border border-danger/20 rounded hover:bg-danger/20 transition-colors">
                                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" x2="9" y1="9" y2="15"></line><line x1="9" x2="15" y1="9" y2="15"></line></svg>
                                            {{ __('Cancel') }}
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile cards --}}
        <div class="md:hidden space-y-3">
            @foreach($announcements as $announcement)
                <div class="bg-surface-alt rounded-lg border border-outline p-4">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <p class="text-on-surface-strong font-medium text-sm leading-snug line-clamp-2">
                            {{ $announcement->getContentPreview(100) }}
                        </p>
                        @if($announcement->status === \App\Models\Announcement::STATUS_SENT)
                            <span class="shrink-0 inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-success-subtle text-success">
                                {{ __('Sent') }}
                            </span>
                        @elseif($announcement->status === \App\Models\Announcement::STATUS_SCHEDULED)
                            <span class="shrink-0 inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-info-subtle text-info">
                                {{ __('Scheduled') }}
                            </span>
                        @elseif($announcement->status === \App\Models\Announcement::STATUS_CANCELLED)
                            <span class="shrink-0 inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-surface text-on-surface border border-outline">
                                {{ __('Cancelled') }}
                            </span>
                        @else
                            <span class="shrink-0 inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-warning-subtle text-warning">
                                {{ __('Draft') }}
                            </span>
                        @endif
                    </div>
                    <div class="flex items-center gap-3 text-xs text-on-surface mb-3">
                        <span class="bg-primary-subtle text-primary px-2 py-0.5 rounded">{{ $announcement->getTargetLabel() }}</span>
                        <span>{{ $announcement->creator?->name ?? '-' }}</span>
                        <span>
                            @if($announcement->status === \App\Models\Announcement::STATUS_SENT)
                                {{ $announcement->sent_at?->format('M d, Y H:i') ?? '-' }}
                            @elseif($announcement->status === \App\Models\Announcement::STATUS_SCHEDULED)
                                {{ $announcement->scheduled_at?->format('M d, Y H:i') ?? '-' }}
                            @else
                                {{ $announcement->created_at->format('M d, Y H:i') }}
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($announcement->canBeEdited())
                            <a href="/vault-entry/announcements/{{ $announcement->id }}/edit"
                               class="flex-1 text-center px-3 py-2 text-xs font-medium text-on-surface bg-surface border border-outline rounded-lg hover:bg-surface-alt transition-colors">
                                {{ __('Edit') }}
                            </a>
                        @endif
                        @if($announcement->canBeCancelled())
                            <button
                                type="button"
                                @click="confirmCancel({{ $announcement->id }})"
                                class="flex-1 px-3 py-2 text-xs font-medium text-danger bg-danger-subtle border border-danger/20 rounded-lg hover:bg-danger/20 transition-colors">
                                {{ __('Cancel Schedule') }}
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($announcements->hasPages())
            <div class="flex justify-center">
                {{ $announcements->links() }}
            </div>
        @endif
    @endif

    {{-- Cancel Confirmation Modal --}}
    <div
        x-show="confirmCancelId !== null"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        x-cloak
    >
        <div class="absolute inset-0 bg-black/50" @click="closeConfirm()"></div>
        <div
            class="relative bg-surface rounded-xl border border-outline shadow-dropdown p-6 max-w-sm w-full"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
        >
            <div class="flex flex-col items-center gap-4 text-center">
                <div class="w-12 h-12 rounded-full bg-warning-subtle flex items-center justify-center">
                    <svg class="w-6 h-6 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" x2="12" y1="9" y2="13"></line><line x1="12" x2="12.01" y1="17" y2="17"></line></svg>
                </div>
                <div>
                    <h3 class="font-semibold text-on-surface-strong">{{ __('Cancel Announcement?') }}</h3>
                    <p class="text-sm text-on-surface mt-1">{{ __('This will prevent the scheduled announcement from being sent. This action cannot be undone.') }}</p>
                </div>
                <div class="flex gap-3 w-full">
                    <button
                        type="button"
                        @click="closeConfirm()"
                        class="flex-1 px-4 py-2.5 text-sm font-medium text-on-surface bg-surface border border-outline rounded-lg hover:bg-surface-alt transition-colors">
                        {{ __('Keep It') }}
                    </button>
                    <button
                        type="button"
                        @click="executeCancel(confirmCancelId, '/vault-entry/announcements/' + confirmCancelId + '/cancel')"
                        :disabled="cancellingId !== null"
                        class="flex-1 px-4 py-2.5 text-sm font-medium text-on-danger bg-danger hover:bg-danger/90 rounded-lg transition-colors disabled:opacity-50">
                        <span x-show="cancellingId === null">{{ __('Cancel Schedule') }}</span>
                        <span x-show="cancellingId !== null">{{ __('Cancelling...') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
