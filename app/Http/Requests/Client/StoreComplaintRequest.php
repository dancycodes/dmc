<?php

namespace App\Http\Requests\Client;

use App\Services\ComplaintSubmissionService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * F-183: Validation rules for client complaint submission.
 *
 * BR-185: Categories: food_quality, delivery_issue, missing_item, wrong_order, other.
 * BR-186: Description required, min 10, max 1000 characters.
 * BR-187: Photo optional, max one image.
 * BR-188: Accepted formats: JPEG, PNG, WebP; max 5MB.
 */
class StoreComplaintRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'category' => [
                'required',
                'string',
                'in:'.implode(',', ComplaintSubmissionService::CLIENT_CATEGORIES),
            ],
            'description' => [
                'required',
                'string',
                'min:'.ComplaintSubmissionService::MIN_DESCRIPTION_LENGTH,
                'max:'.ComplaintSubmissionService::MAX_DESCRIPTION_LENGTH,
            ],
            'photo' => [
                'nullable',
                'image',
                'mimes:'.implode(',', ComplaintSubmissionService::ACCEPTED_PHOTO_MIMES),
                'max:'.ComplaintSubmissionService::MAX_PHOTO_SIZE_KB,
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
            'category.required' => __('Please select a complaint category.'),
            'category.in' => __('Please select a valid complaint category.'),
            'description.required' => __('Please describe the issue you experienced.'),
            'description.min' => __('Description must be at least :min characters.'),
            'description.max' => __('Description cannot exceed :max characters.'),
            'photo.image' => __('The uploaded file must be an image.'),
            'photo.mimes' => __('Accepted image formats: JPEG, PNG, WebP.'),
            'photo.max' => __('Image size must not exceed 5MB.'),
        ];
    }
}
