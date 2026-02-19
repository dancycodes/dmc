<?php

namespace App\Services;

use App\Models\ComponentRequirementRule;
use App\Models\CookSchedule;
use App\Models\DeliveryArea;
use App\Models\Meal;
use App\Models\MealComponent;
use App\Models\MealSchedule;
use App\Models\PickupLocation;
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
