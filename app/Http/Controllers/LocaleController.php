<?php

namespace App\Http\Controllers;

use App\Http\Requests\SwitchLocaleRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class LocaleController extends Controller
{
    /**
     * Switch the application locale.
     *
     * Persists to session for all users. For authenticated users,
     * also persists to the preferred_language database field.
     */
    public function switch(Request $request): mixed
    {
        if ($request->isGale()) {
            $validated = $request->validateState([
                'locale' => ['required', 'string', 'in:'.implode(',', availableLocales())],
            ]);

            $locale = $validated['locale'];
        } else {
            $formRequest = SwitchLocaleRequest::createFrom($request);
            $formRequest->setContainer(app())->setRedirector(app('redirect'))->validateResolved();
            $validated = $formRequest->validated();

            $locale = $validated['locale'];
        }

        $request->session()->put('locale', $locale);

        App::setLocale($locale);

        if (Auth::check()) {
            Auth::user()->update(['preferred_language' => $locale]);
        }

        if ($request->isGale()) {
            return gale()->reload();
        }

        return gale()->redirect()->back('/');
    }
}
