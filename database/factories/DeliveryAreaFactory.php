<?php

namespace Database\Factories;

use App\Models\DeliveryArea;
use App\Models\Tenant;
use App\Models\Town;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryArea>
 */
class DeliveryAreaFactory extends Factory
{
    protected $model = DeliveryArea::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'town_id' => Town::factory(),
        ];
    }
}
