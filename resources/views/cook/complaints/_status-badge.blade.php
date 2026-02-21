{{--
    F-184: Cook complaint status badge partial.
    @param string $status
--}}
@php
    $badgeClasses = match($status) {
        'open' => 'bg-warning-subtle text-warning',
        'in_review' => 'bg-info-subtle text-info',
        'escalated' => 'bg-danger-subtle text-danger',
        'resolved' => 'bg-success-subtle text-success',
        'dismissed' => 'bg-surface-alt text-on-surface/60',
        default => 'bg-surface-alt text-on-surface',
    };
    $label = match($status) {
        'open' => __('Open'),
        'in_review' => __('In Review'),
        'escalated' => __('Escalated'),
        'resolved' => __('Resolved'),
        'dismissed' => __('Dismissed'),
        default => ucfirst(str_replace('_', ' ', $status)),
    };
@endphp
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badgeClasses }}">
    {{ $label }}
</span>
