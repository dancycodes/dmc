<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CrossDomainSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CrossDomainAuthController extends Controller
{
    public function __construct(
        private CrossDomainSessionService $crossDomainService,
    ) {}

    /**
     * Generate a cross-domain authentication token for the current user.
     *
     * This endpoint is called when a user on the main domain or a subdomain
     * wants to navigate to a custom domain tenant. It generates a one-time
     * token that can be used to establish a session on the custom domain.
     *
     * BR-083: Session sharing must work between main domain and custom tenant domains.
     * BR-085: Session sharing must not expose any user data to tenant owners.
     * BR-087: Uses one-time tokens to prevent replay attacks.
     */
    public function generateToken(Request $request): mixed
    {
        $user = Auth::user();

        if (! $user) {
            return gale()->view('auth.login', [
                'tenant' => tenant(),
            ], web: true);
        }

        $token = $this->crossDomainService->generateToken($user);
        $targetDomain = $request->input('domain', '');
        $intendedPath = $request->input('intended', '/');

        if (empty($targetDomain)) {
            abort(400);
        }

        $redirectUrl = $this->crossDomainService->buildRedirectUrl(
            $targetDomain,
            $token,
            $intendedPath,
        );

        return redirect()->away($redirectUrl);
    }

    /**
     * Consume a cross-domain authentication token and establish a session.
     *
     * This endpoint is called on the custom domain after a redirect from
     * the main domain. It validates the token, logs the user in, and
     * redirects to the intended path.
     *
     * BR-083: Establishes session on custom domain via token exchange.
     * BR-085: Token contains only user ID, no sensitive data exposed.
     * BR-087: Token is consumed immediately (one-time use).
     */
    public function consumeToken(Request $request): mixed
    {
        $token = $request->query('token', '');
        $intendedPath = $request->query('intended', '/');

        if (empty($token)) {
            return redirect($intendedPath);
        }

        // Already authenticated — skip token validation
        if (Auth::check()) {
            return redirect($intendedPath);
        }

        $user = $this->crossDomainService->validateAndConsumeToken($token);

        if ($user === null) {
            // Token invalid or expired — redirect to intended page as guest
            return redirect($intendedPath);
        }

        // Check active status before establishing session (BR-053)
        if (! $user->isActive()) {
            return redirect($intendedPath);
        }

        // Establish session on the custom domain
        Auth::login($user);
        $request->session()->regenerate();

        return redirect($intendedPath);
    }
}
