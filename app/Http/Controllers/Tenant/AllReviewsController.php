<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\TenantLandingService;
use Illuminate\Http\Request;

/**
 * F-130: Ratings Summary Display — All Reviews Page
 *
 * Renders a paginated list of all reviews for the tenant.
 * Accessible via the "See all reviews" link on the landing page (BR-172).
 */
class AllReviewsController extends Controller
{
    /**
     * Show the paginated all-reviews page for the tenant.
     *
     * BR-172: Destination of "See all reviews" link.
     * BR-169: Reviews sorted by creation date descending.
     * BR-174: All text localized via __().
     */
    public function index(Request $request, TenantLandingService $landingService): mixed
    {
        $tenant = tenant();
        $page = max(1, (int) $request->query('page', 1));

        $reviewsData = $landingService->getAllReviewsData($tenant, $page);

        return gale()->view('tenant.reviews', [
            'tenant' => $tenant,
            'reviewsData' => $reviewsData,
        ], web: true);
    }

    /**
     * Load more reviews via Gale fragment navigation.
     *
     * BR-172: Paginated view for all reviews.
     */
    public function loadMore(Request $request, TenantLandingService $landingService): mixed
    {
        $tenant = tenant();
        $page = max(1, (int) $request->query('page', 1));

        $reviewsData = $landingService->getAllReviewsData($tenant, $page);

        if ($request->isGaleNavigate('all-reviews')) {
            return gale()->fragment('tenant.reviews', 'all-reviews-content', [
                'tenant' => $tenant,
                'reviewsData' => $reviewsData,
            ]);
        }

        return gale()->view('tenant.reviews', [
            'tenant' => $tenant,
            'reviewsData' => $reviewsData,
        ], web: true);
    }
}
