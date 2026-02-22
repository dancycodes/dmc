<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UploadPhotoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * BR-103: Accepted file formats: JPG (JPEG), PNG, WebP only.
     * BR-104: Maximum file size: 2MB (2048 KB).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => __('Please select a photo to upload.'),
            'photo.file' => __('The photo must be a valid file.'),
            'photo.image' => __('The photo must be a JPG, PNG, or WebP image.'),
            'photo.mimes' => __('The photo must be a JPG, PNG, or WebP image.'),
            'photo.max' => __('The photo must be smaller than 2MB.'),
        ];
    }
}
