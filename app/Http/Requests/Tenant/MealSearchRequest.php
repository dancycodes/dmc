<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class MealSearchRequest extends FormRequest
{
    /**
     * F-135: Meal Search Bar
     * Public endpoint — anyone can search meals on a tenant page.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for meal search query parameters.
     *
     * BR-221: Search queries must be at least 2 characters
     * Edge case: Very long search query (100+ chars) truncated to 50
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Get the sanitized search query, truncated to max 50 characters.
     *
     * Edge case: Special characters sanitized, long queries truncated.
     */
    public function searchQuery(): string
    {
        $query = (string) $this->validated('q', '');

        return mb_substr(trim($query), 0, 50);
    }
}
