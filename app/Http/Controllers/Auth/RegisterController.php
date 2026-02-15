<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
     *
     * Creates the user, assigns the client role, sends email verification,
     * logs the activity, and auto-logs the user in.
     */
    public function register(Request $request): mixed
    {
        if ($request->isGale()) {
            // Normalize phone and email in the JSON body before validateState reads it
            $rawPhone = (string) $request->state('phone', '');
            $normalizedPhone = RegisterRequest::normalizePhone($rawPhone);
            $normalizedEmail = strtolower(trim((string) $request->state('email', '')));
            $trimmedName = trim((string) $request->state('name', ''));

            $request->json()->set('phone', $normalizedPhone);
            $request->json()->set('email', $normalizedEmail);
            $request->json()->set('name', $trimmedName);

            $validated = $request->validateState([
                'name' => ['required', 'string', 'min:1', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'phone' => ['required', 'string', 'regex:'.RegisterRequest::CAMEROON_PHONE_REGEX],
                'password' => [
                    'required',
                    'string',
                    Password::min(8)->mixedCase()->numbers(),
                    'confirmed',
                ],
            ], [
                'name.required' => __('Name is required.'),
                'name.max' => __('Name must not exceed 255 characters.'),
                'email.required' => __('Email address is required.'),
                'email.email' => __('Please enter a valid email address.'),
                'email.unique' => __('This email is already registered.'),
                'phone.required' => __('Phone number is required.'),
                'phone.regex' => __('Please enter a valid Cameroon phone number.'),
                'password.required' => __('Password is required.'),
                'password.confirmed' => __('Password confirmation does not match.'),
            ]);
        } else {
            $formRequest = RegisterRequest::createFrom($request);
            $formRequest->setContainer(app())->setRedirector(app('redirect'))->validateResolved();
            $validated = $formRequest->validated();
        }

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => $validated['password'],
                'is_active' => true,
                'preferred_language' => app()->getLocale(),
            ]);

            // BR-032: Assign default "client" role
            $user->assignRole('client');

            return $user;
        });

        // BR-035: Log registration activity
        activity('users')
            ->performedOn($user)
            ->causedBy($user)
            ->event('registered')
            ->withProperties(['ip' => $request->ip()])
            ->log(__('User was registered'));

        // BR-033: Send email verification notification
        event(new Registered($user));

        // BR-034: Auto-login the new user
        Auth::login($user);
        $request->session()->regenerate();

        // BR-034: Redirect to intended page with success toast
        return gale()->redirect('/')->intended('/')
            ->with('toast', [
                'type' => 'success',
                'message' => __('Welcome to DancyMeals!'),
            ]);
    }
}
