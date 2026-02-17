{{--
    Payout Task Detail View
    --------------------------
    F-065: Scenario 4 — Investigating failure details

    Shows full details: cook name, tenant, mobile money number, payment method, amount,
    original withdrawal request date, number of previous retry attempts, detailed
    Flutterwave error response, and cook's withdrawal history.
--}}
@extends('layouts.admin')

@section('title', __('Payout Task') . ' #' . $task->id)
@section('page-title', __('Payout Task Details'))

@section('content')
<div class="space-y-6" x-data="{
    showCompleteModal: false,
    reference_number: '',
    resolution_notes: '',
    error: '',
    openCompleteModal() {
        this.reference_number = '';
        this.resolution_notes = '';
        this.error = '';
        this.showCompleteModal = true;
    },
    closeCompleteModal() {
        this.showCompleteModal = false;
    },
    submitManualComplete() {
        if (!this.reference_number || this.reference_number.length < 3) {
            this.error = '{{ __('A reference number is required (min 3 characters).') }}';
            return;
        }
        this.error = '';
        $action('{{ url('/vault-entry/payouts/'.$task->id.'/mark-complete') }}', {
            include: ['reference_number', 'resolution_notes']
        });
    }
}">
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[
        ['label' => __('Payouts'), 'url' => url('/vault-entry/payouts')],
        ['label' => __('Task') . ' #' . $task->id],
    ]" />

    {{-- Header with action buttons --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-on-surface-strong">
                {{ __('Payout Task') }} #{{ $task->id }}
            </h2>
            <p class="text-sm text-on-surface mt-1">
                {{ __('Requested on :date', ['date' => $task->requested_at?->format('F d, Y \\a\\t H:i')]) }}
            </p>
        </div>

        {{-- Status badge --}}
        <div>
            @if($task->isPending())
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-semibold bg-warning-subtle text-warning">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    {{ __('Pending') }}
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-semibold bg-success-subtle text-success">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    {{ $task->statusLabel() }}
                </span>
            @endif
        </div>
    </div>

    {{-- Main content grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left column: task details --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Payment Details Card --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline overflow-hidden">
                <div class="px-5 py-4 border-b border-outline dark:border-outline">
                    <h3 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wider">{{ __('Payment Details') }}</h3>
                </div>
                <div class="p-5">
                    {{-- Amount (prominent) --}}
                    <div class="text-center mb-6 py-4 bg-surface dark:bg-surface rounded-lg">
                        <p class="text-3xl font-bold text-on-surface-strong font-mono">{{ $task->formattedAmount() }}</p>
                        <p class="text-sm text-on-surface/60 mt-1">{{ $task->paymentMethodLabel() }}</p>
                    </div>

                    {{-- Details grid --}}
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Mobile Money Number') }}</dt>
                            <dd class="text-sm text-on-surface-strong font-mono mt-1">{{ $task->mobile_money_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Payment Method') }}</dt>
                            <dd class="text-sm text-on-surface-strong mt-1">{{ $task->paymentMethodLabel() }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Requested Date') }}</dt>
                            <dd class="text-sm text-on-surface-strong mt-1">{{ $task->requested_at?->format('M d, Y H:i') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Retry Count') }}</dt>
                            <dd class="text-sm mt-1">
                                <span class="{{ $task->retry_count >= \App\Models\PayoutTask::MAX_RETRIES ? 'text-danger font-semibold' : 'text-on-surface-strong' }}">
                                    {{ $task->retry_count }} / {{ \App\Models\PayoutTask::MAX_RETRIES }}
                                </span>
                            </dd>
                        </div>
                        @if($task->flutterwave_reference)
                            <div>
                                <dt class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Flutterwave Reference') }}</dt>
                                <dd class="text-sm text-on-surface-strong font-mono mt-1">{{ $task->flutterwave_reference }}</dd>
                            </div>
                        @endif
                        @if($task->flutterwave_transfer_id)
                            <div>
                                <dt class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Transfer ID') }}</dt>
                                <dd class="text-sm text-on-surface-strong font-mono mt-1">{{ $task->flutterwave_transfer_id }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- Failure Details Card --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline overflow-hidden">
                <div class="px-5 py-4 border-b border-outline dark:border-outline">
                    <h3 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wider">{{ __('Failure Details') }}</h3>
                </div>
                <div class="p-5">
                    {{-- Failure reason --}}
                    <div class="p-4 rounded-lg bg-danger-subtle/30 dark:bg-danger-subtle/20 border border-danger/20 mb-4">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-danger shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" x2="12" y1="8" y2="12"></line><line x1="12" x2="12.01" y1="16" y2="16"></line></svg>
                            <div>
                                <p class="text-sm font-semibold text-danger">{{ __('Failure Reason') }}</p>
                                <p class="text-sm text-danger mt-1">{{ $task->failure_reason }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Flutterwave response (raw debug data) --}}
                    @if($task->flutterwave_response)
                        <div>
                            <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide mb-2">{{ __('Flutterwave API Response') }}</p>
                            <pre class="p-3 rounded-lg bg-surface dark:bg-surface border border-outline dark:border-outline text-xs font-mono text-on-surface overflow-x-auto max-h-48">{{ json_encode($task->flutterwave_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    @endif

                    @if($task->last_retry_at)
                        <p class="text-xs text-on-surface/60 mt-3">
                            {{ __('Last retry attempt') }}: {{ $task->last_retry_at->format('M d, Y H:i:s') }} ({{ $task->last_retry_at->diffForHumans() }})
                        </p>
                    @endif
                </div>
            </div>

            {{-- Resolution Details (if resolved) --}}
            @if($task->isResolved())
                <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline overflow-hidden">
                    <div class="px-5 py-4 border-b border-outline dark:border-outline">
                        <h3 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wider">{{ __('Resolution') }}</h3>
                    </div>
                    <div class="p-5">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <dt class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Status') }}</dt>
                                <dd class="text-sm text-success font-semibold mt-1">{{ $task->statusLabel() }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Completed At') }}</dt>
                                <dd class="text-sm text-on-surface-strong mt-1">{{ $task->completed_at?->format('M d, Y H:i') }}</dd>
                            </div>
                            @if($task->completedByUser)
                                <div>
                                    <dt class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Completed By') }}</dt>
                                    <dd class="text-sm text-on-surface-strong mt-1">{{ $task->completedByUser->name }}</dd>
                                </div>
                            @endif
                            @if($task->reference_number)
                                <div>
                                    <dt class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Reference Number') }}</dt>
                                    <dd class="text-sm text-on-surface-strong font-mono mt-1">{{ $task->reference_number }}</dd>
                                </div>
                            @endif
                        </dl>
                        @if($task->resolution_notes)
                            <div class="mt-4 pt-4 border-t border-outline dark:border-outline">
                                <dt class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Notes') }}</dt>
                                <dd class="text-sm text-on-surface mt-1">{{ $task->resolution_notes }}</dd>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Action buttons (only for pending tasks) --}}
            @if($task->isPending())
                <div class="flex flex-col sm:flex-row gap-3">
                    @if($task->canRetry())
                        <button
                            @click="$action('{{ url('/vault-entry/payouts/'.$task->id.'/retry') }}')"
                            class="inline-flex items-center justify-center gap-2 h-11 px-6 text-sm rounded-lg font-semibold bg-primary text-on-primary hover:bg-primary-hover transition-all duration-200"
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
                        <span class="inline-flex items-center justify-center gap-2 h-11 px-6 text-sm rounded-lg font-semibold bg-surface text-on-surface/40 border border-outline cursor-not-allowed" title="{{ __('Maximum retry attempts reached') }}">
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
                            {{ __('Retry Transfer') }}
                        </span>
                    @endif

                    <button
                        @click="openCompleteModal()"
                        class="inline-flex items-center justify-center gap-2 h-11 px-6 text-sm rounded-lg font-semibold border border-outline text-on-surface hover:bg-surface transition-all duration-200"
                    >
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                        {{ __('Mark as Manually Completed') }}
                    </button>
                </div>
            @endif
        </div>

        {{-- Right column: cook info + history --}}
        <div class="space-y-6">
            {{-- Cook Information Card --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline overflow-hidden">
                <div class="px-5 py-4 border-b border-outline dark:border-outline">
                    <h3 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wider">{{ __('Cook Information') }}</h3>
                </div>
                <div class="p-5">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 rounded-full bg-primary-subtle flex items-center justify-center text-primary font-bold text-lg shrink-0">
                            {{ strtoupper(mb_substr($task->cook?->name ?? '?', 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-on-surface-strong truncate">{{ $task->cook?->name ?? __('Unknown') }}</p>
                            <p class="text-xs text-on-surface/60 truncate">{{ $task->cook?->email ?? '' }}</p>
                        </div>
                    </div>
                    <dl class="space-y-3">
                        @if($task->cook?->phone)
                            <div>
                                <dt class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Phone') }}</dt>
                                <dd class="text-sm text-on-surface-strong font-mono mt-0.5">{{ $task->cook->phone }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Tenant') }}</dt>
                            <dd class="text-sm text-on-surface-strong mt-0.5">
                                @if($task->tenant)
                                    <a href="{{ url('/vault-entry/tenants/'.$task->tenant->slug) }}" class="text-primary hover:text-primary-hover transition-colors" x-navigate>
                                        {{ $task->tenant->name }}
                                    </a>
                                @else
                                    {{ __('Unknown') }}
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Cook Payout History --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline overflow-hidden">
                <div class="px-5 py-4 border-b border-outline dark:border-outline">
                    <h3 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wider">{{ __('Payout History') }}</h3>
                </div>
                <div class="p-5">
                    @if($cookPayoutHistory->count() > 0)
                        <div class="space-y-3">
                            @foreach($cookPayoutHistory as $history)
                                <div class="flex items-center justify-between p-3 rounded-lg bg-surface dark:bg-surface">
                                    <div class="min-w-0">
                                        <p class="text-sm font-mono font-semibold text-on-surface-strong">{{ $history->formattedAmount() }}</p>
                                        <p class="text-xs text-on-surface/60">{{ $history->requested_at?->format('M d, Y') }}</p>
                                    </div>
                                    <div class="shrink-0">
                                        @if($history->isPending())
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-warning-subtle text-warning">{{ __('Pending') }}</span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-success-subtle text-success">{{ $history->statusLabel() }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-on-surface/60 text-center py-4">{{ __('No other payout tasks for this cook.') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Mark as Manually Completed Modal --}}
    @if($task->isPending())
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
                <div class="p-3 rounded-lg bg-info-subtle/30 dark:bg-info-subtle/20 border border-info/20">
                    <p class="text-sm text-on-surface">
                        {{ __('You are marking a payout of') }}
                        <span class="font-bold text-on-surface-strong">{{ $task->formattedAmount() }}</span>
                        {{ __('to') }}
                        <span class="font-semibold text-on-surface-strong">{{ $task->cook?->name ?? __('Unknown') }}</span>
                        {{ __('as manually completed.') }}
                    </p>
                </div>

                <div x-show="error" class="p-3 rounded-lg bg-danger-subtle/30 border border-danger/20" x-cloak>
                    <p class="text-sm text-danger" x-text="error"></p>
                </div>

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
    @endif
</div>
@endsection
