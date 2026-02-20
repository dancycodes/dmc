<?php

namespace App\Http\Requests\Cook;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * BR-162: Only users with manage-orders permission.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('can-manage-orders') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * F-155: Cook Order List View query parameters.
     * BR-160: Filters and search can be combined simultaneously.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(Order::STATUSES)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort' => ['nullable', 'string', 'in:created_at,status,grand_total'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
