<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Show the login form.
     */
    public function showLoginForm(Request $request): mixed
    {
        return gale()->view('auth.login', [
            'tenant' => tenant(),
        ], web: true);
    }

    /**
     * Handle a login request.
     */
    public function login(LoginRequest $request): mixed
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return gale()
                ->messages([
                    'email' => __('These credentials do not match our records.'),
                ])
                ->state('submitting', false);
        }

        $request->session()->regenerate();

        return gale()->redirect('/')->intended('/');
    }

    /**
     * Handle a logout request.
     */
    public function logout(Request $request): mixed
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return gale()->redirect('/');
    }
}
