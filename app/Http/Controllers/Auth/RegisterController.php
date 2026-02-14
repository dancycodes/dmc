<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
    public function register(RegisterRequest $request): mixed
    {
        $validated = $request->validated();

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
