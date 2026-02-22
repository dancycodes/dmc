<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use App\Services\TestimonialService;
use Illuminate\Http\Request;

/**
 * F-180: Testimonial Submission Form
 *
 * Handles testimonial submission from the tenant landing page.
 * The landing page itself handles rendering context — this controller
 * processes only the POST submission.
 */
class TestimonialController extends Controller
{
    public function __construct(
        private TestimonialService $testimonialService,
    ) {}

    /**
     * Handle testimonial submission from the tenant landing page.
     *
     * BR-426: Only authenticated clients with completed orders can submit.
     * BR-427: One testimonial per client per tenant.
     * BR-428: Maximum 1,000 characters.
     * BR-429: Submitted with 'pending' status.
     * BR-434: Cook receives push + DB notification.
     * BR-435: Activity logged.
     */
    public function submit(Request $request): mixed
    {
        $tenant = tenant();

        // BR-432: Require authentication
        if (! auth()->check()) {
            if ($request->isGale()) {
                return gale()->dispatch('toast', [
                    'type' => 'error',
                    'message' => __('Please log in to share your experience.'),
                ]);
            }

            return redirect()->route('login');
        }

        $user = auth()->user();

        // Validate the testimonial text
        $validated = $request->validateState([
            'testimonialText' => [
                'required',
                'string',
                'min:10',
                'max:'.Testimonial::MAX_TEXT_LENGTH,
            ],
        ]);

        $result = $this->testimonialService->submit($user, $tenant, $validated['testimonialText']);

        if (! $result['success']) {
            return gale()->messages([
                'testimonialText' => $result['message'],
            ]);
        }

        return gale()
            ->state([
                'testimonialSubmitted' => true,
                'testimonialText' => '',
                'testimonialCharCount' => 0,
                'showTestimonialModal' => false,
            ])
            ->dispatch('toast', [
                'type' => 'success',
                'message' => $result['message'],
            ]);
    }
}
