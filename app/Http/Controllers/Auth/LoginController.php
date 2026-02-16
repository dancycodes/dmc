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

        // F-050: Record last login timestamp
        $authenticatedUser = Auth::user();
        $authenticatedUser->forceFill(['last_login_at' => now()])->saveQuietly();

        // BR-057: Log successful login activity
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
     *
     * BR-058: Destroys entire server-side session and invalidates cookie.
     * BR-059: Redirects to home page of current domain.
     * BR-060: POST request (CSRF-protected).
     * BR-061: Logs "logged_out" event via Spatie Activitylog.
     * BR-062: After logout, protected pages require re-authentication.
     * BR-063: Works on any domain (main or tenant).
     */
    public function logout(Request $request): mixed
    {
        $user = Auth::user();

        // BR-061: Log logout activity before session is destroyed
        if ($user) {
            activity('users')
                ->performedOn($user)
                ->causedBy($user)
                ->event('logged_out')
                ->withProperties(['ip' => $request->ip()])
                ->log(__('User logged out'));
        }

        Auth::logout();

        // BR-058: Destroy session and invalidate cookie
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // BR-059: Redirect to home page of current domain (full page reload for auth state change)
        return redirect('/');
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
