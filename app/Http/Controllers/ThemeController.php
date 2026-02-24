<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateThemePreferenceRequest;
use App\Services\ThemeService;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    public function __construct(
        private ThemeService $themeService,
    ) {}

    /**
     * Update the authenticated user's theme preference.
     *
     * Stores the preference in the database so it can be restored
     * when the user logs in on a new device. The frontend also
     * stores the preference in localStorage for instant application.
     */
    public function update(UpdateThemePreferenceRequest $request): mixed
    {
        $theme = $request->validated('theme');

        $request->user()->update([
            'theme_preference' => $this->themeService->normalizeTheme($theme),
        ]);

        if ($request->isGale()) {
            return gale()->state('themeSaved', true);
        }

        return gale()->redirect()->back('/')->with('status', __('Theme preference updated.'));
    }

    /**
     * Get the current user's theme preference (for API/Gale consumption).
     */
    public function show(Request $request): mixed
    {
        $preference = $this->themeService->resolvePreference(
            $request->user()?->theme_preference
        );

        if ($request->isGale()) {
            return gale()->state('themePreference', $preference);
        }

        return response()->json(['preference' => $preference]);
    }
}
