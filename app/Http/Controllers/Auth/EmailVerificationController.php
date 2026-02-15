<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /**
     * Show the email verification notice page.
     *
     * If the user is already verified, redirect to home.
     * Otherwise show the verify-email page with resend button.
     */
    public function notice(Request $request): mixed
    {
        if ($request->user()->hasVerifiedEmail()) {
            return gale()->redirect('/')->route('home');
        }

        return gale()->view('auth.verify-email', [
            'tenant' => tenant(),
            'email' => $request->user()->email,
        ], web: true);
    }

    /**
     * Handle the email verification link click.
     *
     * Uses Laravel's EmailVerificationRequest which validates
     * the signed URL and checks the user ID/hash match (BR-043, BR-045).
     */
    public function verify(EmailVerificationRequest $request): mixed
    {
        if ($request->user()->hasVerifiedEmail()) {
            return gale()->redirect('/')->route('home');
        }

        $request->fulfill();

        // BR-043: Log verification activity
        activity('users')
            ->performedOn($request->user())
            ->causedBy($request->user())
            ->event('email_verified')
            ->withProperties(['ip' => $request->ip()])
            ->log(__('Email address was verified'));

        return gale()->redirect('/')->intended('/')
            ->with('toast', [
                'type' => 'success',
                'message' => __('Email verified successfully.'),
            ]);
    }

    /**
     * Resend the email verification notification.
     *
     * Rate-limited via route middleware (BR-042: max 5/hour per user).
     * Client-side cooldown handles the 60-second timer (BR-041).
     */
    public function resend(Request $request): mixed
    {
        if ($request->user()->hasVerifiedEmail()) {
            if ($request->isGale()) {
                return gale()->state('alreadyVerified', true);
            }

            return gale()->redirect('/')->route('home');
        }

        $request->user()->sendEmailVerificationNotification();

        if ($request->isGale()) {
            return gale()->state('resent', true)
                ->state('cooldownActive', true);
        }

        return gale()->redirect('/')->back()
            ->with('toast', [
                'type' => 'success',
                'message' => __('Verification email sent.'),
            ]);
    }
}
