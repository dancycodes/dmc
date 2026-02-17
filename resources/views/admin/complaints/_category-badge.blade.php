{{--
    Complaint Category Badge
    ------------------------
    F-060: Color-coded category indicators.
    BR-161: Categories: Food Quality, Late Delivery, Missing Items, Wrong Order, Rude Behavior, Other

    UI/UX: Category badges color-coded:
    - Food Quality (red)
    - Late Delivery (orange)
    - Missing Items (yellow)
    - Wrong Order (purple)
    - Rude Behavior (pink)
    - Other (gray)
--}}
@php
    $badgeClasses = match($category) {
        'food_quality' => 'bg-danger-subtle text-danger dark:bg-danger-subtle dark:text-danger',
        'late_delivery' => 'bg-warning-subtle text-warning dark:bg-warning-subtle dark:text-warning',
        'missing_items' => 'bg-secondary-subtle text-secondary dark:bg-secondary-subtle dark:text-secondary',
        'wrong_order' => 'bg-info-subtle text-info dark:bg-info-subtle dark:text-info',
        'rude_behavior' => 'bg-primary-subtle text-primary dark:bg-primary-subtle dark:text-primary',
        'other' => 'bg-surface-alt text-on-surface/60 dark:bg-surface-alt dark:text-on-surface/60',
        default => 'bg-surface-alt text-on-surface/60 dark:bg-surface-alt dark:text-on-surface/60',
    };

    $label = match($category) {
        'food_quality' => __('Food Quality'),
        'late_delivery' => __('Late Delivery'),
        'missing_items' => __('Missing Items'),
        'wrong_order' => __('Wrong Order'),
        'rude_behavior' => __('Rude Behavior'),
        'other' => __('Other'),
        default => ucfirst(str_replace('_', ' ', $category)),
    };
@endphp
<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $badgeClasses }}">
    {{ $label }}
</span>
