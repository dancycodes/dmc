<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MarkPayoutCompleteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('can-manage-payouts') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * BR-200: "Manually completed" requires a reference number or note as proof.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reference_number' => ['required', 'string', 'min:3', 'max:255'],
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
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
            'reference_number.required' => __('A reference number is required as proof of manual payment.'),
            'reference_number.min' => __('The reference number must be at least :min characters.'),
            'reference_number.max' => __('The reference number must not exceed :max characters.'),
            'resolution_notes.max' => __('The notes must not exceed :max characters.'),
        ];
    }
}
