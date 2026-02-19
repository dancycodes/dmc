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
     * @return array{name: string, bio: string|null, hasImages: bool, coverImages: array, whatsapp: string|null, phone: string|null, socialLinks: array}
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

        return [
            'name' => $tenant->name,
            'bio' => $tenant->description,
            'hasImages' => ! empty($coverImages),
            'coverImages' => $coverImages,
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
