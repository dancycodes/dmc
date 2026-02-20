{{--
    Transaction Type Badge (F-164)
    BR-261: Visual distinction for transaction types.
--}}
@php
    $badgeConfig = match($type) {
        'payment' => [
            'label' => __('Payment'),
            'classes' => 'bg-info-subtle text-info dark:bg-info-subtle dark:text-info',
        ],
        'refund' => [
            'label' => __('Refund'),
            'classes' => 'bg-success-subtle text-success dark:bg-success-subtle dark:text-success',
        ],
        'wallet_payment' => [
            'label' => __('Wallet'),
            'classes' => 'bg-warning-subtle text-warning dark:bg-warning-subtle dark:text-warning',
        ],
        default => [
            'label' => __('Unknown'),
            'classes' => 'bg-surface-alt text-on-surface dark:bg-surface-alt dark:text-on-surface',
        ],
    };
@endphp
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badgeConfig['classes'] }}">
    {{ $badgeConfig['label'] }}
</span>
