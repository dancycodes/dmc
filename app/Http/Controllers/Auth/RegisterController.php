<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    /**
     * Show the registration form.
     */
    public function showRegistrationForm(Request $request): mixed
    {
        return gale()->view('auth.register', [
            'tenant' => tenant(),
        ], web: true);
    }

    /**
     * Handle a registration request.
     */
    public function register(Request $request): mixed
    {
        if ($request->isGale()) {
            $validated = $request->validateState([
                'name' => ['required', 'string', 'min:1', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'phone' => ['required', 'string', 'regex:/^(\+?237)?[6][0-9]{8}$/'],
                'password' => ['required', 'string', Password::min(8), 'confirmed'],
            ]);

            $validated['name'] = trim($validated['name']);
            $validated['email'] = strtolower(trim($validated['email']));
        } else {
            $formRequest = RegisterRequest::createFrom($request);
            $formRequest->setContainer(app())->setRedirector(app('redirect'))->validateResolved();
            $validated = $formRequest->validated();
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => $validated['password'],
            'is_active' => true,
            'preferred_language' => 'en',
        ]);

        Auth::login($user);

        return gale()->redirect('/')->intended('/');
    }
}
