<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdateLanguagePreferenceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class LanguagePreferenceController extends Controller
{
    /**
     * Display the language preference setting page.
     *
     * Shows the user's current language preference with radio button options
     * to switch between English and French.
     *
     * BR-184: Supported languages: "en" (English) and "fr" (French).
     * BR-185: The preference is stored in the user's preferred_language field.
     * BR-190: If the preferred language is not set, default to English ("en").
     * BR-191: The setting must stay in sync with the language switcher component (F-008).
     */
    public function show(Request $request): mixed
    {
        $user = Auth::user();

        return gale()->view('profile.language', [
            'user' => $user,
            'tenant' => tenant(),
            'currentLanguage' => $user->preferred_language ?? 'en',
        ], web: true);
    }

    /**
     * Update the user's language preference.
     *
     * Persists the preferred language to the database and updates the session
     * and application locale so the change is reflected immediately on the
     * next page load.
     *
     * BR-185: Stored in user's preferred_language field.
     * BR-186: Application locale is set based on user's preferred_language.
     * BR-188: Affects all __() translations, date formatting, and locale-dependent display.
     * BR-189: Translatable database columns read based on preferred language.
     * BR-191: Stays in sync with language switcher component (F-008).
     */
    public function update(Request $request): mixed
    {
        $user = Auth::user();

        if ($request->isGale()) {
            $validated = $request->validateState([
                'preferred_language' => ['required', 'string', 'in:en,fr'],
            ], [
                'preferred_language.required' => __('Please select a preferred language.'),
                'preferred_language.in' => __('The selected language is not supported.'),
            ]);
        } else {
            $formRequest = UpdateLanguagePreferenceRequest::createFrom($request);
            $formRequest->setContainer(app())->setRedirector(app('redirect'))->validateResolved();
            $validated = $formRequest->validated();
        }

        $oldLanguage = $user->preferred_language ?? 'en';
        $newLanguage = $validated['preferred_language'];

        // Only update if the language actually changed
        if ($oldLanguage !== $newLanguage) {
            $user->update([
                'preferred_language' => $newLanguage,
            ]);

            // Update session locale to stay in sync (BR-191)
            $request->session()->put('locale', $newLanguage);

            // Set the application locale immediately
            App::setLocale($newLanguage);

            // Log the language change
            activity('users')
                ->performedOn($user)
                ->causedBy($user)
                ->event('updated')
                ->withProperties([
                    'old' => ['preferred_language' => $oldLanguage],
                    'attributes' => ['preferred_language' => $newLanguage],
                    'ip' => $request->ip(),
                ])
                ->log(__('Language preference was updated'));
        }

        return gale()->redirect('/profile/language')
            ->with('toast', [
                'type' => 'success',
                'message' => __('Language preference updated. The change will apply on the next page.'),
            ]);
    }
}
