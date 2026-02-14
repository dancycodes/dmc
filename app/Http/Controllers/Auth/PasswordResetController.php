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
     */
    public function showRequestForm(Request $request): mixed
    {
        return gale()->view('auth.passwords.email', [
            'tenant' => tenant(),
        ], web: true);
    }

    /**
     * Send a password reset link to the given user.
     */
    public function sendResetLink(PasswordResetRequest $request): mixed
    {
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return gale()
                ->messages([
                    '_success' => __('We have emailed your password reset link.'),
                ])
                ->state('submitted', true);
        }

        return gale()
            ->messages([
                'email' => __('We could not find a user with that email address.'),
            ]);
    }
}
