<?php

namespace App\Http\Requests\Admin;

use App\Models\Complaint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveComplaintRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('can-manage-complaints-escalated') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * BR-165: Resolution types
     * BR-166: Resolution note required (min 10 chars)
     * BR-167: Partial refund amount > 0 and <= order total
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'resolution_type' => ['required', 'string', Rule::in(Complaint::RESOLUTION_TYPES)],
            'resolution_notes' => ['required', 'string', 'min:10', 'max:2000'],
            'refund_amount' => ['required_if:resolution_type,partial_refund', 'nullable', 'numeric', 'min:1'],
            'suspension_days' => ['required_if:resolution_type,suspend', 'nullable', 'integer', 'min:1', 'max:365'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'resolution_type.required' => __('Please select a resolution type.'),
            'resolution_type.in' => __('Invalid resolution type selected.'),
            'resolution_notes.required' => __('A resolution note is required.'),
            'resolution_notes.min' => __('Resolution note must be at least :min characters.'),
            'refund_amount.required_if' => __('Refund amount is required for partial refunds.'),
            'refund_amount.min' => __('Refund amount must be greater than 0.'),
            'suspension_days.required_if' => __('Suspension duration is required.'),
            'suspension_days.min' => __('Suspension duration must be at least :min day.'),
            'suspension_days.max' => __('Suspension duration cannot exceed :max days.'),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'resolution_type' => __('resolution type'),
            'resolution_notes' => __('resolution notes'),
            'refund_amount' => __('refund amount'),
            'suspension_days' => __('suspension duration'),
        ];
    }
}
