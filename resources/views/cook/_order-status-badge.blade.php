{{--
    Order Status Badge
    ------------------
    Reusable component for displaying color-coded order status badges.

    F-077: Color-coded status badges per UI/UX notes.
    F-155 BR-158: Distinct colors per status:
    - Pending Payment (gray), Paid (blue), Confirmed (indigo), Preparing (amber),
    - Ready (teal), Out for Delivery (purple), Ready for Pickup (purple),
    - Delivered (green), Picked Up (green), Completed (emerald),
    - Cancelled (red), Refunded (orange)

    @param string $status The order status slug
--}}
@php
    $statusConfig = match($status ?? '') {
        'pending_payment', 'pending' => [
            'label' => __('Pending Payment'),
            'classes' => 'bg-surface-alt text-on-surface/70 dark:bg-surface-alt dark:text-on-surface/70',
        ],
        'payment_failed' => [
            'label' => __('Payment Failed'),
            'classes' => 'bg-danger-subtle text-danger dark:bg-danger-subtle dark:text-danger',
        ],
        'paid' => [
            'label' => __('Paid'),
            'classes' => 'bg-info-subtle text-info dark:bg-info-subtle dark:text-info',
        ],
        'confirmed' => [
            'label' => __('Confirmed'),
            'classes' => 'bg-[oklch(0.93_0.05_270)] text-[oklch(0.45_0.15_270)] dark:bg-[oklch(0.25_0.06_270)] dark:text-[oklch(0.75_0.12_270)]',
        ],
        'preparing' => [
            'label' => __('Preparing'),
            'classes' => 'bg-warning-subtle text-warning dark:bg-warning-subtle dark:text-warning',
        ],
        'ready' => [
            'label' => __('Ready'),
            'classes' => 'bg-[oklch(0.93_0.05_175)] text-[oklch(0.45_0.1_175)] dark:bg-[oklch(0.25_0.04_175)] dark:text-[oklch(0.75_0.1_175)]',
        ],
        'out_for_delivery' => [
            'label' => __('Out for Delivery'),
            'classes' => 'bg-[oklch(0.93_0.05_300)] text-[oklch(0.45_0.15_300)] dark:bg-[oklch(0.25_0.06_300)] dark:text-[oklch(0.75_0.12_300)]',
        ],
        'ready_for_pickup' => [
            'label' => __('Ready for Pickup'),
            'classes' => 'bg-[oklch(0.93_0.05_300)] text-[oklch(0.45_0.15_300)] dark:bg-[oklch(0.25_0.06_300)] dark:text-[oklch(0.75_0.12_300)]',
        ],
        'delivered' => [
            'label' => __('Delivered'),
            'classes' => 'bg-success-subtle text-success dark:bg-success-subtle dark:text-success',
        ],
        'picked_up' => [
            'label' => __('Picked Up'),
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
            'classes' => 'bg-secondary-subtle text-secondary dark:bg-secondary-subtle dark:text-secondary',
        ],
        default => [
            'label' => __(ucfirst(str_replace('_', ' ', $status ?? 'Unknown'))),
            'classes' => 'bg-surface-alt text-on-surface dark:bg-surface-alt dark:text-on-surface',
        ],
    };
@endphp
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusConfig['classes'] }}">
    {{ $statusConfig['label'] }}
</span>
