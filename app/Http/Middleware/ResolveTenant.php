<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(
        private TenantService $tenantService,
    ) {}

    /**
     * Handle an incoming request.
     *
     * Resolves the current tenant from the request hostname:
     * 1. If IP address or exact main domain -> main domain (no tenant)
     * 2. If subdomain of main domain -> lookup tenant by slug
     * 3. If neither -> lookup tenant by custom_domain
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $hostname = strtolower($request->getHost());

        // IP addresses are treated as main domain
        if (TenantService::isIpAddress($hostname)) {
            $this->tenantService->set(null);

            return $next($request);
        }

        $mainDomain = strtolower(TenantService::mainDomain());

        // Exact main domain match
        if ($hostname === $mainDomain) {
            $this->tenantService->set(null);

            return $next($request);
        }

        // Try subdomain resolution
        $subdomain = TenantService::extractSubdomain($hostname);
        if ($subdomain !== null) {
            // Reserved subdomains are treated as main domain
            if (Tenant::isReservedSubdomain($subdomain)) {
                $this->tenantService->set(null);

                return $next($request);
            }

            $tenant = Tenant::findBySlug($subdomain);

            if ($tenant === null) {
                return $this->tenantNotFound($request);
            }

            if (! $tenant->is_active) {
                return $this->tenantUnavailable($request, $tenant);
            }

            $this->tenantService->set($tenant);

            return $next($request);
        }

        // Try custom domain resolution
        $tenant = Tenant::findByCustomDomain($hostname);

        if ($tenant !== null) {
            if (! $tenant->is_active) {
                return $this->tenantUnavailable($request, $tenant);
            }

            $this->tenantService->set($tenant);

            return $next($request);
        }

        // Unknown domain - not a subdomain, not a custom domain, not the main domain
        return $this->tenantNotFound($request);
    }

    /**
     * Return a 404 response for unknown tenant domains.
     */
    private function tenantNotFound(Request $request): Response
    {
        $mainUrl = config('app.url', 'http://localhost');

        return response()->view('errors.tenant-not-found', [
            'mainUrl' => $mainUrl,
        ], 404);
    }

    /**
     * Return a 503 response for inactive tenant domains.
     */
    private function tenantUnavailable(Request $request, Tenant $tenant): Response
    {
        $mainUrl = config('app.url', 'http://localhost');

        return response()->view('errors.tenant-unavailable', [
            'tenantName' => $tenant->name,
            'mainUrl' => $mainUrl,
        ], 503);
    }
}
