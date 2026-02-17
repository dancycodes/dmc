<?php

namespace Database\Factories;

use App\Models\PlatformSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformSetting>
 */
class PlatformSettingFactory extends Factory
{
    protected $model = PlatformSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(2),
            'value' => $this->faker->word(),
            'type' => 'string',
            'group' => 'general',
        ];
    }

    /**
     * Create a wallet_enabled setting.
     */
    public function walletEnabled(bool $enabled = true): static
    {
        return $this->state(fn () => [
            'key' => 'wallet_enabled',
            'value' => $enabled ? '1' : '0',
            'type' => 'boolean',
            'group' => 'features',
        ]);
    }

    /**
     * Create a default_cancellation_window setting.
     */
    public function cancellationWindow(int $minutes = 30): static
    {
        return $this->state(fn () => [
            'key' => 'default_cancellation_window',
            'value' => (string) $minutes,
            'type' => 'integer',
            'group' => 'orders',
        ]);
    }

    /**
     * Create a platform_name setting.
     */
    public function platformName(string $name = 'DancyMeals'): static
    {
        return $this->state(fn () => [
            'key' => 'platform_name',
            'value' => $name,
            'type' => 'string',
            'group' => 'general',
        ]);
    }

    /**
     * Create a maintenance_mode setting.
     */
    public function maintenanceMode(bool $enabled = false): static
    {
        return $this->state(fn () => [
            'key' => 'maintenance_mode',
            'value' => $enabled ? '1' : '0',
            'type' => 'boolean',
            'group' => 'system',
        ]);
    }

    /**
     * Create a maintenance_reason setting.
     */
    public function maintenanceReason(string $reason = ''): static
    {
        return $this->state(fn () => [
            'key' => 'maintenance_reason',
            'value' => $reason,
            'type' => 'string',
            'group' => 'system',
        ]);
    }

    /**
     * Create a support_email setting.
     */
    public function supportEmail(string $email = ''): static
    {
        return $this->state(fn () => [
            'key' => 'support_email',
            'value' => $email,
            'type' => 'string',
            'group' => 'support',
        ]);
    }

    /**
     * Create a support_phone setting.
     */
    public function supportPhone(string $phone = ''): static
    {
        return $this->state(fn () => [
            'key' => 'support_phone',
            'value' => $phone,
            'type' => 'string',
            'group' => 'support',
        ]);
    }
}
