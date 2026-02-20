<?php

namespace App\Http\Requests\Client;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientOrderListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * BR-220: Authentication is required.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(Order::STATUSES)],
            'sort' => ['nullable', 'string', Rule::in(['created_at', 'grand_total'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
