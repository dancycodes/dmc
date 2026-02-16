<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * BR-089: Checks authenticated user's active status on every request.
     * BR-090: Deactivated users are immediately logged out and redirected
     *         to the deactivation message page.
     *
     * This middleware catches deactivated users even with "remember me" cookies,
     * since it queries the database is_active field directly (not session state).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && ! $user->isActive()) {
            // Log the forced logout before destroying the session
            activity('users')
                ->performedOn($user)
                ->causedBy($user)
                ->event('forced_logout')
                ->withProperties(['ip' => $request->ip(), 'reason' => 'account_deactivated'])
                ->log(__('User forced logout due to deactivated account'));

            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // API/JSON requests get a 403 response
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('Your account has been deactivated. Please contact support.'),
                ], Response::HTTP_FORBIDDEN);
            }

            return redirect()->route('account.deactivated');
        }

        return $next($request);
    }
}
