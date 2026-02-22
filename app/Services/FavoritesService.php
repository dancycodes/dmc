<?php

namespace App\Services;

use App\Models\Meal;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * F-198: Favorites List View
 *
 * Handles data retrieval for the user's favorites page.
 * BR-344: Auth required.
 * BR-350: Reverse chronological order (most recently favorited first).
 * BR-354: Paginated 12 per page.
 * BR-351: Unavailable items flagged for dimmed display.
 */
class FavoritesService
{
    /**
     * Number of favorites to display per page.
     */
    public const PER_PAGE = 12;

    /**
     * Get paginated favorite cooks for the given user.
     *
     * BR-350: Ordered by pivot created_at descending.
     * BR-351: Cook marked unavailable when no active tenant assigned.
     *
     * @return LengthAwarePaginator<array<string, mixed>>
     */
    public function getFavoriteCooks(User $user, int $page = 1): LengthAwarePaginator
    {
        // Build paginator from pivot table, joining cook + tenant info.
        $query = DB::table('favorite_cooks')
            ->where('favorite_cooks.user_id', $user->id)
            ->join('users as cook_users', 'favorite_cooks.cook_user_id', '=', 'cook_users.id')
            ->leftJoin('tenants', 'tenants.cook_id', '=', 'cook_users.id')
            ->select([
                'favorite_cooks.cook_user_id',
                'favorite_cooks.created_at as favorited_at',
                'cook_users.name as cook_name',
                'tenants.id as tenant_id',
                'tenants.slug as tenant_slug',
                'tenants.name_en as tenant_name_en',
                'tenants.name_fr as tenant_name_fr',
                'tenants.custom_domain as tenant_custom_domain',
                'tenants.is_active as tenant_is_active',
                'tenants.settings as tenant_settings',
            ])
            ->orderBy('favorite_cooks.created_at', 'desc');

        $total = $query->count();
        $perPage = self::PER_PAGE;
        $offset = ($page - 1) * $perPage;

        $rows = $query->offset($offset)->limit($perPage)->get();

        $items = $rows->map(function (object $row) {
            $tenantSettings = $row->tenant_settings ? json_decode((string) $row->tenant_settings, true) : [];
            $averageRating = $tenantSettings['average_rating'] ?? null;
            $totalRatings = (int) ($tenantSettings['total_ratings'] ?? 0);
            $coverImages = $tenantSettings['cover_images'] ?? [];
            $primaryTown = $tenantSettings['primary_town'] ?? null;

            $isAvailable = (bool) $row->tenant_is_active;

            // Build tenant URL for cook card links (BR-346).
            $tenantUrl = null;
            if ($row->tenant_slug) {
                /** @var Tenant|null $tenant */
                $tenant = Tenant::make([
                    'slug' => $row->tenant_slug,
                    'custom_domain' => $row->tenant_custom_domain,
                ]);
                $tenantUrl = $tenant->getUrl();
            }

            return [
                'cook_user_id' => (int) $row->cook_user_id,
                'cook_name' => $row->cook_name,
                'tenant_id' => $row->tenant_id ? (int) $row->tenant_id : null,
                'tenant_slug' => $row->tenant_slug,
                'tenant_name' => $row->tenant_name_en, // resolved per locale by view
                'tenant_name_en' => $row->tenant_name_en,
                'tenant_name_fr' => $row->tenant_name_fr,
                'tenant_url' => $tenantUrl,
                'cover_images' => $coverImages,
                'average_rating' => $averageRating,
                'total_ratings' => $totalRatings,
                'primary_town' => $primaryTown,
                'is_available' => $isAvailable,
            ];
        });

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => ['tab' => 'cooks']]
        );
    }

    /**
     * Get paginated favorite meals for the given user.
     *
     * BR-350: Ordered by pivot created_at descending.
     * BR-351: Meal marked unavailable when deleted/draft/inactive or tenant inactive.
     * BR-347: Meal links point to meal detail on the tenant domain.
     *
     * @return LengthAwarePaginator<array<string, mixed>>
     */
    public function getFavoriteMeals(User $user, int $page = 1): LengthAwarePaginator
    {
        // Get paginated meal IDs ordered by favorited_at DESC, including soft-deleted.
        $query = DB::table('favorite_meals')
            ->where('favorite_meals.user_id', $user->id)
            ->join('meals', 'favorite_meals.meal_id', '=', 'meals.id')
            ->leftJoin('tenants', 'meals.tenant_id', '=', 'tenants.id')
            ->select([
                'favorite_meals.meal_id',
                'favorite_meals.created_at as favorited_at',
                'meals.name_en',
                'meals.name_fr',
                'meals.price',
                'meals.status',
                'meals.is_active',
                'meals.deleted_at',
                'tenants.id as tenant_id',
                'tenants.slug as tenant_slug',
                'tenants.name_en as tenant_name_en',
                'tenants.name_fr as tenant_name_fr',
                'tenants.custom_domain as tenant_custom_domain',
                'tenants.is_active as tenant_is_active',
            ])
            ->orderBy('favorite_meals.created_at', 'desc');

        $total = $query->count();
        $perPage = self::PER_PAGE;
        $offset = ($page - 1) * $perPage;

        $rows = $query->offset($offset)->limit($perPage)->get();

        // Fetch primary images for the meal IDs in batch.
        $mealIds = $rows->pluck('meal_id')->all();
        $primaryImages = $this->fetchPrimaryMealImages($mealIds);

        $items = $rows->map(function (object $row) use ($primaryImages) {
            $isDeleted = $row->deleted_at !== null;
            $isAvailable = ! $isDeleted
                && $row->status === Meal::STATUS_LIVE
                && (bool) $row->is_active
                && (bool) $row->tenant_is_active;

            // Build meal URL on the tenant domain (BR-347).
            $mealUrl = null;
            if ($row->tenant_slug && ! $isDeleted) {
                $tenant = Tenant::make([
                    'slug' => $row->tenant_slug,
                    'custom_domain' => $row->tenant_custom_domain,
                ]);
                $mealUrl = rtrim($tenant->getUrl(), '/').'/meals/'.$row->meal_id;
            }

            return [
                'meal_id' => (int) $row->meal_id,
                'name_en' => $row->name_en,
                'name_fr' => $row->name_fr,
                'price' => $row->price ? (int) $row->price : null,
                'image' => $primaryImages[(int) $row->meal_id] ?? null,
                'meal_url' => $mealUrl,
                'cook_name_en' => $row->tenant_name_en,
                'cook_name_fr' => $row->tenant_name_fr,
                'tenant_slug' => $row->tenant_slug,
                'tenant_url' => $mealUrl ? rtrim($mealUrl, '/meals/'.$row->meal_id) : null,
                'is_available' => $isAvailable,
            ];
        });

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => ['tab' => 'meals']]
        );
    }

    /**
     * Remove a cook from the user's favorites.
     *
     * BR-348: Each card has a remove action.
     * BR-349: Via Gale without page reload.
     *
     * @return array{success: bool, message: string}
     */
    public function removeFavoriteCook(User $user, int $cookUserId): array
    {
        $exists = $user->favoriteCooks()->where('cook_user_id', $cookUserId)->exists();

        if (! $exists) {
            return ['success' => false, 'message' => __('Cook not found in favorites.')];
        }

        $user->favoriteCooks()->detach($cookUserId);

        return ['success' => true, 'message' => __('Removed from favorites')];
    }

    /**
     * Remove a meal from the user's favorites.
     *
     * BR-348: Each card has a remove action.
     * BR-349: Via Gale without page reload.
     *
     * @return array{success: bool, message: string}
     */
    public function removeFavoriteMeal(User $user, int $mealId): array
    {
        $exists = $user->favoriteMeals()->where('meal_id', $mealId)->exists();

        if (! $exists) {
            return ['success' => false, 'message' => __('Meal not found in favorites.')];
        }

        $user->favoriteMeals()->detach($mealId);

        return ['success' => true, 'message' => __('Removed from favorites')];
    }

    /**
     * Fetch primary image URL for each meal ID in batch.
     *
     * @param  array<int>  $mealIds
     * @return array<int, string|null>
     */
    private function fetchPrimaryMealImages(array $mealIds): array
    {
        if (empty($mealIds)) {
            return [];
        }

        // Distinct on meal_id ordered by position ASC to get the first image per meal.
        $rows = DB::table('meal_images')
            ->whereIn('meal_id', $mealIds)
            ->orderBy('position')
            ->get(['meal_id', 'path'])
            ->unique('meal_id');

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->meal_id] = asset('storage/'.$row->path);
        }

        return $result;
    }
}
