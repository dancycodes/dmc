<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'content' => $this->faker->paragraph(3),
            'target_type' => $this->faker->randomElement([
                Announcement::TARGET_ALL_USERS,
                Announcement::TARGET_ALL_COOKS,
                Announcement::TARGET_ALL_CLIENTS,
            ]),
            'target_tenant_id' => null,
            'status' => Announcement::STATUS_SENT,
            'scheduled_at' => null,
            'sent_at' => now()->subMinutes($this->faker->numberBetween(5, 1440)),
        ];
    }

    /**
     * Factory state for a draft announcement.
     */
    public function draft(): static
    {
        return $this->state([
            'status' => Announcement::STATUS_DRAFT,
            'sent_at' => null,
        ]);
    }

    /**
     * Factory state for a scheduled announcement.
     */
    public function scheduled(): static
    {
        return $this->state([
            'status' => Announcement::STATUS_SCHEDULED,
            'scheduled_at' => now()->addMinutes($this->faker->numberBetween(10, 1440)),
            'sent_at' => null,
        ]);
    }

    /**
     * Factory state for a cancelled announcement.
     */
    public function cancelled(): static
    {
        return $this->state([
            'status' => Announcement::STATUS_CANCELLED,
            'sent_at' => null,
        ]);
    }

    /**
     * Factory state for a sent announcement.
     */
    public function sent(): static
    {
        return $this->state([
            'status' => Announcement::STATUS_SENT,
            'sent_at' => now()->subMinutes($this->faker->numberBetween(5, 1440)),
        ]);
    }
}
