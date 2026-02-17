<?php

namespace App\Http\Requests\Admin;

use App\Models\CommissionChange;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCommissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('can-manage-commission') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * BR-176: Commission range 0%-50% in 0.5% increments
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'commission_rate' => [
                'required',
                'numeric',
                'min:'.CommissionChange::MIN_RATE,
                'max:'.CommissionChange::MAX_RATE,
                function (string $attribute, mixed $value, \Closure $fail) {
                    // BR-176: Must be in 0.5% increments
                    $remainder = fmod((float) $value * 2, 1.0);
                    if (abs($remainder) > 0.001) {
                        $fail(__('Commission rate must be in 0.5% increments.'));
                    }
                },
            ],
            'reason' => ['nullable', 'string', 'max:1000'],
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
            'commission_rate.required' => __('Commission rate is required.'),
            'commission_rate.numeric' => __('Commission rate must be a number.'),
            'commission_rate.min' => __('Commission must be between 0% and 50%.'),
            'commission_rate.max' => __('Commission must be between 0% and 50%.'),
            'reason.max' => __('Reason cannot exceed 1000 characters.'),
        ];
    }
}
