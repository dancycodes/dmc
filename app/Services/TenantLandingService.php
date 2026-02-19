<?php

namespace App\Services;

use App\Models\CookSchedule;
use App\Models\Meal;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TenantLandingService
{
    /**
     * Number of meals to display per page in the grid.
     *
     * F-128 Edge Case: 50+ meals use pagination with "Load More".
     */
    public const MEALS_PER_PAGE = 12;

    public function __construct(
        private TenantThemeService $tenantThemeService,
    ) {}

    /**
     * Gather all data needed for the tenant landing page.
     *
     * F-126: Tenant Landing Page Layout
     * F-128: Available Meals Grid Display
     * BR-126: Only renders on tenant domains
     * BR-127: Cook's selected theme applied dynamically
     *
     * @return array{tenant: Tenant, themeConfig: array, sections: array, cookProfile: array, meals: LengthAwarePaginator}
     */
    public function getLandingPageData(Tenant $tenant, int $page = 1): array
    {
        $themeConfig = $this->tenantThemeService->resolveThemeConfig($tenant);
        $cookProfile = $this->buildCookProfile($tenant);
        $meals = $this->getAvailableMeals($tenant, $page);
        $sections = $this->buildSections($tenant);

        return [
            'tenant' => $tenant,
            'themeConfig' => $themeConfig,
            'cookProfile' => $cookProfile,
            'sections' => $sections,
            'meals' => $meals,
        ];
    }

    /**
     * Build the cook profile data for display.
     *
     * F-127: Cook Brand Header Section
     * BR-136: Brand name from locale (handled by HasTranslatable)
     * BR-137: Tagline is bio excerpt (first 150 chars)
     * BR-141/BR-142: Rating badge with stars and review count
     * BR-143: "New Cook" badge for zero reviews
     *
     * @return array{name: string, bio: string|null, bioExcerpt: string|null, hasImages: bool, coverImages: array, rating: array{average: float, count: int, hasReviews: bool}, whatsapp: string|null, phone: string|null, socialLinks: array}
     */
    private function buildCookProfile(Tenant $tenant): array
    {
        $coverImages = $tenant->getMedia('cover-images')
            ->sortBy('order_column')
            ->map(fn ($media) => [
                'url' => $media->getUrl('carousel'),
                'thumbnail' => $media->getUrl('thumbnail'),
            ])
            ->values()
            ->toArray();

        $bio = $tenant->description;
        $bioExcerpt = $this->truncateBio($bio, 150);
        $rating = $this->buildRatingData($tenant);

        return [
            'name' => $tenant->name,
            'bio' => $bio,
            'bioExcerpt' => $bioExcerpt,
            'hasImages' => ! empty($coverImages),
            'coverImages' => $coverImages,
            'rating' => $rating,
            'whatsapp' => $tenant->whatsapp,
            'phone' => $tenant->phone,
            'socialLinks' => [
                'facebook' => $tenant->social_facebook,
                'instagram' => $tenant->social_instagram,
                'tiktok' => $tenant->social_tiktok,
            ],
        ];
    }

    /**
     * Truncate the bio to a maximum character length, respecting word boundaries.
     *
     * BR-137: Tagline shows first 150 characters of bio, truncated.
     */
    private function truncateBio(?string $bio, int $maxLength): ?string
    {
        if (empty($bio)) {
            return null;
        }

        if (mb_strlen($bio) <= $maxLength) {
            return $bio;
        }

        $truncated = mb_substr($bio, 0, $maxLength);
        $lastSpace = mb_strrpos($truncated, ' ');

        if ($lastSpace !== false && $lastSpace > $maxLength * 0.7) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return $truncated.'...';
    }

    /**
     * Build rating data for the cook.
     *
     * BR-141: Average star rating with numeric value
     * BR-142: Total review count
     * BR-143: "New Cook" badge for zero reviews
     *
     * Forward-compatible: F-176 (Rating System) will provide actual data.
     * Until then, returns zero reviews / "New Cook" state.
     *
     * @return array{average: float, count: int, hasReviews: bool}
     */
    private function buildRatingData(Tenant $tenant): array
    {
        // Forward-compatible: when F-176 rating system is implemented,
        // this will query actual rating data from the orders/reviews table.
        // For now, all cooks show as "New Cook" (zero reviews).
        $average = 0.0;
        $count = 0;

        // Future F-176 implementation will replace the above with:
        // if (\Schema::hasTable('order_ratings')) {
        //     $stats = $tenant->orderRatings()->selectRaw('AVG(rating) as avg, COUNT(*) as cnt')->first();
        //     $average = round((float) ($stats->avg ?? 0), 1);
        //     $count = (int) ($stats->cnt ?? 0);
        // }

        return [
            'average' => $average,
            'count' => $count,
            'hasReviews' => $count > 0,
        ];
    }

    /**
     * Get available meals for the tenant landing page grid.
     *
     * F-128: Available Meals Grid Display
     * BR-146: Only meals with status=live AND is_available=true
     * BR-147: Filtered against cook's current schedule
     * BR-148: Each card data includes image, name, starting price, prep time, tags
     * BR-149: Starting price = min component price
     * BR-155: Ordered by position, then by name
     */
    public function getAvailableMeals(Tenant $tenant, int $page = 1): LengthAwarePaginator
    {
        $scheduledDays = $this->getScheduledDays($tenant);

        $query = Meal::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', Meal::STATUS_LIVE)
            ->where('is_available', true)
            ->with([
                'images' => fn ($q) => $q->orderBy('position')->limit(1),
                'components' => fn ($q) => $q->where('is_available', true)->select('id', 'meal_id', 'price'),
                'tags' => fn ($q) => $q->select('tags.id', 'tags.name_en', 'tags.name_fr'),
            ]);

        // BR-147: Filter meals by schedule
        if (! empty($scheduledDays)) {
            $query->where(function ($q) use ($scheduledDays) {
                // Meals with custom schedules: filter by their own schedule days
                $q->whereHas('schedules', function ($sq) use ($scheduledDays) {
                    $sq->where('is_available', true)
                        ->whereIn('day_of_week', $scheduledDays);
                });
                // Meals without custom schedules: use cook's schedule (all days apply)
                $q->orWhereDoesntHave('schedules');
            });
        }

        // BR-155: Order by position, then name
        $locale = app()->getLocale();
        $nameColumn = 'name_'.$locale;

        return $query
            ->orderBy('position')
            ->orderBy($nameColumn)
            ->paginate(self::MEALS_PER_PAGE, ['*'], 'page', $page);
    }

    /**
     * Get the days of the week that the cook has available schedules for.
     *
     * BR-147: Only meals orderable on today or upcoming schedule days appear.
     * This returns all days that have at least one available schedule entry.
     *
     * @return array<string> Array of day_of_week values (e.g., ['monday', 'wednesday'])
     */
    private function getScheduledDays(Tenant $tenant): array
    {
        return CookSchedule::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_available', true)
            ->distinct()
            ->pluck('day_of_week')
            ->toArray();
    }

    /**
     * Build a meal card data array for the view.
     *
     * BR-148: Primary image, name, starting price, prep time, tags, availability
     * BR-149: Starting price = minimum component price
     * BR-150: Prep time as badge
     * BR-151: Tags as chips (max 3 visible + "+N more")
     * BR-154: Name shown in user's current language
     *
     * @return array{id: int, name: string, slug: string, image: string|null, startingPrice: int|null, prepTime: int|null, tags: array, tagOverflow: int, allComponentsUnavailable: bool}
     */
    public function buildMealCardData(Meal $meal): array
    {
        $locale = app()->getLocale();
        $name = $meal->{'name_'.$locale} ?? $meal->name_en;

        // BR-149: Starting price is the minimum available component price
        $availableComponents = $meal->components->where('is_available', true);
        $startingPrice = $availableComponents->isNotEmpty()
            ? $availableComponents->min('price')
            : null;

        // BR-148: Primary image (first by position)
        $primaryImage = $meal->images->first();
        $imageUrl = $primaryImage ? $primaryImage->thumbnail_url : null;

        // BR-151: Tags, max 3 visible
        $allTags = $meal->tags->map(fn ($tag) => [
            'id' => $tag->id,
            'name' => $tag->{'name_'.$locale} ?? $tag->name_en,
        ])->toArray();
        $visibleTags = array_slice($allTags, 0, 3);
        $tagOverflow = max(0, count($allTags) - 3);

        // Edge case: all components unavailable = "Sold Out"
        $allComponentsUnavailable = $meal->components->isNotEmpty()
            && $meal->components->where('is_available', true)->isEmpty();

        return [
            'id' => $meal->id,
            'name' => $name,
            'image' => $imageUrl,
            'startingPrice' => $startingPrice,
            'prepTime' => $meal->estimated_prep_time,
            'tags' => $visibleTags,
            'tagOverflow' => $tagOverflow,
            'allComponentsUnavailable' => $allComponentsUnavailable,
        ];
    }

    /**
     * Format a price in XAF currency.
     */
    public static function formatPrice(int $price): string
    {
        return number_format($price, 0, '.', ',').' XAF';
    }

    /**
     * Build the sections configuration for the landing page.
     *
     * BR-133: Sections render in order: hero, meals grid, about/bio,
     * ratings summary, testimonials, schedule, delivery areas, footer.
     *
     * Each section indicates whether it has data to show, allowing
     * child features (F-127 through F-134) to populate them.
     *
     * @return array<string, array{id: string, label: string, hasData: bool}>
     */
    private function buildSections(Tenant $tenant): array
    {
        $hasBio = ! empty($tenant->description);
        $hasCoverImages = $tenant->getMedia('cover-images')->isNotEmpty();
        $hasMeals = $tenant->meals()->where('status', 'live')->where('is_active', true)->exists();
        $hasSchedule = $tenant->cookSchedules()->exists();
        $hasDeliveryAreas = $tenant->deliveryAreas()->exists();

        return [
            'hero' => [
                'id' => 'hero',
                'label' => __('Home'),
                'hasData' => $hasCoverImages || $hasBio,
            ],
            'meals' => [
                'id' => 'meals',
                'label' => __('Meals'),
                'hasData' => $hasMeals,
            ],
            'about' => [
                'id' => 'about',
                'label' => __('About'),
                'hasData' => $hasBio,
            ],
            'ratings' => [
                'id' => 'ratings',
                'label' => __('Ratings'),
                'hasData' => false, // Populated by F-130
            ],
            'testimonials' => [
                'id' => 'testimonials',
                'label' => __('Testimonials'),
                'hasData' => false, // Populated by F-131
            ],
            'schedule' => [
                'id' => 'schedule',
                'label' => __('Schedule'),
                'hasData' => $hasSchedule,
            ],
            'delivery' => [
                'id' => 'delivery',
                'label' => __('Delivery'),
                'hasData' => $hasDeliveryAreas,
            ],
        ];
    }
}
