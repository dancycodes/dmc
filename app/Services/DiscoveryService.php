<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DiscoveryService
{
    /**
     * Default number of cook cards per page.
     */
    public const PER_PAGE = 12;

    /**
     * Get discoverable tenants (active, with cook assigned).
     *
     * BR-067: Only cooks with is_active=true on their tenant are displayed.
     * When meals table exists (F-108), add: AND at least one active, available meal.
     */
    public function getDiscoverableCooks(
        ?string $search = null,
        ?string $sort = null,
        ?string $direction = null,
        int $perPage = self::PER_PAGE,
    ): LengthAwarePaginator {
        $query = Tenant::query()
            ->active()
            ->whereNotNull('cook_id')
            ->with(['cook']);

        // Apply search if provided (F-068 will expand this)
        if (! empty($search)) {
            $query->search($search);
        }

        // Apply sorting
        $sortColumn = match ($sort) {
            'name' => 'name_'.app()->getLocale(),
            'newest' => 'created_at',
            default => 'created_at',
        };

        $sortDirection = $direction === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortColumn, $sortDirection);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Get the total count of discoverable cooks.
     */
    public function getDiscoverableCookCount(): int
    {
        return Tenant::query()
            ->active()
            ->whereNotNull('cook_id')
            ->count();
    }
}
