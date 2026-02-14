<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * Locale detection priority:
     * 1. Authenticated user's preferred_language
     * 2. Session locale value
     * 3. Browser Accept-Language header
     * 4. Default English
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        App::setLocale($locale);

        return $next($request);
    }

    /**
     * Resolve the locale from user preference, session, or browser header.
     */
    protected function resolveLocale(Request $request): string
    {
        $availableLocales = config('app.available_locales', ['en', 'fr']);
        $defaultLocale = config('app.locale', 'en');

        // Priority 1: Authenticated user's preferred_language
        if (Auth::check() && Auth::user()->preferred_language) {
            $userLocale = Auth::user()->preferred_language;

            if (in_array($userLocale, $availableLocales, true)) {
                return $userLocale;
            }
        }

        // Priority 2: Session locale
        $sessionLocale = $request->session()->get('locale');

        if ($sessionLocale && in_array($sessionLocale, $availableLocales, true)) {
            return $sessionLocale;
        }

        // Priority 3: Browser Accept-Language header
        $browserLocale = $this->parseAcceptLanguage($request, $availableLocales);

        if ($browserLocale) {
            return $browserLocale;
        }

        // Priority 4: Default locale
        return $defaultLocale;
    }

    /**
     * Parse the Accept-Language header and return the best matching locale.
     */
    protected function parseAcceptLanguage(Request $request, array $availableLocales): ?string
    {
        $acceptLanguage = $request->header('Accept-Language');

        if (! $acceptLanguage) {
            return null;
        }

        $preferred = $request->getLanguages();

        foreach ($preferred as $language) {
            // Exact match (e.g., 'fr' or 'en')
            if (in_array($language, $availableLocales, true)) {
                return $language;
            }

            // Prefix match (e.g., 'fr-FR' -> 'fr', 'en-US' -> 'en')
            $prefix = substr($language, 0, 2);

            if (in_array($prefix, $availableLocales, true)) {
                return $prefix;
            }
        }

        return null;
    }
}
