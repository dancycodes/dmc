{{--
    Manual Payout Task Queue
    --------------------------
    F-065: Displays a queue of failed Flutterwave transfers requiring manual admin intervention.

    BR-199: Only failed Flutterwave transfers appear in this queue
    BR-200: "Manually completed" requires a reference number or note as proof
    BR-201: Retry initiates a new Flutterwave transfer with the same parameters
    BR-202: Maximum 3 automatic retry attempts; after that, only manual completion
    BR-203: Cook's wallet balance is not re-credited on failure
    BR-204: All queue actions (mark complete, retry) are logged
    BR-205: Completed/resolved tasks move to a "Completed" tab
    BR-206: Badge count on sidebar indicating pending tasks

    UI/UX:
    - Task list with clear priority: oldest failed transfers at the top
    - Each task card shows: cook avatar, cook name, amount (large), mobile money number,
      failure reason (red text), requested date, retry count
    - Two action buttons: "Retry Transfer" (primary) and "Mark as Manually Completed" (secondary)
    - "Mark as Manually Completed" opens a modal for reference number input
    - Tabs: Active (pending) / Completed (resolved)
    - Amount formatted: "45,000 XAF"
    - Mobile: card layout, full-width action buttons
    - Breadcrumb: Admin > Payouts
--}}
@extends('layouts.admin')

@section('title', __('Payouts'))
@section('page-title', __('Payouts'))

@section('content')
<div class="space-y-6">
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[['label' => __('Payouts')]]" />

    {{-- Header --}}
    <div>
        <h2 class="text-lg font-semibold text-on-surface-strong">{{ __('Manual Payout Task Queue') }}</h2>
        <p class="text-sm text-on-surface mt-1">{{ __('Manage failed automatic transfers that require manual intervention.') }}</p>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 gap-4">
        {{-- Pending Tasks --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-warning-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-on-surface uppercase tracking-wide truncate">{{ __('Pending') }}</p>
                    <p class="text-2xl font-bold text-warning">{{ number_format($pendingCount) }}</p>
                </div>
            </div>
        </div>

        {{-- Completed Tasks --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-success-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-on-surface uppercase tracking-wide truncate">{{ __('Completed') }}</p>
                    <p class="text-2xl font-bold text-success">{{ number_format($completedCount) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Main content area with tabs, search, and task list --}}
    @fragment('payout-list-content')
    <div id="payout-list-content"
         x-data="{
             search: '{{ addslashes($search ?? '') }}',
             tab: '{{ $tab ?? 'active' }}',
             baseUrl: '{{ url('/vault-entry/payouts') }}',
             showCompleteModal: false,
             selectedTaskId: null,
             selectedTaskCook: '',
             selectedTaskAmount: '',
             reference_number: '',
             resolution_notes: '',
             error: '',
             buildUrl() {
                 let params = new URLSearchParams();
                 if (this.search) params.set('search', this.search);
                 if (this.tab && this.tab !== 'active') params.set('tab', this.tab);
                 let qs = params.toString();
                 return this.baseUrl + (qs ? '?' + qs : '');
             },
             doSearch() {
                 $navigate(this.buildUrl(), { key: 'payout-list', replace: true });
             },
             switchTab(newTab) {
                 this.tab = newTab;
                 this.search = '';
                 $navigate(this.buildUrl(), { key: 'payout-list', replace: true });
             },
             clearSearch() {
                 this.search = '';
                 $navigate(this.buildUrl(), { key: 'payout-list', replace: true });
             },
             openCompleteModal(taskId, cookName, amount) {
                 this.selectedTaskId = taskId;
                 this.selectedTaskCook = cookName;
                 this.selectedTaskAmount = amount;
                 this.reference_number = '';
                 this.resolution_notes = '';
                 this.error = '';
                 this.showCompleteModal = true;
             },
             closeCompleteModal() {
                 this.showCompleteModal = false;
                 this.selectedTaskId = null;
             },
             submitManualComplete() {
                 if (!this.reference_number || this.reference_number.length < 3) {
                     this.error = '{{ __('A reference number is required (min 3 characters).') }}';
                     return;
                 }
                 this.error = '';
                 $action('/vault-entry/payouts/' + this.selectedTaskId + '/mark-complete', {
                     include: ['reference_number', 'resolution_notes']
                 });
             }
         }"
    >
        {{-- Tabs: Active / Completed --}}
        <div class="flex border-b border-outline dark:border-outline mb-4">
            <button
                @click="switchTab('active')"
                :class="tab === 'active'
                    ? 'border-b-2 border-primary text-primary font-semibold'
                    : 'text-on-surface hover:text-on-surface-strong'"
                class="px-4 py-2.5 text-sm transition-colors relative"
            >
                {{ __('Active') }}
                @if($pendingCount > 0)
                    <span class="ml-1.5 inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-xs font-bold bg-danger text-on-danger">
                        {{ $pendingCount }}
                    </span>
                @endif
            </button>
            <button
                @click="switchTab('completed')"
                :class="tab === 'completed'
                    ? 'border-b-2 border-primary text-primary font-semibold'
                    : 'text-on-surface hover:text-on-surface-strong'"
                class="px-4 py-2.5 text-sm transition-colors"
            >
                {{ __('Completed') }}
                @if($completedCount > 0)
                    <span class="ml-1.5 inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-xs font-bold bg-surface text-on-surface border border-outline">
                        {{ $completedCount }}
                    </span>
                @endif
            </button>
        </div>

        {{-- Search bar --}}
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
            <div class="relative flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-on-surface/50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                <input
                    type="text"
                    x-model="search"
                    @input.debounce.300ms="doSearch()"
                    placeholder="{{ __('Search by cook name, email, phone, or reference...') }}"
                    class="w-full h-10 pl-10 pr-9 border border-outline dark:border-outline rounded-lg text-sm text-on-surface-strong placeholder:text-on-surface/50 bg-surface dark:bg-surface transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                />
                <button
                    x-show="search.length > 0"
                    @click="clearSearch()"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface/50 hover:text-on-surface transition-colors"
                    x-cloak
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                </button>
            </div>
        </div>

        {{-- Task List --}}
        @if($tasks->count() > 0)
            <div class="space-y-3">
                @foreach($tasks as $task)
                    <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline overflow-hidden {{ $task->isPending() ? 'border-l-4 border-l-warning' : '' }}">
                        <div class="p-4 sm:p-5">
                            {{-- Top row: cook info + amount --}}
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-center gap-3 min-w-0">
                                    {{-- Cook avatar --}}
                                    <div class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center text-primary font-semibold text-sm shrink-0">
                                        {{ strtoupper(mb_substr($task->cook?->name ?? '?', 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <a href="{{ url('/vault-entry/payouts/'.$task->id) }}" class="text-sm font-semibold text-on-surface-strong hover:text-primary transition-colors truncate block" x-navigate>
                                            {{ $task->cook?->name ?? __('Unknown Cook') }}
                                        </a>
                                        <p class="text-xs text-on-surface/60 truncate">
                                            {{ $task->tenant?->name ?? __('Unknown Tenant') }}
                                        </p>
                                    </div>
                                </div>
                                {{-- Amount (prominent) --}}
                                <div class="text-right shrink-0">
                                    <p class="text-lg sm:text-xl font-bold text-on-surface-strong font-mono">
                                        {{ $task->formattedAmount() }}
                                    </p>
                                    <p class="text-xs text-on-surface/60">
                                        {{ $task->paymentMethodLabel() }}
                                    </p>
                                </div>
                            </div>

                            {{-- Details row: phone, failure reason, date, retries --}}
                            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                                {{-- Mobile Money Number --}}
                                <div class="flex items-center gap-2 text-on-surface">
                                    <svg class="w-4 h-4 text-on-surface/40 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                    <span class="font-mono">{{ $task->mobile_money_number }}</span>
                                </div>
                                {{-- Requested date --}}
                                <div class="flex items-center gap-2 text-on-surface/60">
                                    <svg class="w-4 h-4 text-on-surface/40 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"></rect><line x1="16" x2="16" y1="2" y2="6"></line><line x1="8" x2="8" y1="2" y2="6"></line><line x1="3" x2="21" y1="10" y2="10"></line></svg>
                                    <span>{{ __('Requested') }}: {{ $task->requested_at?->format('M d, Y') }}</span>
                                </div>
                            </div>

                            {{-- Failure reason (highlighted warning) --}}
                            @if($task->isPending())
                                <div class="mt-3 p-3 rounded-lg bg-danger-subtle/30 dark:bg-danger-subtle/20 border border-danger/20">
                                    <div class="flex items-start gap-2">
                                        <svg class="w-4 h-4 text-danger shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" x2="12" y1="8" y2="12"></line><line x1="12" x2="12.01" y1="16" y2="16"></line></svg>
                                        <p class="text-sm text-danger">{{ $task->failure_reason }}</p>
                                    </div>
                                </div>
                            @endif

                            {{-- Retry count indicator --}}
                            @if($task->retry_count > 0)
                                <div class="mt-2 flex items-center gap-2">
                                    <span class="text-xs font-medium {{ $task->retry_count >= \App\Models\PayoutTask::MAX_RETRIES ? 'text-danger' : 'text-warning' }}">
                                        {{ __(':count/:max retries used', ['count' => $task->retry_count, 'max' => \App\Models\PayoutTask::MAX_RETRIES]) }}
                                    </span>
                                    @if($task->last_retry_at)
                                        <span class="text-xs text-on-surface/40">
                                            {{ __('Last retry') }}: {{ $task->last_retry_at->diffForHumans() }}
                                        </span>
                                    @endif
                                </div>
                            @endif

                            {{-- Resolution info for completed tasks --}}
                            @if($task->isResolved())
                                <div class="mt-3 p-3 rounded-lg bg-success-subtle/30 dark:bg-success-subtle/20 border border-success/20">
                                    <div class="flex items-start gap-2">
                                        <svg class="w-4 h-4 text-success shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                                        <div>
                                            <p class="text-sm font-medium text-success">{{ $task->statusLabel() }}</p>
                                            @if($task->reference_number)
                                                <p class="text-xs text-on-surface/60 mt-0.5">{{ __('Ref') }}: {{ $task->reference_number }}</p>
                                            @endif
                                            @if($task->completedByUser)
                                                <p class="text-xs text-on-surface/60">{{ __('By') }}: {{ $task->completedByUser->name }} &mdash; {{ $task->completed_at?->format('M d, Y H:i') }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- Action buttons (only for pending tasks) --}}
                            @if($task->isPending())
                                <div class="mt-4 flex flex-col sm:flex-row gap-2">
                                    {{-- Retry Transfer button --}}
                                    @if($task->canRetry())
                                        <button
                                            @click="$action('{{ url('/vault-entry/payouts/'.$task->id.'/retry') }}')"
                                            class="inline-flex items-center justify-center gap-2 h-10 px-5 text-sm rounded-lg font-semibold bg-primary text-on-primary hover:bg-primary-hover transition-all duration-200"
                                        >
                                            <span x-show="!$fetching()">
                                                <svg class="w-4 h-4 inline-block -mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
                                                {{ __('Retry Transfer') }}
                                            </span>
                                            <span x-show="$fetching()" x-cloak>
                                                <svg class="w-4 h-4 animate-spin inline-block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" stroke-dasharray="31.4 31.4" stroke-dashoffset="10"></circle></svg>
                                                {{ __('Retrying...') }}
                                            </span>
                                        </button>
                                    @else
                                        <span class="inline-flex items-center justify-center gap-2 h-10 px-5 text-sm rounded-lg font-semibold bg-surface text-on-surface/40 border border-outline cursor-not-allowed" title="{{ __('Maximum retry attempts reached') }}">
                                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
                                            {{ __('Retry Transfer') }}
                                        </span>
                                    @endif

                                    {{-- Mark as Manually Completed button --}}
                                    <button
                                        @click="openCompleteModal({{ $task->id }}, '{{ addslashes($task->cook?->name ?? __('Unknown Cook')) }}', '{{ $task->formattedAmount() }}')"
                                        class="inline-flex items-center justify-center gap-2 h-10 px-5 text-sm rounded-lg font-semibold border border-outline text-on-surface hover:bg-surface transition-all duration-200"
                                    >
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                                        {{ __('Mark as Manually Completed') }}
                                    </button>

                                    {{-- View Details link --}}
                                    <a
                                        href="{{ url('/vault-entry/payouts/'.$task->id) }}"
                                        x-navigate
                                        class="inline-flex items-center justify-center gap-2 h-10 px-5 text-sm rounded-lg font-semibold text-primary hover:text-primary-hover transition-colors"
                                    >
                                        {{ __('View Details') }}
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if($tasks->hasPages())
                <div class="mt-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <p class="text-sm text-on-surface/60">
                        {{ __('Showing :from-:to of :total tasks', [
                            'from' => $tasks->firstItem(),
                            'to' => $tasks->lastItem(),
                            'total' => $tasks->total(),
                        ]) }}
                    </p>
                    <div x-data x-navigate>
                        {{ $tasks->links() }}
                    </div>
                </div>
            @else
                <p class="mt-4 text-sm text-on-surface/60">
                    {{ __('Showing :from-:to of :total tasks', [
                        'from' => $tasks->firstItem() ?? 0,
                        'to' => $tasks->lastItem() ?? 0,
                        'total' => $tasks->total(),
                    ]) }}
                </p>
            @endif

        @elseif(!empty($search))
            {{-- No results from search --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-8 sm:p-12 text-center">
                <svg class="w-12 h-12 mx-auto text-on-surface/30 mb-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                <p class="text-on-surface font-medium">{{ __('No payout tasks match your search.') }}</p>
                <p class="text-sm text-on-surface/60 mt-1">{{ __('Try adjusting your search criteria.') }}</p>
                <button
                    @click="clearSearch()"
                    class="mt-4 inline-flex items-center gap-2 h-10 px-5 text-sm rounded-lg font-semibold border border-outline text-on-surface hover:bg-surface-alt transition-all duration-200"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                    {{ __('Clear Search') }}
                </button>
            </div>
        @else
            {{-- Empty state --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-8 sm:p-12 text-center">
                @if($tab === 'completed')
                    <svg class="w-12 h-12 mx-auto text-on-surface/30 mb-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"></rect><line x1="2" x2="22" y1="10" y2="10"></line></svg>
                    <p class="text-on-surface font-medium">{{ __('No completed payout tasks yet.') }}</p>
                    <p class="text-sm text-on-surface/60 mt-1">{{ __('Completed payouts will appear here for audit purposes.') }}</p>
                @else
                    <svg class="w-12 h-12 mx-auto text-success/50 mb-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <p class="text-on-surface font-medium">{{ __('No pending payout tasks. All transfers are up to date.') }}</p>
                    <p class="text-sm text-on-surface/60 mt-1">{{ __('Failed transfers will appear here when they need manual intervention.') }}</p>
                @endif
            </div>
        @endif

        {{-- Mark as Manually Completed Modal --}}
        <div
            x-show="showCompleteModal"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
            x-cloak
            @keydown.escape.window="closeCompleteModal()"
        >
            <div
                x-show="showCompleteModal"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                @click.stop
                class="w-full max-w-lg bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-dropdown"
            >
                {{-- Modal Header --}}
                <div class="flex items-center justify-between p-5 border-b border-outline dark:border-outline">
                    <h3 class="text-lg font-semibold text-on-surface-strong">{{ __('Mark as Manually Completed') }}</h3>
                    <button @click="closeCompleteModal()" class="w-8 h-8 rounded-lg flex items-center justify-center text-on-surface hover:bg-surface transition-colors">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="p-5 space-y-4">
                    {{-- Task summary --}}
                    <div class="p-3 rounded-lg bg-info-subtle/30 dark:bg-info-subtle/20 border border-info/20">
                        <p class="text-sm text-on-surface">
                            {{ __('You are marking a payout of') }}
                            <span class="font-bold text-on-surface-strong" x-text="selectedTaskAmount"></span>
                            {{ __('to') }}
                            <span class="font-semibold text-on-surface-strong" x-text="selectedTaskCook"></span>
                            {{ __('as manually completed.') }}
                        </p>
                    </div>

                    {{-- Error display --}}
                    <div x-show="error" class="p-3 rounded-lg bg-danger-subtle/30 border border-danger/20" x-cloak>
                        <p class="text-sm text-danger" x-text="error"></p>
                    </div>

                    {{-- Reference number --}}
                    <div>
                        <label class="block text-sm font-medium text-on-surface-strong mb-1.5">
                            {{ __('Reference Number') }} <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            x-model="reference_number"
                            x-name="reference_number"
                            placeholder="{{ __('Enter the manual transfer reference number...') }}"
                            class="w-full h-10 px-3 border border-outline dark:border-outline rounded-lg text-sm text-on-surface-strong placeholder:text-on-surface/50 bg-surface dark:bg-surface transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                        />
                        <p x-message="reference_number" class="text-sm text-danger mt-1"></p>
                        <p class="text-xs text-on-surface/60 mt-1">{{ __('The reference number from the manual payment (bank transfer, mobile money portal, etc.)') }}</p>
                    </div>

                    {{-- Resolution notes (optional) --}}
                    <div>
                        <label class="block text-sm font-medium text-on-surface-strong mb-1.5">
                            {{ __('Notes') }} <span class="text-on-surface/40">({{ __('optional') }})</span>
                        </label>
                        <textarea
                            x-model="resolution_notes"
                            x-name="resolution_notes"
                            rows="3"
                            placeholder="{{ __('Any additional notes about the manual payment...') }}"
                            class="w-full px-3 py-2 border border-outline dark:border-outline rounded-lg text-sm text-on-surface-strong placeholder:text-on-surface/50 bg-surface dark:bg-surface transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary resize-none"
                        ></textarea>
                        <p x-message="resolution_notes" class="text-sm text-danger mt-1"></p>
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="flex items-center justify-end gap-3 p-5 border-t border-outline dark:border-outline">
                    <button
                        @click="closeCompleteModal()"
                        class="h-10 px-5 text-sm rounded-lg font-semibold border border-outline text-on-surface hover:bg-surface transition-all duration-200"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button
                        @click="submitManualComplete()"
                        class="inline-flex items-center gap-2 h-10 px-5 text-sm rounded-lg font-semibold bg-primary text-on-primary hover:bg-primary-hover transition-all duration-200"
                    >
                        <span x-show="!$fetching()">{{ __('Confirm Completion') }}</span>
                        <span x-show="$fetching()" x-cloak>
                            <svg class="w-4 h-4 animate-spin inline-block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" stroke-dasharray="31.4 31.4" stroke-dashoffset="10"></circle></svg>
                            {{ __('Processing...') }}
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endfragment
</div>
@endsection
