{{--
    F-184: Cook complaint category badge partial.
    @param string $category
--}}
@php
    $label = match($category) {
        'food_quality' => __('Food Quality'),
        'delivery_issue' => __('Delivery Issue'),
        'missing_item' => __('Missing Item'),
        'wrong_order' => __('Wrong Order'),
        'late_delivery' => __('Late Delivery'),
        'missing_items' => __('Missing Items'),
        'rude_behavior' => __('Rude Behavior'),
        'other' => __('Other'),
        default => ucfirst(str_replace('_', ' ', $category)),
    };
@endphp
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-secondary-subtle text-secondary">
    {{ $label }}
</span>
