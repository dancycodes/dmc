{{--
    Payment Status Badge
    --------------------
    F-059: Reusable status badge for payment transactions.

    UI/UX: green (successful), red (failed), yellow (pending), blue (refunded)
--}}
@php
    $badgeClasses = match($status) {
        'successful' => 'bg-success-subtle text-success',
        'failed' => 'bg-danger-subtle text-danger',
        'pending' => 'bg-warning-subtle text-warning',
        'refunded' => 'bg-info-subtle text-info',
        default => 'bg-outline/20 text-on-surface/60',
    };

    $label = match($status) {
        'successful' => __('Successful'),
        'failed' => __('Failed'),
        'pending' => __('Pending'),
        'refunded' => __('Refunded'),
        default => __('Unknown'),
    };
@endphp
<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $badgeClasses }}">
    <span class="w-1.5 h-1.5 rounded-full bg-current mr-1.5"></span>
    {{ $label }}
</span>
