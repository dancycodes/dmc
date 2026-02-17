<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Town;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
     * BR-091: Filters between categories combine with AND logic.
     * BR-095: Filter changes update the grid via Gale without page reload.
     * When meals table exists (F-108), add: AND at least one active, available meal.
     */
    public function getDiscoverableCooks(
        ?string $search = null,
        ?string $sort = null,
        ?string $direction = null,
        ?int $town = null,
        ?string $availability = null,
        ?array $tags = null,
        ?int $minRating = null,
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

        // F-069: Apply filters (BR-091: AND logic between categories)
        $this->applyTownFilter($query, $town);
        $this->applyAvailabilityFilter($query, $availability);
        $this->applyTagsFilter($query, $tags);
        $this->applyRatingFilter($query, $minRating);

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

    /**
     * Get available towns for the filter dropdown.
     *
     * BR-097: Only towns that active cooks deliver to.
     * Forward-compatible: uses delivery_areas when available, falls back to all active towns.
     *
     * @return Collection<int, Town>
     */
    public function getFilterTowns(): Collection
    {
        if (! Schema::hasTable('towns')) {
            return collect();
        }

        $query = Town::query()->active();

        // When delivery_areas exists, only show towns with active tenants delivering there
        if (Schema::hasTable('delivery_areas')) {
            $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('delivery_areas')
                    ->join('tenants', 'tenants.id', '=', 'delivery_areas.tenant_id')
                    ->where('tenants.is_active', true)
                    ->whereNotNull('tenants.cook_id')
                    ->whereRaw('delivery_areas.town_id = towns.id');
            });
        }

        $locale = app()->getLocale();

        return $query->orderBy('name_'.$locale)->get();
    }

    /**
     * Get available tags for the filter chips.
     *
     * Forward-compatible: returns empty collection until tags/meal_tag/meals tables exist.
     */
    public function getFilterTags(): Collection
    {
        if (! Schema::hasTable('tags') || ! Schema::hasTable('meal_tag') || ! Schema::hasTable('meals')) {
            return collect();
        }

        $locale = app()->getLocale();

        return DB::table('tags')
            ->join('meal_tag', 'meal_tag.tag_id', '=', 'tags.id')
            ->join('meals', 'meals.id', '=', 'meal_tag.meal_id')
            ->join('tenants', 'tenants.id', '=', 'meals.tenant_id')
            ->where('tenants.is_active', true)
            ->whereNotNull('tenants.cook_id')
            ->where('meals.is_active', true)
            ->select('tags.id', 'tags.name_en', 'tags.name_fr')
            ->distinct()
            ->orderBy('tags.name_'.$locale)
            ->get();
    }

    /**
     * Count the number of active filters applied.
     *
     * BR-093: Total number of individual filter values applied.
     */
    public function countActiveFilters(
        ?int $town = null,
        ?string $availability = null,
        ?array $tags = null,
        ?int $minRating = null,
    ): int {
        $count = 0;

        if ($town) {
            $count++;
        }

        if ($availability && $availability !== 'all') {
            $count++;
        }

        if (! empty($tags)) {
            $count += count($tags);
        }

        if ($minRating) {
            $count++;
        }

        return $count;
    }

    /**
     * Apply town filter to the query.
     *
     * BR-097: Filter by town that cooks deliver to.
     * Forward-compatible with delivery_areas table (F-074).
     */
    private function applyTownFilter(\Illuminate\Database\Eloquent\Builder $query, ?int $townId): void
    {
        if (! $townId || ! Schema::hasTable('towns')) {
            return;
        }

        if (Schema::hasTable('delivery_areas')) {
            $query->whereExists(function ($sub) use ($townId) {
                $sub->select(DB::raw(1))
                    ->from('delivery_areas')
                    ->whereRaw('delivery_areas.tenant_id = tenants.id')
                    ->where('delivery_areas.town_id', $townId);
            });
        }
        // Without delivery_areas, town filter is a no-op (cannot determine cook-town association)
    }

    /**
     * Apply availability filter to the query.
     *
     * BR-098: "Available now" checks schedule against current time in Africa/Douala.
     * BR-099: "Available today" checks schedule for any remaining availability today.
     * Forward-compatible with schedules table (F-098).
     */
    private function applyAvailabilityFilter(\Illuminate\Database\Eloquent\Builder $query, ?string $availability): void
    {
        if (! $availability || $availability === 'all') {
            return;
        }

        // Forward-compatible: when cook_schedules table exists, filter by schedule
        if (! Schema::hasTable('cook_schedules')) {
            return;
        }

        $now = now()->timezone('Africa/Douala');
        $dayOfWeek = strtolower($now->format('l'));
        $currentTime = $now->format('H:i:s');

        if ($availability === 'now') {
            // BR-098: Cook has a schedule entry for today's day AND current time is within their operating hours
            $query->whereExists(function ($sub) use ($dayOfWeek, $currentTime) {
                $sub->select(DB::raw(1))
                    ->from('cook_schedules')
                    ->whereRaw('cook_schedules.tenant_id = tenants.id')
                    ->where('cook_schedules.day_of_week', $dayOfWeek)
                    ->where('cook_schedules.is_active', true)
                    ->where('cook_schedules.open_time', '<=', $currentTime)
                    ->where('cook_schedules.close_time', '>=', $currentTime);
            });
        } elseif ($availability === 'today') {
            // BR-099: Cook has a schedule entry for today with any remaining time
            $query->whereExists(function ($sub) use ($dayOfWeek, $currentTime) {
                $sub->select(DB::raw(1))
                    ->from('cook_schedules')
                    ->whereRaw('cook_schedules.tenant_id = tenants.id')
                    ->where('cook_schedules.day_of_week', $dayOfWeek)
                    ->where('cook_schedules.is_active', true)
                    ->where('cook_schedules.close_time', '>=', $currentTime);
            });
        }
    }

    /**
     * Apply tags filter to the query.
     *
     * BR-092: Tags within the tag filter combine with OR logic (matches any selected tag).
     * Forward-compatible with tags/meal_tag/meals tables (F-115, F-108).
     */
    private function applyTagsFilter(\Illuminate\Database\Eloquent\Builder $query, ?array $tagIds): void
    {
        if (empty($tagIds)) {
            return;
        }

        if (! Schema::hasTable('tags') || ! Schema::hasTable('meal_tag') || ! Schema::hasTable('meals')) {
            return;
        }

        // BR-092: OR logic within tag filter — cooks with meals tagged with ANY selected tag
        $query->whereExists(function ($sub) use ($tagIds) {
            $sub->select(DB::raw(1))
                ->from('meal_tag')
                ->join('meals', 'meals.id', '=', 'meal_tag.meal_id')
                ->whereRaw('meals.tenant_id = tenants.id')
                ->where('meals.is_active', true)
                ->whereIn('meal_tag.tag_id', $tagIds);
        });
    }

    /**
     * Apply minimum rating filter to the query.
     *
     * Forward-compatible with ratings/reviews tables (F-176).
     */
    private function applyRatingFilter(\Illuminate\Database\Eloquent\Builder $query, ?int $minRating): void
    {
        if (! $minRating) {
            return;
        }

        // Forward-compatible: when ratings table exists, filter by average rating
        if (! Schema::hasTable('ratings')) {
            return;
        }

        $query->whereExists(function ($sub) use ($minRating) {
            $sub->select(DB::raw(1))
                ->from('ratings')
                ->join('orders', 'orders.id', '=', 'ratings.order_id')
                ->whereRaw('orders.tenant_id = tenants.id')
                ->groupBy('orders.tenant_id')
                ->havingRaw('AVG(ratings.score) >= ?', [$minRating]);
        });
    }
}
