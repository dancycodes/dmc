<?php

namespace App\Http\Requests\Admin;

use App\Services\PlatformAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the period filter inputs for the analytics dashboard.
 *
 * F-057: Platform Analytics Dashboard
 * BR-140: Periods: Today, This Week, This Month, This Year, Custom Range
 * BR-141: Custom range maximum span is 1 year
 */
class PlatformAnalyticsRequest extends FormRequest
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
            'period' => ['sometimes', Rule::in(PlatformAnalyticsService::PERIODS)],
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
                        // BR-141: Custom range max 1 year
                        if ($start->diffInDays($end) > 365) {
                            $fail(__('Custom range cannot exceed 1 year.'));
                        }
                    }
                },
            ],
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
     * Get the resolved period, defaulting to "today".
     */
    public function getPeriod(): string
    {
        return $this->input('period', 'today');
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
}
