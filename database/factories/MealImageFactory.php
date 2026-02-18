<?php

namespace Database\Factories;

use App\Models\Meal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MealImage>
 */
class MealImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $extension = fake()->randomElement(['jpg', 'png', 'webp']);
        $mimeMap = [
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ];
        $filename = fake()->uuid().'.'.$extension;

        return [
            'meal_id' => Meal::factory(),
            'path' => 'meal-images/'.$filename,
            'thumbnail_path' => 'meal-images/thumbs/'.$filename,
            'position' => 0,
            'original_filename' => 'meal-photo-'.fake()->unique()->numerify('###').'.'.$extension,
            'mime_type' => $mimeMap[$extension],
            'file_size' => fake()->numberBetween(50000, 2000000),
        ];
    }

    /**
     * Set a specific position.
     */
    public function positioned(int $position): static
    {
        return $this->state(fn () => [
            'position' => $position,
        ]);
    }

    /**
     * Create as a JPEG image.
     */
    public function jpeg(): static
    {
        return $this->state(fn () => [
            'mime_type' => 'image/jpeg',
            'original_filename' => 'meal-photo-'.fake()->unique()->numerify('###').'.jpg',
        ]);
    }

    /**
     * Create as a PNG image.
     */
    public function png(): static
    {
        return $this->state(fn () => [
            'mime_type' => 'image/png',
            'original_filename' => 'meal-photo-'.fake()->unique()->numerify('###').'.png',
        ]);
    }

    /**
     * Create as a WebP image.
     */
    public function webp(): static
    {
        return $this->state(fn () => [
            'mime_type' => 'image/webp',
            'original_filename' => 'meal-photo-'.fake()->unique()->numerify('###').'.webp',
        ]);
    }

    /**
     * Create a small file (under 500KB).
     */
    public function small(): static
    {
        return $this->state(fn () => [
            'file_size' => fake()->numberBetween(50000, 500000),
        ]);
    }
}
