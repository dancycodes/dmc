<?php

namespace App\Http\Requests\Cook;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

/**
 * F-158: Mass Order Status Update request validation.
 *
 * BR-189: Only orders at the same current status can be bulk-updated together.
 * BR-197: Only users with manage-orders permission can perform mass updates.
 */
class MassOrderStatusUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * BR-197: Only users with manage-orders permission.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('can-manage-orders') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['required', 'integer', 'exists:orders,id'],
            'target_status' => ['required', 'string', 'in:'.implode(',', Order::STATUSES)],
        ];
    }

    /**
     * Custom error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'order_ids.required' => __('Please select at least one order.'),
            'order_ids.min' => __('Please select at least one order.'),
            'target_status.required' => __('Target status is required.'),
            'target_status.in' => __('Invalid target status.'),
        ];
    }
}
