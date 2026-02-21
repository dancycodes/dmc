<?php

namespace App\Http\Requests\Cook;

use App\Services\CookSettingsService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * F-213: Minimum Order Amount Configuration
 *
 * Validates the minimum order amount value submitted by a cook.
 *
 * BR-508: Allowed range: 0 to 100,000 XAF (inclusive).
 * BR-509: Value must be a whole number (integer XAF).
 */
class UpdateMinimumOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'minimum_order_amount' => [
                'required',
                'integer',
                'min:'.CookSettingsService::MIN_ORDER_AMOUNT,
                'max:'.CookSettingsService::MAX_ORDER_AMOUNT,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'minimum_order_amount.required' => __('The minimum order amount is required.'),
            'minimum_order_amount.integer' => __('Minimum order amount must be a number.'),
            'minimum_order_amount.min' => __('Minimum order amount must be between :min and :max XAF.', [
                'min' => CookSettingsService::MIN_ORDER_AMOUNT,
                'max' => number_format(CookSettingsService::MAX_ORDER_AMOUNT),
            ]),
            'minimum_order_amount.max' => __('Minimum order amount must be between :min and :max XAF.', [
                'min' => CookSettingsService::MIN_ORDER_AMOUNT,
                'max' => number_format(CookSettingsService::MAX_ORDER_AMOUNT),
            ]),
        ];
    }
}
