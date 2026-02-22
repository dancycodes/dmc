<?php

namespace App\Http\Requests\Admin;

use App\Services\AdminRevenueAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the period filter inputs for the admin revenue analytics page.
 *
 * F-205: Admin Platform Revenue Analytics
 * BR-424: Date range options: This Month, Last 3 Months, Last 6 Months, This Year, Last Year, Custom
 */
class AdminRevenueAnalyticsRequest extends FormRequest
{
    /**
     * Admin panel routes are authorized via middleware, no per-request auth needed.
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
            'period' => ['sometimes', Rule::in(array_keys(AdminRevenueAnalyticsService::PERIODS))],
            'custom_start' => ['required_if:period,custom', 'nullable', 'date'],
            'custom_end' => [
                'required_if:period,custom',
                'nullable',
                'date',
                'after_or_equal:custom_start',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->input('period') === 'custom' && $this->input('custom_start')) {
                        $start = Carbon::parse($this->input('custom_start'));
                        $end = Carbon::parse($value);
                        if ($start->diffInDays($end) > 365) {
                            $fail(__('Custom range cannot exceed 1 year.'));
                        }
                    }
                },
            ],
            'compare' => ['sometimes', 'boolean'],
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
            'custom_start' => __('start date'),
            'custom_end' => __('end date'),
        ];
    }

    /**
     * Get the resolved period, defaulting to "this_month".
     */
    public function getPeriod(): string
    {
        $period = $this->input('period', 'this_month');
        if (! array_key_exists($period, AdminRevenueAnalyticsService::PERIODS)) {
            return 'this_month';
        }

        return $period;
    }

    /**
     * Get the resolved custom start date string.
     */
    public function getCustomStart(): ?string
    {
        return $this->input('custom_start');
    }

    /**
     * Get the resolved custom end date string.
     */
    public function getCustomEnd(): ?string
    {
        return $this->input('custom_end');
    }

    /**
     * Whether comparison mode is enabled.
     */
    public function getCompare(): bool
    {
        return (bool) $this->input('compare', false);
    }
}
