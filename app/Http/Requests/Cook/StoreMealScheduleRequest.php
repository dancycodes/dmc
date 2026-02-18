<?php

namespace App\Http\Requests\Cook;

use App\Models\MealSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * F-106: Meal Schedule Override
 *
 * Validates meal schedule entry creation for traditional HTTP requests.
 * BR-166: Same validation rules as cook schedule creation.
 */
class StoreMealScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->can('can-manage-meals') && $user->can('can-manage-schedules');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'day_of_week' => ['required', 'string', Rule::in(MealSchedule::DAYS_OF_WEEK)],
            'is_available' => ['required'],
            'label' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Get the custom error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'day_of_week.required' => __('Please select a day of the week.'),
            'day_of_week.in' => __('Please select a valid day of the week.'),
            'is_available.required' => __('Availability status is required.'),
            'label.max' => __('Label must not exceed 100 characters.'),
        ];
    }
}
