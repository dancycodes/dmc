<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordResetRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

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
     * Show the password reset form (F-027 placeholder).
     *
     * This method stub exists to support route compilation for URL generation
     * in the password reset email. F-027 will implement the full form.
     */
    public function showResetForm(Request $request, string $token): mixed
    {
        return gale()->view('auth.passwords.email', [
            'tenant' => tenant(),
        ], web: true);
    }
}
