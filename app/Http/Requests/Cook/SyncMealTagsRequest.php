<?php

namespace App\Http\Requests\Cook;

use App\Services\MealTagService;
use Illuminate\Foundation\Http\FormRequest;

class SyncMealTagsRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'selected_tag_ids' => ['present', 'array', 'max:'.MealTagService::MAX_TAGS_PER_MEAL],
            'selected_tag_ids.*' => ['integer', 'exists:tags,id'],
        ];
    }

    /**
     * Get custom error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'selected_tag_ids.max' => __('Maximum :max tags per meal.', ['max' => MealTagService::MAX_TAGS_PER_MEAL]),
        ];
    }
}
