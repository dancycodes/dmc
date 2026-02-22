<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FinancialReportRequest — validates filter parameters for the financial reports page.
 *
 * F-058: Financial Reports & Export
 */
class FinancialReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tab' => ['nullable', 'string', 'in:overview,by_cook,pending_payouts,failed_payments'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'cook_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function getTab(): string
    {
        return $this->validated()['tab'] ?? 'overview';
    }

    public function getStartDate(): ?string
    {
        return $this->validated()['start_date'] ?? null;
    }

    public function getEndDate(): ?string
    {
        return $this->validated()['end_date'] ?? null;
    }

    public function getCookId(): ?int
    {
        $val = $this->validated()['cook_id'] ?? null;

        return $val ? (int) $val : null;
    }
}
