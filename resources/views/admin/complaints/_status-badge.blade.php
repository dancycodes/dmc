{{--
    Complaint Status Badge
    ----------------------
    F-060: Status indicator for escalated complaints.
    BR-162: Statuses: Pending Resolution, Under Review, Resolved, Dismissed
--}}
@php
    $badgeClasses = match($status) {
        'pending_resolution' => 'bg-warning-subtle text-warning dark:bg-warning-subtle dark:text-warning',
        'under_review' => 'bg-info-subtle text-info dark:bg-info-subtle dark:text-info',
        'resolved' => 'bg-success-subtle text-success dark:bg-success-subtle dark:text-success',
        'dismissed' => 'bg-surface-alt text-on-surface/60 dark:bg-surface-alt dark:text-on-surface/60',
        'escalated' => 'bg-danger-subtle text-danger dark:bg-danger-subtle dark:text-danger',
        default => 'bg-surface-alt text-on-surface/60 dark:bg-surface-alt dark:text-on-surface/60',
    };

    $label = match($status) {
        'pending_resolution' => __('Pending Resolution'),
        'under_review' => __('Under Review'),
        'resolved' => __('Resolved'),
        'dismissed' => __('Dismissed'),
        'escalated' => __('Escalated'),
        default => ucfirst(str_replace('_', ' ', $status)),
    };
@endphp
<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $badgeClasses }}">
    {{ $label }}
</span>
