<?php

namespace Database\Factories;

use App\Models\Inspection;
use App\Models\InspectionSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspectionSection>
 */
class InspectionSectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inspection_id' => Inspection::factory(),
            'name' => fake()->unique()->words(2, true),
            'sort_order' => fake()->numberBetween(1, 50),
        ];
    }
}
