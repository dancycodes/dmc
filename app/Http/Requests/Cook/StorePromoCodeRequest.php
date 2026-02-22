<?php

namespace App\Http\Requests\Cook;

use App\Models\PromoCode;
use Illuminate\Foundation\Http\FormRequest;

/**
 * F-215: Cook Promo Code Creation
 *
 * Validates the promo code creation form for HTTP fallback path.
 *
 * BR-533: Code alphanumeric, 3-20 chars.
 * BR-535: Discount type: percentage or fixed.
 * BR-536: Percentage 1-100.
 * BR-537: Fixed 1-100,000.
 * BR-538: Minimum order 0-100,000.
 * BR-539: Max uses 0-100,000.
 * BR-540: Max per client 0-100.
 * BR-541: Start date required, today or future.
 * BR-542: End date optional, after or equal to start date.
 */
class StorePromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|\Illuminate\Contracts\Validation\Rule>>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'min:3',
                'max:20',
                'regex:/^[A-Za-z0-9]+$/',
            ],
            'discount_type' => [
                'required',
                'string',
                'in:'.implode(',', PromoCode::DISCOUNT_TYPES),
            ],
            'discount_value' => [
                'required',
                'integer',
                'min:1',
                'max:'.PromoCode::MAX_FIXED,
            ],
            'minimum_order_amount' => [
                'required',
                'integer',
                'min:'.PromoCode::MIN_ORDER_AMOUNT,
                'max:'.PromoCode::MAX_ORDER_AMOUNT,
            ],
            'max_uses' => [
                'required',
                'integer',
                'min:0',
                'max:'.PromoCode::MAX_TOTAL_USES,
            ],
            'max_uses_per_client' => [
                'required',
                'integer',
                'min:0',
                'max:'.PromoCode::MAX_PER_CLIENT_USES,
            ],
            'starts_at' => [
                'required',
                'date',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ],
            'ends_at' => [
                'nullable',
                'date',
                'date_format:Y-m-d',
                'after_or_equal:starts_at',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => __('The promo code must contain only letters and numbers.'),
            'code.min' => __('The promo code must be between 3 and 20 characters.'),
            'code.max' => __('The promo code must be between 3 and 20 characters.'),
            'discount_value.min' => __('The discount value must be at least 1.'),
            'starts_at.after_or_equal' => __('The start date must be today or later.'),
            'ends_at.after_or_equal' => __('The end date must be on or after the start date.'),
        ];
    }
}
