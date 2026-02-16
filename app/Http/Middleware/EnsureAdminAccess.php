<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccess
{
    /**
     * Ensure the authenticated user has the can-access-admin-panel permission.
     *
     * BR-045: Only users with can-access-admin-panel permission may access the admin panel.
     * BR-043: Admin panel routes are ONLY accessible on the main domain. (Handled by main.domain middleware.)
     * BR-044: Requests to /vault-entry/* on tenant domains return 404. (Handled by main.domain middleware.)
     *
     * This middleware is applied AFTER the auth middleware, so the user is guaranteed
     * to be authenticated at this point. Unauthenticated users are redirected to login
     * by the auth middleware before reaching this check.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->can('can-access-admin-panel')) {
            abort(403);
        }

        return $next($request);
    }
}
