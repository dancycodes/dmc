<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
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

    /**
     * Display the profile edit form.
     *
     * Pre-populates the form with the user's current name, phone, and
     * preferred language. Email is displayed as read-only (BR-114).
     *
     * BR-097: Only accessible to authenticated users.
     * BR-100: Accessible from any domain.
     */
    public function edit(Request $request): mixed
    {
        $user = Auth::user();

        return gale()->view('profile.edit', [
            'user' => $user,
            'tenant' => tenant(),
        ], web: true);
    }

    /**
     * Update the authenticated user's basic profile information.
     *
     * Handles both Gale SSE requests (from Alpine $action) and traditional
     * HTTP form submissions. Updates name, phone, and preferred language.
     * Email is NOT editable (BR-114).
     *
     * BR-112: Name is required, 2-255 characters.
     * BR-113: Phone must match Cameroon format (+237XXXXXXXXX).
     * BR-115: Preferred language must be "en" or "fr".
     * BR-116: Form submission via Gale without page reload.
     * BR-117: Successful updates logged via Spatie Activitylog.
     * BR-118: All validation messages localized via __().
     */
    public function update(Request $request): mixed
    {
        $user = Auth::user();

        if ($request->isGale()) {
            $rawPhone = (string) $request->state('phone', '');
            $normalizedPhone = RegisterRequest::normalizePhone($rawPhone);
            $trimmedName = trim((string) $request->state('name', ''));

            $request->json()->set('phone', $normalizedPhone);
            $request->json()->set('name', $trimmedName);

            $validated = $request->validateState([
                'name' => ['required', 'string', 'min:2', 'max:255'],
                'phone' => ['required', 'string', 'regex:'.RegisterRequest::CAMEROON_PHONE_REGEX],
                'preferred_language' => ['required', 'string', 'in:en,fr'],
            ], [
                'name.required' => __('Name is required.'),
                'name.min' => __('Name must be at least 2 characters.'),
                'name.max' => __('Name must not exceed 255 characters.'),
                'phone.required' => __('Phone number is required.'),
                'phone.regex' => __('Please enter a valid Cameroon phone number (+237 followed by 9 digits).'),
                'preferred_language.required' => __('Please select a preferred language.'),
                'preferred_language.in' => __('The selected language is not supported.'),
            ]);
        } else {
            $formRequest = UpdateProfileRequest::createFrom($request);
            $formRequest->setContainer(app())->setRedirector(app('redirect'))->validateResolved();
            $validated = $formRequest->validated();
        }

        // Capture old values for activity log before updating
        $oldValues = [
            'name' => $user->name,
            'phone' => $user->phone,
            'preferred_language' => $user->preferred_language,
        ];

        // Update user profile fields
        $user->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'preferred_language' => $validated['preferred_language'],
        ]);

        // Determine which fields actually changed for the activity log
        $changedFields = [];
        $oldLog = [];
        $newLog = [];

        if ($oldValues['name'] !== $user->name) {
            $changedFields[] = 'name';
            $oldLog['name'] = $oldValues['name'];
            $newLog['name'] = $user->name;
        }

        if ($oldValues['phone'] !== $user->phone) {
            $changedFields[] = 'phone';
            $oldLog['phone'] = $oldValues['phone'];
            $newLog['phone'] = $user->phone;
        }

        if ($oldValues['preferred_language'] !== $user->preferred_language) {
            $changedFields[] = 'preferred_language';
            $oldLog['preferred_language'] = $oldValues['preferred_language'];
            $newLog['preferred_language'] = $user->preferred_language;
        }

        // BR-117: Log the update if any fields changed
        if (! empty($changedFields)) {
            activity('users')
                ->performedOn($user)
                ->causedBy($user)
                ->event('updated')
                ->withProperties([
                    'old' => $oldLog,
                    'attributes' => $newLog,
                    'ip' => $request->ip(),
                ])
                ->log(__('Profile was updated'));
        }

        return gale()->redirect('/profile/edit')
            ->with('toast', [
                'type' => 'success',
                'message' => __('Profile updated successfully.'),
            ]);
    }
}
