<?php

namespace App\Http\Middleware;

use App\Services\TenantService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMainDomain
{
    public function __construct(
        private TenantService $tenantService,
    ) {}

    /**
     * Only allow requests on the main domain (no tenant resolved).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->tenantService->isTenantDomain()) {
            abort(404);
        }

        return $next($request);
    }
}
