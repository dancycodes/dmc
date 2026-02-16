<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    /**
     * Display the authenticated user's profile page.
     *
     * Shows personal information (name, email, phone, language, member since),
     * email verification status, profile photo or default avatar, and action
     * links to related profile management features.
     *
     * BR-097: Only accessible to authenticated users (enforced by 'auth' middleware).
     * BR-098: Users can only view their own profile.
     * BR-100: Accessible from any domain (main or tenant).
     */
    public function show(Request $request): mixed
    {
        $user = Auth::user();

        return gale()->view('profile.show', [
            'user' => $user,
            'tenant' => tenant(),
        ], web: true);
    }
}
