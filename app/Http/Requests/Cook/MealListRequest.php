<?php

namespace App\Http\Requests\Cook;

use Illuminate\Foundation\Http\FormRequest;

class MealListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * BR-268: Only users with can-manage-meals permission.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('can-manage-meals') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * F-116: Meal List View query parameters.
     * BR-264: Status filter options: All, Draft, Live.
     * BR-265: Availability filter options: All, Available, Unavailable.
     * BR-266: Sort options: name_asc, name_desc, newest, oldest, most_ordered.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:draft,live'],
            'availability' => ['nullable', 'string', 'in:available,unavailable'],
            'sort' => ['nullable', 'string', 'in:name_asc,name_desc,newest,oldest,most_ordered'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
