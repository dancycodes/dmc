<?php

namespace App\Http\Requests\Client;

use App\Models\Rating;
use Illuminate\Foundation\Http\FormRequest;

/**
 * F-176: Store Rating Request
 * F-177: Order Review Text Submission
 *
 * Validates star rating and optional review text for order rating submission.
 * BR-389: Rating scale is 1-5 stars (integer only).
 * BR-399: Review text is optional.
 * BR-400: Maximum review length is 500 characters.
 */
class StoreRatingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'stars' => [
                'required',
                'integer',
                'min:'.Rating::MIN_STARS,
                'max:'.Rating::MAX_STARS,
            ],
            'review_text' => [
                'nullable',
                'string',
                'max:'.Rating::MAX_REVIEW_LENGTH,
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'stars.required' => __('Please select a star rating.'),
            'stars.integer' => __('Rating must be a whole number.'),
            'stars.min' => __('Rating must be at least :min star.', ['min' => Rating::MIN_STARS]),
            'stars.max' => __('Rating cannot exceed :max stars.', ['max' => Rating::MAX_STARS]),
            'review_text.max' => __('Review text cannot exceed :max characters.', ['max' => Rating::MAX_REVIEW_LENGTH]),
        ];
    }
}
