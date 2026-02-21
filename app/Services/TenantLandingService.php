<?php

namespace App\Services;

use App\Models\ComponentRequirementRule;
use App\Models\CookSchedule;
use App\Models\DeliveryArea;
use App\Models\DeliveryAreaQuarter;
use App\Models\Meal;
use App\Models\MealComponent;
use App\Models\MealSchedule;
use App\Models\PickupLocation;
use App\Models\QuarterGroup;
use App\Models\Tag;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * F-126: Tenant Landing Page data aggregation service.
 * F-179: Integrates cook overall rating from cached tenant settings.
 */
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
     * F-132: Schedule & Availability Display
     * BR-126: Only renders on tenant domains
     * BR-127: Cook's selected theme applied dynamically
     *
     * @return array{tenant: Tenant, themeConfig: array, sections: array, cookProfile: array, meals: LengthAwarePaginator, scheduleDisplay: array, deliveryDisplay: array, filterData: array, cancellationWindowMinutes: int, minimumOrderAmount: int}
     */
    public function getLandingPageData(Tenant $tenant, int $page = 1): array
    {
        $themeConfig = $this->tenantThemeService->resolveThemeConfig($tenant);
        $cookProfile = $this->buildCookProfile($tenant);
        $meals = $this->getAvailableMeals($tenant, $page);
        $sections = $this->buildSections($tenant);
        $scheduleDisplay = $this->getScheduleDisplayData($tenant);
        $deliveryDisplay = $this->getDeliveryDisplayData($tenant);
        $filterData = $this->getFilterData($tenant);

        // F-212 BR-501: The cancellation policy is displayed on the tenant landing page.
        $cancellationWindowMinutes = $tenant->getCancellationWindowMinutes();

        // F-213 BR-512: The minimum order amount is displayed on the tenant landing page (when > 0).
        $minimumOrderAmount = $tenant->getMinimumOrderAmount();

        return [
            'tenant' => $tenant,
            'themeConfig' => $themeConfig,
            'cookProfile' => $cookProfile,
            'sections' => $sections,
            'meals' => $meals,
            'scheduleDisplay' => $scheduleDisplay,
            'deliveryDisplay' => $deliveryDisplay,
            'filterData' => $filterData,
            'cancellationWindowMinutes' => $cancellationWindowMinutes,
            'minimumOrderAmount' => $minimumOrderAmount,
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
     * F-179: Cook Overall Rating Calculation
     * BR-141: Average star rating with numeric value
     * BR-142: Total review count
     * BR-143: "New Cook" badge for zero reviews
     * BR-417: Simple average of all stars / number of ratings
     * BR-418: Displayed as X.X/5 with one decimal place
     * BR-421: Reads cached values from tenant settings JSON
     * BR-423: hasReviews=false for zero ratings (shows "New Cook")
     *
     * @return array{average: float, count: int, hasReviews: bool}
     */
    private function buildRatingData(Tenant $tenant): array
    {
        $ratingService = app(RatingService::class);
        $cached = $ratingService->getCachedCookRating($tenant);

        return [
            'average' => $cached['average'],
            'count' => $cached['count'],
            'hasReviews' => $cached['hasRating'],
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
     * Search available meals for a tenant by query string.
     *
     * F-135: Meal Search Bar
     * BR-214: Search matches meal name (en/fr), description (en/fr), component names, tag names
     * BR-215: Case-insensitive search
     * BR-217: Results filter the existing meals grid via Gale (no page reload)
     * BR-221: Minimum 2 characters required to trigger search
     */
    public function searchMeals(Tenant $tenant, string $query, int $page = 1): LengthAwarePaginator
    {
        $scheduledDays = $this->getScheduledDays($tenant);
        $locale = app()->getLocale();
        $nameColumn = 'name_'.$locale;

        // BR-215: Case-insensitive search using PostgreSQL ILIKE
        $searchTerm = '%'.addcslashes($query, '%_\\').'%';

        $mealQuery = Meal::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', Meal::STATUS_LIVE)
            ->where('is_available', true)
            ->with([
                'images' => fn ($q) => $q->orderBy('position')->limit(1),
                'components' => fn ($q) => $q->where('is_available', true)->select('id', 'meal_id', 'price'),
                'tags' => fn ($q) => $q->select('tags.id', 'tags.name_en', 'tags.name_fr'),
            ]);

        // BR-214: Search across meal names, descriptions, component names, tag names
        $mealQuery->where(function ($q) use ($searchTerm) {
            // Match meal name (en/fr)
            $q->where('name_en', 'ILIKE', $searchTerm)
                ->orWhere('name_fr', 'ILIKE', $searchTerm)
                // Match meal description (en/fr)
                ->orWhere('description_en', 'ILIKE', $searchTerm)
                ->orWhere('description_fr', 'ILIKE', $searchTerm)
                // Match component names
                ->orWhereHas('components', function ($cq) use ($searchTerm) {
                    $cq->where('name_en', 'ILIKE', $searchTerm)
                        ->orWhere('name_fr', 'ILIKE', $searchTerm);
                })
                // Match tag names
                ->orWhereHas('tags', function ($tq) use ($searchTerm) {
                    $tq->where('tags.name_en', 'ILIKE', $searchTerm)
                        ->orWhere('tags.name_fr', 'ILIKE', $searchTerm);
                });
        });

        // BR-147: Filter meals by schedule (same logic as getAvailableMeals)
        if (! empty($scheduledDays)) {
            $mealQuery->where(function ($q) use ($scheduledDays) {
                $q->whereHas('schedules', function ($sq) use ($scheduledDays) {
                    $sq->where('is_available', true)
                        ->whereIn('day_of_week', $scheduledDays);
                });
                $q->orWhereDoesntHave('schedules');
            });
        }

        return $mealQuery
            ->orderBy('position')
            ->orderBy($nameColumn)
            ->paginate(self::MEALS_PER_PAGE, ['*'], 'page', $page);
    }

    /**
     * Get filter data for the tenant's meals (tags, price range).
     *
     * F-136: Meal Filters
     * BR-223: Tag filter populated from cook's meal tags
     * BR-227: Price range bounds from meal starting prices
     *
     * @return array{tags: array, priceRange: array{min: int, max: int}, hasTags: bool, hasPriceRange: bool}
     */
    public function getFilterData(Tenant $tenant): array
    {
        $locale = app()->getLocale();

        // Get tags that are actually assigned to live+available meals for this cook
        $tags = Tag::query()
            ->where('tags.tenant_id', $tenant->id)
            ->whereHas('meals', function (Builder $q) use ($tenant) {
                $q->where('tenant_id', $tenant->id)
                    ->where('status', Meal::STATUS_LIVE)
                    ->where('is_available', true);
            })
            ->orderBy('name_'.$locale)
            ->get()
            ->map(fn (Tag $tag) => [
                'id' => $tag->id,
                'name' => $tag->{'name_'.$locale} ?? $tag->name_en,
            ])
            ->toArray();

        // Get price range from available meal component prices
        $priceStats = MealComponent::query()
            ->whereHas('meal', function (Builder $q) use ($tenant) {
                $q->where('tenant_id', $tenant->id)
                    ->where('status', Meal::STATUS_LIVE)
                    ->where('is_available', true);
            })
            ->where('is_available', true)
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();

        $minPrice = (int) ($priceStats->min_price ?? 0);
        $maxPrice = (int) ($priceStats->max_price ?? 0);

        return [
            'tags' => $tags,
            'priceRange' => [
                'min' => $minPrice,
                'max' => $maxPrice,
            ],
            'hasTags' => ! empty($tags),
            'hasPriceRange' => $minPrice !== $maxPrice && $maxPrice > 0,
        ];
    }

    /**
     * Filter available meals with search, tags, availability, and price range.
     *
     * F-136: Meal Filters
     * BR-223: Tag filter OR logic (meals matching ANY selected tag)
     * BR-224/BR-225: Availability filter ("Available Now" = within current schedule window)
     * BR-226: Price range uses meal starting price (min component price)
     * BR-228: AND logic between filter types
     * BR-232: Combinable with search (F-135)
     *
     * @param  array<int>  $tagIds
     */
    public function filterMeals(
        Tenant $tenant,
        string $searchQuery = '',
        array $tagIds = [],
        string $availability = 'all',
        ?int $priceMin = null,
        ?int $priceMax = null,
        int $page = 1,
    ): LengthAwarePaginator {
        $scheduledDays = $this->getScheduledDays($tenant);
        $locale = app()->getLocale();
        $nameColumn = 'name_'.$locale;

        $query = Meal::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', Meal::STATUS_LIVE)
            ->where('is_available', true)
            ->with([
                'images' => fn ($q) => $q->orderBy('position')->limit(1),
                'components' => fn ($q) => $q->where('is_available', true)->select('id', 'meal_id', 'price'),
                'tags' => fn ($q) => $q->select('tags.id', 'tags.name_en', 'tags.name_fr'),
            ]);

        // BR-232: Apply search query (from F-135)
        if (mb_strlen($searchQuery) >= 2) {
            $searchTerm = '%'.addcslashes($searchQuery, '%_\\').'%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name_en', 'ILIKE', $searchTerm)
                    ->orWhere('name_fr', 'ILIKE', $searchTerm)
                    ->orWhere('description_en', 'ILIKE', $searchTerm)
                    ->orWhere('description_fr', 'ILIKE', $searchTerm)
                    ->orWhereHas('components', function ($cq) use ($searchTerm) {
                        $cq->where('name_en', 'ILIKE', $searchTerm)
                            ->orWhere('name_fr', 'ILIKE', $searchTerm);
                    })
                    ->orWhereHas('tags', function ($tq) use ($searchTerm) {
                        $tq->where('tags.name_en', 'ILIKE', $searchTerm)
                            ->orWhere('tags.name_fr', 'ILIKE', $searchTerm);
                    });
            });
        }

        // BR-223: Tag filter — OR logic within tags
        if (! empty($tagIds)) {
            $query->whereHas('tags', function ($tq) use ($tagIds) {
                $tq->whereIn('tags.id', $tagIds);
            });
        }

        // BR-224/BR-225: Availability filter — "Available Now" means within current schedule window
        if ($availability === 'available_now') {
            $this->applyAvailableNowFilter($query, $tenant);
        } else {
            // Default "all" filter still respects schedule days
            if (! empty($scheduledDays)) {
                $query->where(function ($q) use ($scheduledDays) {
                    $q->whereHas('schedules', function ($sq) use ($scheduledDays) {
                        $sq->where('is_available', true)
                            ->whereIn('day_of_week', $scheduledDays);
                    });
                    $q->orWhereDoesntHave('schedules');
                });
            }
        }

        // BR-226: Price range filter using starting price (min component price)
        // Starting price = MIN(price) of available components for each meal
        if ($priceMin !== null || $priceMax !== null) {
            $startingPriceSql = '(SELECT MIN(mc_price.price) FROM meal_components mc_price WHERE mc_price.meal_id = meals.id AND mc_price.is_available = true)';

            if ($priceMin !== null) {
                $query->whereRaw($startingPriceSql.' >= ?', [$priceMin]);
            }
            if ($priceMax !== null) {
                $query->whereRaw($startingPriceSql.' <= ?', [$priceMax]);
            }
        }

        return $query
            ->orderBy('position')
            ->orderBy($nameColumn)
            ->paginate(self::MEALS_PER_PAGE, ['*'], 'page', $page);
    }

    /**
     * Apply "Available Now" filter to a meal query.
     *
     * BR-225: "Available Now" means the meal can be ordered within the current
     * active schedule window. Check both meal-specific schedules and cook schedules.
     */
    private function applyAvailableNowFilter(Builder $query, Tenant $tenant): void
    {
        $now = Carbon::now('Africa/Douala');
        $todayDay = strtolower($now->format('l'));

        // Get cook schedules that are currently active (within order window)
        $activeCookScheduleIds = CookSchedule::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_available', true)
            ->get()
            ->filter(function ($schedule) use ($now) {
                if (! $schedule->hasOrderInterval()) {
                    return false;
                }
                $window = $this->resolveOrderWindowDates($schedule, $now);

                return $window !== null && $now->between($window['start'], $window['end']);
            })
            ->pluck('day_of_week')
            ->unique()
            ->toArray();

        if (empty($activeCookScheduleIds)) {
            // No active windows at all — filter to nothing
            $query->whereRaw('1 = 0');

            return;
        }

        // Meals with custom schedules: must have an active window day
        // Meals without custom schedules: cook's active window days apply
        $query->where(function ($q) use ($activeCookScheduleIds) {
            $q->whereHas('schedules', function ($sq) use ($activeCookScheduleIds) {
                $sq->where('is_available', true)
                    ->whereIn('day_of_week', $activeCookScheduleIds);
            });
            $q->orWhereDoesntHave('schedules');
        });
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
     * Get full meal detail data for the meal detail page.
     *
     * F-129: Meal Detail View
     * BR-156: Displays name, description, images, all components, schedule, locations
     * BR-157: Image carousel up to 3 images
     * BR-158: Each component shows name, price, unit, availability status
     * BR-160: Requirement rules displayed in plain language
     * BR-164: Schedule with day/time information
     * BR-165: Available delivery towns and pickup locations
     *
     * @return array{meal: array, components: array, schedule: array, locations: array}
     */
    public function getMealDetailData(Meal $meal, Tenant $tenant): array
    {
        $meal->load([
            'images' => fn ($q) => $q->orderBy('position'),
            'components' => fn ($q) => $q->orderBy('position'),
            'components.requirementRules.targetComponents',
            'tags',
        ]);

        $locale = app()->getLocale();

        return [
            'meal' => $this->buildMealDetail($meal, $locale),
            'components' => $this->buildComponentsDetail($meal, $locale),
            'schedule' => $this->buildScheduleDetail($meal, $tenant, $locale),
            'locations' => $this->buildLocationsDetail($meal, $tenant, $locale),
        ];
    }

    /**
     * Build the core meal data for the detail view.
     *
     * @return array{id: int, name: string, description: string|null, images: array, tags: array, prepTime: int|null, allUnavailable: bool, hasComponents: bool}
     */
    private function buildMealDetail(Meal $meal, string $locale): array
    {
        $name = $meal->{'name_'.$locale} ?? $meal->name_en;
        $description = $meal->{'description_'.$locale} ?? $meal->description_en;

        $images = $meal->images->map(fn ($image) => [
            'url' => $image->url,
            'thumbnail' => $image->thumbnail_url,
        ])->toArray();

        $tags = $meal->tags->map(fn ($tag) => [
            'id' => $tag->id,
            'name' => $tag->{'name_'.$locale} ?? $tag->name_en,
        ])->toArray();

        $allUnavailable = $meal->components->isNotEmpty()
            && $meal->components->where('is_available', true)->isEmpty();

        return [
            'id' => $meal->id,
            'name' => $name,
            'description' => $description,
            'images' => $images,
            'tags' => $tags,
            'prepTime' => $meal->estimated_prep_time,
            'allUnavailable' => $allUnavailable,
            'hasComponents' => $meal->components->isNotEmpty(),
        ];
    }

    /**
     * Build the component list for the detail view.
     *
     * BR-158: Each component shows name, price, unit, availability status
     * BR-159: Availability statuses: "Available", "Low Stock (X left)", "Sold Out"
     * BR-160: Requirement rules in plain language
     * BR-161: Quantity selector respects min/max/stock limits
     * BR-162: Add to Cart disabled for sold-out components
     *
     * @return array<int, array{id: int, name: string, price: int, formattedPrice: string, unit: string, isAvailable: bool, availabilityStatus: string, availabilityColor: string, maxSelectable: int, minQuantity: int, isFree: bool, requirements: array}>
     */
    private function buildComponentsDetail(Meal $meal, string $locale): array
    {
        return $meal->components->map(function (MealComponent $component) use ($locale) {
            $name = $component->{'name_'.$locale} ?? $component->name_en;
            $unit = $component->unit_label;

            // BR-159: Determine availability status
            $availabilityData = $this->getComponentAvailability($component);

            // BR-161: Calculate max selectable quantity
            $maxSelectable = $this->getMaxSelectableQuantity($component);

            // BR-160: Build requirement rules in plain language
            $requirements = $this->buildRequirementRules($component, $locale);

            // Edge case: price 0 = free add-on
            $isFree = $component->price === 0;

            return [
                'id' => $component->id,
                'name' => $name,
                'price' => $component->price,
                'formattedPrice' => $isFree ? __('Free') : self::formatPrice($component->price),
                'unit' => $unit,
                'isAvailable' => $component->is_available && ! $component->isOutOfStock(),
                'availabilityStatus' => $availabilityData['status'],
                'availabilityColor' => $availabilityData['color'],
                'maxSelectable' => $maxSelectable,
                'minQuantity' => $component->min_quantity ?? 1,
                'isFree' => $isFree,
                'requirements' => $requirements,
            ];
        })->toArray();
    }

    /**
     * Get the availability status and color for a component.
     *
     * BR-159: "Available", "Low Stock (X left)", "Sold Out"
     *
     * @return array{status: string, color: string}
     */
    private function getComponentAvailability(MealComponent $component): array
    {
        if (! $component->is_available || $component->isOutOfStock()) {
            return [
                'status' => __('Sold Out'),
                'color' => 'danger',
            ];
        }

        if ($component->isLowStock()) {
            return [
                'status' => __('Only :count left', ['count' => $component->available_quantity]),
                'color' => 'warning',
            ];
        }

        return [
            'status' => __('Available'),
            'color' => 'success',
        ];
    }

    /**
     * Get the maximum selectable quantity for a component.
     *
     * BR-161: Quantity max based on available stock or cook-defined max.
     */
    private function getMaxSelectableQuantity(MealComponent $component): int
    {
        $limits = [];

        if (! $component->hasUnlimitedMaxQuantity()) {
            $limits[] = $component->max_quantity;
        }

        if (! $component->hasUnlimitedAvailableQuantity()) {
            $limits[] = $component->available_quantity;
        }

        if (empty($limits)) {
            return 99; // Reasonable upper bound for unlimited
        }

        return max(1, min($limits));
    }

    /**
     * Build requirement rules in plain language.
     *
     * BR-160: "Requires: Ndole (main dish)", "Requires all of: X, Y", "Incompatible with: Z"
     *
     * @return array<int, array{type: string, label: string, components: string}>
     */
    private function buildRequirementRules(MealComponent $component, string $locale): array
    {
        return $component->requirementRules->map(function (ComponentRequirementRule $rule) use ($locale) {
            $targetNames = $rule->targetComponents->map(
                fn ($target) => $target->{'name_'.$locale} ?? $target->name_en
            )->toArray();

            return [
                'type' => $rule->rule_type,
                'label' => $rule->rule_type_label,
                'components' => implode(', ', $targetNames),
            ];
        })->toArray();
    }

    /**
     * Build schedule data for a meal.
     *
     * BR-164: Shows day/time information for when the meal is orderable.
     * Uses meal-specific schedule override (F-106) if available,
     * otherwise falls back to cook's tenant schedule (F-098).
     *
     * @return array{hasSchedule: bool, source: string, entries: array}
     */
    private function buildScheduleDetail(Meal $meal, Tenant $tenant, string $locale): array
    {
        // Check for meal-specific schedule (F-106)
        $mealSchedules = MealSchedule::query()
            ->where('meal_id', $meal->id)
            ->where('is_available', true)
            ->orderByRaw("CASE day_of_week WHEN 'monday' THEN 1 WHEN 'tuesday' THEN 2 WHEN 'wednesday' THEN 3 WHEN 'thursday' THEN 4 WHEN 'friday' THEN 5 WHEN 'saturday' THEN 6 WHEN 'sunday' THEN 7 END")
            ->orderBy('position')
            ->get();

        if ($mealSchedules->isNotEmpty()) {
            return [
                'hasSchedule' => true,
                'source' => 'meal',
                'entries' => $this->formatScheduleEntries($mealSchedules, $locale),
            ];
        }

        // Fallback to cook schedule (F-098)
        $cookSchedules = CookSchedule::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_available', true)
            ->orderByRaw("CASE day_of_week WHEN 'monday' THEN 1 WHEN 'tuesday' THEN 2 WHEN 'wednesday' THEN 3 WHEN 'thursday' THEN 4 WHEN 'friday' THEN 5 WHEN 'saturday' THEN 6 WHEN 'sunday' THEN 7 END")
            ->orderBy('position')
            ->get();

        if ($cookSchedules->isNotEmpty()) {
            return [
                'hasSchedule' => true,
                'source' => 'cook',
                'entries' => $this->formatScheduleEntries($cookSchedules, $locale),
            ];
        }

        return [
            'hasSchedule' => false,
            'source' => 'none',
            'entries' => [],
        ];
    }

    /**
     * Format schedule entries for display.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $schedules
     * @return array<int, array{day: string, dayLabel: string, label: string, orderInterval: string|null, deliveryInterval: string|null, pickupInterval: string|null, isToday: bool}>
     */
    private function formatScheduleEntries($schedules, string $locale): array
    {
        $today = strtolower(now()->format('l')); // e.g., 'monday'

        return $schedules->map(function ($schedule) use ($today) {
            return [
                'day' => $schedule->day_of_week,
                'dayLabel' => $schedule->day_label,
                'label' => $schedule->display_label,
                'orderInterval' => $schedule->order_interval_summary,
                'deliveryInterval' => $schedule->delivery_interval_summary,
                'pickupInterval' => $schedule->pickup_interval_summary,
                'isToday' => $schedule->day_of_week === $today,
            ];
        })->toArray();
    }

    /**
     * Build delivery and pickup location data.
     *
     * BR-165: Available delivery towns and pickup locations.
     * Uses meal-specific location overrides (F-096) if available,
     * otherwise falls back to cook's delivery areas.
     *
     * @return array{deliveryTowns: array, pickupLocations: array, hasLocations: bool}
     */
    private function buildLocationsDetail(Meal $meal, Tenant $tenant, string $locale): array
    {
        $deliveryTowns = [];
        $pickupLocations = [];

        // Check for meal-specific location overrides (F-096)
        if ($meal->has_custom_locations) {
            $overrides = $meal->locationOverrides()
                ->with(['quarter.town', 'pickupLocation.town'])
                ->get();

            foreach ($overrides as $override) {
                if ($override->isDeliveryOverride() && $override->quarter && $override->quarter->town) {
                    $town = $override->quarter->town;
                    $townName = $town->{'name_'.$locale} ?? $town->name_en;
                    $quarterName = $override->quarter->{'name_'.$locale} ?? $override->quarter->name_en;

                    if (! isset($deliveryTowns[$town->id])) {
                        $deliveryTowns[$town->id] = [
                            'name' => $townName,
                            'quarters' => [],
                        ];
                    }

                    $deliveryTowns[$town->id]['quarters'][] = [
                        'name' => $quarterName,
                        'fee' => $override->custom_delivery_fee ?? 0,
                        'formattedFee' => $override->custom_delivery_fee
                            ? self::formatPrice($override->custom_delivery_fee)
                            : __('Free'),
                    ];
                }

                if ($override->isPickupOverride() && $override->pickupLocation) {
                    $pickup = $override->pickupLocation;
                    $pickupLocations[] = [
                        'name' => $pickup->{'name_'.$locale} ?? $pickup->name_en,
                        'town' => $pickup->town ? ($pickup->town->{'name_'.$locale} ?? $pickup->town->name_en) : '',
                        'address' => $pickup->address,
                    ];
                }
            }

            return [
                'deliveryTowns' => array_values($deliveryTowns),
                'pickupLocations' => $pickupLocations,
                'hasLocations' => ! empty($deliveryTowns) || ! empty($pickupLocations),
            ];
        }

        // Default: cook's delivery areas
        $areas = DeliveryArea::query()
            ->where('tenant_id', $tenant->id)
            ->with(['town', 'deliveryAreaQuarters.quarter'])
            ->get();

        foreach ($areas as $area) {
            if ($area->town) {
                $townName = $area->town->{'name_'.$locale} ?? $area->town->name_en;
                $deliveryTowns[$area->town->id] = [
                    'name' => $townName,
                    'quarters' => $area->deliveryAreaQuarters->map(function ($daq) use ($locale) {
                        $quarterName = $daq->quarter ? ($daq->quarter->{'name_'.$locale} ?? $daq->quarter->name_en) : '';

                        return [
                            'name' => $quarterName,
                            'fee' => $daq->delivery_fee,
                            'formattedFee' => self::formatPrice($daq->delivery_fee),
                        ];
                    })->toArray(),
                ];
            }
        }

        // Pickup locations
        $pickups = PickupLocation::query()
            ->where('tenant_id', $tenant->id)
            ->with(['town', 'quarter'])
            ->get();

        $pickupLocations = $pickups->map(function ($pickup) use ($locale) {
            $name = $pickup->{'name_'.$locale} ?? $pickup->name_en;
            $townName = $pickup->town ? ($pickup->town->{'name_'.$locale} ?? $pickup->town->name_en) : '';

            return [
                'name' => $name,
                'town' => $townName,
                'address' => $pickup->address,
            ];
        })->toArray();

        return [
            'deliveryTowns' => array_values($deliveryTowns),
            'pickupLocations' => $pickupLocations,
            'hasLocations' => ! empty($deliveryTowns) || ! empty($pickupLocations),
        ];
    }

    /**
     * Build the full 7-day schedule display data for the tenant landing page.
     *
     * F-132: Schedule & Availability Display
     * BR-186: All 7 days displayed with availability status
     * BR-187: Order, delivery, pickup windows shown per day/slot
     * BR-188: Current day highlighted
     * BR-189: "Available Now" badge when within active order window
     * BR-190: "Next available" badge when outside order windows
     * BR-191: Unavailable days clearly marked
     * BR-192: Multiple slots per day displayed with labels
     * BR-193: Times in Africa/Douala timezone
     * BR-194: All text localized via __()
     *
     * @return array{hasSchedule: bool, days: array, availabilityBadge: array, timezoneNote: string}
     */
    public function getScheduleDisplayData(Tenant $tenant): array
    {
        $schedules = CookSchedule::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('position')
            ->get();

        if ($schedules->isEmpty()) {
            return [
                'hasSchedule' => false,
                'days' => [],
                'availabilityBadge' => [
                    'type' => 'none',
                    'label' => __('Schedule not yet available. Contact the cook for ordering information.'),
                    'color' => 'info',
                ],
                'timezoneNote' => '',
            ];
        }

        $now = Carbon::now('Africa/Douala');
        $todayDay = strtolower($now->format('l'));

        // Build 7-day grid grouped by day
        $schedulesByDay = $schedules->groupBy('day_of_week');
        $days = $this->buildWeeklySchedule($schedulesByDay, $todayDay);

        // Build availability badge
        $availabilityBadge = $this->buildAvailabilityBadge($schedules, $now, $todayDay);

        return [
            'hasSchedule' => true,
            'days' => $days,
            'availabilityBadge' => $availabilityBadge,
            'timezoneNote' => __('All times shown in Africa/Douala timezone (WAT)'),
        ];
    }

    /**
     * Build the weekly schedule array for all 7 days.
     *
     * BR-186: All 7 days displayed
     * BR-191: Unavailable days clearly marked
     * BR-192: Multiple slots per day with labels
     *
     * @param  \Illuminate\Support\Collection  $schedulesByDay
     * @return array<int, array{day: string, dayLabel: string, dayShort: string, isToday: bool, isAvailable: bool, slots: array}>
     */
    private function buildWeeklySchedule($schedulesByDay, string $todayDay): array
    {
        $days = [];

        foreach (CookSchedule::DAYS_OF_WEEK as $day) {
            $daySchedules = $schedulesByDay->get($day, collect());
            $availableSlots = $daySchedules->where('is_available', true);

            $slots = $availableSlots->map(function ($schedule) {
                return [
                    'label' => $schedule->display_label,
                    'orderInterval' => $schedule->order_interval_summary,
                    'deliveryInterval' => $schedule->delivery_interval_summary,
                    'pickupInterval' => $schedule->pickup_interval_summary,
                    'hasOrderInterval' => $schedule->hasOrderInterval(),
                    'hasDeliveryInterval' => $schedule->hasDeliveryInterval(),
                    'hasPickupInterval' => $schedule->hasPickupInterval(),
                ];
            })->values()->toArray();

            $days[] = [
                'day' => $day,
                'dayLabel' => __(CookSchedule::DAY_LABELS[$day] ?? 'Unknown'),
                'dayShort' => __(mb_substr(CookSchedule::DAY_LABELS[$day] ?? '', 0, 3)),
                'isToday' => $day === $todayDay,
                'isAvailable' => ! empty($slots),
                'slots' => $slots,
            ];
        }

        return $days;
    }

    /**
     * Build the availability badge data.
     *
     * BR-189: "Available Now" when current time is within an active order window
     * BR-190: "Next available: [day/time]" when outside order windows
     * Edge case: order window ends in < 15 min shows "Closing soon" warning
     * Edge case: all 7 days unavailable shows "Currently not accepting orders"
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $schedules
     * @return array{type: string, label: string, color: string}
     */
    private function buildAvailabilityBadge($schedules, Carbon $now, string $todayDay): array
    {
        $availableSchedules = $schedules->where('is_available', true);

        // Edge case: all days unavailable
        if ($availableSchedules->isEmpty()) {
            return [
                'type' => 'closed',
                'label' => __('Currently not accepting orders'),
                'color' => 'danger',
            ];
        }

        // Check if currently within an active order window
        $activeWindow = $this->findActiveOrderWindow($availableSchedules, $now, $todayDay);

        if ($activeWindow !== null) {
            $minutesLeft = $activeWindow['minutesRemaining'];

            if ($minutesLeft <= 15) {
                return [
                    'type' => 'closing_soon',
                    'label' => __('Available Now - Closing soon at :time', [
                        'time' => $activeWindow['endTimeFormatted'],
                    ]),
                    'color' => 'warning',
                ];
            }

            return [
                'type' => 'available',
                'label' => __('Available Now - Orders open until :time', [
                    'time' => $activeWindow['endTimeFormatted'],
                ]),
                'color' => 'success',
            ];
        }

        // Find next available window
        $nextWindow = $this->findNextOrderWindow($availableSchedules, $now, $todayDay);

        if ($nextWindow !== null) {
            return [
                'type' => 'next',
                'label' => __('Next available: :day at :time', [
                    'day' => $nextWindow['dayLabel'],
                    'time' => $nextWindow['startTimeFormatted'],
                ]),
                'color' => 'warning',
            ];
        }

        return [
            'type' => 'closed',
            'label' => __('Currently not accepting orders'),
            'color' => 'danger',
        ];
    }

    /**
     * Find an active order window that the current time falls within.
     *
     * BR-189: Check if now is between order_start and order_end, accounting for day offsets.
     * The order window is relative to the schedule's "open day" (day_of_week).
     * order_start can be on a previous day (offset > 0 means days before open day).
     * order_end can also be on a previous day (offset > 0) or same day (offset = 0).
     *
     * @param  \Illuminate\Support\Collection  $availableSchedules
     * @return array{endTimeFormatted: string, minutesRemaining: int}|null
     */
    private function findActiveOrderWindow($availableSchedules, Carbon $now, string $todayDay): ?array
    {
        foreach ($availableSchedules as $schedule) {
            if (! $schedule->hasOrderInterval()) {
                continue;
            }

            // Calculate the actual start and end datetimes for this week's window
            $window = $this->resolveOrderWindowDates($schedule, $now);

            if ($window === null) {
                continue;
            }

            if ($now->between($window['start'], $window['end'])) {
                $minutesRemaining = (int) $now->diffInMinutes($window['end'], false);

                return [
                    'endTimeFormatted' => $window['end']->format('g:i A'),
                    'minutesRemaining' => max(0, $minutesRemaining),
                ];
            }
        }

        return null;
    }

    /**
     * Resolve the absolute datetime boundaries for an order window this week.
     *
     * The schedule entry's day_of_week is the "open day" (when food is served).
     * order_start_day_offset means how many days BEFORE the open day the order window starts.
     * order_end_day_offset means how many days BEFORE the open day the order window ends.
     *
     * @return array{start: Carbon, end: Carbon}|null
     */
    private function resolveOrderWindowDates(CookSchedule $schedule, Carbon $now): ?array
    {
        $dayIndex = array_search($schedule->day_of_week, CookSchedule::DAYS_OF_WEEK);
        if ($dayIndex === false) {
            return null;
        }

        // Find this week's occurrence of the open day
        $openDay = $now->copy()->startOfWeek()->addDays($dayIndex);

        // If the open day is past and the window is fully past, look at next week
        $startOffset = $schedule->order_start_day_offset ?? 0;
        $endOffset = $schedule->order_end_day_offset ?? 0;

        // Calculate window start: open day minus start offset, at start time
        $windowStart = $openDay->copy()
            ->subDays($startOffset)
            ->setTimeFromTimeString($schedule->order_start_time);

        // Calculate window end: open day minus end offset, at end time
        $windowEnd = $openDay->copy()
            ->subDays($endOffset)
            ->setTimeFromTimeString($schedule->order_end_time);

        // Also check next week's window
        $nextWeekStart = $windowStart->copy()->addWeek();
        $nextWeekEnd = $windowEnd->copy()->addWeek();

        // Return whichever window the current time might fall in
        if ($now->between($windowStart, $windowEnd)) {
            return ['start' => $windowStart, 'end' => $windowEnd];
        }

        if ($now->between($nextWeekStart, $nextWeekEnd)) {
            return ['start' => $nextWeekStart, 'end' => $nextWeekEnd];
        }

        // For findNextOrderWindow, return this week's window
        return ['start' => $windowStart, 'end' => $windowEnd];
    }

    /**
     * Find the next upcoming order window.
     *
     * BR-190: "Next available: [day] at [time]"
     *
     * @param  \Illuminate\Support\Collection  $availableSchedules
     * @return array{dayLabel: string, startTimeFormatted: string}|null
     */
    private function findNextOrderWindow($availableSchedules, Carbon $now, string $todayDay): ?array
    {
        $candidates = [];

        foreach ($availableSchedules as $schedule) {
            if (! $schedule->hasOrderInterval()) {
                continue;
            }

            $window = $this->resolveOrderWindowDates($schedule, $now);

            if ($window === null) {
                continue;
            }

            // Find the next future start time
            $candidateStart = $window['start'];

            // If this window start is in the past, try next week
            if ($candidateStart->lte($now)) {
                $candidateStart = $candidateStart->copy()->addWeek();
            }

            $candidates[] = [
                'start' => $candidateStart,
                'dayLabel' => __(CookSchedule::DAY_LABELS[$schedule->day_of_week] ?? 'Unknown'),
                'startTimeFormatted' => Carbon::parse($schedule->order_start_time)->format('g:i A'),
            ];
        }

        if (empty($candidates)) {
            return null;
        }

        // Sort by earliest start time
        usort($candidates, fn ($a, $b) => $a['start']->timestamp <=> $b['start']->timestamp);

        return $candidates[0];
    }

    /**
     * Build the delivery areas display data for the tenant landing page.
     *
     * F-133: Delivery Areas & Fees Display
     * BR-195: Delivery areas organized hierarchically: town > quarters with fees
     * BR-196: Fee of 0 shows "Free delivery"
     * BR-197: Quarters with the same group fee are visually grouped
     * BR-198: Pickup locations listed separately with full address
     * BR-199: Pickup is always "Free"
     * BR-200: Fallback message for unlisted areas with WhatsApp contact
     * BR-201: Towns expandable/collapsible (all expanded on desktop)
     * BR-202: All text localized via __()
     * BR-203: Town/quarter names in user's current language
     *
     * @return array{hasDeliveryAreas: bool, towns: array, pickupLocations: array, hasPickupLocations: bool, whatsappLink: string|null}
     */
    public function getDeliveryDisplayData(Tenant $tenant): array
    {
        $locale = app()->getLocale();
        $orderColumn = 'name_'.$locale;

        // Get delivery areas with towns and quarters
        $deliveryAreas = DeliveryArea::query()
            ->where('tenant_id', $tenant->id)
            ->with(['town', 'deliveryAreaQuarters.quarter'])
            ->join('towns', 'delivery_areas.town_id', '=', 'towns.id')
            ->orderBy('towns.'.$orderColumn)
            ->select('delivery_areas.*')
            ->get();

        $towns = [];

        foreach ($deliveryAreas as $area) {
            if (! $area->town) {
                continue;
            }

            $townName = $area->town->{'name_'.$locale} ?? $area->town->name_en;
            $quarters = [];

            // Sort quarters alphabetically in current locale
            $sortedQuarters = $area->deliveryAreaQuarters
                ->sortBy(fn (DeliveryAreaQuarter $daq) => mb_strtolower($daq->quarter->{'name_'.$locale} ?? $daq->quarter->name_en));

            foreach ($sortedQuarters as $daq) {
                if (! $daq->quarter) {
                    continue;
                }

                $quarterName = $daq->quarter->{'name_'.$locale} ?? $daq->quarter->name_en;

                // BR-197: Check for group membership and group fee override
                $group = QuarterGroup::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereHas('quarters', function ($q) use ($daq) {
                        $q->where('quarters.id', $daq->quarter_id);
                    })
                    ->first();

                $effectiveFee = $group ? $group->delivery_fee : $daq->delivery_fee;
                $groupName = $group?->name;
                $groupId = $group?->id;

                $quarters[] = [
                    'name' => $quarterName,
                    'fee' => $effectiveFee,
                    'formattedFee' => $effectiveFee === 0
                        ? __('Free delivery')
                        : self::formatPrice($effectiveFee),
                    'isFree' => $effectiveFee === 0,
                    'groupName' => $groupName,
                    'groupId' => $groupId,
                ];
            }

            if (! empty($quarters)) {
                $towns[] = [
                    'id' => $area->id,
                    'name' => $townName,
                    'quarters' => $quarters,
                    'quarterCount' => count($quarters),
                ];
            }
        }

        // Get pickup locations
        $pickupLocations = PickupLocation::query()
            ->where('tenant_id', $tenant->id)
            ->with(['town', 'quarter'])
            ->orderBy('name_'.$locale)
            ->get()
            ->map(function (PickupLocation $pickup) use ($locale) {
                $name = $pickup->{'name_'.$locale} ?? $pickup->name_en;
                $townName = $pickup->town
                    ? ($pickup->town->{'name_'.$locale} ?? $pickup->town->name_en)
                    : '';
                $quarterName = $pickup->quarter
                    ? ($pickup->quarter->{'name_'.$locale} ?? $pickup->quarter->name_en)
                    : '';

                // Build full address string
                $addressParts = array_filter([$quarterName, $pickup->address, $townName]);

                return [
                    'name' => $name,
                    'fullAddress' => implode(', ', $addressParts),
                    'town' => $townName,
                    'quarter' => $quarterName,
                    'address' => $pickup->address,
                ];
            })
            ->toArray();

        // Build WhatsApp link for fallback message
        $whatsappLink = null;
        if ($tenant->whatsapp) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $tenant->whatsapp);
            $whatsappLink = 'https://wa.me/'.$cleanPhone;
        }

        return [
            'hasDeliveryAreas' => ! empty($towns),
            'towns' => $towns,
            'pickupLocations' => $pickupLocations,
            'hasPickupLocations' => ! empty($pickupLocations),
            'whatsappLink' => $whatsappLink,
        ];
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
        $hasDeliveryAreas = $tenant->deliveryAreas()->exists()
            || PickupLocation::where('tenant_id', $tenant->id)->exists();

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
