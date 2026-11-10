<?php

namespace Database\Factories;

use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Enums\VehicleType;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'registration_number' => strtoupper(fake()->unique()->bothify('??? ####')),
            'make' => fake()->randomElement(['Toyota', 'Honda', 'Suzuki', 'Nissan', 'Mitsubishi']),
            'model' => fake()->word(),
            'year' => fake()->numberBetween(2000, (int) date('Y')),
            'vehicle_type' => VehicleType::Car,
            'fuel_type' => fake()->randomElement(FuelType::cases()),
            'transmission' => fake()->randomElement(Transmission::cases()),
            'color' => fake()->safeColorName(),
            'mileage' => fake()->numberBetween(0, 300000),
            'chassis_number' => null,
            'engine_number' => null,
        ];
    }
}
