<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\PlatformSettingService;
use Illuminate\Http\Request;

/**
 * Platform Settings Management Controller.
 *
 * F-063: Platform Settings Management
 * Provides a global platform settings page within the admin panel.
 * BR-189: All settings changes are logged in the activity log.
 * BR-190: Critical settings require confirmation dialog.
 * BR-191: Settings save without full page reload (via Gale).
 */
class PlatformSettingController extends Controller
{
    public function __construct(private PlatformSettingService $settingService) {}

    /**
     * Display the platform settings page.
     *
     * Scenario 1-5: Shows all current settings organized by group.
     */
    public function index(Request $request): mixed
    {
        if (! $request->user()?->can('can-manage-platform-settings')) {
            abort(403);
        }

        $settings = $this->settingService->getAllFlat();
        $isSuperAdmin = $request->user()?->hasRole('super-admin');

        $data = [
            'settings' => $settings,
            'isSuperAdmin' => $isSuperAdmin,
            'groups' => PlatformSetting::GROUPS,
            'criticalSettings' => PlatformSetting::CRITICAL_SETTINGS,
            'superAdminOnly' => PlatformSetting::SUPER_ADMIN_ONLY,
        ];

        return gale()->view('admin.settings.index', $data, web: true);
    }

    /**
     * Update a single platform setting.
     *
     * BR-189: All settings changes are logged.
     * BR-187: Maintenance mode restricted to super-admin.
     * BR-190: Critical settings require frontend confirmation (handled client-side).
     */
    public function update(Request $request): mixed
    {
        if (! $request->user()?->can('can-manage-platform-settings')) {
            abort(403);
        }

        if ($request->isGale()) {
            $validated = $request->validateState($this->validationRules());
        } else {
            $validated = $request->validate($this->validationRules());
        }

        $key = $validated['setting_key'];
        $value = $validated['setting_value'];

        // Validate key exists in defaults
        if (! isset(PlatformSetting::DEFAULTS[$key])) {
            return $this->errorResponse($request, __('Invalid setting key.'));
        }

        // BR-187: Maintenance mode is accessible only to super-admin
        if (in_array($key, PlatformSetting::SUPER_ADMIN_ONLY) && ! $request->user()?->hasRole('super-admin')) {
            return $this->errorResponse($request, __('Only super-admins can modify this setting.'));
        }

        // Type-specific validation
        $validationError = $this->validateSettingValue($key, $value);
        if ($validationError) {
            return $this->errorResponse($request, $validationError);
        }

        // Cast value based on type
        $default = PlatformSetting::DEFAULTS[$key];
        $castValue = match ($default['type']) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            default => (string) $value,
        };

        $result = $this->settingService->update($key, $castValue, $request->user());

        $toastMessage = $this->getSuccessMessage($key, $result['old_value'], $result['new_value']);

        if ($request->isGale()) {
            return gale()->state('error', '')
                ->state('success', $key)
                ->state($key, $result['new_value'])
                ->dispatch('setting-saved', [
                    'key' => $key,
                    'message' => $toastMessage,
                ]);
        }

        session()->flash('toast', [
            'type' => 'success',
            'message' => $toastMessage,
        ]);

        return redirect('/vault-entry/settings');
    }

    /**
     * Validate a setting value based on its type and business rules.
     */
    private function validateSettingValue(string $key, mixed $value): ?string
    {
        return match ($key) {
            'platform_name' => empty(trim((string) $value))
                ? __('Platform name is required.')
                : (strlen(trim((string) $value)) > 255 ? __('Platform name must not exceed 255 characters.') : null),
            'default_cancellation_window' => $this->validateCancellationWindow($value),
            'support_email' => $this->validateSupportEmail($value),
            'support_phone' => $this->validateSupportPhone($value),
            default => null,
        };
    }

    /**
     * Validate cancellation window value.
     * BR-185: Cancellation window range: 0 to 120 minutes.
     */
    private function validateCancellationWindow(mixed $value): ?string
    {
        $intValue = (int) $value;
        if ($intValue < 0 || $intValue > 120) {
            return __('Must be between 0 and 120 minutes.');
        }

        return null;
    }

    /**
     * Validate support email.
     */
    private function validateSupportEmail(mixed $value): ?string
    {
        $email = trim((string) $value);
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return __('Please enter a valid email address.');
        }

        return null;
    }

    /**
     * Validate support phone.
     */
    private function validateSupportPhone(mixed $value): ?string
    {
        $phone = trim((string) $value);
        if ($phone !== '' && strlen($phone) > 20) {
            return __('Phone number must not exceed 20 characters.');
        }

        return null;
    }

    /**
     * Build a human-readable success message for a setting change.
     */
    private function getSuccessMessage(string $key, mixed $oldValue, mixed $newValue): string
    {
        $label = $this->getSettingLabel($key);

        $default = PlatformSetting::DEFAULTS[$key];
        if ($default['type'] === 'boolean') {
            $state = $newValue ? __('enabled') : __('disabled');

            return __(':setting has been :state.', ['setting' => $label, 'state' => $state]);
        }

        return __(':setting has been updated.', ['setting' => $label]);
    }

    /**
     * Get the human-readable label for a setting key.
     */
    private function getSettingLabel(string $key): string
    {
        return match ($key) {
            'platform_name' => __('Platform name'),
            'wallet_enabled' => __('Wallet payments'),
            'default_cancellation_window' => __('Default cancellation window'),
            'support_email' => __('Support email'),
            'support_phone' => __('Support phone'),
            'maintenance_mode' => __('Maintenance mode'),
            'maintenance_reason' => __('Maintenance reason'),
            default => $key,
        };
    }

    /**
     * Return an error response for both Gale and HTTP.
     */
    private function errorResponse(Request $request, string $message): mixed
    {
        if ($request->isGale()) {
            return gale()->state('error', $message)->state('success', '');
        }

        session()->flash('toast', [
            'type' => 'error',
            'message' => $message,
        ]);

        return redirect('/vault-entry/settings');
    }

    /**
     * Validation rules for setting update.
     *
     * @return array<string, array<int, string>>
     */
    private function validationRules(): array
    {
        return [
            'setting_key' => ['required', 'string', 'max:100'],
            'setting_value' => ['present'],
        ];
    }
}
