<?php

namespace App\Http\Requests\Cook;

use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTagRequest extends FormRequest
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
     * BR-253: Tag name required in both EN and FR
     * BR-259: Tag name max length: 50 characters per language
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:'.Tag::NAME_MAX_LENGTH],
            'name_fr' => ['required', 'string', 'max:'.Tag::NAME_MAX_LENGTH],
        ];
    }

    /**
     * Custom error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name_en.required' => __('The English name is required.'),
            'name_en.max' => __('The English name must not exceed :max characters.', ['max' => Tag::NAME_MAX_LENGTH]),
            'name_fr.required' => __('The French name is required.'),
            'name_fr.max' => __('The French name must not exceed :max characters.', ['max' => Tag::NAME_MAX_LENGTH]),
        ];
    }
}
