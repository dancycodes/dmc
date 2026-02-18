<?php

namespace App\Http\Requests\Cook;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGroupFeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('can-manage-locations') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'delivery_fee' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'delivery_fee.required' => __('Delivery fee is required.'),
            'delivery_fee.integer' => __('Delivery fee must be a whole number.'),
            'delivery_fee.min' => __('Delivery fee must be 0 or greater.'),
        ];
    }
}
