<?php

namespace App\Http\Requests\Admin;

use App\Services\CookPerformanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates query parameters for the cook performance metrics page.
 *
 * F-206: Admin Cook Performance Metrics
 * BR-430: All columns are sortable.
 * BR-431: Filters: cook status (active/inactive), region/town.
 * BR-432: Search by cook name.
 * BR-436: Paginated at 25 per page.
 * BR-437: Date range applies to orders, revenue, and complaints.
 */
class CookPerformanceRequest extends FormRequest
{
    /**
     * Admin panel routes are protected by middleware; no per-request auth needed.
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
            'period' => ['sometimes', Rule::in(array_keys(CookPerformanceService::PERIODS))],
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
            'sort' => ['sometimes', Rule::in(CookPerformanceService::SORT_COLUMNS)],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'inactive'])],
            'region' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
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
            'sort' => __('sort column'),
            'direction' => __('sort direction'),
            'search' => __('search'),
            'status' => __('status filter'),
            'region' => __('region filter'),
        ];
    }

    /**
     * Get the resolved period key, defaulting to "this_month".
     */
    public function getPeriod(): string
    {
        $period = $this->input('period', 'this_month');

        return array_key_exists($period, CookPerformanceService::PERIODS)
            ? $period
            : 'this_month';
    }

    public function getCustomStart(): ?string
    {
        return $this->input('custom_start');
    }

    public function getCustomEnd(): ?string
    {
        return $this->input('custom_end');
    }

    public function getSortBy(): string
    {
        $sort = $this->input('sort', 'total_revenue');

        return in_array($sort, CookPerformanceService::SORT_COLUMNS, true) ? $sort : 'total_revenue';
    }

    public function getSortDirection(): string
    {
        $dir = $this->input('direction', 'desc');

        return in_array(strtolower($dir), ['asc', 'desc'], true) ? strtolower($dir) : 'desc';
    }

    public function getSearch(): ?string
    {
        $search = $this->input('search');

        return $search ? trim($search) : null;
    }

    public function getStatus(): ?string
    {
        $status = $this->input('status');

        return in_array($status, ['active', 'inactive'], true) ? $status : null;
    }

    public function getRegionId(): ?int
    {
        $region = $this->input('region');

        return $region ? (int) $region : null;
    }

    public function getPage(): int
    {
        return max(1, (int) $this->input('page', 1));
    }
}
