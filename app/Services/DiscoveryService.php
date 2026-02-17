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
     * BR-083: Discovery search across cook name, meal name, town name, tags.
     * BR-086: Search results respect any active filters (combined).
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

        // BR-083/BR-084/BR-085: Discovery search with accent-insensitive partial matching
        if (! empty($search) && mb_strlen(trim($search)) >= 2) {
            $query->discoverySearch($search);
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
