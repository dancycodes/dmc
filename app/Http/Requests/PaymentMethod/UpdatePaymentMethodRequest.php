<?php

namespace App\Http\Requests\PaymentMethod;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdatePaymentMethodRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * BR-163: Only label and phone are editable. Provider is read-only.
     * BR-165: Phone validation must match the existing provider (same rules as BR-151).
     * BR-166: Label uniqueness excludes the current payment method.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = Auth::id();
        $paymentMethodId = $this->route('paymentMethod')?->id;

        return [
            'label' => [
                'required',
                'string',
                'max:50',
                Rule::unique('payment_methods', 'label')
                    ->where('user_id', $userId)
                    ->ignore($paymentMethodId),
            ],
            'phone' => [
                'required',
                'string',
            ],
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
            'label.required' => __('Payment method label is required.'),
            'label.max' => __('Label must not exceed 50 characters.'),
            'label.unique' => __('You already have a payment method with this label.'),
            'phone.required' => __('Phone number is required.'),
        ];
    }
}
