<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Meal;
use App\Services\CartService;
use App\Services\RatingService;
use App\Services\TenantLandingService;
use Illuminate\Http\Request;

class MealDetailController extends Controller
{
    public function __construct(
        private TenantLandingService $landingService,
        private CartService $cartService,
        private RatingService $ratingService,
    ) {}

    /**
     * Display the meal detail page on a tenant domain.
     *
     * F-129: Meal Detail View
     * F-138: Cart data passed for requirement rule checking
     * F-178: Rating & Review Display on Meal
     * BR-156: Displays name, description, images, components, schedule, locations
     * BR-157: Image carousel supports up to 3 images
     * BR-163: Adding to cart updates the cart state reactively via Gale
     * BR-166: All text localized via __()
     * BR-408: Average rating from orders containing this meal
     */
    public function show(Request $request, Meal $meal): mixed
    {
        $tenant = tenant();

        if (! $tenant || $meal->tenant_id !== $tenant->id) {
            abort(404);
        }

        // Only show live, available meals to public visitors
        if ($meal->status !== Meal::STATUS_LIVE || ! $meal->is_available) {
            abort(404);
        }

        $detailData = $this->landingService->getMealDetailData($meal, $tenant);

        // F-138: Get cart data for requirement rule checking and cart summary
        $cart = $this->cartService->getCart($tenant->id);
        $cartComponentsForMeal = $this->cartService->getCartComponentsForMeal($tenant->id, $meal->id);

        // F-178: Get rating & review display data
        $reviewData = $this->ratingService->getMealReviewDisplayData($meal->id, $tenant->id);

        return gale()->view('tenant.meal-detail', [
            'tenant' => $tenant,
            'meal' => $meal,
            'mealData' => $detailData['meal'],
            'components' => $detailData['components'],
            'schedule' => $detailData['schedule'],
            'locations' => $detailData['locations'],
            'cart' => $cart,
            'cartComponentsForMeal' => $cartComponentsForMeal,
            'reviewData' => $reviewData,
        ], web: true);
    }

    /**
     * F-178: Load more reviews for a meal via Gale fragment.
     *
     * BR-413: Paginated with 10 per page.
     * BR-412: Sorted newest first.
     */
    public function loadMoreReviews(Request $request, Meal $meal): mixed
    {
        $tenant = tenant();

        if (! $tenant || $meal->tenant_id !== $tenant->id) {
            abort(404);
        }

        $page = max(1, (int) $request->query('review_page', 1));
        $reviewData = $this->ratingService->getMealReviewDisplayData($meal->id, $tenant->id, $page);

        return gale()->fragment('tenant._meal-reviews', 'meal-reviews-section', [
            'reviewData' => $reviewData,
            'meal' => $meal,
        ]);
    }
}
