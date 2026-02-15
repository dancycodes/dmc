<?php

namespace App\Http\Middleware;

use App\Services\TenantThemeService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class InjectTenantTheme
{
    public function __construct(
        private TenantThemeService $tenantThemeService,
    ) {}

    /**
     * Share tenant theme data with all views.
     *
     * When on a tenant domain, resolves the tenant's theme customization
     * (preset, font, border radius) and shares the generated inline CSS
     * and font link tag with all Blade views. On the main domain, shares
     * empty strings so the default theme applies.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        $tenantThemeCss = $this->tenantThemeService->generateInlineCss($tenant);
        $tenantFontLink = $this->tenantThemeService->getFontLinkTag($tenant);

        View::share('tenantThemeCss', $tenantThemeCss);
        View::share('tenantFontLink', $tenantFontLink);

        return $next($request);
    }
}
