<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Inspection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inspection>
 */
class InspectionFactory extends Factory
{
    /**
     * An inspection of an in-progress booking, with no sections. Use
     * InspectionService::createFor() for one with the real checklist.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $inspector = User::factory()->inspector();

        return [
            'inspector_id' => $inspector,
            'booking_id' => fn (array $attributes) => Booking::factory()
                ->inProgress(User::findOrFail($attributes['inspector_id'])),
            'started_at' => now(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => ['completed_at' => now()]);
    }
}
