<?php

namespace App\Http\Requests\Admin;

use App\Services\AdminGrowthMetricsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the period filter inputs for the admin growth metrics page.
 *
 * F-207: Admin Growth Metrics
 * BR-445: Date range options: Last 3 Months, Last 6 Months, This Year, Last Year, All Time
 */
class AdminGrowthMetricsRequest extends FormRequest
{
    /**
     * Admin panel routes are authorized via middleware; no per-request auth needed.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'period' => ['sometimes', Rule::in(array_keys(AdminGrowthMetricsService::PERIODS))],
        ];
    }

    /**
     * Get user-friendly attribute names.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'period' => __('period'),
        ];
    }

    /**
     * Get the resolved period, defaulting to "last_6_months".
     */
    public function getPeriod(): string
    {
        $period = $this->input('period', 'last_6_months');

        if (! array_key_exists($period, AdminGrowthMetricsService::PERIODS)) {
            return 'last_6_months';
        }

        return $period;
    }
}
