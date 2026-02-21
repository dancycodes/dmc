{{--
    Cook Managers / Team Page
    -------------------------
    F-209: Cook Creates Manager Role

    Features:
    - Invite manager by email with validation
    - Manager list with avatar, name, email, date added, remove button
    - Confirmation dialog before removing a manager
    - Empty state with CTA
    - All interactions via Gale (no page reloads)
    - Mobile-first responsive layout
    - Light/dark mode support
    - All text localized with __()

    BR-462: Only cooks can manage managers
    BR-463: Only existing users by email
    BR-464: Assigns manager role scoped to this tenant
    BR-465: Unlimited managers allowed
    BR-466: Removal revokes only this tenant's manager role
    BR-467: Duplicate check prevents double-invitation
    BR-468: Cook cannot invite themselves
    BR-469: List shows name, email, date added, remove
    BR-470: Actions logged via Spatie Activitylog
    BR-471: All text uses __() localization
    BR-472: All interactions via Gale
--}}
@extends('layouts.cook-dashboard')

@section('title', __('Team'))
@section('page-title', __('Team'))

@section('content')
<div
    class="max-w-4xl mx-auto"
    x-data="{
        email: '',
        confirmRemoveId: null,
        confirmRemoveName: '',

        openConfirmRemove(id, name) {
            this.confirmRemoveId = id;
            this.confirmRemoveName = name;
        },
        cancelRemove() {
            this.confirmRemoveId = null;
            this.confirmRemoveName = '';
        },
        executeRemove() {
            if (this.confirmRemoveId) {
                $action('/dashboard/managers/' + this.confirmRemoveId, { method: 'DELETE' });
                this.cancelRemove();
            }
        }
    }"
    x-sync="['email']"
>
    {{-- Page header --}}
    <div class="mb-6">
        <h2 class="text-xl font-display font-bold text-on-surface-strong">{{ __('Team') }}</h2>
        <p class="mt-1 text-sm text-on-surface">
            {{ __('Invite team members to help you manage your business.') }}
        </p>
    </div>

    {{-- Invite Form --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-4 sm:p-6 shadow-card mb-8">
        <h3 class="text-base font-semibold text-on-surface-strong mb-4">
            {{ __('Invite a Manager') }}
        </h3>
        <form @submit.prevent="$action('{{ route('cook.managers.invite') }}', { include: ['email'] })">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <label for="manager-email" class="block text-sm font-medium text-on-surface mb-1.5">
                        {{ __('Email Address') }} <span class="text-danger">*</span>
                    </label>
                    <input
                        id="manager-email"
                        type="email"
                        x-name="email"
                        maxlength="255"
                        placeholder="{{ __('name@example.com') }}"
                        class="w-full px-3 py-2.5 bg-surface dark:bg-surface text-on-surface border border-outline dark:border-outline rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors duration-200"
                        autocomplete="off"
                    >
                    <p x-message="email" class="mt-1.5 text-sm text-danger"></p>
                </div>
                <div class="sm:mt-7 shrink-0">
                    <button
                        type="submit"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-primary text-on-primary rounded-lg font-medium text-sm hover:bg-primary-hover transition-colors duration-200 shadow-sm"
                        :disabled="$fetching()"
                    >
                        <span x-show="!$fetching()">
                            {{-- User Plus icon (Lucide, md=20) --}}
                            <svg class="w-4 h-4 inline -mt-0.5 mr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" x2="19" y1="8" y2="14"></line><line x1="22" x2="16" y1="11" y2="11"></line></svg>
                            {{ __('Invite') }}
                        </span>
                        <span x-show="$fetching()" x-cloak>
                            {{-- Spinner --}}
                            <svg class="animate-spin w-4 h-4 inline mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            {{ __('Inviting...') }}
                        </span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- Managers List --}}
    @fragment('managers-list')
    <div id="managers-list">
        @if($managers->isEmpty())
            {{-- Empty state --}}
            <div class="text-center py-12 px-6 bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-card">
                {{-- Users icon (Lucide, xl=32) --}}
                <div class="w-14 h-14 rounded-full bg-primary-subtle flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                </div>
                <h3 class="text-base font-semibold text-on-surface-strong mb-2">
                    {{ __("You haven't added any team members yet.") }}
                </h3>
                <p class="text-sm text-on-surface max-w-sm mx-auto">
                    {{ __('Invite someone to help manage your business.') }}
                </p>
            </div>
        @else
            {{-- Section header --}}
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-on-surface uppercase tracking-wide">
                    {{ __('Current Managers') }}
                </h3>
                <span class="text-xs text-on-surface bg-surface-alt dark:bg-surface-alt border border-outline px-2.5 py-1 rounded-full font-medium">
                    {{ trans_choice(':count member|:count members', $managers->count(), ['count' => $managers->count()]) }}
                </span>
            </div>

            {{-- Desktop table layout --}}
            <div class="hidden md:block bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-card overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-outline dark:border-outline bg-surface dark:bg-surface">
                            <th class="px-4 py-3 text-left text-xs font-semibold text-on-surface uppercase tracking-wide">
                                {{ __('Manager') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-on-surface uppercase tracking-wide hidden lg:table-cell">
                                {{ __('Email') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-on-surface uppercase tracking-wide">
                                {{ __('Added') }}
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-on-surface uppercase tracking-wide">
                                {{ __('Action') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline dark:divide-outline">
                        @foreach($managers as $manager)
                        <tr class="hover:bg-surface dark:hover:bg-surface transition-colors duration-150">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    {{-- Avatar --}}
                                    <div class="w-9 h-9 rounded-full bg-primary-subtle flex items-center justify-center shrink-0 overflow-hidden">
                                        @if($manager->profile_photo_path)
                                            <img src="{{ $manager->profile_photo_url }}" alt="{{ $manager->name }}" class="w-full h-full object-cover">
                                        @else
                                            <span class="text-sm font-semibold text-primary">
                                                {{ mb_strtoupper(mb_substr($manager->name, 0, 1)) }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-on-surface-strong truncate">
                                            {{ $manager->name }}
                                        </p>
                                        <p class="text-xs text-on-surface truncate lg:hidden">
                                            {{ $manager->email }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 hidden lg:table-cell">
                                <span class="text-sm text-on-surface">{{ $manager->email }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-sm text-on-surface">
                                    {{ \Carbon\Carbon::parse($manager->role_assigned_at)->format('M j, Y') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    @click="openConfirmRemove({{ $manager->id }}, '{{ addslashes($manager->name) }}')"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-danger text-sm font-medium rounded-lg border border-danger/30 hover:bg-danger-subtle transition-colors duration-200"
                                    aria-label="{{ __('Remove :name as manager', ['name' => $manager->name]) }}"
                                >
                                    {{-- User Minus icon (Lucide, sm=16) --}}
                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="22" x2="16" y1="11" y2="11"></line></svg>
                                    {{ __('Remove') }}
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile card layout --}}
            <div class="md:hidden space-y-3">
                @foreach($managers as $manager)
                <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-4 shadow-card">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            {{-- Avatar --}}
                            <div class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center shrink-0 overflow-hidden">
                                @if($manager->profile_photo_path)
                                    <img src="{{ $manager->profile_photo_url }}" alt="{{ $manager->name }}" class="w-full h-full object-cover">
                                @else
                                    <span class="text-sm font-semibold text-primary">
                                        {{ mb_strtoupper(mb_substr($manager->name, 0, 1)) }}
                                    </span>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-on-surface-strong truncate">
                                    {{ $manager->name }}
                                </p>
                                <p class="text-xs text-on-surface truncate mt-0.5">
                                    {{ $manager->email }}
                                </p>
                                <p class="text-xs text-on-surface mt-1">
                                    {{ __('Added') }}: {{ \Carbon\Carbon::parse($manager->role_assigned_at)->format('M j, Y') }}
                                </p>
                            </div>
                        </div>
                        <button
                            @click="openConfirmRemove({{ $manager->id }}, '{{ addslashes($manager->name) }}')"
                            class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 text-danger text-sm font-medium rounded-lg border border-danger/30 hover:bg-danger-subtle transition-colors duration-200"
                        >
                            {{-- User Minus icon --}}
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="22" x2="16" y1="11" y2="11"></line></svg>
                            {{ __('Remove') }}
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>
    @endfragment

    {{-- Remove Confirmation Modal --}}
    <div
        x-show="confirmRemoveId !== null"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
        role="dialog"
        aria-modal="true"
        @keydown.escape.window="cancelRemove()"
        @click.self="cancelRemove()"
    >
        <div
            x-show="confirmRemoveId !== null"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative bg-surface dark:bg-surface rounded-2xl shadow-lg max-w-sm w-full p-6"
        >
            {{-- Warning icon --}}
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-danger-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="22" x2="16" y1="11" y2="11"></line></svg>
                </div>
                <h3 class="text-base font-semibold text-on-surface-strong">{{ __('Remove Manager') }}</h3>
            </div>

            <p class="text-sm text-on-surface mb-6">
                {{ __('Remove') }}
                <strong x-text="confirmRemoveName" class="text-on-surface-strong"></strong>
                {{ __('as manager? They will lose access to your dashboard.') }}
            </p>

            <div class="flex justify-end gap-3">
                <button
                    @click="cancelRemove()"
                    class="px-4 py-2 text-sm font-medium text-on-surface border border-outline rounded-lg hover:bg-surface-alt transition-colors duration-200"
                >
                    {{ __('Cancel') }}
                </button>
                <button
                    @click="executeRemove()"
                    class="px-4 py-2 text-sm font-medium text-on-danger bg-danger rounded-lg hover:opacity-90 transition-opacity duration-200"
                    :disabled="$fetching()"
                >
                    <span x-show="!$fetching()">{{ __('Remove') }}</span>
                    <span x-show="$fetching()" x-cloak>{{ __('Removing...') }}</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
