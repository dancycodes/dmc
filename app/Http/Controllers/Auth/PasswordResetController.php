<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordResetExecutionRequest;
use App\Http\Requests\Auth\PasswordResetRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends Controller
{
    /**
     * Show the form to request a password reset link.
     *
     * Accessible on any domain (BR-068). Shows tenant branding
     * on tenant domains via the shared auth layout.
     */
    public function showRequestForm(Request $request): mixed
    {
        return gale()->view('auth.passwords.email', [
            'tenant' => tenant(),
        ], web: true);
    }

    /**
     * Send a password reset link to the given user.
     *
     * BR-064: Always shows the same success message regardless of whether
     * the email exists in the system (prevents email enumeration).
     * BR-065: Rate limited to 3 requests per 15 minutes per email.
     * BR-067: Reset email contains a signed URL for password reset execution.
     */
    public function sendResetLink(Request $request): mixed
    {
        if ($request->isGale()) {
            $validated = $request->validateState([
                'email' => ['required', 'email', 'max:255'],
            ], [
                'email.required' => __('Email address is required.'),
                'email.email' => __('Please enter a valid email address.'),
                'email.max' => __('Email address must not exceed 255 characters.'),
            ]);

            $email = strtolower(trim($validated['email']));
        } else {
            $formRequest = PasswordResetRequest::createFrom($request);
            $formRequest->setContainer(app())->setRedirector(app('redirect'))->validateResolved();
            $validated = $formRequest->validated();

            $email = $validated['email'];
        }

        // Send the reset link via the password broker.
        // The User model's sendPasswordResetNotification() handles the branded email.
        Password::sendResetLink(['email' => $email]);

        // BR-064: Always return the same success message regardless of whether
        // the email exists. This prevents email enumeration attacks.
        $successMessage = __('If an account with that email exists, we\'ve sent a password reset link.');

        if ($request->isGale()) {
            return gale()
                ->messages(['_success' => $successMessage])
                ->state('submitted', true)
                ->state('submitting', false);
        }

        return redirect()->back()->with('status', $successMessage);
    }

    /**
     * Show the password reset form.
     *
     * BR-072: Validates that the token exists and has not expired (60-minute window).
     * BR-079: Invalid or expired tokens show an error with a CTA to request a new link.
     */
    public function showResetForm(Request $request, string $token): mixed
    {
        $email = $request->query('email', '');

        // Validate token existence and expiration before showing the form
        $tokenStatus = $this->validateResetToken($email, $token);

        if ($tokenStatus === 'invalid') {
            return gale()->view('auth.passwords.reset', [
                'tenant' => tenant(),
                'token' => $token,
                'email' => $email,
                'tokenError' => 'invalid',
            ], web: true);
        }

        if ($tokenStatus === 'expired') {
            return gale()->view('auth.passwords.reset', [
                'tenant' => tenant(),
                'token' => $token,
                'email' => $email,
                'tokenError' => 'expired',
            ], web: true);
        }

        return gale()->view('auth.passwords.reset', [
            'tenant' => tenant(),
            'token' => $token,
            'email' => $email,
            'tokenError' => null,
        ], web: true);
    }

    /**
     * Handle the password reset execution.
     *
     * BR-072: Token must be valid and not expired.
     * BR-073: Password strength same as registration.
     * BR-074: Password confirmation must match.
     * BR-075: Token is invalidated after successful reset.
     * BR-076: Redirect to login page (not auto-logged in).
     * BR-077: All existing sessions are invalidated.
     * BR-078: Activity logged via Spatie Activitylog.
     */
    public function resetPassword(Request $request): mixed
    {
        if ($request->isGale()) {
            $validated = $request->validateState([
                'token' => ['required', 'string'],
                'email' => ['required', 'string', 'email', 'max:255'],
                'password' => [
                    'required',
                    'string',
                    PasswordRule::min(8)
                        ->mixedCase()
                        ->numbers(),
                    'confirmed',
                ],
            ], [
                'token.required' => __('The password reset token is required.'),
                'email.required' => __('Email address is required.'),
                'email.email' => __('Please enter a valid email address.'),
                'password.required' => __('Password is required.'),
                'password.confirmed' => __('Password confirmation does not match.'),
            ]);

            $email = strtolower(trim($validated['email']));
            $token = $validated['token'];
            $password = $validated['password'];
        } else {
            $formRequest = PasswordResetExecutionRequest::createFrom($request);
            $formRequest->setContainer(app())->setRedirector(app('redirect'))->validateResolved();
            $validated = $formRequest->validated();

            $email = $validated['email'];
            $token = $validated['token'];
            $password = $validated['password'];
        }

        // Use Laravel's Password facade to reset the password
        $status = Password::reset(
            [
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $password,
                'token' => $token,
            ],
            function (User $user, string $password) use ($request) {
                // Update password and regenerate remember token (BR-075)
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // BR-078: Log password reset activity
                activity('users')
                    ->performedOn($user)
                    ->causedBy($user)
                    ->event('password_reset')
                    ->withProperties(['ip' => $request->ip()])
                    ->log(__('Password was reset'));

                // Fire the PasswordReset event
                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            // BR-077: Invalidate current session (any logged-in sessions)
            if (Auth::check()) {
                Auth::logout();
            }
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $successMessage = __('Password reset successfully. Please log in.');

            // BR-076: Redirect to login page with success message
            return gale()->redirect(route('login'))->with('toast', [
                'type' => 'success',
                'message' => $successMessage,
            ]);
        }

        // Handle failure — translate the status to a user-friendly error
        $errorMessage = $this->getResetErrorMessage($status);

        return $this->failedResetResponse($request, $errorMessage);
    }

    /**
     * Validate a password reset token without consuming it.
     *
     * @return string 'valid', 'invalid', or 'expired'
     */
    private function validateResetToken(string $email, string $token): string
    {
        if (empty($email) || empty($token)) {
            return 'invalid';
        }

        $user = User::where('email', strtolower(trim($email)))->first();

        if (! $user) {
            return 'invalid';
        }

        // Check if token exists in password_reset_tokens table
        $record = DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->first();

        if (! $record) {
            return 'invalid';
        }

        // Check if token hash matches
        if (! Hash::check($token, $record->token)) {
            return 'invalid';
        }

        // Check expiration (60 minutes from config)
        $expiresIn = config('auth.passwords.users.expire', 60);
        $createdAt = \Carbon\Carbon::parse($record->created_at);

        if ($createdAt->addMinutes($expiresIn)->isPast()) {
            return 'expired';
        }

        return 'valid';
    }

    /**
     * Get a user-friendly error message for the reset status.
     */
    private function getResetErrorMessage(string $status): string
    {
        return match ($status) {
            Password::INVALID_TOKEN => __('This password reset link is invalid.'),
            Password::INVALID_USER => __('We could not find a user with that email address.'),
            Password::RESET_THROTTLED => __('Please wait before trying again.'),
            default => __('An error occurred while resetting your password. Please try again.'),
        };
    }

    /**
     * Return appropriate error response for failed password reset.
     */
    private function failedResetResponse(Request $request, string $message): mixed
    {
        if ($request->isGale()) {
            return gale()
                ->messages(['password' => $message])
                ->state('submitting', false);
        }

        return redirect()->back()
            ->withErrors(['password' => $message])
            ->withInput(['email' => $request->input('email')]);
    }
}
