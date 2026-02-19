<?php

namespace App\Services;

use App\Models\Tenant;

class TenantLandingService
{
    public function __construct(
        private TenantThemeService $tenantThemeService,
    ) {}

    /**
     * Gather all data needed for the tenant landing page.
     *
     * F-126: Tenant Landing Page Layout
     * BR-126: Only renders on tenant domains
     * BR-127: Cook's selected theme applied dynamically
     *
     * @return array{tenant: Tenant, themeConfig: array, sections: array, cookProfile: array}
     */
    public function getLandingPageData(Tenant $tenant): array
    {
        $themeConfig = $this->tenantThemeService->resolveThemeConfig($tenant);
        $cookProfile = $this->buildCookProfile($tenant);
        $sections = $this->buildSections($tenant);

        return [
            'tenant' => $tenant,
            'themeConfig' => $themeConfig,
            'cookProfile' => $cookProfile,
            'sections' => $sections,
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
