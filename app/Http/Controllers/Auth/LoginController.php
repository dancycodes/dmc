<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Show the login form.
     *
     * Redirects authenticated users to home (edge case: already logged in).
     */
    public function showLoginForm(Request $request): mixed
    {
        return gale()->view('auth.login', [
            'tenant' => tenant(),
        ], web: true);
    }

    /**
     * Handle a login request.
     *
     * Validates credentials, checks active status (BR-053), logs activity (BR-057),
     * and redirects to intended page with welcome toast (BR-050).
     */
    public function login(Request $request): mixed
    {
        if ($request->isGale()) {
            $validated = $request->validateState([
                'email' => ['required', 'string', 'email'],
                'password' => ['required', 'string'],
            ], [
                'email.required' => __('Email address is required.'),
                'email.email' => __('Please enter a valid email address.'),
                'password.required' => __('Password is required.'),
            ]);

            $email = strtolower(trim($validated['email']));
            $remember = (bool) $request->state('remember', false);
        } else {
            $formRequest = LoginRequest::createFrom($request);
            $formRequest->setContainer(app())->setRedirector(app('redirect'))->validateResolved();
            $validated = $formRequest->validated();

            $email = $validated['email'];
            $remember = $request->boolean('remember');
        }

        // BR-053: Check if the user's account is active before authenticating
        $user = User::where('email', $email)->first();

        if ($user && ! $user->isActive()) {
            return $this->failedLoginResponse($request, $email, __('Your account has been deactivated.'));
        }

        // Attempt authentication
        if (! Auth::attempt(['email' => $email, 'password' => $validated['password']], $remember)) {
            // BR-051: Generic credentials error — never reveal whether email exists
            return $this->failedLoginResponse($request, $email, __('These credentials do not match our records.'));
        }

        $request->session()->regenerate();

        // BR-057: Log successful login activity
        $authenticatedUser = Auth::user();
        activity('users')
            ->performedOn($authenticatedUser)
            ->causedBy($authenticatedUser)
            ->event('login')
            ->withProperties(['ip' => $request->ip()])
            ->log(__('User logged in'));

        // BR-050: Redirect to intended page with welcome toast
        return gale()->redirect('/')->intended('/')
            ->with('toast', [
                'type' => 'success',
                'message' => __('Welcome back!'),
            ]);
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

    /**
     * Return appropriate error response for failed login.
     *
     * Gale requests get SSE message updates; regular HTTP requests get
     * a standard Laravel redirect with ViewErrorBag.
     */
    private function failedLoginResponse(Request $request, string $email, string $message): mixed
    {
        if ($request->isGale()) {
            return gale()
                ->messages(['email' => $message])
                ->state('submitting', false);
        }

        return redirect()->back()
            ->withErrors(['email' => $message])
            ->withInput(['email' => $email]);
    }
}
