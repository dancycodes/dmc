<?php

namespace App\Http\Requests\Cook;

use App\Models\MealImage;
use Illuminate\Foundation\Http\FormRequest;

class StoreMealImageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('can-manage-meals') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * BR-199: Accepted formats: jpg/jpeg, png, webp
     * BR-200: Maximum file size: 2MB per image
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1'],
            'images.*' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.MealImage::MAX_FILE_SIZE_KB,
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
            'images.required' => __('Please select at least one image to upload.'),
            'images.*.image' => __('Only JPG, PNG, and WebP images are accepted.'),
            'images.*.mimes' => __('Only JPG, PNG, and WebP images are accepted.'),
            'images.*.max' => __('Image must be 2MB or smaller.'),
        ];
    }
}
