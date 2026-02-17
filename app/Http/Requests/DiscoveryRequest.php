<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DiscoveryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * BR-068: Discovery page is publicly accessible without authentication.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * BR-089: Minimum search query length is 2 characters.
     * BR-090: Filter categories: town, availability, tags, min_rating.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', Rule::in(['name', 'newest'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            // F-069: Discovery filters
            'town' => ['nullable', 'integer', 'min:1'],
            'availability' => ['nullable', 'string', Rule::in(['all', 'now', 'today'])],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['integer', 'min:1'],
            'min_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }
}
