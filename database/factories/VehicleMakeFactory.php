<?php

namespace Database\Factories;

use App\Models\VehicleMake;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleMake>
 */
class VehicleMakeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->lexify('??????')),
            'is_active' => true,
        ];
    }

    public function hidden(): static
    {
        return $this->state(['is_active' => false]);
    }
}
