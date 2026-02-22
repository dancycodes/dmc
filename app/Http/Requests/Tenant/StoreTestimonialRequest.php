<?php

namespace App\Http\Requests\Tenant;

use App\Models\Testimonial;
use Illuminate\Foundation\Http\FormRequest;

/**
 * F-180: Form request for testimonial submission (HTTP fallback).
 *
 * BR-428: Testimonial text is required, max 1,000 characters.
 */
class StoreTestimonialRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'testimonialText' => [
                'required',
                'string',
                'min:10',
                'max:'.Testimonial::MAX_TEXT_LENGTH,
            ],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'testimonialText.required' => __('Testimonial text is required.'),
            'testimonialText.min' => __('Your testimonial must be at least 10 characters.'),
            'testimonialText.max' => __('Your testimonial must not exceed :max characters.', ['max' => Testimonial::MAX_TEXT_LENGTH]),
        ];
    }
}
