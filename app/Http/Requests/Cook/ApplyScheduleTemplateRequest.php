<?php

namespace App\Http\Requests\Cook;

use App\Models\CookSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * F-105: Schedule Template Application to Days
 *
 * Validates the day selection for template application.
 * BR-154: At least one day must be selected.
 */
class ApplyScheduleTemplateRequest extends FormRequest
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
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['required', 'string', Rule::in(CookSchedule::DAYS_OF_WEEK)],
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
            'days.required' => __('Select at least one day.'),
            'days.min' => __('Select at least one day.'),
            'days.*.in' => __('Invalid day selected.'),
        ];
    }
}
