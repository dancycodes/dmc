<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdateNotificationPreferencesRequest;
use App\Models\NotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationPreferencesController extends Controller
{
    /**
     * Display the notification preferences management page.
     *
     * BR-178: First visit generates default preferences (all ON) for
     * any types not yet configured.
     * BR-179: Preferences are global per user, not tenant-scoped.
     */
    public function show(Request $request): mixed
    {
        $user = Auth::user();
        $preferences = NotificationPreference::getAllForUser($user->id);

        return gale()->view('profile.notifications', [
            'user' => $user,
            'tenant' => tenant(),
            'preferences' => $preferences,
            'types' => NotificationPreference::TYPES,
            'typeLabels' => NotificationPreference::TYPE_LABELS,
            'typeDescriptions' => NotificationPreference::TYPE_DESCRIPTIONS,
        ], web: true);
    }

    /**
     * Save the user's notification preferences.
     *
     * BR-176: Toggle push and email channels per notification type.
     * BR-177: Database channel is always ON — not stored or toggled here.
     * BR-181: Changes take effect immediately for future notifications.
     * BR-183: All text via __().
     */
    public function update(Request $request): mixed
    {
        $user = Auth::user();

        if ($request->isGale()) {
            $validated = $request->validateState([
                'preferences' => ['sometimes', 'array'],
            ]);
            $preferencesInput = $validated['preferences'] ?? [];
        } else {
            $formRequest = app(UpdateNotificationPreferencesRequest::class);
            $preferencesInput = $formRequest->validated()['preferences'] ?? [];
        }

        // Persist each type's preferences
        foreach (NotificationPreference::TYPES as $type) {
            $typeInput = $preferencesInput[$type] ?? [];

            NotificationPreference::updateOrCreate(
                ['user_id' => $user->id, 'notification_type' => $type],
                [
                    'push_enabled' => (bool) ($typeInput['push_enabled'] ?? true),
                    'email_enabled' => (bool) ($typeInput['email_enabled'] ?? true),
                ]
            );
        }

        // Activity log
        activity('users')
            ->performedOn($user)
            ->causedBy($user)
            ->event('updated')
            ->withProperties([
                'ip' => $request->ip(),
                'description' => 'Notification preferences updated',
            ])
            ->log(__('Notification preferences were updated'));

        return gale()->redirect('/profile/notifications')
            ->with('toast', [
                'type' => 'success',
                'message' => __('Notification preferences updated.'),
            ]);
    }
}
