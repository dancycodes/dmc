{{--
    Admin Complaint Resolution
    --------------------------
    F-061: Full complaint detail page with resolution form.

    BR-165: Resolution options: Dismiss, Partial Refund, Full Refund, Warning, Suspend
    BR-166: Resolution note required for all resolution types
    BR-167: Partial refund amount must be > 0 and <= order total
    BR-168: Refunds credited to client wallet (not Flutterwave reversal)
    BR-169: Full refund credits entire order amount
    BR-170: Warnings recorded on cook's profile
    BR-171: Suspension deactivates tenant for specified duration
    BR-172: Both parties receive notifications
    BR-173: Resolution logged in activity log
    BR-174: Cannot re-resolve resolved complaints
--}}
@extends('layouts.admin')

@section('title', __('Complaint') . ' #' . $complaint->id)
@section('page-title', __('Complaint Resolution'))

@section('content')
<div class="space-y-6">
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[
        ['label' => __('Complaints'), 'url' => url('/vault-entry/complaints')],
        ['label' => '#' . $complaint->id],
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
                @else
                    <svg class="w-5 h-5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" x2="9" y1="9" y2="15"></line><line x1="9" x2="15" y1="9" y2="15"></line></svg>
                @endif
                <p class="text-sm font-medium">{{ session('toast.message') }}</p>
            </div>
        </div>
    @endif

    {{-- Header with status --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="text-xl sm:text-2xl font-bold text-on-surface-strong">{{ __('Complaint') }} #{{ $complaint->id }}</h2>
                @include('admin.complaints._status-badge', ['status' => $complaint->status])
            </div>
            <p class="text-sm text-on-surface mt-1">{{ __('Submitted') }} {{ $complaint->submitted_at?->format('M d, Y H:i') }}</p>
        </div>

        @if($complaint->isResolved())
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-success-subtle border border-success/20 text-success text-sm font-medium">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                {{ $complaint->resolutionTypeLabel() }}
            </div>
        @elseif($complaint->isOverdue())
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-danger-subtle border border-danger/20 text-danger text-sm font-medium">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                {{ __('Overdue') }} &mdash; {{ $complaint->timeSinceEscalation() }}
            </div>
        @endif
    </div>

    {{-- Already refunded warning --}}
    @if($orderAlreadyRefunded)
        <div class="p-4 rounded-lg border border-warning/30 bg-warning-subtle/30">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-warning shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                <div>
                    <p class="text-sm font-medium text-warning">{{ __('This order has already been refunded') }}</p>
                    <p class="text-xs text-on-surface mt-0.5">{{ __('A previous complaint for this order resulted in a refund. Admin can still dismiss this complaint.') }}</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Main content: 3-column on desktop, stacked on mobile --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- LEFT COLUMN: Complaint thread --}}
        <div class="lg:col-span-1 space-y-4">
            {{-- Category and escalation info --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4">
                <h3 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wide mb-3">{{ __('Complaint Info') }}</h3>
                <div class="space-y-3">
                    <div>
                        <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Category') }}</p>
                        <div class="mt-1">@include('admin.complaints._category-badge', ['category' => $complaint->category])</div>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Escalation Reason') }}</p>
                        <p class="text-sm text-on-surface-strong mt-1">{{ $complaint->escalationReasonLabel() }}</p>
                    </div>
                    @if($complaint->escalated_at)
                    <div>
                        <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Escalated') }}</p>
                        <p class="text-sm text-on-surface-strong mt-1">{{ $complaint->escalated_at->format('M d, Y H:i') }}</p>
                    </div>
                    @endif
                    @if($complaint->order_id)
                    <div>
                        <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Order ID') }}</p>
                        <p class="text-sm text-on-surface-strong font-mono mt-1">ORD-{{ $complaint->order_id }}</p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Conversation thread --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4">
                <h3 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wide mb-3">{{ __('Conversation') }}</h3>

                <div class="space-y-4">
                    {{-- Client message --}}
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full bg-info-subtle flex items-center justify-center text-info font-semibold text-xs shrink-0">
                            {{ mb_strtoupper(mb_substr($complaint->client?->name ?? '?', 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <p class="text-sm font-medium text-on-surface-strong">{{ $complaint->client?->name ?? __('Unknown Client') }}</p>
                                <span class="text-xs text-on-surface/50 px-1.5 py-0.5 rounded bg-info-subtle/50">{{ __('Client') }}</span>
                            </div>
                            <div class="bg-surface dark:bg-surface rounded-lg border border-outline/50 dark:border-outline/50 p-3">
                                <p class="text-sm text-on-surface whitespace-pre-wrap">{{ $complaint->description }}</p>
                            </div>
                            <p class="text-xs text-on-surface/50 mt-1">{{ $complaint->submitted_at?->format('M d, Y H:i') }}</p>
                        </div>
                    </div>

                    {{-- Cook response --}}
                    @if($complaint->cook_response)
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-warning-subtle flex items-center justify-center text-warning font-semibold text-xs shrink-0">
                                {{ mb_strtoupper(mb_substr($complaint->cook?->name ?? '?', 0, 1)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <p class="text-sm font-medium text-on-surface-strong">{{ $complaint->cook?->name ?? __('Unknown Cook') }}</p>
                                    <span class="text-xs text-on-surface/50 px-1.5 py-0.5 rounded bg-warning-subtle/50">{{ __('Cook') }}</span>
                                </div>
                                <div class="bg-surface dark:bg-surface rounded-lg border border-outline/50 dark:border-outline/50 p-3">
                                    <p class="text-sm text-on-surface whitespace-pre-wrap">{{ $complaint->cook_response }}</p>
                                </div>
                                <p class="text-xs text-on-surface/50 mt-1">{{ $complaint->cook_responded_at?->format('M d, Y H:i') }}</p>
                            </div>
                        </div>
                    @else
                        <div class="py-3 text-center">
                            <p class="text-xs text-on-surface/40 italic">{{ __('Cook has not responded') }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Cook history --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4">
                <h3 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wide mb-3">{{ __('Cook History') }}</h3>
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-on-surface">{{ __('Total Complaints') }}</p>
                        <span class="text-sm font-semibold text-on-surface-strong">{{ $cookComplaintCount }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-on-surface">{{ __('Warnings Issued') }}</p>
                        <span class="text-sm font-semibold {{ $cookWarningCount > 0 ? 'text-warning' : 'text-on-surface-strong' }}">{{ $cookWarningCount }}</span>
                    </div>
                    @if(count($previousSuspensions) > 0)
                        <div class="pt-2 mt-2 border-t border-outline/50 dark:border-outline/50">
                            <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide mb-2">{{ __('Previous Suspensions') }}</p>
                            @foreach($previousSuspensions as $suspension)
                                <div class="flex items-center justify-between text-xs py-1">
                                    <span class="text-on-surface">{{ __(':days days', ['days' => $suspension['suspension_days']]) }} ({{ __('Complaint') }} #{{ $suspension['complaint_id'] }})</span>
                                    <span class="text-on-surface/60">{{ $suspension['resolved_at'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- CENTER COLUMN: Order and payment details --}}
        <div class="lg:col-span-1 space-y-4">
            {{-- Client info --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4">
                <h3 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wide mb-3">{{ __('Client') }}</h3>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center text-primary font-bold text-sm shrink-0">
                        {{ mb_strtoupper(mb_substr($complaint->client?->name ?? '?', 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-on-surface-strong">{{ $complaint->client?->name ?? '---' }}</p>
                        <p class="text-xs text-on-surface">{{ $complaint->client?->email ?? '---' }}</p>
                        @if($complaint->client?->phone)
                            <p class="text-xs text-on-surface font-mono">+237 {{ $complaint->client->phone }}</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Cook info --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4">
                <h3 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wide mb-3">{{ __('Cook') }}</h3>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-warning-subtle flex items-center justify-center text-warning font-bold text-sm shrink-0">
                        {{ mb_strtoupper(mb_substr($complaint->cook?->name ?? '?', 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-on-surface-strong">{{ $complaint->cook?->name ?? '---' }}</p>
                        <p class="text-xs text-on-surface">{{ $complaint->cook?->email ?? '---' }}</p>
                        @if($complaint->tenant)
                            <p class="text-xs text-on-surface mt-0.5">{{ __('Tenant') }}: {{ $complaint->tenant->name }}</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Payment/Order details --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4">
                <h3 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wide mb-3">{{ __('Payment Details') }}</h3>
                @if($paymentTransaction)
                    <div class="space-y-3">
                        <div>
                            <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Amount') }}</p>
                            <p class="text-lg font-bold text-on-surface-strong mt-0.5">{{ $paymentTransaction->formattedAmount() }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Method') }}</p>
                                <p class="text-sm text-on-surface-strong mt-0.5">{{ $paymentTransaction->paymentMethodLabel() }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Status') }}</p>
                                <span class="inline-flex items-center mt-0.5 px-2 py-0.5 rounded text-xs font-semibold {{ $paymentTransaction->status === 'successful' ? 'bg-success-subtle text-success' : 'bg-outline/20 text-on-surface/60' }}">
                                    {{ ucfirst($paymentTransaction->status) }}
                                </span>
                            </div>
                        </div>
                        @if($paymentTransaction->flutterwave_reference)
                        <div>
                            <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Reference') }}</p>
                            <p class="text-xs text-on-surface font-mono mt-0.5 break-all">{{ $paymentTransaction->flutterwave_reference }}</p>
                        </div>
                        @endif
                        <div>
                            <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Date') }}</p>
                            <p class="text-sm text-on-surface-strong mt-0.5">{{ $paymentTransaction->created_at?->format('M d, Y H:i') }}</p>
                        </div>
                    </div>
                @else
                    <div class="text-center py-4">
                        <div class="w-10 h-10 mx-auto rounded-full bg-outline/10 flex items-center justify-center mb-2">
                            <svg class="w-5 h-5 text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"></rect><line x1="2" x2="22" y1="10" y2="10"></line></svg>
                        </div>
                        <p class="text-sm text-on-surface/60">{{ __('No payment information available') }}</p>
                    </div>
                @endif
            </div>

            {{-- Resolution details (shown only if already resolved) --}}
            @if($complaint->isResolved())
                <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4">
                    <h3 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wide mb-3">{{ __('Resolution Details') }}</h3>
                    <div class="space-y-3">
                        <div>
                            <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Resolution Type') }}</p>
                            <p class="text-sm font-medium text-on-surface-strong mt-0.5">{{ $complaint->resolutionTypeLabel() }}</p>
                        </div>
                        @if($complaint->refund_amount)
                        <div>
                            <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Refund Amount') }}</p>
                            <p class="text-sm font-medium text-success mt-0.5">{{ __('XAF :amount', ['amount' => number_format((float)$complaint->refund_amount)]) }}</p>
                        </div>
                        @endif
                        @if($complaint->suspension_days)
                        <div>
                            <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Suspension Duration') }}</p>
                            <p class="text-sm text-on-surface-strong mt-0.5">{{ __(':days days', ['days' => $complaint->suspension_days]) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Suspension Ends') }}</p>
                            <p class="text-sm text-on-surface-strong mt-0.5">{{ $complaint->suspension_ends_at?->format('M d, Y H:i') }}</p>
                        </div>
                        @endif
                        <div>
                            <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Resolution Notes') }}</p>
                            <p class="text-sm text-on-surface mt-1 whitespace-pre-wrap">{{ $complaint->resolution_notes }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Resolved By') }}</p>
                            <p class="text-sm text-on-surface-strong mt-0.5">{{ $complaint->resolvedByUser?->name ?? __('Unknown') }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Resolved At') }}</p>
                            <p class="text-sm text-on-surface-strong mt-0.5">{{ $complaint->resolved_at?->format('M d, Y H:i') }}</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- RIGHT COLUMN: Resolution form (only if not yet resolved) --}}
        <div class="lg:col-span-1">
            @if(!$complaint->isResolved())
                <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 lg:sticky lg:top-4"
                     x-data="{
                         resolution_type: '',
                         resolution_notes: '',
                         refund_amount: '',
                         suspension_days: '',
                         showConfirm: false,
                         resolved: false,
                         error: '',
                         resolveUrl: '{{ url('/vault-entry/complaints/' . $complaint->id . '/resolve') }}',
                         orderAmount: {{ $orderAmount ?? 'null' }},
                         submitResolution() {
                             if (this.resolution_type === 'suspend') {
                                 this.showConfirm = true;
                             } else {
                                 this.doResolve();
                             }
                         },
                         doResolve() {
                             this.showConfirm = false;
                             $action(this.resolveUrl, {
                                 include: ['resolution_type', 'resolution_notes', 'refund_amount', 'suspension_days']
                             });
                         }
                     }"
                >
                    <h3 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wide mb-4">{{ __('Resolution') }}</h3>

                    {{-- Error display --}}
                    <template x-if="error">
                        <div class="mb-4 p-3 rounded-lg bg-danger-subtle border border-danger/20 text-danger text-sm" x-text="error"></div>
                    </template>

                    {{-- Resolution type radio buttons --}}
                    <div class="space-y-2 mb-4">
                        <label class="text-xs font-medium text-on-surface/60 uppercase tracking-wide">{{ __('Resolution Type') }}</label>

                        {{-- Dismiss --}}
                        <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-all duration-200"
                               :class="resolution_type === 'dismiss' ? 'border-primary bg-primary-subtle/30' : 'border-outline hover:border-primary/50'">
                            <input type="radio" x-model="resolution_type" value="dismiss" class="w-4 h-4 text-primary border-outline focus:ring-primary" x-name="resolution_type">
                            <div>
                                <p class="text-sm font-medium text-on-surface-strong">{{ __('Dismiss') }}</p>
                                <p class="text-xs text-on-surface/60">{{ __('No action needed') }}</p>
                            </div>
                        </label>

                        {{-- Partial Refund --}}
                        <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-all duration-200"
                               :class="resolution_type === 'partial_refund' ? 'border-primary bg-primary-subtle/30' : 'border-outline hover:border-primary/50'">
                            <input type="radio" x-model="resolution_type" value="partial_refund" class="w-4 h-4 text-primary border-outline focus:ring-primary" x-name="resolution_type">
                            <div>
                                <p class="text-sm font-medium text-on-surface-strong">{{ __('Partial Refund') }}</p>
                                <p class="text-xs text-on-surface/60">{{ __('Credit partial amount to client wallet') }}</p>
                            </div>
                        </label>

                        {{-- Full Refund --}}
                        <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-all duration-200"
                               :class="resolution_type === 'full_refund' ? 'border-primary bg-primary-subtle/30' : 'border-outline hover:border-primary/50'">
                            <input type="radio" x-model="resolution_type" value="full_refund" class="w-4 h-4 text-primary border-outline focus:ring-primary" x-name="resolution_type">
                            <div>
                                <p class="text-sm font-medium text-on-surface-strong">{{ __('Full Refund') }}</p>
                                <p class="text-xs text-on-surface/60">
                                    @if($orderAmount)
                                        {{ __('Credit :amount XAF to client wallet', ['amount' => number_format($orderAmount)]) }}
                                    @else
                                        {{ __('Credit full order amount to client wallet') }}
                                    @endif
                                </p>
                            </div>
                        </label>

                        {{-- Warning --}}
                        <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-all duration-200"
                               :class="resolution_type === 'warning' ? 'border-warning bg-warning-subtle/30' : 'border-outline hover:border-warning/50'">
                            <input type="radio" x-model="resolution_type" value="warning" class="w-4 h-4 text-warning border-outline focus:ring-warning" x-name="resolution_type">
                            <div>
                                <p class="text-sm font-medium text-on-surface-strong">{{ __('Warning to Cook') }}</p>
                                <p class="text-xs text-on-surface/60">{{ __('Record warning on cook profile') }}</p>
                            </div>
                        </label>

                        {{-- Suspend --}}
                        <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-all duration-200"
                               :class="resolution_type === 'suspend' ? 'border-danger bg-danger-subtle/30' : 'border-outline hover:border-danger/50'">
                            <input type="radio" x-model="resolution_type" value="suspend" class="w-4 h-4 text-danger border-outline focus:ring-danger" x-name="resolution_type">
                            <div>
                                <p class="text-sm font-medium text-on-surface-strong">{{ __('Suspend Cook') }}</p>
                                <p class="text-xs text-on-surface/60">{{ __('Temporarily deactivate cook tenant') }}</p>
                            </div>
                        </label>

                        <p x-message="resolution_type" class="text-xs text-danger mt-1"></p>
                    </div>

                    {{-- Conditional fields --}}

                    {{-- Partial refund amount --}}
                    <div x-show="resolution_type === 'partial_refund'" x-transition class="mb-4">
                        <label class="block text-xs font-medium text-on-surface/60 uppercase tracking-wide mb-1.5">{{ __('Refund Amount (XAF)') }}</label>
                        <input
                            type="number"
                            x-model="refund_amount"
                            x-name="refund_amount"
                            min="1"
                            :max="orderAmount"
                            placeholder="{{ __('Enter amount') }}"
                            class="w-full h-10 px-3 rounded-lg border border-outline bg-surface dark:bg-surface text-on-surface-strong text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                        >
                        <p x-message="refund_amount" class="text-xs text-danger mt-1"></p>
                        <template x-if="orderAmount">
                            <p class="text-xs text-on-surface/50 mt-1">{{ __('Maximum') }}: <span x-text="orderAmount ? orderAmount.toLocaleString() + ' XAF' : ''"></span></p>
                        </template>
                    </div>

                    {{-- Suspension duration --}}
                    <div x-show="resolution_type === 'suspend'" x-transition class="mb-4">
                        <label class="block text-xs font-medium text-on-surface/60 uppercase tracking-wide mb-1.5">{{ __('Suspension Duration (days)') }}</label>
                        <input
                            type="number"
                            x-model="suspension_days"
                            x-name="suspension_days"
                            min="1"
                            max="365"
                            placeholder="{{ __('Number of days') }}"
                            class="w-full h-10 px-3 rounded-lg border border-outline bg-surface dark:bg-surface text-on-surface-strong text-sm focus:outline-none focus:ring-2 focus:ring-danger focus:border-danger transition-colors"
                        >
                        <p x-message="suspension_days" class="text-xs text-danger mt-1"></p>
                    </div>

                    {{-- Resolution notes (always visible) --}}
                    <div class="mb-4">
                        <label class="block text-xs font-medium text-on-surface/60 uppercase tracking-wide mb-1.5">{{ __('Resolution Notes') }} <span class="text-danger">*</span></label>
                        <textarea
                            x-model="resolution_notes"
                            x-name="resolution_notes"
                            rows="4"
                            minlength="10"
                            maxlength="2000"
                            placeholder="{{ __('Explain the resolution decision (minimum 10 characters)...') }}"
                            class="w-full px-3 py-2 rounded-lg border border-outline bg-surface dark:bg-surface text-on-surface-strong text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors resize-y"
                        ></textarea>
                        <p x-message="resolution_notes" class="text-xs text-danger mt-1"></p>
                        <p class="text-xs text-on-surface/50 mt-1">
                            <span x-text="resolution_notes.length"></span>/2000 {{ __('characters') }}
                        </p>
                    </div>

                    {{-- Submit button --}}
                    <button
                        @click="submitResolution()"
                        :disabled="!resolution_type || $fetching()"
                        class="w-full h-10 rounded-lg font-semibold text-sm transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                        :class="{
                            'bg-danger hover:bg-danger/90 text-on-danger focus:ring-danger': resolution_type === 'suspend',
                            'bg-warning hover:bg-warning/90 text-on-warning focus:ring-warning': resolution_type === 'warning',
                            'bg-primary hover:bg-primary-hover text-on-primary focus:ring-primary': resolution_type && resolution_type !== 'suspend' && resolution_type !== 'warning',
                            'bg-outline/50 text-on-surface/50': !resolution_type,
                        }"
                    >
                        <span x-show="!$fetching()" class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                            {{ __('Submit Resolution') }}
                        </span>
                        <span x-show="$fetching()" x-cloak class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            {{ __('Processing...') }}
                        </span>
                    </button>

                    {{-- Suspension confirmation modal --}}
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
                        <div class="absolute inset-0 bg-on-surface/50 dark:bg-on-surface/70" @click="showConfirm = false"></div>
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
                            <div class="w-12 h-12 mx-auto rounded-full bg-danger-subtle flex items-center justify-center mb-4">
                                <svg class="w-6 h-6 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                            </div>
                            <h3 class="text-lg font-semibold text-on-surface-strong text-center mb-2">
                                {{ __('Confirm Cook Suspension') }}
                            </h3>
                            <p class="text-sm text-on-surface text-center mb-6">
                                {{ __('This will deactivate the cook\'s tenant for the specified duration. The cook will not be able to receive orders during the suspension period. Continue?') }}
                            </p>
                            <div class="flex items-center gap-3">
                                <button
                                    @click="showConfirm = false"
                                    class="flex-1 h-10 px-4 text-sm rounded-lg font-semibold border border-outline text-on-surface hover:bg-surface-alt transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                                >
                                    {{ __('Cancel') }}
                                </button>
                                <button
                                    @click="doResolve()"
                                    class="flex-1 h-10 px-4 text-sm rounded-lg font-semibold bg-danger hover:bg-danger/90 text-on-danger transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-danger focus:ring-offset-2"
                                >
                                    <span x-show="!$fetching()" class="inline-flex items-center justify-center gap-2">
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" x2="19.07" y1="4.93" y2="19.07"></line></svg>
                                        {{ __('Suspend Cook') }}
                                    </span>
                                    <span x-show="$fetching()" x-cloak class="inline-flex items-center justify-center gap-2">
                                        <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                        {{ __('Suspending...') }}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                {{-- Already resolved message --}}
                <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4">
                    <div class="text-center py-4">
                        <div class="w-12 h-12 mx-auto rounded-full bg-success-subtle flex items-center justify-center mb-3">
                            <svg class="w-6 h-6 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                        </div>
                        <p class="text-sm font-medium text-on-surface-strong">{{ __('This complaint has been resolved') }}</p>
                        <p class="text-xs text-on-surface/60 mt-1">{{ __('No further action is possible.') }}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Back navigation --}}
    <div x-data x-navigate>
        <a
            href="{{ url('/vault-entry/complaints') }}"
            class="inline-flex items-center gap-2 text-sm font-medium text-on-surface hover:text-primary transition-colors"
        >
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
            {{ __('Back to Complaints') }}
        </a>
    </div>
</div>
@endsection
