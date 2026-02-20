{{--
    Transaction Status Badge (F-164)
    UI/UX: Status badge colors per spec.
    Completed (green), Pending (amber), Failed (red).
--}}
@php
    $badgeConfig = match($status) {
        'completed', 'successful' => [
            'label' => __('Completed'),
            'classes' => 'bg-success-subtle text-success dark:bg-success-subtle dark:text-success',
        ],
        'pending' => [
            'label' => __('Pending'),
            'classes' => 'bg-warning-subtle text-warning dark:bg-warning-subtle dark:text-warning',
        ],
        'failed' => [
            'label' => __('Failed'),
            'classes' => 'bg-danger-subtle text-danger dark:bg-danger-subtle dark:text-danger',
        ],
        'refunded' => [
            'label' => __('Refunded'),
            'classes' => 'bg-info-subtle text-info dark:bg-info-subtle dark:text-info',
        ],
        default => [
            'label' => ucfirst($status ?? __('Unknown')),
            'classes' => 'bg-surface-alt text-on-surface dark:bg-surface-alt dark:text-on-surface',
        ],
    };
@endphp
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badgeConfig['classes'] }}">
    {{ $badgeConfig['label'] }}
</span>
