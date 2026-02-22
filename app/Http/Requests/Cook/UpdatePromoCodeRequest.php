<?php

namespace App\Http\Requests\Cook;

use App\Models\PromoCode;
use Illuminate\Foundation\Http\FormRequest;

/**
 * F-216: Cook Promo Code Edit
 *
 * Validates the promo code edit form for the HTTP fallback path.
 *
 * BR-550: Editable fields: discount_value, minimum_order_amount, max_uses,
 *         max_uses_per_client, starts_at, ends_at.
 * BR-554: Validation rules are the same as creation (F-215) for editable fields.
 */
class UpdatePromoCodeRequest extends FormRequest
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
            'discount_value.min' => __('The discount value must be at least 1.'),
            'ends_at.after_or_equal' => __('The end date must be on or after the start date.'),
        ];
    }
}
