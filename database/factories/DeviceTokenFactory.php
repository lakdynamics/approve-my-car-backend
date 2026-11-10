<?php

namespace Database\Factories;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DeviceToken> */
class DeviceTokenFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $token = fake()->sha256();

        return [
            'user_id' => User::factory(),
            'device_id' => fake()->uuid(),
            'platform' => 'android',
            'token' => $token,
            'token_hash' => hash('sha256', $token),
            'last_seen_at' => now(),
        ];
    }
}
