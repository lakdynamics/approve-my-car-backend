<?php

namespace Database\Factories;

use App\Models\ChecklistItem;
use App\Models\ChecklistSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistItem>
 */
class ChecklistItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checklist_section_id' => ChecklistSection::factory(),
            'label' => ucfirst(fake()->unique()->words(3, true)),
            'description' => null,
            'sort_order' => fn (array $attributes) => (int) ChecklistItem::where('checklist_section_id', $attributes['checklist_section_id'])->max('sort_order') + 1,
            'is_active' => true,
        ];
    }

    public function hidden(): static
    {
        return $this->state(['is_active' => false]);
    }
}
