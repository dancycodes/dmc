<?php

namespace App\Http\Requests\PaymentMethod;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StorePaymentMethodRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = Auth::id();

        return [
            'label' => [
                'required',
                'string',
                'max:50',
                Rule::unique('payment_methods', 'label')->where('user_id', $userId),
            ],
            'provider' => [
                'required',
                'string',
                Rule::in(PaymentMethod::PROVIDERS),
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
            'provider.required' => __('Please select a payment provider.'),
            'provider.in' => __('Please select a valid payment provider.'),
            'phone.required' => __('Phone number is required.'),
        ];
    }
}
