<?php

namespace App\Http\Requests\Cook;

use App\Models\CookSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * F-098: Cook Day Schedule Creation
 *
 * Validates the creation of a new cook schedule entry.
 */
class StoreCookScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('can-manage-schedules') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'day_of_week' => ['required', 'string', Rule::in(CookSchedule::DAYS_OF_WEEK)],
            'is_available' => ['required', 'boolean'],
            'label' => ['nullable', 'string', 'max:100'],
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
            'day_of_week.required' => __('Please select a day of the week.'),
            'day_of_week.in' => __('Please select a valid day of the week.'),
            'is_available.required' => __('Availability status is required.'),
            'is_available.boolean' => __('Availability must be true or false.'),
            'label.max' => __('Label must not exceed 100 characters.'),
        ];
    }
}
