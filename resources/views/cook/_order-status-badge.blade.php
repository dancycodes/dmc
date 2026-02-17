{{--
    Order Status Badge
    ------------------
    Reusable component for displaying color-coded order status badges.

    F-077: Color-coded status badges per UI/UX notes:
    - Pending (yellow/warning)
    - Confirmed (blue/info)
    - Preparing (orange/secondary)
    - Ready (green/success)
    - Other statuses gracefully handled

    @param string $status The order status slug
--}}
@php
    $statusConfig = match($status ?? '') {
        'pending' => [
            'label' => __('Pending'),
            'classes' => 'bg-warning-subtle text-warning dark:bg-warning-subtle dark:text-warning',
        ],
        'paid' => [
            'label' => __('Paid'),
            'classes' => 'bg-info-subtle text-info dark:bg-info-subtle dark:text-info',
        ],
        'confirmed' => [
            'label' => __('Confirmed'),
            'classes' => 'bg-info-subtle text-info dark:bg-info-subtle dark:text-info',
        ],
        'preparing' => [
            'label' => __('Preparing'),
            'classes' => 'bg-secondary-subtle text-secondary dark:bg-secondary-subtle dark:text-secondary',
        ],
        'ready' => [
            'label' => __('Ready'),
            'classes' => 'bg-success-subtle text-success dark:bg-success-subtle dark:text-success',
        ],
        'delivered', 'picked_up' => [
            'label' => __('Completed'),
            'classes' => 'bg-success-subtle text-success dark:bg-success-subtle dark:text-success',
        ],
        'completed' => [
            'label' => __('Completed'),
            'classes' => 'bg-success-subtle text-success dark:bg-success-subtle dark:text-success',
        ],
        'cancelled' => [
            'label' => __('Cancelled'),
            'classes' => 'bg-danger-subtle text-danger dark:bg-danger-subtle dark:text-danger',
        ],
        'refunded' => [
            'label' => __('Refunded'),
            'classes' => 'bg-danger-subtle text-danger dark:bg-danger-subtle dark:text-danger',
        ],
        default => [
            'label' => __(ucfirst($status ?? 'Unknown')),
            'classes' => 'bg-surface-alt text-on-surface dark:bg-surface-alt dark:text-on-surface',
        ],
    };
@endphp
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusConfig['classes'] }}">
    {{ $statusConfig['label'] }}
</span>
