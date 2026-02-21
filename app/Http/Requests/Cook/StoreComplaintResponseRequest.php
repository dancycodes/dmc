<?php

namespace App\Http\Requests\Cook;

use App\Models\ComplaintResponse;
use Illuminate\Foundation\Http\FormRequest;

/**
 * F-184: Validates cook/manager complaint response submission.
 *
 * BR-196: Response text required, min 10, max 2000 chars.
 * BR-197: Resolution type required, one of the allowed values.
 * BR-198: Partial refund requires amount > 0.
 */
class StoreComplaintResponseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'message' => [
                'required',
                'string',
                'min:'.ComplaintResponse::MIN_MESSAGE_LENGTH,
                'max:'.ComplaintResponse::MAX_MESSAGE_LENGTH,
            ],
            'resolution_type' => [
                'required',
                'string',
                'in:'.implode(',', ComplaintResponse::RESOLUTION_TYPES),
            ],
            'refund_amount' => [
                'nullable',
                'integer',
                'min:1',
            ],
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
            'message.required' => __('Please write a response message.'),
            'message.min' => __('Response must be at least :min characters.'),
            'message.max' => __('Response cannot exceed :max characters.'),
            'resolution_type.required' => __('Please select a resolution type.'),
            'resolution_type.in' => __('Please select a valid resolution type.'),
            'refund_amount.min' => __('Refund amount must be at least 1 XAF.'),
        ];
    }
}
