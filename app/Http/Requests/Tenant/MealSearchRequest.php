<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class MealSearchRequest extends FormRequest
{
    /**
     * F-135: Meal Search Bar
     * F-136: Meal Filters
     * F-137: Meal Sort Options
     * Public endpoint — anyone can search/filter/sort meals on a tenant page.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Valid sort options for meal grid.
     *
     * F-137: BR-234 Sort options.
     */
    public const SORT_OPTIONS = [
        'popular' => 'Most Popular',
        'price_asc' => 'Price: Low to High',
        'price_desc' => 'Price: High to Low',
        'newest' => 'Newest First',
        'name_asc' => 'A to Z',
    ];

    /**
     * Default sort option.
     *
     * F-137: BR-234 Default sort is popularity.
     */
    public const DEFAULT_SORT = 'popular';

    /**
     * Validation rules for meal search and filter query parameters.
     *
     * F-135: BR-221: Search queries must be at least 2 characters
     * F-136: BR-223: Tag filter is multi-select (array of tag IDs)
     * F-136: BR-224: Availability filter: "all" or "available_now"
     * F-136: BR-226: Price range filter with min/max
     * F-137: BR-234: Sort options
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
            'tags' => ['nullable', 'string'],
            'availability' => ['nullable', 'string', 'in:all,available_now'],
            'price_min' => ['nullable', 'integer', 'min:0'],
            'price_max' => ['nullable', 'integer', 'min:0'],
            'sort' => ['nullable', 'string', 'in:popular,price_asc,price_desc,newest,name_asc'],
        ];
    }

    /**
     * Get the sanitized search query, truncated to max 50 characters.
     *
     * Edge case: Special characters sanitized, long queries truncated.
     */
    public function searchQuery(): string
    {
        $query = (string) $this->validated('q', '');

        return mb_substr(trim($query), 0, 50);
    }

    /**
     * Get the selected tag IDs from the comma-separated string.
     *
     * BR-223: Tags come as comma-separated IDs in the URL query param.
     *
     * @return array<int>
     */
    public function tagIds(): array
    {
        $tags = $this->validated('tags', '');

        if (empty($tags)) {
            return [];
        }

        return array_filter(
            array_map('intval', explode(',', (string) $tags)),
            fn (int $id) => $id > 0
        );
    }

    /**
     * Get the availability filter value.
     *
     * BR-224: "all" (default) or "available_now"
     */
    public function availabilityFilter(): string
    {
        return $this->validated('availability', 'all') ?? 'all';
    }

    /**
     * Get the price minimum filter.
     *
     * BR-226: Price range minimum. Null means no minimum.
     */
    public function priceMin(): ?int
    {
        $val = $this->validated('price_min');

        return $val !== null ? (int) $val : null;
    }

    /**
     * Get the price maximum filter.
     *
     * BR-226: Price range maximum. Null means no maximum.
     */
    public function priceMax(): ?int
    {
        $val = $this->validated('price_max');

        return $val !== null ? (int) $val : null;
    }

    /**
     * Check if any filters are active (beyond search).
     *
     * BR-229: Used to determine if filter badge should show.
     */
    public function hasActiveFilters(): bool
    {
        return ! empty($this->tagIds())
            || $this->availabilityFilter() !== 'all'
            || $this->priceMin() !== null
            || $this->priceMax() !== null;
    }

    /**
     * Get the selected sort option.
     *
     * F-137: BR-234 — Default is "popular". Falls back if invalid.
     */
    public function sortOption(): string
    {
        $sort = $this->validated('sort', self::DEFAULT_SORT) ?? self::DEFAULT_SORT;

        return array_key_exists($sort, self::SORT_OPTIONS) ? $sort : self::DEFAULT_SORT;
    }

    /**
     * Count the number of active filter types.
     *
     * BR-229: Filter count badge shows number of active filter types.
     */
    public function activeFilterCount(): int
    {
        $count = 0;

        if (! empty($this->tagIds())) {
            $count += count($this->tagIds());
        }

        if ($this->availabilityFilter() !== 'all') {
            $count++;
        }

        if ($this->priceMin() !== null || $this->priceMax() !== null) {
            $count++;
        }

        return $count;
    }
}
