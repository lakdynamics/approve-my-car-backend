<?php

namespace Database\Factories;

use App\Enums\District;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAddress>
 */
class UserAddressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => fake()->randomElement(['Home', 'Office', null]),
            'line1' => fake()->buildingNumber().' '.fake()->streetName(),
            'line2' => null,
            'city' => fake()->city(),
            'district' => fake()->randomElement(District::cases()),
            'postal_code' => fake()->numerify('#####'),
            'latitude' => null,
            'longitude' => null,
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => ['is_default' => true]);
    }
}
